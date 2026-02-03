<?php
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

$contractId = (int)($_GET['id'] ?? 0);

if ($contractId === 0) {
    $_SESSION['error'] = "ID de contrat invalide.";
    header('Location: contrats.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Check which columns exist (used by both validate and cancel actions)
    $existingColumns = [];
    $result = $pdo->query("
        SELECT COLUMN_NAME 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'contrats' 
        AND COLUMN_NAME IN ('validated_by', 'validation_notes', 'motif_annulation')
    ");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[$row['COLUMN_NAME']] = true;
    }
    
    if ($_POST['action'] === 'validate') {
        // Validate the contract and add company signature
        $notes = trim($_POST['validation_notes'] ?? '');
        $adminId = $_SESSION['admin_id'] ?? null;
        
        // Build UPDATE query based on existing columns
        $updateFields = ['statut = ?', 'date_validation = NOW()'];
        $params = ['valide'];
        
        if (isset($existingColumns['validation_notes'])) {
            $updateFields[] = 'validation_notes = ?';
            $params[] = $notes;
        }
        
        if (isset($existingColumns['validated_by'])) {
            $updateFields[] = 'validated_by = ?';
            $params[] = $adminId;
        }
        
        $params[] = $contractId;
        
        $stmt = $pdo->prepare("
            UPDATE contrats 
            SET " . implode(', ', $updateFields) . "
            WHERE id = ?
        ");
        $stmt->execute($params);
        
        // Regenerate PDF with company signature now that contract is validated
        error_log("Contract Validation: Régénération du PDF pour contrat #$contractId après validation");
        require_once __DIR__ . '/../pdf/generate-bail.php';
        $pdfPath = generateBailPDF($contractId);
        
        // Check if PDF generation was successful
        if (!$pdfPath) {
            error_log("Contract Validation: ERREUR - La régénération du PDF a échoué (generateBailPDF a retourné false) pour contrat #$contractId");
        } elseif (!file_exists($pdfPath)) {
            error_log("Contract Validation: ERREUR - Le PDF régénéré n'existe pas: $pdfPath pour contrat #$contractId");
        } else {
            error_log("Contract Validation: PDF régénéré avec succès: $pdfPath pour contrat #$contractId");
        }
        
        // Get contract and tenant details for emails
        // Important: Select c.* first, then explicitly name logements columns to avoid column name collision
        $contrat = fetchOne("
            SELECT c.*, 
                   c.id as contrat_id, 
                   c.reference_unique as reference_contrat,
                   l.reference,
                   l.adresse,
                   l.appartement,
                   l.type,
                   l.surface,
                   l.loyer,
                   l.charges,
                   l.depot_garantie,
                   l.parking
            FROM contrats c
            INNER JOIN logements l ON c.logement_id = l.id
            WHERE c.id = ?
        ", [$contractId]);
        
        $locataires = fetchAll("
            SELECT * FROM locataires 
            WHERE contrat_id = ? 
            ORDER BY ordre
        ", [$contractId]);
        
        // Prepare email data
        $locatairesNames = array_map(function($loc) {
            return $loc['prenom'] . ' ' . $loc['nom'];
        }, $locataires);
        
        $adminInfo = fetchOne("SELECT nom, prenom FROM administrateurs WHERE id = ?", [$adminId]);
        $adminName = $adminInfo ? $adminInfo['prenom'] . ' ' . $adminInfo['nom'] : 'Administrateur';
        
        // Send email to client
        if (!empty($locataires)) {
            $firstTenant = $locataires[0];
            sendTemplatedEmail('contrat_valide_client', $firstTenant['email'], [
                'nom' => $firstTenant['nom'],
                'prenom' => $firstTenant['prenom'],
                'reference' => $contrat['reference_contrat'],
                'logement' => $contrat['reference'] . ' - ' . $contrat['adresse'],
                'date_prise_effet' => date('d/m/Y', strtotime($contrat['date_prise_effet'])),
                'depot_garantie' => number_format($contrat['depot_garantie'], 2, ',', ' '),
                'lien_telecharger' => BASE_URL . '/pdf/download.php?contrat_id=' . $contractId
            ]);
        }
        
        // Send email to admins
        sendTemplatedEmail('contrat_valide_admin', ADMIN_EMAIL, [
            'reference' => $contrat['reference_contrat'],
            'logement' => $contrat['reference'] . ' - ' . $contrat['adresse'],
            'locataires' => implode(', ', $locatairesNames),
            'admin_nom' => $adminName,
            'date_validation' => date('d/m/Y H:i')
        ]);
        
        $_SESSION['success'] = "Contrat validé avec succès. La signature électronique de la société a été ajoutée au PDF.";
        header('Location: contrat-detail.php?id=' . $contractId);
        exit;
    }
    elseif ($_POST['action'] === 'cancel') {
        // Cancel the contract
        $motif = trim($_POST['motif_annulation'] ?? '');
        $adminId = $_SESSION['admin_id'] ?? null;
        
        if (empty($motif)) {
            $_SESSION['error'] = "Le motif d'annulation est requis.";
            header('Location: contrat-detail.php?id=' . $contractId);
            exit;
        }
        
        // Build UPDATE query based on existing columns
        $updateFields = ['statut = ?', 'updated_at = NOW()'];
        $params = ['annule'];
        
        if (isset($existingColumns['motif_annulation'])) {
            $updateFields[] = 'motif_annulation = ?';
            $params[] = $motif;
        }
        
        if (isset($existingColumns['validated_by'])) {
            $updateFields[] = 'validated_by = ?';
            $params[] = $adminId;
        }
        
        $params[] = $contractId;
        
        $stmt = $pdo->prepare("
            UPDATE contrats 
            SET " . implode(', ', $updateFields) . "
            WHERE id = ?
        ");
        $stmt->execute($params);
        
        // Get contract and tenant details for emails
        // Important: Select c.* first, then explicitly name logements columns to avoid column name collision
        $contrat = fetchOne("
            SELECT c.*, 
                   c.id as contrat_id, 
                   c.reference_unique as reference_contrat,
                   l.reference,
                   l.adresse,
                   l.appartement,
                   l.type,
                   l.surface,
                   l.loyer,
                   l.charges,
                   l.depot_garantie,
                   l.parking
            FROM contrats c
            INNER JOIN logements l ON c.logement_id = l.id
            WHERE c.id = ?
        ", [$contractId]);
        
        $locataires = fetchAll("
            SELECT * FROM locataires 
            WHERE contrat_id = ? 
            ORDER BY ordre
        ", [$contractId]);
        
        // Prepare email data
        $locatairesNames = array_map(function($loc) {
            return $loc['prenom'] . ' ' . $loc['nom'];
        }, $locataires);
        
        $adminInfo = fetchOne("SELECT nom, prenom FROM administrateurs WHERE id = ?", [$adminId]);
        $adminName = $adminInfo ? $adminInfo['prenom'] . ' ' . $adminInfo['nom'] : 'Administrateur';
        
        // Send email to client
        if (!empty($locataires)) {
            $firstTenant = $locataires[0];
            sendTemplatedEmail('contrat_annule_client', $firstTenant['email'], [
                'nom' => $firstTenant['nom'],
                'prenom' => $firstTenant['prenom'],
                'reference' => $contrat['reference_contrat'],
                'logement' => $contrat['reference'] . ' - ' . $contrat['adresse'],
                'motif_annulation' => $motif
            ]);
        }
        
        // Send email to admins
        sendTemplatedEmail('contrat_annule_admin', ADMIN_EMAIL, [
            'reference' => $contrat['reference_contrat'],
            'logement' => $contrat['reference'] . ' - ' . $contrat['adresse'],
            'locataires' => implode(', ', $locatairesNames),
            'admin_nom' => $adminName,
            'date_annulation' => date('d/m/Y H:i'),
            'motif_annulation' => $motif
        ]);
        
        $_SESSION['success'] = "Contrat annulé. Le client a été notifié.";
        header('Location: contrat-detail.php?id=' . $contractId);
        exit;
    }
}

// Get contract details
$contrat = fetchOne("
    SELECT c.*, 
           l.reference as logement_ref, 
           l.adresse as logement_adresse,
           l.appartement,
           l.type,
           l.surface,
           l.loyer,
           l.charges,
           l.depot_garantie,
           l.parking,
           (SELECT COUNT(*) FROM locataires WHERE contrat_id = c.id) as nb_locataires_total,
           (SELECT COUNT(*) FROM locataires WHERE contrat_id = c.id AND signature_data IS NOT NULL) as nb_locataires_signed
    FROM contrats c
    LEFT JOIN logements l ON c.logement_id = l.id
    WHERE c.id = ?
", [$contractId]);

if (!$contrat) {
    $_SESSION['error'] = "Contrat non trouvé.";
    header('Location: contrats.php');
    exit;
}

// Get tenants
$locataires = fetchAll("
    SELECT * FROM locataires 
    WHERE contrat_id = ? 
    ORDER BY ordre
", [$contractId]);

// Get validator info if exists
$validatorInfo = null;
if ($contrat['validated_by']) {
    $validatorInfo = fetchOne("SELECT nom, prenom FROM administrateurs WHERE id = ?", [$contrat['validated_by']]);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails du Contrat - <?php echo htmlspecialchars($contrat['reference_unique']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <?php require_once __DIR__ . '/includes/sidebar-styles.php'; ?>
    <style>
        .header {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .detail-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .detail-card h5 {
            color: #2c3e50;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
        }
        .info-row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid #ecf0f1;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #2c3e50;
            width: 200px;
            flex-shrink: 0;
        }
        .info-value {
            color: #34495e;
        }
        .status-badge {
            padding: 8px 15px;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: 500;
            display: inline-block;
        }
        .status-en_attente { background: #fff3cd; color: #856404; }
        .status-signe { background: #cfe2ff; color: #084298; }
        .status-en_verification { background: #fff3cd; color: #664d03; }
        .status-valide { background: #d4edda; color: #155724; }
        .status-expire { background: #f8d7da; color: #721c24; }
        .status-annule { background: #e2e3e5; color: #383d41; }
        .tenant-card {
            background: #f8f9fa;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .signature-preview {
            max-width: 300px;
            max-height: 150px;
            border: 0;
            border-radius: 4px;
            padding: 5px;
        }
        .action-section {
            background: #e8f4f8;
            border-left: 4px solid #3498db;
            padding: 20px;
            border-radius: 4px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/menu.php'; ?>

    <div class="main-content">
        <div class="header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4><i class="bi bi-file-earmark-text"></i> Détails du Contrat</h4>
                    <p class="mb-0 text-muted">Référence: <strong><?php echo htmlspecialchars($contrat['reference_unique']); ?></strong></p>
                </div>
                <div>
                    <a href="contrats.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Retour
                    </a>
                    <?php if ($contrat['statut'] === 'valide' || $contrat['statut'] === 'signe'): ?>
                        <a href="../pdf/download.php?contrat_id=<?php echo $contrat['id']; ?>" class="btn btn-success">
                            <i class="bi bi-download"></i> Télécharger PDF
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-6">
                <!-- Contract Information -->
                <div class="detail-card">
                    <h5><i class="bi bi-file-earmark-check"></i> Informations du Contrat</h5>
                    <div class="info-row">
                        <div class="info-label">Statut</div>
                        <div class="info-value">
                            <span class="status-badge status-<?php echo $contrat['statut']; ?>">
                                <?php
                                $statut_labels = [
                                    'en_attente' => 'En attente de signature',
                                    'signe' => 'Signé par le client',
                                    'en_verification' => 'En vérification',
                                    'valide' => 'Validé',
                                    'expire' => 'Expiré',
                                    'annule' => 'Annulé'
                                ];
                                echo $statut_labels[$contrat['statut']] ?? $contrat['statut'];
                                ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Nombre de locataires</div>
                        <div class="info-value"><?php echo $contrat['nb_locataires']; ?> (<?php echo $contrat['nb_locataires_signed']; ?> signé(s))</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Date de création</div>
                        <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($contrat['date_creation'])); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Date d'expiration lien</div>
                        <div class="info-value">
                            <?php if ($contrat['date_expiration']): ?>
                                <?php echo date('d/m/Y H:i', strtotime($contrat['date_expiration'])); ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Date de signature</div>
                        <div class="info-value">
                            <?php if ($contrat['date_signature']): ?>
                                <?php echo date('d/m/Y H:i', strtotime($contrat['date_signature'])); ?>
                            <?php else: ?>
                                <span class="text-muted">Non signé</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (isset($contrat['date_validation']) && $contrat['date_validation']): ?>
                    <div class="info-row">
                        <div class="info-label">Date de validation</div>
                        <div class="info-value">
                            <?php echo date('d/m/Y H:i', strtotime($contrat['date_validation'])); ?>
                            <?php if ($validatorInfo): ?>
                                <br><small class="text-muted">Par: <?php echo htmlspecialchars($validatorInfo['prenom'] . ' ' . $validatorInfo['nom']); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($contrat['date_prise_effet']): ?>
                    <div class="info-row">
                        <div class="info-label">Date de prise d'effet</div>
                        <div class="info-value"><?php echo date('d/m/Y', strtotime($contrat['date_prise_effet'])); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($contrat['validation_notes']) && $contrat['validation_notes']): ?>
                    <div class="info-row">
                        <div class="info-label">Notes de validation</div>
                        <div class="info-value"><?php echo nl2br(htmlspecialchars($contrat['validation_notes'])); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($contrat['motif_annulation']) && $contrat['motif_annulation']): ?>
                    <div class="info-row">
                        <div class="info-label">Motif d'annulation</div>
                        <div class="info-value text-danger"><?php echo nl2br(htmlspecialchars($contrat['motif_annulation'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-6">
                <!-- Property Information -->
                <div class="detail-card">
                    <h5><i class="bi bi-building"></i> Informations du Logement</h5>
                    <div class="info-row">
                        <div class="info-label">Référence</div>
                        <div class="info-value"><?php echo htmlspecialchars($contrat['logement_ref']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Adresse</div>
                        <div class="info-value"><?php echo htmlspecialchars($contrat['logement_adresse']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Appartement</div>
                        <div class="info-value"><?php echo htmlspecialchars($contrat['appartement']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Type</div>
                        <div class="info-value"><?php echo htmlspecialchars($contrat['type']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Surface</div>
                        <div class="info-value"><?php echo htmlspecialchars($contrat['surface']); ?> m²</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Loyer</div>
                        <div class="info-value"><?php echo number_format($contrat['loyer'], 2, ',', ' '); ?> €</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Charges</div>
                        <div class="info-value"><?php echo number_format($contrat['charges'], 2, ',', ' '); ?> €</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Loyer total</div>
                        <div class="info-value"><strong><?php echo number_format($contrat['loyer'] + $contrat['charges'], 2, ',', ' '); ?> €</strong></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Dépôt de garantie</div>
                        <div class="info-value"><?php echo number_format($contrat['depot_garantie'], 2, ',', ' '); ?> €</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Parking</div>
                        <div class="info-value"><?php echo htmlspecialchars($contrat['parking']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tenants Information -->
        <div class="detail-card">
            <h5><i class="bi bi-people"></i> Locataires</h5>
            <?php if (empty($locataires)): ?>
                <p class="text-muted">Aucun locataire enregistré.</p>
            <?php else: ?>
                <?php foreach ($locataires as $locataire): ?>
                    <div class="tenant-card">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Locataire <?php echo $locataire['ordre']; ?></h6>
                                <div class="info-row">
                                    <div class="info-label">Nom</div>
                                    <div class="info-value"><?php echo htmlspecialchars($locataire['nom']); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Prénom</div>
                                    <div class="info-value"><?php echo htmlspecialchars($locataire['prenom']); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Date de naissance</div>
                                    <div class="info-value"><?php echo date('d/m/Y', strtotime($locataire['date_naissance'])); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Email</div>
                                    <div class="info-value"><?php echo htmlspecialchars($locataire['email']); ?></div>
                                </div>
                                <?php if ($locataire['telephone']): ?>
                                <div class="info-row">
                                    <div class="info-label">Téléphone</div>
                                    <div class="info-value"><?php echo htmlspecialchars($locataire['telephone']); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <?php if ($locataire['signature_timestamp']): ?>
                                    <div class="mb-3">
                                        <strong><i class="bi bi-check-circle text-success"></i> Signature validée</strong>
                                        <br><small class="text-muted">Le <?php echo date('d/m/Y H:i', strtotime($locataire['signature_timestamp'])); ?></small>
                                        <br><small class="text-muted">IP: <?php echo htmlspecialchars($locataire['signature_ip']); ?></small>
                                    </div>
                                    <?php if ($locataire['signature_data']): ?>
                                        <div>
                                            <strong>Aperçu de la signature:</strong><br>
                                            <img src="<?php echo htmlspecialchars($locataire['signature_data']); ?>" 
                                                 alt="Signature" 
                                                 class="signature-preview">
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="alert alert-warning mb-0">
                                        <i class="bi bi-exclamation-triangle"></i> Non signé
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($locataire['piece_identite_recto']): ?>
                                    <div class="mt-3">
                                        <strong><i class="bi bi-check-circle text-success"></i> Pièce d'identité uploadée</strong>
                                    </div>
                                <?php else: ?>
                                    <div class="mt-3">
                                        <i class="bi bi-x-circle text-danger"></i> Pièce d'identité non uploadée
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($locataire['mention_lu_approuve']): ?>
                                    <div class="mt-2">
                                        <i class="bi bi-check-circle text-success"></i> Mention "Lu et approuvé" validée
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Documents Section -->
        <div class="detail-card">
            <h5><i class="bi bi-file-earmark-text"></i> Documents Envoyés</h5>
            <?php
            // Helper function to check if tenant has documents
            function tenantHasDocuments($locataire) {
                return !empty($locataire['piece_identite_recto']) || 
                       !empty($locataire['piece_identite_verso']) || 
                       !empty($locataire['preuve_paiement_depot']);
            }
            
            // Helper function to validate and sanitize filename for security
            function validateAndSanitizeFilename($filename) {
                if (empty($filename)) {
                    return null;
                }
                
                // Security: Prevent directory traversal attacks
                // basename() removes any directory components, keeping only the filename
                // This is defense-in-depth: basename already removes .., /, and \
                $filename = basename($filename);
                
                // Verify the filename is not empty after sanitization
                if (empty($filename)) {
                    return null;
                }
                
                return $filename;
            }
            
            // Helper function to validate file path is within uploads directory
            function validateFilePath($relativePath) {
                $uploadsDir = dirname(__DIR__) . '/uploads/';
                $fullPath = $uploadsDir . $relativePath;
                
                // Get real paths for comparison
                $realUploadsDir = realpath($uploadsDir);
                $realFilePath = realpath($fullPath);
                
                // If file doesn't exist, realpath returns false
                if ($realFilePath === false) {
                    return null;
                }
                
                // Ensure the resolved path is within the uploads directory
                if (strpos($realFilePath, $realUploadsDir) !== 0) {
                    return null;
                }
                
                return $fullPath;
            }
            
            // Helper function to render document card
            function renderDocumentCard($documentPath, $title, $icon) {
                $safePath = validateAndSanitizeFilename($documentPath);
                if (!$safePath) {
                    return;
                }
                
                $extension = strtolower(pathinfo($safePath, PATHINFO_EXTENSION));
                $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif']);
                $relativePath = '../uploads/' . $safePath;
                
                // Validate the file path is within uploads and exists
                $validatedPath = validateFilePath($safePath);
                $fileExists = ($validatedPath !== null);
                
                echo '<div class="col-md-4 mb-3">';
                echo '    <div class="card">';
                echo '        <div class="card-body">';
                echo '            <h6 class="card-title"><i class="bi bi-' . htmlspecialchars($icon) . '"></i> ' . htmlspecialchars($title) . '</h6>';
                
                // Show image preview if it's an image and file exists
                if ($isImage && $fileExists) {
                    echo '            <img src="' . htmlspecialchars($relativePath) . '" class="img-fluid mb-2" style="max-height: 150px; object-fit: cover;" alt="' . htmlspecialchars($title) . '">';
                }
                
                // Only show download button if file exists
                if ($fileExists) {
                    echo '            <a href="' . htmlspecialchars($relativePath) . '" ';
                    echo '               class="btn btn-sm btn-primary" ';
                    echo '               download ';
                    echo '               target="_blank">';
                    echo '                <i class="bi bi-download"></i> Télécharger';
                    echo '            </a>';
                } else {
                    echo '            <p class="text-muted small mb-0">Fichier non disponible</p>';
                }
                
                echo '        </div>';
                echo '    </div>';
                echo '</div>';
            }
            
            // Check if any tenant has documents
            $hasDocuments = false;
            foreach ($locataires as $locataire) {
                if (tenantHasDocuments($locataire)) {
                    $hasDocuments = true;
                    break;
                }
            }
            
            if (!$hasDocuments): ?>
                <p class="text-muted">Aucun document envoyé pour le moment.</p>
            <?php else: ?>
                <?php foreach ($locataires as $locataire): ?>
                    <?php if (!tenantHasDocuments($locataire)) continue; ?>
                    <div class="mb-4">
                        <h6><i class="bi bi-person"></i> Locataire <?php echo $locataire['ordre']; ?> - <?php echo htmlspecialchars($locataire['prenom'] . ' ' . $locataire['nom']); ?></h6>
                        <div class="row mt-2">
                            <?php
                            if (!empty($locataire['piece_identite_recto'])) {
                                renderDocumentCard($locataire['piece_identite_recto'], 'Pièce d\'identité (Recto)', 'card-image');
                            }
                            if (!empty($locataire['piece_identite_verso'])) {
                                renderDocumentCard($locataire['piece_identite_verso'], 'Pièce d\'identité (Verso)', 'card-image');
                            }
                            if (!empty($locataire['preuve_paiement_depot'])) {
                                renderDocumentCard($locataire['preuve_paiement_depot'], 'Preuve de paiement', 'receipt');
                            }
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Action Section for signed contracts -->
        <?php if ($contrat['statut'] === 'signe'): ?>
        <div class="action-section">
            <h5><i class="bi bi-clipboard-check"></i> Actions de Vérification</h5>
            <p>Le contrat a été signé par le client. Vous devez maintenant vérifier les informations et:</p>
            <ul>
                <li><strong>Valider</strong> le contrat si tout est correct (la signature électronique de la société sera ajoutée automatiquement)</li>
                <li><strong>Annuler</strong> le contrat si des corrections sont nécessaires (possibilité de régénérer un nouveau contrat)</li>
            </ul>
            
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card border-success">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="bi bi-check-circle"></i> Valider le Contrat</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" onsubmit="return confirm('Êtes-vous sûr de vouloir valider ce contrat ?\n\nCette action ajoutera la signature électronique de la société au PDF et notifiera le client.');">
                                <input type="hidden" name="action" value="validate">
                                <div class="mb-3">
                                    <label for="validation_notes" class="form-label">Notes de validation (optionnel)</label>
                                    <textarea 
                                        class="form-control" 
                                        id="validation_notes" 
                                        name="validation_notes" 
                                        rows="3" 
                                        placeholder="Notes internes..."></textarea>
                                </div>
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-check-circle"></i> Valider le Contrat
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card border-danger">
                        <div class="card-header bg-danger text-white">
                            <h6 class="mb-0"><i class="bi bi-x-circle"></i> Annuler le Contrat</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" onsubmit="return confirm('Êtes-vous sûr de vouloir annuler ce contrat ?\n\nLe client sera notifié de l\'annulation.');">
                                <input type="hidden" name="action" value="cancel">
                                <div class="mb-3">
                                    <label for="motif_annulation" class="form-label">Motif d'annulation <span class="text-danger">*</span></label>
                                    <textarea 
                                        class="form-control" 
                                        id="motif_annulation" 
                                        name="motif_annulation" 
                                        rows="3" 
                                        placeholder="Raison de l'annulation (sera communiquée au client)..."
                                        required></textarea>
                                </div>
                                <button type="submit" class="btn btn-danger w-100">
                                    <i class="bi bi-x-circle"></i> Annuler le Contrat
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
