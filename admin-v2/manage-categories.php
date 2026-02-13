<?php
/**
 * Category Management Interface
 * Allows admin to manage inventory categories and subcategories
 */
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'add_category':
                $stmt = $pdo->prepare("
                    INSERT INTO inventaire_categories (nom, icone, ordre)
                    VALUES (?, ?, ?)
                ");
                $ordre = $pdo->query("SELECT COALESCE(MAX(ordre), 0) + 10 FROM inventaire_categories")->fetchColumn();
                $stmt->execute([
                    $_POST['nom'],
                    $_POST['icone'] ?? 'bi-box',
                    $ordre
                ]);
                echo json_encode(['success' => true, 'message' => 'Catégorie ajoutée avec succès', 'id' => $pdo->lastInsertId()]);
                break;
                
            case 'edit_category':
                $stmt = $pdo->prepare("
                    UPDATE inventaire_categories 
                    SET nom = ?, icone = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['nom'],
                    $_POST['icone'] ?? 'bi-box',
                    $_POST['id']
                ]);
                echo json_encode(['success' => true, 'message' => 'Catégorie modifiée avec succès']);
                break;
                
            case 'delete_category':
                // Check if category has equipment
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM inventaire_equipements WHERE categorie_id = ?
                ");
                $stmt->execute([$_POST['id']]);
                $count = $stmt->fetchColumn();
                
                if ($count > 0 && empty($_POST['confirmed'])) {
                    echo json_encode([
                        'success' => false, 
                        'needs_confirmation' => true,
                        'message' => "Cette catégorie contient {$count} équipement(s). La suppression de cette catégorie supprimera également tous les équipements associés. Voulez-vous continuer ?"
                    ]);
                } else {
                    // Delete category (cascade will delete equipment and subcategories)
                    $stmt = $pdo->prepare("DELETE FROM inventaire_categories WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    echo json_encode(['success' => true, 'message' => 'Catégorie et tous ses équipements supprimés']);
                }
                break;
                
            case 'add_subcategory':
                $stmt = $pdo->prepare("
                    INSERT INTO inventaire_sous_categories (categorie_id, nom, ordre)
                    VALUES (?, ?, ?)
                ");
                $ordre = $pdo->prepare("SELECT COALESCE(MAX(ordre), 0) + 1 FROM inventaire_sous_categories WHERE categorie_id = ?");
                $ordre->execute([$_POST['categorie_id']]);
                $ordre = $ordre->fetchColumn();
                
                $stmt->execute([
                    $_POST['categorie_id'],
                    $_POST['nom'],
                    $ordre
                ]);
                echo json_encode(['success' => true, 'message' => 'Sous-catégorie ajoutée avec succès', 'id' => $pdo->lastInsertId()]);
                break;
                
            case 'edit_subcategory':
                $stmt = $pdo->prepare("
                    UPDATE inventaire_sous_categories 
                    SET nom = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['nom'],
                    $_POST['id']
                ]);
                echo json_encode(['success' => true, 'message' => 'Sous-catégorie modifiée avec succès']);
                break;
                
            case 'delete_subcategory':
                // Check if subcategory has equipment
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM inventaire_equipements WHERE sous_categorie_id = ?
                ");
                $stmt->execute([$_POST['id']]);
                $count = $stmt->fetchColumn();
                
                if ($count > 0 && empty($_POST['confirmed'])) {
                    echo json_encode([
                        'success' => false, 
                        'needs_confirmation' => true,
                        'message' => "Cette sous-catégorie contient {$count} équipement(s). Voulez-vous vraiment la supprimer ?"
                    ]);
                } else {
                    $stmt = $pdo->prepare("DELETE FROM inventaire_sous_categories WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    echo json_encode(['success' => true, 'message' => 'Sous-catégorie supprimée']);
                }
                break;
                
            case 'reorder_categories':
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE inventaire_categories SET ordre = ? WHERE id = ?");
                foreach ($_POST['order'] as $index => $id) {
                    $stmt->execute([($index + 1) * 10, $id]);
                }
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Ordre mis à jour']);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Get categories with subcategories
$stmt = $pdo->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM inventaire_equipements WHERE categorie_id = c.id) as equipment_count,
           (SELECT COUNT(*) FROM inventaire_sous_categories WHERE categorie_id = c.id) as subcat_count
    FROM inventaire_categories c
    ORDER BY c.ordre, c.nom
");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get subcategories
$stmt = $pdo->query("
    SELECT s.*,
           (SELECT COUNT(*) FROM inventaire_equipements WHERE sous_categorie_id = s.id) as equipment_count
    FROM inventaire_sous_categories s
    ORDER BY s.categorie_id, s.ordre, s.nom
");
$subcategories = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $subcategories[$row['categorie_id']][] = $row;
}

// Bootstrap icons list
$icons = [
    'bi-box' => 'Boîte',
    'bi-house' => 'Maison',
    'bi-house-door' => 'Porte',
    'bi-plugin' => 'Prise',
    'bi-cup-hot' => 'Cuisine',
    'bi-droplet' => 'Eau',
    'bi-basket' => 'Panier',
    'bi-tv' => 'TV',
    'bi-tools' => 'Outils',
    'bi-knife' => 'Couteau',
    'bi-speedometer2' => 'Compteur',
    'bi-key' => 'Clé',
    'bi-door-open' => 'Porte ouverte',
    'bi-lamp' => 'Lampe',
    'bi-lightning' => 'Électricité'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Catégories - Inventaire</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.css">
    <?php require_once __DIR__ . '/includes/sidebar-styles.php'; ?>
    <style>
        .category-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s;
            cursor: move;
        }
        .category-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .category-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        .category-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.2rem;
            font-weight: 600;
        }
        .category-icon {
            font-size: 1.5rem;
            color: #3498db;
        }
        .category-actions {
            display: flex;
            gap: 5px;
        }
        .subcategory-list {
            padding-left: 30px;
            margin-top: 10px;
        }
        .subcategory-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            margin-bottom: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .badge-count {
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.85rem;
        }
        .drag-handle {
            cursor: move;
            color: #999;
        }
        .sortable-ghost {
            opacity: 0.4;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h2">Gestion des Catégories d'Inventaire</h1>
                    <p class="text-muted">Gérez les catégories et sous-catégories d'équipements</p>
                </div>
                <button class="btn btn-primary" onclick="showAddCategoryModal()">
                    <i class="bi bi-plus-circle"></i> Ajouter une catégorie
                </button>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['success']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <div id="categories-list">
                <?php foreach ($categories as $category): ?>
                    <div class="category-card" data-id="<?= $category['id'] ?>">
                        <div class="category-header">
                            <div class="category-title">
                                <span class="drag-handle"><i class="bi bi-grip-vertical"></i></span>
                                <i class="category-icon <?= htmlspecialchars($category['icone']) ?>"></i>
                                <span><?= htmlspecialchars($category['nom']) ?></span>
                                <span class="badge-count">
                                    <?= $category['equipment_count'] ?> équipement(s)
                                </span>
                                <?php if ($category['subcat_count'] > 0): ?>
                                    <span class="badge-count">
                                        <?= $category['subcat_count'] ?> sous-catégorie(s)
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="category-actions">
                                <button class="btn btn-sm btn-outline-primary" 
                                        onclick="showEditCategoryModal(<?= $category['id'] ?>, '<?= htmlspecialchars(addslashes($category['nom'])) ?>', '<?= $category['icone'] ?>')">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-success" 
                                        onclick="showAddSubcategoryModal(<?= $category['id'] ?>, '<?= htmlspecialchars(addslashes($category['nom'])) ?>')">
                                    <i class="bi bi-plus"></i> Sous-catégorie
                                </button>
                                <button class="btn btn-sm btn-outline-danger" 
                                        onclick="deleteCategory(<?= $category['id'] ?>)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                        
                        <?php if (isset($subcategories[$category['id']])): ?>
                            <div class="subcategory-list">
                                <?php foreach ($subcategories[$category['id']] as $subcat): ?>
                                    <div class="subcategory-item">
                                        <div>
                                            <i class="bi bi-arrow-return-right text-muted"></i>
                                            <?= htmlspecialchars($subcat['nom']) ?>
                                            <span class="badge-count ms-2">
                                                <?= $subcat['equipment_count'] ?> équipement(s)
                                            </span>
                                        </div>
                                        <div>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="showEditSubcategoryModal(<?= $subcat['id'] ?>, '<?= htmlspecialchars(addslashes($subcat['nom'])) ?>')">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="deleteSubcategory(<?= $subcat['id'] ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Add/Edit Category Modal -->
    <div class="modal fade" id="categoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="categoryModalTitle">Ajouter une catégorie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="category_id">
                    <div class="mb-3">
                        <label class="form-label">Nom de la catégorie *</label>
                        <input type="text" class="form-control" id="category_nom" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Icône</label>
                        <select class="form-select" id="category_icone">
                            <?php foreach ($icons as $iconClass => $iconLabel): ?>
                                <option value="<?= $iconClass ?>">
                                    <?= $iconLabel ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" onclick="saveCategory()">Enregistrer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Subcategory Modal -->
    <div class="modal fade" id="subcategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="subcategoryModalTitle">Ajouter une sous-catégorie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="subcategory_id">
                    <input type="hidden" id="subcategory_categorie_id">
                    <p class="text-muted" id="subcategory_parent_name"></p>
                    <div class="mb-3">
                        <label class="form-label">Nom de la sous-catégorie *</label>
                        <input type="text" class="form-control" id="subcategory_nom" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" onclick="saveSubcategory()">Enregistrer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle"></i> Confirmation requise
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="confirm-message"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-danger" id="confirm-button">Confirmer la suppression</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <script>
        // Initialize Sortable for drag and drop
        const categoriesList = document.getElementById('categories-list');
        new Sortable(categoriesList, {
            animation: 150,
            handle: '.drag-handle',
            ghostClass: 'sortable-ghost',
            onEnd: function() {
                const order = Array.from(categoriesList.children).map(el => el.dataset.id);
                fetch('manage-categories.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        ajax: '1',
                        action: 'reorder_categories',
                        order: order
                    })
                });
            }
        });

        function showAddCategoryModal() {
            document.getElementById('categoryModalTitle').textContent = 'Ajouter une catégorie';
            document.getElementById('category_id').value = '';
            document.getElementById('category_nom').value = '';
            document.getElementById('category_icone').value = 'bi-box';
            new bootstrap.Modal(document.getElementById('categoryModal')).show();
        }

        function showEditCategoryModal(id, nom, icone) {
            document.getElementById('categoryModalTitle').textContent = 'Modifier la catégorie';
            document.getElementById('category_id').value = id;
            document.getElementById('category_nom').value = nom;
            document.getElementById('category_icone').value = icone;
            new bootstrap.Modal(document.getElementById('categoryModal')).show();
        }

        function saveCategory() {
            const id = document.getElementById('category_id').value;
            const nom = document.getElementById('category_nom').value;
            const icone = document.getElementById('category_icone').value;
            
            if (!nom) {
                alert('Le nom de la catégorie est requis');
                return;
            }
            
            fetch('manage-categories.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    ajax: '1',
                    action: id ? 'edit_category' : 'add_category',
                    id: id,
                    nom: nom,
                    icone: icone
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                }
            });
        }

        function deleteCategory(id) {
            fetch('manage-categories.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    ajax: '1',
                    action: 'delete_category',
                    id: id
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.needs_confirmation) {
                    showConfirmation(data.message, () => {
                        fetch('manage-categories.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: new URLSearchParams({
                                ajax: '1',
                                action: 'delete_category',
                                id: id,
                                confirmed: '1'
                            })
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            } else {
                                alert(data.message);
                            }
                        });
                    });
                } else if (data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                }
            });
        }

        function showAddSubcategoryModal(categorieId, categorieName) {
            document.getElementById('subcategoryModalTitle').textContent = 'Ajouter une sous-catégorie';
            document.getElementById('subcategory_id').value = '';
            document.getElementById('subcategory_categorie_id').value = categorieId;
            document.getElementById('subcategory_parent_name').textContent = 'Catégorie parente: ' + categorieName;
            document.getElementById('subcategory_nom').value = '';
            new bootstrap.Modal(document.getElementById('subcategoryModal')).show();
        }

        function showEditSubcategoryModal(id, nom) {
            document.getElementById('subcategoryModalTitle').textContent = 'Modifier la sous-catégorie';
            document.getElementById('subcategory_id').value = id;
            document.getElementById('subcategory_nom').value = nom;
            new bootstrap.Modal(document.getElementById('subcategoryModal')).show();
        }

        function saveSubcategory() {
            const id = document.getElementById('subcategory_id').value;
            const nom = document.getElementById('subcategory_nom').value;
            const categorieId = document.getElementById('subcategory_categorie_id').value;
            
            if (!nom) {
                alert('Le nom de la sous-catégorie est requis');
                return;
            }
            
            fetch('manage-categories.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    ajax: '1',
                    action: id ? 'edit_subcategory' : 'add_subcategory',
                    id: id,
                    categorie_id: categorieId,
                    nom: nom
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                }
            });
        }

        function deleteSubcategory(id) {
            fetch('manage-categories.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    ajax: '1',
                    action: 'delete_subcategory',
                    id: id
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.needs_confirmation) {
                    showConfirmation(data.message, () => {
                        fetch('manage-categories.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: new URLSearchParams({
                                ajax: '1',
                                action: 'delete_subcategory',
                                id: id,
                                confirmed: '1'
                            })
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            } else {
                                alert(data.message);
                            }
                        });
                    });
                } else if (data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                }
            });
        }

        function showConfirmation(message, onConfirm) {
            document.getElementById('confirm-message').textContent = message;
            const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
            document.getElementById('confirm-button').onclick = () => {
                modal.hide();
                onConfirm();
            };
            modal.show();
        }
    </script>
</body>
</html>
