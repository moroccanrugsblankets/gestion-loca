<?php
/**
 * Finalize and Send État des Lieux
 * My Invest Immobilier
 */

require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';
require_once '../includes/mail-templates.php';
require_once '../pdf/generate-etat-lieux.php';

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
           c.id as contrat_id,
           c.reference_unique as contrat_ref
    FROM etats_lieux edl
    LEFT JOIN contrats c ON edl.contrat_id = c.id
    WHERE edl.id = ?
");
$stmt->execute([$id]);
$etat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$etat) {
    $_SESSION['error'] = "État des lieux non trouvé";
    header('Location: etats-lieux.php');
    exit;
}

// Handle finalization
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'finalize') {
    try {
        $pdo->beginTransaction();
        
        // Generate PDF
        $pdfPath = generateEtatDesLieuxPDF($etat['contrat_id'], $etat['type']);
        
        if (!$pdfPath || !file_exists($pdfPath)) {
            throw new Exception("Erreur lors de la génération du PDF");
        }
        
        // Prepare email
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = $config['smtp']['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['smtp']['username'];
        $mail->Password = $config['smtp']['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $config['smtp']['port'];
        $mail->CharSet = 'UTF-8';
        
        // Recipients
        $mail->setFrom($config['email_from'], 'My Invest Immobilier');
        $mail->addAddress($etat['locataire_email'], $etat['locataire_nom_complet']);
        $mail->addCC('gestion@myinvest-immobilier.com');
        
        // Content
        $typeLabel = $etat['type'] === 'entree' ? "d'entrée" : "de sortie";
        $mail->Subject = "État des lieux {$typeLabel} - {$etat['adresse']}";
        
        $mail->Body = "Bonjour,\n\n";
        $mail->Body .= "Veuillez trouver ci-joint l'état des lieux {$typeLabel} pour le logement situé au :\n";
        $mail->Body .= "{$etat['adresse']}\n\n";
        $mail->Body .= "Date de l'état des lieux : " . date('d/m/Y', strtotime($etat['date_etat'])) . "\n\n";
        $mail->Body .= "Ce document est à conserver précieusement.\n\n";
        $mail->Body .= "Cordialement,\n";
        $mail->Body .= "SCI My Invest Immobilier\n";
        $mail->Body .= "Représentée par Maxime ALEXANDRE";
        
        // Attach PDF
        $filename = 'etat_lieux_' . $etat['type'] . '_' . $etat['contrat_ref'] . '.pdf';
        $mail->addAttachment($pdfPath, $filename);
        
        // Send email
        $mail->send();
        
        // Update status
        $stmt = $pdo->prepare("
            UPDATE etats_lieux 
            SET statut = 'envoye', 
                email_envoye = TRUE, 
                date_envoi_email = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        
        $pdo->commit();
        
        // Clean up temporary PDF if needed
        if (strpos($pdfPath, '/tmp/') !== false) {
            @unlink($pdfPath);
        }
        
        $_SESSION['success'] = "État des lieux finalisé et envoyé avec succès à " . htmlspecialchars($etat['locataire_email']);
        header('Location: etats-lieux.php');
        exit;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error finalizing état des lieux: " . $e->getMessage());
        $_SESSION['error'] = "Erreur lors de la finalisation: " . $e->getMessage();
    }
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
