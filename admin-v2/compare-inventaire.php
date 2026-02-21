<?php
/**
 * Compare Inventaire - Side-by-side comparison of entry and exit inventories
 * Simplified version
 */
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

$contrat_id = isset($_GET['contrat_id']) ? (int)$_GET['contrat_id'] : 0;
$entree_id = isset($_GET['entree']) ? (int)$_GET['entree'] : 0;
$sortie_id = isset($_GET['sortie']) ? (int)$_GET['sortie'] : 0;

if ($entree_id && $sortie_id) {
    // Direct inventory IDs provided (from contrat-detail.php)
    $stmt = $pdo->prepare("SELECT * FROM inventaires WHERE id = ? AND type = 'entree' LIMIT 1");
    $stmt->execute([$entree_id]);
    $inv_entree = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM inventaires WHERE id = ? AND type = 'sortie' LIMIT 1");
    $stmt->execute([$sortie_id]);
    $inv_sortie = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($contrat_id) {
    // Lookup by contract ID
    $stmt = $pdo->prepare("SELECT * FROM inventaires WHERE contrat_id = ? AND type = 'entree' ORDER BY date_inventaire DESC LIMIT 1");
    $stmt->execute([$contrat_id]);
    $inv_entree = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM inventaires WHERE contrat_id = ? AND type = 'sortie' ORDER BY date_inventaire DESC LIMIT 1");
    $stmt->execute([$contrat_id]);
    $inv_sortie = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $_SESSION['error'] = "Contrat non spécifié";
    header('Location: inventaires.php');
    exit;
}

if (!$inv_entree || !$inv_sortie) {
    $_SESSION['error'] = "Les deux inventaires (entrée et sortie) doivent exister pour effectuer une comparaison";
    header('Location: inventaires.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comparaison des inventaires</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <?php require_once __DIR__ . '/includes/sidebar-styles.php'; ?>
    <style>
        .header { background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .comparison-card { background: white; border-radius: 10px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .diff-highlight { background-color: #fff3cd; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/menu.php'; ?>

    <div class="main-content">
        <div class="header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4><i class="bi bi-arrows-angle-contract"></i> Comparaison des inventaires</h4>
                    <p class="text-muted mb-0">Entrée vs Sortie</p>
                </div>
                <a href="inventaires.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Retour
                </a>
            </div>
        </div>

        <div class="comparison-card">
            <div class="alert alert-info">
                <strong>Note:</strong> Cette page affiche une comparaison côte à côte des inventaires d'entrée et de sortie. 
                Les différences sont mises en évidence.
            </strong>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <h5 class="text-success">Inventaire d'entrée</h5>
                    <p><strong>Référence:</strong> <?php echo htmlspecialchars($inv_entree['reference_unique']); ?></p>
                    <p><strong>Date:</strong> <?php echo date('d/m/Y', strtotime($inv_entree['date_inventaire'])); ?></p>
                    <a href="view-inventaire.php?id=<?php echo $inv_entree['id']; ?>" class="btn btn-sm btn-success">Voir détails</a>
                </div>
                <div class="col-md-6">
                    <h5 class="text-danger">Inventaire de sortie</h5>
                    <p><strong>Référence:</strong> <?php echo htmlspecialchars($inv_sortie['reference_unique']); ?></p>
                    <p><strong>Date:</strong> <?php echo date('d/m/Y', strtotime($inv_sortie['date_inventaire'])); ?></p>
                    <a href="view-inventaire.php?id=<?php echo $inv_sortie['id']; ?>" class="btn btn-sm btn-danger">Voir détails</a>
                </div>
            </div>

            <hr class="my-4">

            <p class="text-muted">
                <i class="bi bi-info-circle"></i> 
                Pour une comparaison détaillée des équipements, veuillez consulter les PDF de chaque inventaire ou utiliser la vue détaillée.
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
