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
    // Get flexible delay parameters from database
    $delaiValeur = (int)getParameter('delai_reponse_valeur', 4);
    $delaiUnite = getParameter('delai_reponse_unite', 'jours');
    
    logMessage("Using automatic response delay: $delaiValeur $delaiUnite");
    
    // Convert delay to appropriate format based on unit
    $candidatures = [];
    
    if ($delaiUnite === 'jours') {
        // Use business days logic (existing behavior)
        $query = "SELECT * FROM v_candidatures_a_traiter WHERE jours_ouvres_ecoules >= ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$delaiValeur]);
        $candidatures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Use hours or minutes logic (calculate from created_at)
        $hoursDelay = 0;
        if ($delaiUnite === 'heures') {
            $hoursDelay = $delaiValeur;
        } elseif ($delaiUnite === 'minutes') {
            $hoursDelay = $delaiValeur / 60;
        }
        
        $query = "
            SELECT c.* 
            FROM candidatures c
            WHERE c.statut = 'en_cours'
            AND c.reponse_automatique = 'en_attente'
            AND TIMESTAMPDIFF(HOUR, c.created_at, NOW()) >= ?
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$hoursDelay]);
        $candidatures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    logMessage("Found " . count($candidatures) . " applications to process");
    
    foreach ($candidatures as $candidature) {
        $id = $candidature['id'];
        $email = $candidature['email'];
        $nom = $candidature['nom'];
        $prenom = $candidature['prenom'];
        $reference = $candidature['reference_unique'] ?? $candidature['reference_candidature'] ?? '';
        
        logMessage("Processing application #$id for $prenom $nom");
        
        // Evaluate acceptance criteria with NEW STRICTER RULES
        $result = evaluateCandidature($candidature);
        $accepted = $result['accepted'];
        $motifRefus = $result['motif'];
        
        if ($accepted) {
            // Send acceptance email using database template
            $logement = isset($candidature['logement_reference']) ? $candidature['logement_reference'] : 'Logement';
            $confirmUrl = $config['SITE_URL'] . "/candidature/confirmer-interet.php?ref=" . urlencode($reference);
            
            $variables = [
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'logement' => $logement,
                'reference' => $reference,
                'date' => date('d/m/Y'),
                'lien_confirmation' => $confirmUrl
            ];
            
            // Update status to "Accepté"
            $updateStmt = $pdo->prepare("UPDATE candidatures SET statut = 'accepte', reponse_automatique = 'accepte', date_reponse_auto = NOW(), date_reponse_envoyee = NOW() WHERE id = ?");
            $updateStmt->execute([$id]);
            
            // Send email using template
            if (sendTemplatedEmail('candidature_acceptee', $email, $variables)) {
                logMessage("Acceptance email sent to $email for application #$id");
                
                // Log the action
                $logStmt = $pdo->prepare("INSERT INTO logs (type_entite, entite_id, action, details) VALUES (?, ?, ?, ?)");
                $logStmt->execute(['candidature', $id, 'email_acceptation', "Email d'acceptation envoyé à $email"]);
            } else {
                logMessage("ERROR: Failed to send acceptance email to $email");
            }
            
        } else {
            // Send rejection email using database template
            $variables = [
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email
            ];
            
            // Update status to "Refusé" with rejection reason
            $updateStmt = $pdo->prepare("UPDATE candidatures SET statut = 'refuse', reponse_automatique = 'refuse', motif_refus = ?, date_reponse_auto = NOW(), date_reponse_envoyee = NOW() WHERE id = ?");
            $updateStmt->execute([$motifRefus, $id]);
            
            // Send email using template
            if (sendTemplatedEmail('candidature_refusee', $email, $variables)) {
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
?>
