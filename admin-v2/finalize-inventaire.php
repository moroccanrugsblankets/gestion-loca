<?php
/**
 * Finalize and Send Inventaire
 * My Invest Immobilier
 */

require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Get inventaire ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle finalization BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'finalize') {
    error_log("=== FINALIZE INVENTAIRE - POST REQUEST ===");
    error_log("Action: finalize");
    
    // Need to fetch inventaire data for processing
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("
                SELECT inv.*, 
                       c.id as contrat_id,
                       c.reference_unique as contrat_ref,
                       l.adresse as logement_adresse,
                       l.appartement as logement_appartement
                FROM inventaires inv
                LEFT JOIN contrats c ON inv.contrat_id = c.id
                LEFT JOIN logements l ON inv.logement_id = l.id
                WHERE inv.id = ?
            ");
            $stmt->execute([$id]);
            $inventaire = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($inventaire) {
                error_log("Starting transaction...");
                $pdo->beginTransaction();
                
                // Check if PDF generation function exists
                if (!file_exists(__DIR__ . '/../pdf/generate-inventaire.php')) {
                    error_log("ERROR: PDF generation file not found");
                    throw new Exception("La génération PDF pour les inventaires n'est pas encore implémentée. Veuillez d'abord créer le fichier pdf/generate-inventaire.php");
                }
                
                require_once __DIR__ . '/../pdf/generate-inventaire.php';
                
                // Generate PDF
                error_log("Generating PDF for inventaire_id: " . $inventaire['id'] . ", type: " . $inventaire['type']);
                $pdfPath = generateInventairePDF($inventaire['id']);
                
                if (!$pdfPath || !file_exists($pdfPath)) {
                    error_log("ERROR: PDF generation failed. Path returned: " . ($pdfPath ?? 'NULL'));
                    throw new Exception("Erreur lors de la génération du PDF");
                }
                
                error_log("PDF generated successfully: " . $pdfPath);
                error_log("PDF file size: " . filesize($pdfPath) . " bytes");
                
                // Prepare email data with template variables
                $templateId = $inventaire['type'] === 'entree' ? 'inventaire_entree_envoye' : 'inventaire_sortie_envoye';
                
                $emailVariables = [
                    'locataire_nom' => $inventaire['locataire_nom_complet'],
                    'adresse' => $inventaire['adresse'],
                    'date_inventaire' => date('d/m/Y', strtotime($inventaire['date_inventaire'])),
                    'reference' => $inventaire['reference_unique'] ?? 'N/A',
                    'type' => $typeLabel
                ];
                
                error_log("Sending email with template: $templateId");
                
                // Send email to tenant using template
                $emailSent = sendTemplatedEmail($templateId, $inventaire['locataire_email'], $emailVariables, $pdfPath);
                
                if (!$emailSent) {
                    error_log("ERROR: Failed to send email to tenant using template");
                    throw new Exception("Erreur lors de l'envoi de l'email au locataire");
                }
                
                error_log("Email sent successfully to tenant!");
                
                // Send copy to admin using admin template
                $adminEmailVariables = array_merge($emailVariables, [
                    'type' => $typeLabel
                ]);
                
                // Fifth parameter: $isAdminEmail = true (suppresses errors to avoid blocking the workflow)
                $adminEmailSent = sendTemplatedEmail('inventaire_admin_copie', ADMIN_EMAIL, $adminEmailVariables, $pdfPath, true);
                
                if ($adminEmailSent) {
                    error_log("Admin copy email sent successfully to: " . ADMIN_EMAIL);
                } else {
                    error_log("WARNING: Failed to send admin copy email");
                }
                
                // Update status
                error_log("Updating database status...");
                $stmt = $pdo->prepare("
                    UPDATE inventaires 
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
                
                error_log("=== FINALIZE INVENTAIRE - SUCCESS ===");
                $_SESSION['success'] = "Inventaire finalisé et envoyé avec succès à " . htmlspecialchars($inventaire['locataire_email']);
                header('Location: inventaires.php');
                exit;
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                error_log("Rolling back transaction...");
                $pdo->rollBack();
            }
            error_log("=== FINALIZE INVENTAIRE - ERROR ===");
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
error_log("=== FINALIZE INVENTAIRE - START ===");
error_log("Requested ID: " . $id);

if ($id < 1) {
    error_log("ERROR: Invalid ID provided - " . $id);
    $_SESSION['error'] = "ID de l'inventaire invalide";
    header('Location: inventaires.php');
    exit;
}

// Get inventaire details
try {
    error_log("Fetching inventaire from database with ID: " . $id);
    
    $stmt = $pdo->prepare("
        SELECT inv.*, 
               c.id as contrat_id,
               c.reference_unique as contrat_ref,
               l.adresse as logement_adresse,
               l.appartement as logement_appartement
        FROM inventaires inv
        LEFT JOIN contrats c ON inv.contrat_id = c.id
        LEFT JOIN logements l ON inv.logement_id = l.id
        WHERE inv.id = ?
    ");
    $stmt->execute([$id]);
    $inventaire = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inventaire) {
        error_log("ERROR: Inventaire not found in database for ID: " . $id);
        $_SESSION['error'] = "Inventaire non trouvé";
        header('Location: inventaires.php');
        exit;
    }
    
    // Log retrieved data for debugging
    error_log("Inventaire found - ID: " . $inventaire['id']);
    error_log("Contrat ID: " . ($inventaire['contrat_id'] ?? 'NULL'));
    error_log("Type: " . ($inventaire['type'] ?? 'NULL'));
    error_log("Reference unique: " . ($inventaire['reference_unique'] ?? 'NULL'));
    error_log("Locataire email: " . ($inventaire['locataire_email'] ?? 'NULL'));
    error_log("Locataire nom complet: " . ($inventaire['locataire_nom_complet'] ?? 'NULL'));
    error_log("Adresse: " . ($inventaire['adresse'] ?? 'NULL'));
    error_log("Date inventaire: " . ($inventaire['date_inventaire'] ?? 'NULL'));
    error_log("Contrat ref: " . ($inventaire['contrat_ref'] ?? 'NULL'));
    
    // Compute type label once for reuse
    $typeLabel = $inventaire['type'] === 'entree' ? "d'entrée" : "de sortie";
    
    // Fix missing address from logement if available
    $needsUpdate = false;
    $fieldsToUpdate = [];
    
    if (empty($inventaire['adresse']) && !empty($inventaire['logement_adresse'])) {
        error_log("Address is NULL, populating from logement: " . $inventaire['logement_adresse']);
        $inventaire['adresse'] = $inventaire['logement_adresse'];
        $fieldsToUpdate['adresse'] = $inventaire['adresse'];
        $needsUpdate = true;
    }
    
    if (empty($inventaire['appartement']) && !empty($inventaire['logement_appartement'])) {
        error_log("Appartement is NULL, populating from logement: " . $inventaire['logement_appartement']);
        $inventaire['appartement'] = $inventaire['logement_appartement'];
        $fieldsToUpdate['appartement'] = $inventaire['appartement'];
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
            $sql = "UPDATE inventaires SET " . implode(', ', $setParts) . " WHERE id = ?";
            $updateStmt = $pdo->prepare($sql);
            $updateStmt->execute($params);
            error_log("Updated database with: " . implode(', ', array_keys($fieldsToUpdate)));
        }
    }
    
    // Check for missing required fields
    $missingFields = [];
    $requiredFields = ['contrat_id', 'type', 'locataire_email', 'locataire_nom_complet', 'adresse', 'date_inventaire'];
    foreach ($requiredFields as $field) {
        if (empty($inventaire[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        error_log("WARNING: Missing required fields: " . implode(', ', $missingFields));
    }
    
} catch (PDOException $e) {
    error_log("DATABASE ERROR while fetching inventaire: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $_SESSION['error'] = "Erreur de base de données: " . $e->getMessage();
    header('Location: inventaires.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finaliser Inventaire</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .gradient-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            margin-bottom: 2rem;
            border-radius: 10px;
        }
        .info-card {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 5px;
        }
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 5px;
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'includes/menu.php'; ?>
    
    <div class="container mt-4">
        <div class="gradient-header">
            <h1 class="mb-0">
                <i class="bi bi-send"></i>
                Finaliser et Envoyer l'Inventaire
            </h1>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i>
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="bi bi-info-circle"></i>
                            Détails de l'inventaire
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="info-card">
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-2"><strong>Référence:</strong><br><?php echo htmlspecialchars($inventaire['reference_unique'] ?? 'N/A'); ?></p>
                                    <p class="mb-2"><strong>Type:</strong><br>
                                        <?php 
                                        // Display label for UI (different from email typeLabel)
                                        $displayTypeLabel = $inventaire['type'] === 'entree' ? "Inventaire d'entrée" : "Inventaire de sortie";
                                        $badgeClass = $inventaire['type'] === 'entree' ? 'bg-success' : 'bg-warning';
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?>"><?php echo $displayTypeLabel; ?></span>
                                    </p>
                                    <p class="mb-2"><strong>Date:</strong><br><?php echo date('d/m/Y', strtotime($inventaire['date_inventaire'])); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-2"><strong>Adresse:</strong><br><?php echo htmlspecialchars($inventaire['adresse']); ?></p>
                                    <?php if (!empty($inventaire['appartement'])): ?>
                                        <p class="mb-2"><strong>Appartement:</strong><br><?php echo htmlspecialchars($inventaire['appartement']); ?></p>
                                    <?php endif; ?>
                                    <p class="mb-2"><strong>Locataire:</strong><br><?php echo htmlspecialchars($inventaire['locataire_nom_complet']); ?></p>
                                    <p class="mb-2"><strong>Email:</strong><br><?php echo htmlspecialchars($inventaire['locataire_email']); ?></p>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($missingFields)): ?>
                        <div class="warning-box">
                            <h6><i class="bi bi-exclamation-triangle"></i> Attention: Champs manquants</h6>
                            <p class="mb-0">Les champs suivants sont vides: <strong><?php echo implode(', ', $missingFields); ?></strong></p>
                            <p class="mb-0 mt-2"><small>Il est recommandé de compléter ces informations avant de finaliser l'inventaire.</small></p>
                        </div>
                        <?php endif; ?>

                        <div class="alert alert-info">
                            <h6><i class="bi bi-info-circle"></i> Que va-t-il se passer ?</h6>
                            <ol class="mb-0">
                                <li>Un PDF de l'inventaire sera généré</li>
                                <li>Un email sera envoyé au locataire (<?php echo htmlspecialchars($inventaire['locataire_email']); ?>) avec le PDF en pièce jointe</li>
                                <li>Une copie sera envoyée à l'administrateur (<?php echo ADMIN_EMAIL; ?>)</li>
                                <li>Le statut de l'inventaire sera mis à jour en "Envoyé"</li>
                            </ol>
                        </div>

                        <form method="POST" action="" id="finalizeForm">
                            <input type="hidden" name="action" value="finalize">
                            
                            <div class="d-flex gap-2 justify-content-between mt-4">
                                <a href="inventaires.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i>
                                    Annuler
                                </a>
                                <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                    <i class="bi bi-send"></i>
                                    Finaliser et Envoyer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('finalizeForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Envoi en cours...';
        });
    </script>
</body>
</html>
