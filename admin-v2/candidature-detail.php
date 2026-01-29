<?php
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

// Get application ID and validate
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Validate ID is positive
if (!$id || $id < 1) {
    header('Location: candidatures.php');
    exit;
}

// Fetch application details
$stmt = $pdo->prepare("
    SELECT c.*
    FROM candidatures c
    WHERE c.id = ?
");
$stmt->execute([$id]);
$candidature = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$candidature) {
    header('Location: candidatures.php');
    exit;
}

// Fetch documents separately to maintain type information
$stmt = $pdo->prepare("
    SELECT type_document, nom_fichier, chemin_fichier, uploaded_at
    FROM candidature_documents
    WHERE candidature_id = ?
    ORDER BY type_document, uploaded_at
");
$stmt->execute([$id]);
$allDocuments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch action history
// Try to fetch logs using candidature_id first (if column exists)
// Fallback to using type_entite and entite_id (polymorphic structure)
$logs = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM logs 
        WHERE candidature_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If candidature_id column doesn't exist, use polymorphic structure
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM logs 
            WHERE type_entite = 'candidature' AND entite_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$id]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        // If both queries fail, log the error and continue with empty logs
        error_log("Error fetching logs for candidature #$id: " . $e2->getMessage());
        $logs = [];
    }
}

// Process documents and group by type
$documentsByType = [];
$documentTypeLabels = [
    'piece_identite' => 'Pièce d\'identité',
    'bulletins_salaire' => 'Bulletins de salaire',
    'contrat_travail' => 'Contrat de travail',
    'avis_imposition' => 'Avis d\'imposition',
    'quittances_loyer' => 'Quittances de loyer',
    'justificatif_revenus' => 'Justificatif de revenus',
    'justificatif_domicile' => 'Justificatif de domicile',
    'autre' => 'Autre document'
];

foreach ($allDocuments as $doc) {
    $type = $doc['type_document'];
    if (!isset($documentsByType[$type])) {
        $documentsByType[$type] = [];
    }
    $documentsByType[$type][] = [
        'name' => $doc['nom_fichier'],
        'path' => $doc['chemin_fichier'],
        'uploaded_at' => $doc['uploaded_at']
    ];
}

