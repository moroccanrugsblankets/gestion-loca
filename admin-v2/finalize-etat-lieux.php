<?php
/**
 * Finalize and Send État des Lieux
 * My Invest Immobilier
 */

require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../pdf/generate-etat-lieux.php';

// Get état des lieux ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle finalization BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'finalize') {
    error_log("=== FINALIZE ETAT LIEUX - POST REQUEST ===");
    error_log("Action: finalize");
    
    // Need to fetch etat data for processing
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("
                SELECT edl.*, 
                       c.id as contrat_id,
                       c.reference_unique as contrat_ref,
                       l.adresse as logement_adresse,
                       l.appartement as logement_appartement
                FROM etats_lieux edl
                LEFT JOIN contrats c ON edl.contrat_id = c.id
                LEFT JOIN logements l ON c.logement_id = l.id
                WHERE edl.id = ?
            ");
            $stmt->execute([$id]);
            $etat = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($etat) {
                error_log("Starting transaction...");
                $pdo->beginTransaction();
                
                // Generate PDF
                error_log("Generating PDF for contrat_id: " . $etat['contrat_id'] . ", type: " . $etat['type']);
                $pdfPath = generateEtatDesLieuxPDF($etat['contrat_id'], $etat['type']);
                
                if (!$pdfPath || !file_exists($pdfPath)) {
                    error_log("ERROR: PDF generation failed. Path returned: " . ($pdfPath ?? 'NULL'));
                    throw new Exception("Erreur lors de la génération du PDF");
                }
                
                error_log("PDF generated successfully: " . $pdfPath);
                error_log("PDF file size: " . filesize($pdfPath) . " bytes");
                
                // Prepare email data with template variables
                $typeLabel = $etat['type'] === 'entree' ? "d'entrée" : "de sortie";
                $templateId = $etat['type'] === 'entree' ? 'etat_lieux_entree_envoye' : 'etat_lieux_sortie_envoye';
                
                $emailVariables = [
                    'locataire_nom' => $etat['locataire_nom_complet'],
                    'adresse' => $etat['adresse'],
                    'date_etat' => date('d/m/Y', strtotime($etat['date_etat'])),
                    'reference' => $etat['reference_unique'] ?? 'N/A',
                    'type' => $typeLabel
                ];
                
                error_log("Sending email with template: $templateId");
                
                // Send email to tenant using template
                $emailSent = sendTemplatedEmail($templateId, $etat['locataire_email'], $emailVariables, $pdfPath);
                
                if (!$emailSent) {
                    error_log("ERROR: Failed to send email to tenant using template");
                    throw new Exception("Erreur lors de l'envoi de l'email au locataire");
                }
                
                error_log("Email sent successfully to tenant!");
                
                // Send copy to admin using admin template
                $adminEmailVariables = array_merge($emailVariables, [
                    'type' => $typeLabel
                ]);
                
                $adminEmailSent = sendTemplatedEmail('etat_lieux_admin_copie', ADMIN_EMAIL, $adminEmailVariables, $pdfPath, true);
                
                if ($adminEmailSent) {
                    error_log("Admin copy email sent successfully to: " . ADMIN_EMAIL);
                } else {
                    error_log("WARNING: Failed to send admin copy email");
                }
                
                // Update status
                error_log("Updating database status...");
                $stmt = $pdo->prepare("
                    UPDATE etats_lieux 
                    SET statut = 'envoye', 
                        email_envoye = TRUE, 
                        date_envoi_email = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$id]);
                error_log("Database updated successfully");
                
                $pdo->commit();
                error_log("Transaction committed");
                
                // Clean up temporary PDF if needed
                if (strpos($pdfPath, '/tmp/') !== false) {
                    error_log("Cleaning up temporary PDF: " . $pdfPath);
                    @unlink($pdfPath);
                }
                
                error_log("=== FINALIZE ETAT LIEUX - SUCCESS ===");
                $_SESSION['success'] = "État des lieux finalisé et envoyé avec succès à " . htmlspecialchars($etat['locataire_email']);
                header('Location: etats-lieux.php');
                exit;
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                error_log("Rolling back transaction...");
                $pdo->rollBack();
            }
            error_log("=== FINALIZE ETAT LIEUX - ERROR ===");
            error_log("Exception type: " . get_class($e));
            error_log("Error message: " . $e->getMessage());
            error_log("Error code: " . $e->getCode());
            error_log("Error file: " . $e->getFile() . ":" . $e->getLine());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            $_SESSION['error'] = "Erreur lors de la finalisation: " . $e->getMessage();
        }
    }
}

// Log the request
error_log("=== FINALIZE ETAT LIEUX - START ===");
error_log("Requested ID: " . $id);

if ($id < 1) {
    error_log("ERROR: Invalid ID provided - " . $id);
    $_SESSION['error'] = "ID de l'état des lieux invalide";
    header('Location: etats-lieux.php');
    exit;
}

