<?php
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

$logement_id = isset($_GET['logement_id']) ? (int)$_GET['logement_id'] : 0;

if (!$logement_id) {
    $_SESSION['error'] = "Logement non spécifié";
    header('Location: logements.php');
    exit;
}

// Get logement info
$stmt = $pdo->prepare("SELECT * FROM logements WHERE id = ?");
$stmt->execute([$logement_id]);
$logement = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$logement) {
    $_SESSION['error'] = "Logement introuvable";
    header('Location: logements.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO inventaire_equipements (logement_id, categorie_id, sous_categorie_id, categorie, nom, description, quantite, valeur_estimee, ordre)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $ordre = (int)($_POST['ordre'] ?? 0);
                    $quantite = (int)($_POST['quantite'] ?? 1);
                    $valeur = !empty($_POST['valeur_estimee']) ? (float)$_POST['valeur_estimee'] : null;
                    $categorie_id = (int)$_POST['categorie_id'];
                    $sous_categorie_id = !empty($_POST['sous_categorie_id']) ? (int)$_POST['sous_categorie_id'] : null;
                    
                    // Get category name for backward compatibility
                    $stmt_cat = $pdo->prepare("SELECT nom FROM inventaire_categories WHERE id = ?");
                    $stmt_cat->execute([$categorie_id]);
                    $categorie_nom = $stmt_cat->fetchColumn();
                    
                    $stmt->execute([
                        $logement_id,
                        $categorie_id,
                        $sous_categorie_id,
                        $categorie_nom,
                        $_POST['nom'],
                        $_POST['description'],
                        $quantite,
                        $valeur,
                        $ordre
                    ]);
                    $_SESSION['success'] = "Équipement ajouté avec succès";
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Erreur lors de l'ajout de l'équipement";
                    error_log("Erreur ajout équipement: " . $e->getMessage());
                }
                break;
                
            case 'edit':
                try {
                    $stmt = $pdo->prepare("
                        UPDATE inventaire_equipements SET 
                            categorie_id = ?, sous_categorie_id = ?, categorie = ?, nom = ?, description = ?, quantite = ?, valeur_estimee = ?, ordre = ?
                        WHERE id = ? AND logement_id = ?
                    ");
                    $ordre = (int)($_POST['ordre'] ?? 0);
                    $quantite = (int)($_POST['quantite'] ?? 1);
                    $valeur = !empty($_POST['valeur_estimee']) ? (float)$_POST['valeur_estimee'] : null;
                    $categorie_id = (int)$_POST['categorie_id'];
                    $sous_categorie_id = !empty($_POST['sous_categorie_id']) ? (int)$_POST['sous_categorie_id'] : null;
                    
                    // Get category name for backward compatibility
                    $stmt_cat = $pdo->prepare("SELECT nom FROM inventaire_categories WHERE id = ?");
                    $stmt_cat->execute([$categorie_id]);
                    $categorie_nom = $stmt_cat->fetchColumn();
                    
                    $stmt->execute([
                        $categorie_id,
                        $sous_categorie_id,
                        $categorie_nom,
                        $_POST['nom'],
                        $_POST['description'],
                        $quantite,
                        $valeur,
                        $ordre,
                        $_POST['equipement_id'],
                        $logement_id
                    ]);
                    $_SESSION['success'] = "Équipement modifié avec succès";
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Erreur lors de la modification de l'équipement";
                    error_log("Erreur modification équipement: " . $e->getMessage());
                }
                break;
                
            case 'delete':
                try {
                    // Confirmation is handled on frontend, but double-check here
                    if (!isset($_POST['confirmed']) || $_POST['confirmed'] !== '1') {
                        $_SESSION['error'] = "Suppression non confirmée";
                        break;
                    }
                    
                    // Soft delete equipment (set deleted_at timestamp instead of DELETE)
                    $stmt = $pdo->prepare("UPDATE inventaire_equipements SET deleted_at = NOW() WHERE id = ? AND logement_id = ? AND deleted_at IS NULL");
                    $stmt->execute([$_POST['equipement_id'], $logement_id]);
                    $_SESSION['success'] = "Équipement supprimé";
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Erreur lors de la suppression de l'équipement";
                    error_log("Erreur suppression équipement: " . $e->getMessage());
                }
                break;
        }
        
        header("Location: manage-inventory-equipements.php?logement_id=$logement_id");
        exit;
    }
}

