<?php
/**
 * Test de validation SMTP - Logique uniquement
 * Ne charge pas PHPMailer, teste seulement la logique de validation
 */

require_once 'includes/config.php';

echo "=== Test de la logique de validation SMTP ===\n\n";

echo "Configuration SMTP actuelle:\n";
echo "- SMTP_AUTH: " . ($config['SMTP_AUTH'] ? 'activé' : 'désactivé') . "\n";
echo "- SMTP_HOST: " . ($config['SMTP_HOST'] ?: '[vide]') . "\n";
echo "- SMTP_USERNAME: " . ($config['SMTP_USERNAME'] ?: '[vide]') . "\n";
echo "- SMTP_PASSWORD: " . (empty($config['SMTP_PASSWORD']) ? '❌ VIDE' : '✓ Défini') . "\n\n";

// Simuler la validation de sendEmail()
function wouldSendEmailSucceed($config) {
    // Validation SMTP configuration if SMTP auth is enabled
    if ($config['SMTP_AUTH']) {
        if (empty($config['SMTP_PASSWORD']) || empty($config['SMTP_USERNAME']) || empty($config['SMTP_HOST'])) {
            return false; // La fonction retournerait false immédiatement
        }
    }
    return true; // Configuration valide, la fonction continuerait
}

$wouldSucceed = wouldSendEmailSucceed($config);

echo "Test: Est-ce que sendEmail() pourrait envoyer un email?\n";
echo "Résultat: " . ($wouldSucceed ? "✓ OUI" : "❌ NON") . "\n\n";

if (!$wouldSucceed) {
    echo "Simulation du comportement de generer-contrat.php:\n";
    echo "---------------------------------------------------\n";
    echo "\$emailSent = sendEmail(...);  // Retournerait: FALSE\n\n";
    echo "if (\$emailSent) {\n";
    echo "    // Ce bloc NE SERAIT PAS exécuté\n";
    echo "    \$_SESSION['success'] = 'Contrat généré avec succès et email envoyé';\n";
    echo "} else {\n";
    echo "    // ✓ Ce bloc SERAIT exécuté\n";
    echo "    \$_SESSION['warning'] = 'Contrat généré mais l\\'email n\\'a pas pu être envoyé';\n";
    echo "}\n\n";
    
    echo "✓ CORRECT: L'utilisateur verra le message d'avertissement, pas le message de succès!\n\n";
    
    echo "Solution pour activer l'envoi d'emails:\n";
    echo "1. cp includes/config.local.php.template includes/config.local.php\n";
    echo "2. Éditez config.local.php et ajoutez votre SMTP_PASSWORD\n";
    echo "3. Voir PHPMAILER_CONFIGURATION.md pour les détails\n";
} else {
    echo "✓ Configuration SMTP valide - Les emails peuvent être envoyés\n";
}

echo "\n=== Fin du test ===\n";
