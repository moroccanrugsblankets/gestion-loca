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
require_once __DIR__ . '/../includes/functions.php';
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
    // Get delay parameter from database (defaults to 4 if not set)
    $delaiReponse = getParameter('delai_reponse_jours', 4);
    logMessage("Using automatic response delay: $delaiReponse business days");
    
    // Get applications that are ready to be processed
    $query = "SELECT * FROM v_candidatures_a_traiter WHERE jours_ouvres_ecoules >= ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$delaiReponse]);
    $candidatures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    logMessage("Found " . count($candidatures) . " applications to process");
    
    foreach ($candidatures as $candidature) {
        $id = $candidature['id'];
        $email = $candidature['email'];
        $nom = $candidature['nom'];
        $prenom = $candidature['prenom'];
        
        logMessage("Processing application #$id for $prenom $nom");
        
        // Evaluate acceptance criteria with NEW STRICTER RULES
        $result = evaluateCandidature($candidature);
        $accepted = $result['accepted'];
        $motifRefus = $result['motif'];
        
        if ($accepted) {
            // Send acceptance email
            $subject = "Candidature acceptée - MyInvest Immobilier";
            $message = getAcceptanceEmailTemplate($prenom, $nom, $candidature['reference_candidature']);
            
            // Update status to "Accepté"
            $updateStmt = $pdo->prepare("UPDATE candidatures SET statut = 'accepte', reponse_automatique = 'accepte', date_reponse_auto = NOW(), date_reponse_envoyee = NOW() WHERE id = ?");
            $updateStmt->execute([$id]);
            
            // Send email
            if (sendEmail($email, $subject, $message)) {
                logMessage("Acceptance email sent to $email for application #$id");
                
                // Log the action
                $logStmt = $pdo->prepare("INSERT INTO logs (type_entite, entite_id, action, details) VALUES (?, ?, ?, ?)");
                $logStmt->execute(['candidature', $id, 'email_acceptation', "Email d'acceptation envoyé à $email"]);
            } else {
                logMessage("ERROR: Failed to send acceptance email to $email");
            }
            
        } else {
            // Send rejection email
            $subject = "Candidature - MyInvest Immobilier";
            $message = getRejectionEmailTemplate($prenom, $nom);
            
            // Update status to "Refusé" with rejection reason
            $updateStmt = $pdo->prepare("UPDATE candidatures SET statut = 'refuse', reponse_automatique = 'refuse', motif_refus = ?, date_reponse_auto = NOW(), date_reponse_envoyee = NOW() WHERE id = ?");
            $updateStmt->execute([$motifRefus, $id]);
            
            // Send email
            if (sendEmail($email, $subject, $message)) {
                logMessage("Rejection email sent to $email for application #$id. Reason: $motifRefus");
                
                // Log the action
                $logStmt = $pdo->prepare("INSERT INTO logs (type_entite, entite_id, action, details) VALUES (?, ?, ?, ?)");
                $logStmt->execute(['candidature', $id, 'email_refus', "Email de refus envoyé à $email. Motif: $motifRefus"]);
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
 * Evaluate if a candidature should be accepted based on NEW STRICTER criteria
 * Returns array with 'accepted' (bool) and 'motif' (string) keys
 */
function evaluateCandidature($candidature) {
    // Get parameters from database
    $revenusMinRequis = getParameter('revenus_min_requis', 3000);
    $statutsProAcceptes = getParameter('statuts_pro_acceptes', ['CDI', 'CDD']);
    $typeRevenusAccepte = getParameter('type_revenus_accepte', 'Salaires');
    $nbOccupantsAcceptes = getParameter('nb_occupants_acceptes', ['1', '2']);
    $garantieVisaleRequise = getParameter('garantie_visale_requise', true);
    
    $motifs = [];
    
    // RULE 1: Professional situation - must be CDI or CDD
    if (!in_array($candidature['statut_professionnel'], $statutsProAcceptes)) {
        $motifs[] = "Statut professionnel non accepté (doit être CDI ou CDD)";
    }
    
    // RULE 2: Monthly net income - must be >= 3000€
    // Convert enum values to numeric for comparison
    $revenus = $candidature['revenus_mensuels'];
    if ($revenus === '< 2300' || $revenus === '2300-3000') {
        $motifs[] = "Revenus nets mensuels insuffisants (minimum 3000€ requis)";
    }
    
    // RULE 3: Income type - must be Salaires
    if ($candidature['type_revenus'] !== $typeRevenusAccepte) {
        $motifs[] = "Type de revenus non accepté (doit être: $typeRevenusAccepte)";
    }
    
    // RULE 4: Number of occupants - must be 1 or 2 (not "Autre")
    if (!in_array($candidature['nb_occupants'], $nbOccupantsAcceptes)) {
        $motifs[] = "Nombre d'occupants non accepté (doit être 1 ou 2)";
    }
    
    // RULE 5: Visale guarantee - must be "Oui"
    if ($garantieVisaleRequise && $candidature['garantie_visale'] !== 'Oui') {
        $motifs[] = "Garantie Visale requise";
    }
    
    // RULE 6: If CDI, trial period must be passed
    if ($candidature['statut_professionnel'] === 'CDI' && $candidature['periode_essai'] === 'En cours') {
        $motifs[] = "Période d'essai en cours";
    }
    
    // All criteria must be met for acceptance
    $accepted = empty($motifs);
    $motif = $accepted ? '' : implode(', ', $motifs);
    
    return [
        'accepted' => $accepted,
        'motif' => $motif
    ];
}

/**
 * Get acceptance email template
 */
function getAcceptanceEmailTemplate($prenom, $nom, $reference) {
    global $config;
    $confirmUrl = $config['SITE_URL'] . "/candidature/confirmer-interet.php?ref=" . urlencode($reference);
    
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