// Get all active categories from database
$stmt = $pdo->query("
    SELECT id, nom, icone 
    FROM inventaire_categories 
    WHERE actif = TRUE
    ORDER BY ordre, nom
");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
$categories_by_id = [];
foreach ($categories as $cat) {
    $categories_by_id[$cat['id']] = $cat;
}

// Get equipements for this logement with category info
$stmt = $pdo->prepare("
    SELECT e.*, 
           c.nom as categorie_nom, 
           c.icone as categorie_icone,
           sc.nom as sous_categorie_nom
    FROM inventaire_equipements e
    LEFT JOIN inventaire_categories c ON e.categorie_id = c.id
    LEFT JOIN inventaire_sous_categories sc ON e.sous_categorie_id = sc.id
    WHERE e.logement_id = ? 
    ORDER BY c.ordre, e.ordre, e.nom
");
$stmt->execute([$logement_id]);
$equipements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// AUTO-POPULATE: If equipment is empty, automatically populate with defaults
// This implements the new requirement: no need to click "Reset with defaults" button
if (empty($equipements)) {
    try {
        $pdo->beginTransaction();
        
        // Get logement reference for property-specific equipment
        $stmt = $pdo->prepare("SELECT reference FROM logements WHERE id = ?");
        $stmt->execute([$logement_id]);
        $logement_reference = $stmt->fetchColumn() ?: '';
        
        // Get standardized equipment for this property
        require_once '../includes/inventaire-standard-items.php';
        $standardItems = getStandardInventaireItems($logement_reference);
        
        // Build category name to ID mapping
        $categoryIds = [];
        $stmt = $pdo->query("SELECT id, nom FROM inventaire_categories");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $categoryIds[$row['nom']] = $row['id'];
        }
        
        $insertStmt = $pdo->prepare("
            INSERT INTO inventaire_equipements (logement_id, categorie_id, categorie, nom, quantite, ordre)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $ordre = 0;
        foreach ($standardItems as $categoryName => $categoryItems) {
            $categorie_id = $categoryIds[$categoryName] ?? null;
            foreach ($categoryItems as $item) {
                $ordre++;
                $insertStmt->execute([
                    $logement_id,
                    $categorie_id,
                    $categoryName,
                    $item['nom'],
                    $item['quantite'] ?? 0,
                    $ordre
                ]);
            }
        }
        
        $pdo->commit();
        
        // Reload equipment after auto-population
        $stmt = $pdo->prepare("
            SELECT e.*, 
                   c.id as categorie_id, 
                   c.nom as categorie_nom, 
                   c.icone as categorie_icone,
                   sc.nom as sous_categorie_nom
            FROM inventaire_equipements e
            LEFT JOIN inventaire_categories c ON e.categorie_id = c.id
            LEFT JOIN inventaire_sous_categories sc ON e.sous_categorie_id = sc.id
            WHERE e.logement_id = ? 
            ORDER BY c.ordre, e.ordre, e.nom
        ");
        $stmt->execute([$logement_id]);
        $equipements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $_SESSION['success'] = "Équipements automatiquement chargés pour ce logement";
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error auto-populating equipment: " . $e->getMessage());
        $_SESSION['error'] = "Erreur lors du chargement automatique des équipements";
    }
}

// Group by category ID
$equipements_by_category = [];
foreach ($equipements as $eq) {
    $catId = $eq['categorie_id'];
    
    // If categorie_id is NULL, try to find it from category name for backward compatibility
    if ($catId === null && !empty($eq['categorie'])) {
        foreach ($categories_by_id as $id => $cat) {
            if ($cat['nom'] === $eq['categorie']) {
                $catId = $id;
                break;
            }
        }
    }
    
    if (!isset($equipements_by_category[$catId])) {
        $equipements_by_category[$catId] = [];
    }
    $equipements_by_category[$catId][] = $eq;
}

// Get subcategories for dropdown
$stmt = $pdo->query("
    SELECT sc.*, c.nom as categorie_nom
    FROM inventaire_sous_categories sc
    JOIN inventaire_categories c ON sc.categorie_id = c.id
    WHERE sc.actif = TRUE
    ORDER BY c.ordre, sc.ordre, sc.nom
");
$subcategories = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $subcategories[$row['categorie_id']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Définir l'inventaire - <?php echo htmlspecialchars($logement['reference']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <?php require_once __DIR__ . '/includes/sidebar-styles.php'; ?>
    <style>
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .category-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .category-header {
            font-weight: bold;
            font-size: 1.1rem;
            margin-bottom: 15px;
            color: #495057;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 10px;
        }
        .equipment-item {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 10px;
            border-left: 3px solid #0d6efd;
        }
        .equipment-item:hover {
            background: #e9ecef;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/menu.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4>
                        <i class="bi bi-box-seam"></i> Inventaire du logement
                    </h4>
                    <p class="text-muted mb-0">
                        <?php echo htmlspecialchars($logement['reference']); ?> - 
                        <?php echo htmlspecialchars($logement['type']); ?> - 
                        <?php echo htmlspecialchars($logement['adresse']); ?>
                    </p>
                </div>
                <div>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addEquipementModal">
                        <i class="bi bi-plus-circle"></i> Ajouter un équipement
                    </button>
                    <!-- No reset button needed - equipment is auto-loaded -->
                    <a href="logements.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Retour aux logements
                    </a>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($equipements)): ?>
            <div class="category-card text-center py-5">
                <i class="bi bi-box-seam" style="font-size: 4rem; color: #dee2e6;"></i>
                <h5 class="mt-3 text-muted">Aucun équipement défini</h5>
                <p class="text-muted">Commencez par ajouter les équipements standards de ce logement</p>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addEquipementModal">
                    <i class="bi bi-plus-circle"></i> Ajouter le premier équipement
                </button>
            </div>
        <?php else: ?>
            <?php foreach ($categories as $cat): ?>
                <?php if (isset($equipements_by_category[$cat['id']])): ?>
                    <div class="category-card">
                        <div class="category-header">
                            <i class="<?php echo $cat['icone']; ?>"></i> <?php echo htmlspecialchars($cat['nom']); ?>
                            (<?php echo count($equipements_by_category[$cat['id']]); ?> équipements)
                        </div>
                        
                        <?php foreach ($equipements_by_category[$cat['id']] as $eq): ?>
                            <div class="equipment-item">
                                <div class="row align-items-center">
                                    <div class="col-md-5">
                                        <strong><?php echo htmlspecialchars($eq['nom']); ?></strong>
                                        <?php if ($eq['sous_categorie_nom']): ?>
                                            <br><small class="text-muted"><i class="bi bi-arrow-return-right"></i> <?php echo htmlspecialchars($eq['sous_categorie_nom']); ?></small>
                                        <?php endif; ?>
                                        <?php if ($eq['description']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($eq['description']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-2">
                                        <small class="text-muted">Quantité:</small> 
                                        <strong><?php echo $eq['quantite']; ?></strong>
                                    </div>
                                    <div class="col-md-3">
                                        <?php if ($eq['valeur_estimee']): ?>
                                            <small class="text-muted">Valeur estimée:</small> 
                                            <strong><?php echo number_format($eq['valeur_estimee'], 2, ',', ' '); ?> €</strong>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-2 text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary edit-equipement-btn"
                                                    data-id="<?php echo $eq['id']; ?>"
                                                    data-categorie-id="<?php echo $eq['categorie_id']; ?>"
                                                    data-sous-categorie-id="<?php echo $eq['sous_categorie_id'] ?? ''; ?>"
                                                    data-nom="<?php echo htmlspecialchars($eq['nom']); ?>"
                                                    data-description="<?php echo htmlspecialchars($eq['description']); ?>"
                                                    data-quantite="<?php echo $eq['quantite']; ?>"
                                                    data-valeur="<?php echo $eq['valeur_estimee']; ?>"
                                                    data-ordre="<?php echo $eq['ordre']; ?>"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editEquipementModal">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-outline-danger delete-equipement-btn"
                                                    data-id="<?php echo $eq['id']; ?>"
                                                    data-nom="<?php echo htmlspecialchars($eq['nom']); ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Add Equipement Modal -->
    <div class="modal fade" id="addEquipementModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter un équipement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Catégorie <span class="text-danger">*</span></label>
                            <select name="categorie_id" id="add_categorie_id" class="form-select" required onchange="updateSubcategoriesAdd()">
                                <option value="">Sélectionner...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nom']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3" id="add_subcategory_container" style="display:none;">
                            <label class="form-label">Sous-catégorie</label>
                            <select name="sous_categorie_id" id="add_sous_categorie_id" class="form-select">
                                <option value="">Aucune</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nom de l'équipement <span class="text-danger">*</span></label>
                            <input type="text" name="nom" class="form-control" required 
                                   placeholder="Ex: Réfrigérateur, Canapé, Table...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2" 
                                      placeholder="Détails supplémentaires (marque, couleur, etc.)"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Quantité</label>
                                    <input type="number" name="quantite" class="form-control" value="1" min="1" max="999">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Valeur (€)</label>
                                    <input type="number" name="valeur_estimee" class="form-control" step="0.01" min="0"
                                           placeholder="0.00">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Ordre</label>
                                    <input type="number" name="ordre" class="form-control" value="0" min="0" max="999">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-success">Ajouter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Equipement Modal -->
    <div class="modal fade" id="editEquipementModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier l'équipement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="equipement_id" id="edit_equipement_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Catégorie <span class="text-danger">*</span></label>
                            <select name="categorie_id" id="edit_categorie_id" class="form-select" required onchange="updateSubcategoriesEdit()">
                                <option value="">Sélectionner...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nom']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3" id="edit_subcategory_container" style="display:none;">
                            <label class="form-label">Sous-catégorie</label>
                            <select name="sous_categorie_id" id="edit_sous_categorie_id" class="form-select">
                                <option value="">Aucune</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nom de l'équipement <span class="text-danger">*</span></label>
                            <input type="text" name="nom" id="edit_nom" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Quantité</label>
                                    <input type="number" name="quantite" id="edit_quantite" class="form-control" min="1" max="999">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Valeur (€)</label>
                                    <input type="number" name="valeur_estimee" id="edit_valeur" class="form-control" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Ordre</label>
                                    <input type="number" name="ordre" id="edit_ordre" class="form-control" min="0" max="999">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Equipement Modal -->
    <div class="modal fade" id="deleteEquipementModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle"></i> Confirmer la suppression
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer l'équipement <strong id="delete_equipement_nom"></strong> ?</p>
                    <p class="text-danger"><i class="bi bi-exclamation-circle"></i> Cette action est irréversible.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="confirmed" value="1">
                        <input type="hidden" name="equipement_id" id="delete_equipement_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Confirmer la suppression
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Subcategories data from PHP
        const subcategoriesData = <?php echo json_encode($subcategories, JSON_UNESCAPED_UNICODE); ?>;
        const logementId = <?php echo $logement_id; ?>;
        
        // Populate with default equipment
        
        // Update subcategories dropdown for Add form
        function updateSubcategoriesAdd() {
            const categorieId = document.getElementById('add_categorie_id').value;
            const container = document.getElementById('add_subcategory_container');
            const select = document.getElementById('add_sous_categorie_id');
            
            if (categorieId && subcategoriesData[categorieId]) {
                select.innerHTML = '<option value="">Aucune</option>';
                subcategoriesData[categorieId].forEach(subcat => {
                    select.innerHTML += `<option value="${subcat.id}">${subcat.nom}</option>`;
                });
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
                select.innerHTML = '<option value="">Aucune</option>';
            }
        }
        
        // Update subcategories dropdown for Edit form
        function updateSubcategoriesEdit() {
            const categorieId = document.getElementById('edit_categorie_id').value;
            const container = document.getElementById('edit_subcategory_container');
            const select = document.getElementById('edit_sous_categorie_id');
            
            if (categorieId && subcategoriesData[categorieId]) {
                select.innerHTML = '<option value="">Aucune</option>';
                subcategoriesData[categorieId].forEach(subcat => {
                    select.innerHTML += `<option value="${subcat.id}">${subcat.nom}</option>`;
                });
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
                select.innerHTML = '<option value="">Aucune</option>';
            }
        }
        
        // Edit equipment modal
        document.querySelectorAll('.edit-equipement-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('edit_equipement_id').value = this.dataset.id;
                document.getElementById('edit_categorie_id').value = this.dataset.categorieId;
                document.getElementById('edit_nom').value = this.dataset.nom;
                document.getElementById('edit_description').value = this.dataset.description;
                document.getElementById('edit_quantite').value = this.dataset.quantite;
                document.getElementById('edit_valeur').value = this.dataset.valeur;
                document.getElementById('edit_ordre').value = this.dataset.ordre;
                
                // Update subcategories and select current one
                updateSubcategoriesEdit();
                if (this.dataset.sousCategorieId) {
                    document.getElementById('edit_sous_categorie_id').value = this.dataset.sousCategorieId;
                }
            });
        });

        // Delete equipment - show modal with confirmation
        document.querySelectorAll('.delete-equipement-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const modal = new bootstrap.Modal(document.getElementById('deleteEquipementModal'));
                document.getElementById('delete_equipement_id').value = this.dataset.id;
                document.getElementById('delete_equipement_nom').textContent = this.dataset.nom;
                modal.show();
            });
        });
    </script>
</body>
</html>