// Get état des lieux details
try {
    error_log("Fetching etat des lieux from database with ID: " . $id);
    
    $stmt = $pdo->prepare("
        SELECT edl.*, 
               c.id as contrat_id,
               c.reference_unique as contrat_ref,
               l.adresse as logement_adresse,
               l.appartement as logement_appartement
        FROM etats_lieux edl
        LEFT JOIN contrats c ON edl.contrat_id = c.id
        LEFT JOIN logements l ON c.logement_id = l.id
        WHERE edl.id = ?
    ");
    $stmt->execute([$id]);
    $etat = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$etat) {
        error_log("ERROR: État des lieux not found in database for ID: " . $id);
        $_SESSION['error'] = "État des lieux non trouvé";
        header('Location: etats-lieux.php');
        exit;
    }
    
    // Log retrieved data for debugging
    error_log("État des lieux found - ID: " . $etat['id']);
    error_log("Contrat ID: " . ($etat['contrat_id'] ?? 'NULL'));
    error_log("Type: " . ($etat['type'] ?? 'NULL'));
    error_log("Reference unique: " . ($etat['reference_unique'] ?? 'NULL'));
    error_log("Locataire email: " . ($etat['locataire_email'] ?? 'NULL'));
    error_log("Locataire nom complet: " . ($etat['locataire_nom_complet'] ?? 'NULL'));
    error_log("Adresse: " . ($etat['adresse'] ?? 'NULL'));
    error_log("Date etat: " . ($etat['date_etat'] ?? 'NULL'));
    error_log("Contrat ref: " . ($etat['contrat_ref'] ?? 'NULL'));
    
    // Fix missing address from logement if available
    $needsUpdate = false;
    $fieldsToUpdate = [];
    
    if (empty($etat['adresse']) && !empty($etat['logement_adresse'])) {
        error_log("Address is NULL, populating from logement: " . $etat['logement_adresse']);
        $etat['adresse'] = $etat['logement_adresse'];
        $fieldsToUpdate['adresse'] = $etat['adresse'];
        $needsUpdate = true;
    }
    
    if (empty($etat['appartement']) && !empty($etat['logement_appartement'])) {
        error_log("Appartement is NULL, populating from logement: " . $etat['logement_appartement']);
        $etat['appartement'] = $etat['logement_appartement'];
        $fieldsToUpdate['appartement'] = $etat['appartement'];
        $needsUpdate = true;
    }
    
    // Update database with all missing fields in a single query
    if ($needsUpdate) {
        // Whitelist of allowed fields to prevent SQL injection
        $allowedFields = ['adresse', 'appartement'];
        
        $setParts = [];
        $params = [];
        foreach ($fieldsToUpdate as $field => $value) {
            // Only allow whitelisted fields
            if (in_array($field, $allowedFields, true)) {
                $setParts[] = "`$field` = ?";
                $params[] = $value;
            }
        }
        
        if (!empty($setParts)) {
            $params[] = $id;
            $sql = "UPDATE etats_lieux SET " . implode(', ', $setParts) . " WHERE id = ?";
            $updateStmt = $pdo->prepare($sql);
            $updateStmt->execute($params);
            error_log("Updated database with: " . implode(', ', array_keys($fieldsToUpdate)));
        }
    }
    
    // Check for missing required fields
    $missingFields = [];
    $requiredFields = ['contrat_id', 'type', 'locataire_email', 'locataire_nom_complet', 'adresse', 'date_etat'];
    foreach ($requiredFields as $field) {
        if (empty($etat[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        error_log("WARNING: Missing required fields: " . implode(', ', $missingFields));
    }
    
} catch (PDOException $e) {
    error_log("DATABASE ERROR while fetching etat des lieux: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $_SESSION['error'] = "Erreur de base de données: " . $e->getMessage();
    header('Location: etats-lieux.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finaliser État des lieux</title>
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
        .finalize-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .info-item {
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #6c757d;
            width: 200px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/menu.php'; ?>

    <div class="main-content">
        <div class="header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4>
                        <i class="bi bi-send-check"></i> Finaliser l'état des lieux
                    </h4>
                    <p class="text-muted mb-0">Vérification avant envoi</p>
                </div>
                <a href="edit-etat-lieux.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Retour
                </a>
            </div>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="finalize-card">
            <h5 class="mb-4">Récapitulatif</h5>
            
            <div class="info-item">
                <span class="info-label">Type:</span>
                <span class="badge bg-<?php echo $etat['type'] === 'entree' ? 'success' : 'danger'; ?>">
                    État des lieux <?php echo $etat['type'] === 'entree' ? "d'entrée" : "de sortie"; ?>
                </span>
            </div>
            
            <div class="info-item">
                <span class="info-label">Référence:</span>
                <?php echo htmlspecialchars($etat['reference_unique']); ?>
            </div>
            
            <div class="info-item">
                <span class="info-label">Date:</span>
                <?php echo date('d/m/Y', strtotime($etat['date_etat'])); ?>
            </div>
            
            <div class="info-item">
                <span class="info-label">Adresse:</span>
                <?php echo htmlspecialchars($etat['adresse']); ?>
            </div>
            
            <div class="info-item">
                <span class="info-label">Locataire:</span>
                <?php echo htmlspecialchars($etat['locataire_nom_complet']); ?>
            </div>
            
            <div class="info-item">
                <span class="info-label">Email du locataire:</span>
                <?php echo htmlspecialchars($etat['locataire_email']); ?>
            </div>
            
            <hr class="my-4">
            
            <h5 class="mb-3">Envoi du document</h5>
            
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                <strong>Le PDF sera envoyé automatiquement à:</strong>
                <ul class="mb-0 mt-2">
                    <li>Locataire: <?php echo htmlspecialchars($etat['locataire_email']); ?></li>
                    <li>Copie: gestion@myinvest-immobilier.com</li>
                </ul>
            </div>
            
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                <strong>Note:</strong> Les photos téléchargées ne seront jointes qu'à la copie interne (My Invest Immobilier) 
                et ne seront pas envoyées au locataire.
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="finalize">
                
                <div class="d-flex justify-content-between mt-4">
                    <a href="edit-etat-lieux.php?id=<?php echo $id; ?>" class="btn btn-secondary">
                        <i class="bi bi-pencil"></i> Modifier
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-send-check"></i> Finaliser et envoyer par email
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
