<?php
/**
 * Script de test pour l'envoi d'emails avec PHPMailer
 * Ce script teste que PHPMailer est correctement installé et configuré
 */

// Charger les fichiers nécessaires
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/mail-templates.php';

echo "=== Test de PHPMailer ===\n\n";

// Test 1: Vérifier que PHPMailer est chargé
echo "Test 1: Vérification du chargement de PHPMailer...\n";
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "✓ PHPMailer est correctement chargé\n\n";
} else {
    echo "✗ ERREUR: PHPMailer n'est pas chargé\n\n";
    exit(1);
}

// Test 2: Vérifier que la fonction sendEmail existe
echo "Test 2: Vérification de la fonction sendEmail...\n";
if (function_exists('sendEmail')) {
    echo "✓ La fonction sendEmail existe\n\n";
} else {
    echo "✗ ERREUR: La fonction sendEmail n'existe pas\n\n";
    exit(1);
}

// Test 3: Vérifier que les fonctions de template HTML existent
echo "Test 3: Vérification des fonctions de templates HTML...\n";
$functions = [
    'getCandidatureRecueEmailHTML',
    'getInvitationSignatureEmailHTML',
    'getStatusChangeEmailHTML'
];
$allExist = true;
foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "  ✓ $func existe\n";
    } else {
        echo "  ✗ $func n'existe pas\n";
        $allExist = false;
    }
}
if ($allExist) {
    echo "✓ Toutes les fonctions de templates existent\n\n";
} else {
    echo "✗ ERREUR: Certaines fonctions de templates manquent\n\n";
    exit(1);
}

// Test 4: Tester la génération d'un template HTML
echo "Test 4: Test de génération d'un template HTML...\n";
try {
    $testLogement = [
        'reference' => 'TEST-001',
        'type' => 'Appartement T2',
        'adresse' => '123 Rue de Test, 75001 Paris',
        'loyer' => 1200
    ];
    
    $html = getCandidatureRecueEmailHTML('John', 'Doe', $testLogement, 3);
    
    if (strlen($html) > 100 && strpos($html, '<!DOCTYPE html>') !== false) {
        echo "✓ Template HTML généré avec succès (" . strlen($html) . " caractères)\n\n";
    } else {
        echo "✗ ERREUR: Le template HTML semble invalide\n\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ ERREUR lors de la génération du template: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 5: Vérifier la configuration SMTP
echo "Test 5: Vérification de la configuration SMTP...\n";
$configOK = true;
if (!defined('SMTP_HOST')) {
    echo "  ✗ SMTP_HOST n'est pas défini\n";
    $configOK = false;
} else {
    echo "  ✓ SMTP_HOST: " . SMTP_HOST . "\n";
}

if (!defined('SMTP_PORT')) {
    echo "  ✗ SMTP_PORT n'est pas défini\n";
    $configOK = false;
} else {
    echo "  ✓ SMTP_PORT: " . SMTP_PORT . "\n";
}

if (!defined('SMTP_USERNAME')) {
    echo "  ✗ SMTP_USERNAME n'est pas défini\n";
    $configOK = false;
} else {
    echo "  ✓ SMTP_USERNAME: " . SMTP_USERNAME . "\n";
}

if (!defined('SMTP_PASSWORD')) {
    echo "  ✗ SMTP_PASSWORD n'est pas défini\n";
    $configOK = false;
} else {
    $passLength = strlen(SMTP_PASSWORD);
    if ($passLength > 0) {
        echo "  ✓ SMTP_PASSWORD: défini (" . $passLength . " caractères)\n";
    } else {
        echo "  ⚠ SMTP_PASSWORD: vide (à configurer pour l'envoi réel)\n";
    }
}

if ($configOK) {
    echo "✓ Configuration SMTP complète\n\n";
} else {
    echo "✗ ERREUR: Configuration SMTP incomplète\n\n";
    exit(1);
}

echo "=== Résumé des Tests ===\n";
echo "✓ PHPMailer est installé et configuré correctement\n";
echo "✓ Les fonctions d'envoi d'email sont disponibles\n";
echo "✓ Les templates HTML sont fonctionnels\n";
echo "✓ La configuration SMTP est définie\n\n";

if (empty(SMTP_PASSWORD)) {
    echo "⚠ ATTENTION: Le mot de passe SMTP est vide.\n";
    echo "  Pour un envoi réel d'emails, configurez SMTP_PASSWORD dans includes/config.php\n\n";
}

echo "=== Tests Terminés avec Succès ===\n";
echo "\nPour envoyer des emails en production:\n";
echo "1. Configurez SMTP_PASSWORD dans includes/config.php\n";
echo "2. Vérifiez les paramètres SMTP_HOST, SMTP_PORT et SMTP_USERNAME\n";
echo "3. Testez l'envoi avec un email réel\n";
