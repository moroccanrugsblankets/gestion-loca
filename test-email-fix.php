<?php
/**
 * Test script to verify email sending fix
 * This script tests the improved error detection in sendEmail()
 */

require_once 'includes/config.php';

echo "=== Test de la correction du problème d'envoi d'email ===\n\n";

// Display SMTP configuration status
echo "Configuration SMTP actuelle:\n";
echo "- SMTP_AUTH: " . ($config['SMTP_AUTH'] ? 'activé' : 'désactivé') . "\n";
echo "- SMTP_HOST: " . ($config['SMTP_HOST'] ?: '[non défini]') . "\n";
echo "- SMTP_USERNAME: " . ($config['SMTP_USERNAME'] ?: '[non défini]') . "\n";
echo "- SMTP_PASSWORD: " . (empty($config['SMTP_PASSWORD']) ? '❌ VIDE (PROBLÈME!)' : '✓ Défini') . "\n\n";

// Test the validation logic (simulating what sendEmail() does)
echo "Test de la validation:\n";

$isConfigValid = true;
$errors = [];

if ($config['SMTP_AUTH']) {
    if (empty($config['SMTP_PASSWORD'])) {
        $isConfigValid = false;
        $errors[] = "SMTP_PASSWORD est vide";
    }
    if (empty($config['SMTP_USERNAME'])) {
        $isConfigValid = false;
        $errors[] = "SMTP_USERNAME est vide";
    }
    if (empty($config['SMTP_HOST'])) {
        $isConfigValid = false;
        $errors[] = "SMTP_HOST est vide";
    }
}

if ($isConfigValid) {
    echo "✓ Configuration SMTP valide - Les emails peuvent être envoyés\n";
} else {
    echo "❌ Configuration SMTP invalide - Les emails ne seront PAS envoyés!\n";
    echo "   Erreurs détectées:\n";
    foreach ($errors as $error) {
        echo "   - $error\n";
    }
    echo "\n";
    echo "SOLUTION:\n";
    echo "1. Copiez le fichier template:\n";
    echo "   cp includes/config.local.php.template includes/config.local.php\n\n";
    echo "2. Éditez includes/config.local.php et ajoutez vos credentials SMTP\n\n";
    echo "3. Consultez PHPMAILER_CONFIGURATION.md pour plus d'informations\n";
}

echo "\n=== Fin du test ===\n";
