<?php
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

// Get état des lieux ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id < 1) {
    $_SESSION['error'] = "ID de l'état des lieux invalide";
    header('Location: etats-lieux.php');
    exit;
}

// Get état des lieux details
$stmt = $pdo->prepare("
    SELECT edl.*, 
           c.reference_unique as contrat_ref,
           c.date_prise_effet as date_debut, 
           c.date_fin_prevue as date_fin,
           CONCAT(cand.prenom, ' ', cand.nom) as locataire,
           cand.email as locataire_email,
           l.adresse, l.type as type_logement, l.surface
    FROM etats_lieux edl
    LEFT JOIN contrats c ON edl.contrat_id = c.id
    LEFT JOIN candidatures cand ON c.candidature_id = cand.id
    LEFT JOIN logements l ON c.logement_id = l.id
    WHERE edl.id = ?
");
$stmt->execute([$id]);
$etat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$etat) {
    $_SESSION['error'] = "État des lieux non trouvé";
    header('Location: etats-lieux.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>État des lieux - <?php echo htmlspecialchars($etat['contrat_ref']); ?></title>
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
        .info-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .info-label {
            font-weight: 600;
            color: #6c757d;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }
        .info-value {
            font-size: 1rem;
            color: #212529;
            margin-bottom: 1rem;
        }
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #212529;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e9ecef;
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
                        <?php if ($etat['type'] === 'entree'): ?>
                            <i class="bi bi-box-arrow-in-right text-success"></i>
                        <?php else: ?>
                            <i class="bi bi-box-arrow-right text-danger"></i>
                        <?php endif; ?>
                        État des lieux <?php echo ucfirst(htmlspecialchars($etat['type'])); ?>
                    </h4>
                    <p class="text-muted mb-0">
                        Contrat: <?php echo htmlspecialchars($etat['contrat_ref']); ?>
                    </p>
                </div>
                <div>
                    <a href="etats-lieux.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Retour
                    </a>
                    <?php if ($etat['type'] === 'sortie'): ?>
                    <a href="edit-bilan-logement.php?id=<?php echo $id; ?>" class="btn btn-info">
                        <i class="bi bi-clipboard-check"></i> Bilan du logement
                    </a>
                    <?php endif; ?>
                    <a href="edit-etat-lieux.php?id=<?php echo $id; ?>" class="btn btn-warning">
                        <i class="bi bi-pencil"></i> Modifier
                    </a>
                    <a href="download-etat-lieux.php?id=<?php echo $id; ?>" class="btn btn-primary" target="_blank">
                        <i class="bi bi-download"></i> Télécharger PDF
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

        <div class="row">
            <!-- Left Column -->
            <div class="col-md-6">
                <div class="info-card">
                    <div class="section-title">
                        <i class="bi bi-file-text"></i> Informations générales
                    </div>
                    
                    <div class="info-label">Type</div>
                    <div class="info-value">
                        <span class="badge bg-<?php echo $etat['type'] === 'entree' ? 'success' : 'danger'; ?>">
                            État des lieux <?php echo ucfirst(htmlspecialchars($etat['type'])); ?>
                        </span>
                    </div>

                    <div class="info-label">Date de l'état des lieux</div>
                    <div class="info-value">
                        <?php echo date('d/m/Y', strtotime($etat['date_etat'])); ?>
                    </div>

                    <div class="info-label">Référence du contrat</div>
                    <div class="info-value"><?php echo htmlspecialchars($etat['contrat_ref']); ?></div>

                    <div class="info-label">Période du contrat</div>
                    <div class="info-value">
                        Du <?php echo date('d/m/Y', strtotime($etat['date_debut'])); ?>
                        au <?php echo date('d/m/Y', strtotime($etat['date_fin'])); ?>
                    </div>
                </div>

                <div class="info-card">
                    <div class="section-title">
                        <i class="bi bi-person"></i> Locataire
                    </div>
                    
                    <div class="info-label">Nom</div>
                    <div class="info-value"><?php echo htmlspecialchars($etat['locataire']); ?></div>

                    <div class="info-label">Email</div>
                    <div class="info-value"><?php echo htmlspecialchars($etat['locataire_email']); ?></div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-md-6">
                <div class="info-card">
                    <div class="section-title">
                        <i class="bi bi-house"></i> Logement
                    </div>
                    
                    <div class="info-label">Adresse</div>
                    <div class="info-value"><?php echo htmlspecialchars($etat['adresse']); ?></div>

                    <div class="info-label">Type</div>
                    <div class="info-value"><?php echo htmlspecialchars($etat['type_logement']); ?></div>

                    <div class="info-label">Surface</div>
                    <div class="info-value"><?php echo htmlspecialchars($etat['surface']); ?> m²</div>
                </div>

                <div class="info-card">
                    <div class="section-title">
                        <i class="bi bi-chat-left-text"></i> Observations
                    </div>
                    
                    <div class="info-label">État général</div>
                    <div class="info-value">
                        <?php echo !empty($etat['etat_general']) ? nl2br(htmlspecialchars($etat['etat_general'])) : '<em class="text-muted">Aucune observation</em>'; ?>
                    </div>

                    <div class="info-label">Observations complémentaires</div>
                    <div class="info-value">
                        <?php echo !empty($etat['observations']) ? nl2br(htmlspecialchars($etat['observations'])) : '<em class="text-muted">Aucune observation</em>'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
