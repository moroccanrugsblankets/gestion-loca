<?php
/**
 * Cron Job - Process Rental Applications
 * 
 * This script processes applications that are 4 business days old and sends
 * automated acceptance or rejection emails based on criteria.
 * 
 * Setup: Run this script daily via cron
 * Example: 0 9 * * * /usr/bin/php /path/to/process-candidatures.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mail-templates.php';

// Log file for cron execution
$logFile = __DIR__ . '/cron-log.txt';

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

logMessage("=== Starting candidature processing ===");

try {
    $pdo = getDbConnection();
    
    // Get applications that are ready to be processed (4 business days old)
    $query = "SELECT * FROM v_candidatures_a_traiter WHERE jours_ouvres_ecoules >= 4";
    $stmt = $pdo->query($query);
    $candidatures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    logMessage("Found " . count($candidatures) . " applications to process");
    
    foreach ($candidatures as $candidature) {
        $id = $candidature['id'];
        $email = $candidature['email'];
        $nom = $candidature['nom'];
        $prenom = $candidature['prenom'];
        
        logMessage("Processing application #$id for $prenom $nom");
        
        // Evaluate acceptance criteria
        $accepted = evaluateCandidature($candidature);
        
        if ($accepted) {
            // Send acceptance email
            $subject = "Candidature acceptée - MyInvest Immobilier";
            $message = getAcceptanceEmailTemplate($prenom, $nom, $candidature['reference_candidature']);
            
            // Update status to "Accepté"
            $updateStmt = $pdo->prepare("UPDATE candidatures SET statut = 'Accepté', date_reponse = NOW() WHERE id = ?");
            $updateStmt->execute([$id]);
            
            // Send email
            if (sendEmail($email, $subject, $message)) {
                logMessage("Acceptance email sent to $email for application #$id");
                
                // Log the action
                $logStmt = $pdo->prepare("INSERT INTO logs (candidature_id, action, details) VALUES (?, ?, ?)");
                $logStmt->execute([$id, 'email_acceptation', "Email d'acceptation envoyé à $email"]);
            } else {
                logMessage("ERROR: Failed to send acceptance email to $email");
            }
            
        } else {
            // Send rejection email
            $subject = "Candidature - MyInvest Immobilier";
            $message = getRejectionEmailTemplate($prenom, $nom);
            
            // Update status to "Refusé"
            $updateStmt = $pdo->prepare("UPDATE candidatures SET statut = 'Refusé', date_reponse = NOW() WHERE id = ?");
            $updateStmt->execute([$id]);
            
            // Send email
            if (sendEmail($email, $subject, $message)) {
                logMessage("Rejection email sent to $email for application #$id");
                
                // Log the action
                $logStmt = $pdo->prepare("INSERT INTO logs (candidature_id, action, details) VALUES (?, ?, ?)");
                $logStmt->execute([$id, 'email_refus', "Email de refus envoyé à $email"]);
            } else {
                logMessage("ERROR: Failed to send rejection email to $email");
            }
        }
    }
    
    logMessage("=== Processing complete ===");
    
} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage());
    error_log("Cron error: " . $e->getMessage());
}

/**
 * Evaluate if a candidature should be accepted based on criteria
 */
function evaluateCandidature($candidature) {
    // Criteria for acceptance:
    // 1. Income >= 2300€ (revenus_nets_mensuels must be ">= 2300€" or "3000 € et +")
    // 2. Stable professional status (CDI with trial period passed, or other stable situations)
    // 3. Not currently in trial period for CDI
    
    $revenus = $candidature['revenus_nets_mensuels'];
    $statut_pro = $candidature['statut_professionnel'];
    $periode_essai = $candidature['periode_essai'];
    
    // Check income - reject if < 2300€
    if ($revenus === '< 2300 €') {
        return false;
    }
    
    // Check professional status and trial period
    if ($statut_pro === 'CDI') {
        // For CDI, trial period must be passed
        if ($periode_essai === 'En cours') {
            return false;
        }
    } elseif ($statut_pro === 'CDD') {
        // CDD is acceptable if income is good
        // Already checked income above
    } elseif ($statut_pro === 'Indépendant') {
        // Independent workers are acceptable
    } else {
        // "Autre" status - need manual review, so auto-reject
        return false;
    }
    
    // All criteria met
    return true;
}

/**
 * Send email using PHP mail function
 */
function sendEmail($to, $subject, $htmlMessage) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: MY Invest Immobilier <" . MAIL_FROM . ">" . "\r\n";
    
    return mail($to, $subject, $htmlMessage, $headers);
}

/**
 * Get acceptance email template
 */
function getAcceptanceEmailTemplate($prenom, $nom, $reference) {
    $confirmUrl = SITE_URL . "/candidature/confirmer-interet.php?ref=" . urlencode($reference);
    
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
        .content { background: #f8f9fa; padding: 30px; }
        .button { display: inline-block; padding: 12px 30px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>MY Invest Immobilier</h1>
        </div>
        <div class="content">
            <h2>Bonjour $prenom $nom,</h2>
            
            <p>Nous avons le plaisir de vous informer que votre candidature locative a été acceptée.</p>
            
            <p><strong>Prochaines étapes :</strong></p>
            <ol>
                <li>Confirmez votre intérêt en cliquant sur le bouton ci-dessous</li>
                <li>Nous vous contacterons via WhatsApp pour organiser une visite du logement</li>
                <li>Si la visite est concluante, nous vous enverrons le contrat de bail à signer</li>
            </ol>
            
            <p style="text-align: center;">
                <a href="$confirmUrl" class="button">Confirmer mon intérêt</a>
            </p>
            
            <p><em>Ce lien est valable 48 heures. Passé ce délai, votre candidature sera automatiquement archivée.</em></p>
            
            <p>Nous restons à votre disposition pour toute question.</p>
            
            <p>Cordialement,<br>
            <strong>MY Invest Immobilier</strong><br>
            contact@myinvest-immobilier.com</p>
        </div>
        <div class="footer">
            <p>Référence de candidature: $reference</p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * Get rejection email template
 */
function getRejectionEmailTemplate($prenom, $nom) {
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
        .content { background: #f8f9fa; padding: 30px; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>MY Invest Immobilier</h1>
        </div>
        <div class="content">
            <h2>Bonjour $prenom $nom,</h2>
            
            <p>Nous vous remercions de l'intérêt que vous avez porté à nos logements.</p>
            
            <p>Après étude de votre dossier, nous sommes au regret de vous informer que nous ne pouvons donner suite à votre candidature pour le moment.</p>
            
            <p>Cette décision ne remet pas en cause vos qualités en tant que locataire, mais résulte de critères spécifiques liés au logement proposé.</p>
            
            <p>Nous vous encourageons à postuler à nouveau si votre situation évolue ou pour d'autres opportunités.</p>
            
            <p>Nous vous souhaitons bonne chance dans vos recherches.</p>
            
            <p>Cordialement,<br>
            <strong>MY Invest Immobilier</strong><br>
            contact@myinvest-immobilier.com</p>
        </div>
        <div class="footer">
            <p>MY Invest Immobilier - Gestion locative professionnelle</p>
        </div>
    </div>
</body>
</html>
HTML;
}
?>
