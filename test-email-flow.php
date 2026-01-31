<?php
/**
 * Test complet du flux d'envoi d'email avec la correction
 * Simule ce qui se passe quand on génère un contrat
 */

require_once 'includes/config.php';
require_once 'includes/mail-templates.php';

echo "=== Test complet du flux d'envoi d'email ===\n\n";

// Simulation de l'envoi d'email comme dans generer-contrat.php
$test_email = 'test@example.com';
$subject = "Test - Contrat de bail à signer";
$htmlBody = "<p>Ceci est un test</p>";

echo "Tentative d'envoi d'email à: $test_email\n";
echo "Configuration SMTP_PASSWORD: " . (empty($config['SMTP_PASSWORD']) ? '❌ VIDE' : '✓ Défini') . "\n\n";

// Appel à sendEmail - similaire à la ligne 117 de generer-contrat.php
$emailSent = sendEmail($test_email, $subject, $htmlBody, null, true, true);

echo "\nRésultat de sendEmail(): " . ($emailSent ? "TRUE" : "FALSE") . "\n\n";

// Simulation de la logique de generer-contrat.php (lignes 119-135)
if ($emailSent) {
    echo "✓ Message qui serait affiché à l'utilisateur:\n";
    echo "   'Contrat généré avec succès et email envoyé à $test_email'\n";
    echo "\n⚠️  PROBLÈME: Ce message ne devrait PAS s'afficher car l'email n'a pas été envoyé!\n";
} else {
    echo "✓ Message qui serait affiché à l'utilisateur:\n";
    echo "   'Contrat généré mais l'email n'a pas pu être envoyé'\n";
    echo "\n✓ CORRECT: L'utilisateur est correctement informé que l'email n'a pas été envoyé.\n";
}

echo "\n=== Vérification des logs ===\n";
echo "Vérifiez le fichier error.log pour voir les messages d'erreur détaillés.\n";
echo "Commande: tail -20 error.log\n";

echo "\n=== Résumé ===\n";
if (!$emailSent) {
    echo "✓ La correction fonctionne correctement!\n";
    echo "✓ sendEmail() retourne FALSE quand SMTP n'est pas configuré\n";
    echo "✓ L'utilisateur ne verra PAS le message 'email envoyé' erroné\n";
    echo "\nPour résoudre le problème:\n";
    echo "1. Créez includes/config.local.php à partir du template\n";
    echo "2. Configurez SMTP_PASSWORD avec vos vrais credentials\n";
    echo "3. Consultez PHPMAILER_CONFIGURATION.md pour les détails\n";
} else {
    echo "❌ Problème: sendEmail() a retourné TRUE alors que SMTP n'est pas configuré!\n";
    echo "   Cela devrait être impossible avec la correction.\n";
}

echo "\n=== Fin du test ===\n";