// Status badge helper
function getStatusBadge($status) {
    $badges = [
        'En cours' => 'bg-primary',
        'Accepté' => 'bg-success',
        'Refusé' => 'bg-danger',
        'Visite planifiée' => 'bg-info',
        'Contrat envoyé' => 'bg-warning',
        'Contrat signé' => 'bg-dark'
    ];
    $class = $badges[$status] ?? 'bg-secondary';
    return "<span class='badge $class'>$status</span>";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détail Candidature - Admin MyInvest</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; }
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            width: 250px;
            background: #2c3e50;
            padding: 20px 0;
            overflow-y: auto;
        }
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 12px 20px;
            margin: 2px 0;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: #34495e;
            color: white;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .header {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .info-card {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .info-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            width: 200px;
            color: #666;
        }
        .info-value {
            flex: 1;
            color: #333;
        }
        .document-item {
            display: flex;
            align-items: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .document-type-section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        .document-type-section:last-child {
            border-bottom: none;
        }
        .document-type-header {
            font-size: 1rem;
            font-weight: 600;
            color: #0066cc;
            margin-bottom: 12px;
            padding-left: 5px;
            border-left: 3px solid #0066cc;
        }
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline-item {
            position: relative;
            padding-bottom: 20px;
        }
        .timeline-item:before {
            content: '';
            position: absolute;
            left: -22px;
            top: 5px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #007bff;
        }
        .timeline-item:after {
            content: '';
            position: absolute;
            left: -18px;
            top: 15px;
            width: 2px;
            height: 100%;
            background: #dee2e6;
        }
        .timeline-item:last-child:after {
            display: none;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="text-center mb-4">
            <h5 class="text-white">MyInvest Admin</h5>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="index.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="candidatures.php">
                    <i class="bi bi-file-earmark-text"></i> Candidatures
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logements.php">
                    <i class="bi bi-building"></i> Logements
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="contrats.php">
                    <i class="bi bi-file-earmark-check"></i> Contrats
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> Déconnexion
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header d-flex justify-content-between align-items-center">
            <div>
                <h4>Détail de la Candidature #<?php echo $candidature['reference']; ?></h4>
                <p class="text-muted mb-0">
                    <i class="bi bi-calendar"></i> Soumise le <?php echo date('d/m/Y à H:i', strtotime($candidature['created_at'])); ?>
                </p>
            </div>
            <div>
                <?php echo getStatusBadge($candidature['statut']); ?>
                <button class="btn btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#changeStatusModal">
                    <i class="bi bi-pencil"></i> Changer le statut
                </button>
            </div>
        </div>

        <div class="row">
            <!-- Left Column -->
            <div class="col-lg-8">
                <!-- Personal Information -->
                <div class="info-card">
                    <h5 class="mb-3"><i class="bi bi-person-circle"></i> Informations Personnelles</h5>
                    <div class="info-row">
                        <div class="info-label">Nom complet:</div>
                        <div class="info-value"><?php echo htmlspecialchars($candidature['nom'] . ' ' . $candidature['prenom']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Email:</div>
                        <div class="info-value">
                            <a href="mailto:<?php echo htmlspecialchars($candidature['email']); ?>">
                                <?php echo htmlspecialchars($candidature['email']); ?>
                            </a>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Téléphone:</div>
                        <div class="info-value">
                            <a href="tel:<?php echo htmlspecialchars($candidature['telephone']); ?>">
                                <?php echo htmlspecialchars($candidature['telephone']); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Professional Situation -->
                <div class="info-card">
                    <h5 class="mb-3"><i class="bi bi-briefcase"></i> Situation Professionnelle</h5>
                    <div class="info-row">
                        <div class="info-label">Statut professionnel:</div>
                        <div class="info-value">
                            <strong><?php echo htmlspecialchars($candidature['statut_professionnel']); ?></strong>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Période d'essai:</div>
                        <div class="info-value"><?php echo htmlspecialchars($candidature['periode_essai']); ?></div>
                    </div>
                </div>

                <!-- Financial Situation -->
                <div class="info-card">
                    <h5 class="mb-3"><i class="bi bi-cash-stack"></i> Situation Financière</h5>
                    <div class="info-row">
                        <div class="info-label">Revenus nets mensuels:</div>
                        <div class="info-value">
                            <strong><?php echo htmlspecialchars($candidature['revenus_mensuels']); ?></strong>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Type de revenus:</div>
                        <div class="info-value"><?php echo htmlspecialchars($candidature['type_revenus']); ?></div>
                    </div>
                </div>

                <!-- Housing Situation -->
                <div class="info-card">
                    <h5 class="mb-3"><i class="bi bi-house"></i> Situation de Logement</h5>
                    <div class="info-row">
                        <div class="info-label">Situation actuelle:</div>
                        <div class="info-value"><?php echo htmlspecialchars($candidature['situation_logement']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Préavis donné:</div>
                        <div class="info-value"><?php echo htmlspecialchars($candidature['preavis_donne']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Nombre d'occupants:</div>
                        <div class="info-value"><?php echo htmlspecialchars($candidature['nb_occupants']); ?></div>
                    </div>
                </div>

                <!-- Guarantees -->
                <div class="info-card">
                    <h5 class="mb-3"><i class="bi bi-shield-check"></i> Garanties</h5>
                    <div class="info-row">
                        <div class="info-label">Garantie Visale:</div>
                        <div class="info-value">
                            <?php 
                            $visale = htmlspecialchars($candidature['garantie_visale']);
                            $color = $visale === 'Oui' ? 'success' : ($visale === 'Non' ? 'danger' : 'warning');
                            echo "<span class='badge bg-$color'>$visale</span>";
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Documents -->
                <div class="info-card">
                    <h5 class="mb-3"><i class="bi bi-paperclip"></i> Documents Justificatifs</h5>
                    <?php if (!empty($documentsByType)): ?>
                        <?php foreach ($documentsByType as $type => $docs): ?>
                            <div class="document-type-section">
                                <div class="document-type-header">
                                    <i class="bi bi-folder"></i> 
                                    <?php 
                                    if (isset($documentTypeLabels[$type])) {
                                        echo htmlspecialchars($documentTypeLabels[$type]);
                                    } else {
                                        // Log unexpected document type
                                        error_log("Unexpected document type: $type for candidature #$id");
                                        // Display formatted fallback
                                        echo htmlspecialchars(ucfirst(str_replace('_', ' ', $type)));
                                    }
                                    ?>
                                </div>
                                <?php foreach ($docs as $doc): ?>
                                    <div class="document-item">
                                        <i class="bi bi-file-earmark-pdf text-danger me-2"></i>
                                        <span class="flex-grow-1"><?php echo htmlspecialchars($doc['name']); ?></span>
                                        <a href="../<?php echo htmlspecialchars($doc['path']); ?>" 
                                           class="btn btn-sm btn-outline-primary" 
                                           target="_blank">
                                            <i class="bi bi-download"></i> Télécharger
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">Aucun document uploadé</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-lg-4">
                <!-- Quick Actions -->
                <div class="info-card">
                    <h5 class="mb-3"><i class="bi bi-lightning"></i> Actions Rapides</h5>
                    <div class="d-grid gap-2">
                        <?php if ($candidature['statut'] === 'Accepté' || $candidature['statut'] === 'Visite planifiée'): ?>
                            <a href="generer-contrat.php?candidature_id=<?php echo $id; ?>" class="btn btn-success">
                                <i class="bi bi-file-earmark-plus"></i> Générer le contrat
                            </a>
                        <?php endif; ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sendEmailModal">
                            <i class="bi bi-envelope"></i> Envoyer un email
                        </button>
                        <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                            <i class="bi bi-chat-left-text"></i> Ajouter une note
                        </button>
                    </div>
                </div>

                <!-- Activity Timeline -->
                <div class="info-card">
                    <h5 class="mb-3"><i class="bi bi-clock-history"></i> Historique des Actions</h5>
                    <div class="timeline">
                        <?php foreach ($logs as $log): ?>
                            <div class="timeline-item">
                                <div class="small text-muted">
                                    <?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?>
                                </div>
                                <div><?php echo htmlspecialchars($log['action']); ?></div>
                                <?php if ($log['details']): ?>
                                    <div class="small text-muted"><?php echo htmlspecialchars($log['details']); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($logs)): ?>
                            <p class="text-muted">Aucune action enregistrée</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Status Modal -->
    <div class="modal fade" id="changeStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Changer le Statut</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="change-status.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="candidature_id" value="<?php echo $id; ?>">
                        <div class="mb-3">
                            <label class="form-label">Nouveau statut:</label>
                            <select name="nouveau_statut" class="form-select" required>
                                <option value="">-- Sélectionner --</option>
                                <option value="En cours" <?php echo $candidature['statut'] === 'En cours' ? 'selected' : ''; ?>>En cours</option>
                                <option value="Accepté" <?php echo $candidature['statut'] === 'Accepté' ? 'selected' : ''; ?>>Accepté</option>
                                <option value="Refusé" <?php echo $candidature['statut'] === 'Refusé' ? 'selected' : ''; ?>>Refusé</option>
                                <option value="Visite planifiée" <?php echo $candidature['statut'] === 'Visite planifiée' ? 'selected' : ''; ?>>Visite planifiée</option>
                                <option value="Contrat envoyé" <?php echo $candidature['statut'] === 'Contrat envoyé' ? 'selected' : ''; ?>>Contrat envoyé</option>
                                <option value="Contrat signé" <?php echo $candidature['statut'] === 'Contrat signé' ? 'selected' : ''; ?>>Contrat signé</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Commentaire (optionnel):</label>
                            <textarea name="commentaire" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="send_email" id="sendEmail" checked>
                            <label class="form-check-label" for="sendEmail">
                                Envoyer un email au candidat
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Confirmer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
