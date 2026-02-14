<?php
/**
 * View Inventaire - Read-only view
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
           l.type as logement_type,
           c.reference_unique as contrat_ref
    FROM inventaires inv
    INNER JOIN logements l ON inv.logement_id = l.id
    INNER JOIN contrats c ON inv.contrat_id = c.id
    WHERE inv.id = ?
");
$stmt->execute([$inventaire_id]);
$inventaire = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inventaire) {
    $_SESSION['error'] = "Inventaire introuvable";
    header('Location: inventaires.php');
    exit;
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

// Get locataires
$stmt = $pdo->prepare("SELECT * FROM inventaire_locataires WHERE inventaire_id = ?");
$stmt->execute([$inventaire_id]);
$locataires = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voir l'inventaire - <?php echo htmlspecialchars($inventaire['reference_unique']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <?php require_once __DIR__ . '/includes/sidebar-styles.php'; ?>
    <style>
        .header { background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .info-card { background: white; border-radius: 10px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .equipment-item { background: #f8f9fa; padding: 12px; border-radius: 6px; margin-bottom: 10px; }
        .badge-etat { padding: 5px 10px; font-size: 0.85rem; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/menu.php'; ?>

    <div class="main-content">
        <div class="header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4>Inventaire</h4>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($inventaire['reference_unique']); ?></p>
                </div>
                <div>
                    <a href="edit-inventaire.php?id=<?php echo $inventaire_id; ?>" class="btn btn-primary">
                        <i class="bi bi-pencil"></i> Modifier
                    </a>
                    <a href="download-inventaire.php?id=<?php echo $inventaire_id; ?>" class="btn btn-info" target="_blank">
                        <i class="bi bi-file-pdf"></i> PDF
                    </a>
                    <a href="inventaires.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Retour
                    </a>
                </div>
            </div>
        </div>

        <div class="info-card">
            <h5>Informations générales</h5>
            <div class="row mt-3">
                <div class="col-md-6">
                    <p><strong>Date:</strong> <?php echo date('d/m/Y', strtotime($inventaire['date_inventaire'])); ?></p>
                    <p><strong>Logement:</strong> <?php echo htmlspecialchars($inventaire['logement_reference'] . ' - ' . $inventaire['logement_type']); ?></p>
                    <p><strong>Adresse:</strong> <?php echo htmlspecialchars($inventaire['adresse']); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Contrat:</strong> <?php echo htmlspecialchars($inventaire['contrat_ref']); ?></p>
                    <p><strong>Locataire(s):</strong> <?php echo htmlspecialchars($inventaire['locataire_nom_complet']); ?></p>
                    <p><strong>Statut:</strong> 
                        <span class="badge bg-<?php echo $inventaire['statut'] === 'envoye' ? 'success' : ($inventaire['statut'] === 'finalise' ? 'info' : 'secondary'); ?>">
                            <?php echo ucfirst($inventaire['statut']); ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>

        <?php foreach ($equipements_by_category as $categorie => $equipements): ?>
            <div class="info-card">
                <h5><i class="bi bi-box-seam"></i> <?php echo htmlspecialchars($categorie); ?></h5>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40%;">Élément</th>
                                <th style="width: 15%;" class="text-center">Nombre</th>
                                <th style="width: 45%;">Commentaire</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($equipements as $eq): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($eq['nom']); ?></strong>
                                </td>
                                <td class="text-center">
                                    <?php 
                                    // Use helper function for backward compatibility
                                    echo htmlspecialchars(getInventaireEquipmentQuantity($eq));
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $commentaire = $eq['commentaires'] ?? ($eq['observations'] ?? '');
                                    echo htmlspecialchars($commentaire);
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (!empty($inventaire['observations_generales'])): ?>
            <div class="info-card">
                <h5>Observations générales</h5>
                <p><?php echo nl2br(htmlspecialchars($inventaire['observations_generales'])); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
