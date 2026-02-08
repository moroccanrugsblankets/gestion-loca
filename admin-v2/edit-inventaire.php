<?php
/**
 * Edit Inventaire - Simplified version
 * Allows editing equipment status and observations
 */
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

$inventaire_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$inventaire_id) {
    $_SESSION['error'] = "Inventaire non spécifié";
    header('Location: inventaires.php');
    exit;
}

// Get inventaire data
$stmt = $pdo->prepare("
    SELECT inv.*, 
           l.reference as logement_reference,
           l.type as logement_type
    FROM inventaires inv
    INNER JOIN logements l ON inv.logement_id = l.id
    WHERE inv.id = ?
");
$stmt->execute([$inventaire_id]);
$inventaire = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inventaire) {
    $_SESSION['error'] = "Inventaire introuvable";
    header('Location: inventaires.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Update equipment data
        $equipements_data = [];
        if (isset($_POST['equipement'])) {
            foreach ($_POST['equipement'] as $eq_id => $eq_data) {
                $equipements_data[] = [
                    'equipement_id' => $eq_id,
                    'nom' => $eq_data['nom'] ?? '',
                    'categorie' => $eq_data['categorie'] ?? '',
                    'description' => $eq_data['description'] ?? '',
                    'quantite_attendue' => (int)($eq_data['quantite_attendue'] ?? 0),
                    'quantite_presente' => isset($eq_data['quantite_presente']) ? (int)$eq_data['quantite_presente'] : null,
                    'etat' => $eq_data['etat'] ?? null,
                    'observations' => $eq_data['observations'] ?? null,
                    'photos' => []
                ];
            }
        }
        
        $stmt = $pdo->prepare("
            UPDATE inventaires SET
                equipements_data = ?,
                observations_generales = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            json_encode($equipements_data, JSON_UNESCAPED_UNICODE),
            $_POST['observations_generales'] ?? null,
            $inventaire_id
        ]);
        
        $_SESSION['success'] = "Inventaire mis à jour avec succès";
        header("Location: edit-inventaire.php?id=$inventaire_id");
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur lors de la mise à jour: " . $e->getMessage();
        error_log("Erreur update inventaire: " . $e->getMessage());
    }
}

// Decode equipment data
$equipements_data = json_decode($inventaire['equipements_data'], true);
if (!is_array($equipements_data)) {
    $equipements_data = [];
}

// Group by category
$equipements_by_category = [];
foreach ($equipements_data as $eq) {
    $cat = $eq['categorie'] ?? 'Autre';
    if (!isset($equipements_by_category[$cat])) {
        $equipements_by_category[$cat] = [];
    }
    $equipements_by_category[$cat][] = $eq;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier l'inventaire - <?php echo htmlspecialchars($inventaire['reference_unique']); ?></title>
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
        .form-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .category-section {
            margin-bottom: 30px;
            border-left: 4px solid #0d6efd;
            padding-left: 15px;
        }
        .equipment-row {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/menu.php'; ?>

    <div class="main-content">
        <div class="header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4>Modifier l'inventaire</h4>
                    <p class="text-muted mb-0">
                        <?php echo htmlspecialchars($inventaire['reference_unique']); ?> - 
                        Inventaire d'<?php echo $inventaire['type']; ?> - 
                        <?php echo htmlspecialchars($inventaire['logement_reference']); ?>
                    </p>
                </div>
                <div>
                    <a href="download-inventaire.php?id=<?php echo $inventaire_id; ?>" class="btn btn-info" target="_blank">
                        <i class="bi bi-file-pdf"></i> Voir le PDF
                    </a>
                    <a href="inventaires.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Retour
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

        <form method="POST">
            <?php foreach ($equipements_by_category as $categorie => $equipements): ?>
                <div class="form-card">
                    <div class="category-section">
                        <h5><i class="bi bi-box-seam"></i> <?php echo htmlspecialchars($categorie); ?></h5>
                        
                        <?php foreach ($equipements as $eq): 
                            $eq_id = $eq['equipement_id'];
                        ?>
                            <div class="equipment-row">
                                <div class="row align-items-center">
                                    <div class="col-md-4">
                                        <strong><?php echo htmlspecialchars($eq['nom']); ?></strong>
                                        <?php if (!empty($eq['description'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($eq['description']); ?></small>
                                        <?php endif; ?>
                                        <input type="hidden" name="equipement[<?php echo $eq_id; ?>][nom]" value="<?php echo htmlspecialchars($eq['nom']); ?>">
                                        <input type="hidden" name="equipement[<?php echo $eq_id; ?>][categorie]" value="<?php echo htmlspecialchars($categorie); ?>">
                                        <input type="hidden" name="equipement[<?php echo $eq_id; ?>][description]" value="<?php echo htmlspecialchars($eq['description']); ?>">
                                        <input type="hidden" name="equipement[<?php echo $eq_id; ?>][quantite_attendue]" value="<?php echo $eq['quantite_attendue']; ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">Qté attendue</label>
                                        <input type="number" class="form-control form-control-sm" value="<?php echo $eq['quantite_attendue']; ?>" disabled>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">Qté présente</label>
                                        <input type="number" name="equipement[<?php echo $eq_id; ?>][quantite_presente]" 
                                               class="form-control form-control-sm" 
                                               value="<?php echo $eq['quantite_presente'] ?? ''; ?>" min="0">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">État</label>
                                        <select name="equipement[<?php echo $eq_id; ?>][etat]" class="form-select form-select-sm">
                                            <option value="">-</option>
                                            <option value="Bon" <?php echo ($eq['etat'] ?? '') === 'Bon' ? 'selected' : ''; ?>>Bon</option>
                                            <option value="Moyen" <?php echo ($eq['etat'] ?? '') === 'Moyen' ? 'selected' : ''; ?>>Moyen</option>
                                            <option value="Mauvais" <?php echo ($eq['etat'] ?? '') === 'Mauvais' ? 'selected' : ''; ?>>Mauvais</option>
                                            <option value="Manquant" <?php echo ($eq['etat'] ?? '') === 'Manquant' ? 'selected' : ''; ?>>Manquant</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">Observations</label>
                                        <input type="text" name="equipement[<?php echo $eq_id; ?>][observations]" 
                                               class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars($eq['observations'] ?? ''); ?>" 
                                               placeholder="Notes...">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="form-card">
                <h5>Observations générales</h5>
                <textarea name="observations_generales" class="form-control" rows="4" 
                          placeholder="Observations générales sur l'inventaire..."><?php echo htmlspecialchars($inventaire['observations_generales'] ?? ''); ?></textarea>
            </div>

            <div class="d-flex justify-content-between">
                <a href="inventaires.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Annuler
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Enregistrer
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
