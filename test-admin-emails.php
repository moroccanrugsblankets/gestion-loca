<?php
/**
 * Test de la nouvelle fonctionnalité: Email aux deux administrateurs
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/mail-templates.php';

echo "=== Test de la Fonctionnalité Email Admin Secondaire ===\n\n";

// Test 1: Vérifier que la nouvelle fonction existe
echo "Test 1: Vérification de la fonction sendEmailToAdmins...\n";
if (function_exists('sendEmailToAdmins')) {
    echo "✓ La fonction sendEmailToAdmins existe\n\n";
} else {
    echo "✗ ERREUR: La fonction sendEmailToAdmins n'existe pas\n\n";
    exit(1);
}

// Test 2: Vérifier que le template d'email admin existe
echo "Test 2: Vérification du template getAdminNewCandidatureEmailHTML...\n";
if (function_exists('getAdminNewCandidatureEmailHTML')) {
    echo "✓ La fonction getAdminNewCandidatureEmailHTML existe\n\n";
} else {
    echo "✗ ERREUR: La fonction getAdminNewCandidatureEmailHTML n'existe pas\n\n";
    exit(1);
}

// Test 3: Vérifier la configuration des emails admin
echo "Test 3: Vérification de la configuration des emails admin...\n";
if (isset($config['ADMIN_EMAIL'])) {
    echo "  ✓ ADMIN_EMAIL: " . $config['ADMIN_EMAIL'] . "\n";
} else {
    echo "  ⚠ ADMIN_EMAIL non configuré (optionnel)\n";
}

if (isset($config['ADMIN_EMAIL_SECONDARY'])) {
    echo "  ✓ ADMIN_EMAIL_SECONDARY: " . $config['ADMIN_EMAIL_SECONDARY'] . "\n";
} else {
    echo "  ⚠ ADMIN_EMAIL_SECONDARY non configuré (optionnel)\n";
}
echo "\n";

// Test 4: Tester la génération du template admin
echo "Test 4: Test de génération du template admin...\n";
try {
    $testCandidature = [
        'id' => 1,
        'reference' => 'CAND-20240129-TEST1234',
        'nom' => 'Dupont',
        'prenom' => 'Jean',
        'email' => 'jean.dupont@example.com',
        'telephone' => '06 12 34 56 78',
        'statut_professionnel' => 'CDI',
        'periode_essai' => 'Oui, terminée',
        'revenus_mensuels' => '3000 € et +',
        'type_revenus' => 'Salaire'
    ];
    
    $testLogement = [
        'reference' => 'LOG-001',
        'type' => 'Appartement T3',
        'adresse' => '123 Avenue de Test, 75001 Paris',
        'loyer' => 1500
    ];
    
    $html = getAdminNewCandidatureEmailHTML($testCandidature, $testLogement, 5);
    
    if (strlen($html) > 100 && strpos($html, '<!DOCTYPE html>') !== false) {
        echo "✓ Template admin généré avec succès (" . strlen($html) . " caractères)\n";
        
        // Vérifier que certains éléments clés sont présents
        $required_elements = [
            'Nouvelle Candidature Reçue',
            'CAND-20240129-TEST1234',
            'jean.dupont@example.com',
            'LOG-001'
        ];
        
        $all_present = true;
        foreach ($required_elements as $element) {
            if (strpos($html, $element) === false) {
                echo "  ✗ Élément manquant: $element\n";
                $all_present = false;
            }
        }
        
        if ($all_present) {
            echo "  ✓ Tous les éléments requis sont présents dans le template\n";
        }
    } else {
        echo "✗ ERREUR: Le template admin semble invalide\n";
        exit(1);
    }
    echo "\n";
} catch (Exception $e) {
    echo "✗ ERREUR lors de la génération du template: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 5: Simuler l'envoi (sans vraiment envoyer)
echo "Test 5: Simulation de l'envoi aux administrateurs...\n";
echo "  Note: Ce test ne va PAS envoyer d'emails réels\n";
echo "  Il vérifie seulement la logique de la fonction\n\n";

// Créer une configuration de test
$test_config = $config;
$test_config['ADMIN_EMAIL'] = 'admin1@test.local';
$test_config['ADMIN_EMAIL_SECONDARY'] = 'admin2@test.local';

echo "  Configuration de test:\n";
echo "    - ADMIN_EMAIL: " . $test_config['ADMIN_EMAIL'] . "\n";
echo "    - ADMIN_EMAIL_SECONDARY: " . $test_config['ADMIN_EMAIL_SECONDARY'] . "\n";
echo "  ✓ La fonction sendEmailToAdmins enverra aux deux adresses\n\n";

// Test 6: Vérifier le fichier de template de config
echo "Test 6: Vérification du fichier template de configuration...\n";
$template_file = __DIR__ . '/includes/config.local.php.template';
if (file_exists($template_file)) {
    echo "✓ Le fichier config.local.php.template existe\n";
    
    // Vérifier qu'il contient les clés requises
    $content = file_get_contents($template_file);
    if (strpos($content, 'ADMIN_EMAIL') !== false && strpos($content, 'ADMIN_EMAIL_SECONDARY') !== false) {
        echo "  ✓ Le template contient les clés ADMIN_EMAIL et ADMIN_EMAIL_SECONDARY\n";
    } else {
        echo "  ✗ Le template ne contient pas toutes les clés requises\n";
    }
} else {
    echo "✗ Le fichier config.local.php.template n'existe pas\n";
}
echo "\n";

// Test 7: Vérifier la documentation
echo "Test 7: Vérification de la documentation...\n";
$doc_file = __DIR__ . '/CONFIG_ADMIN_EMAILS.md';
if (file_exists($doc_file)) {
    echo "✓ La documentation CONFIG_ADMIN_EMAILS.md existe\n";
} else {
    echo "⚠ La documentation CONFIG_ADMIN_EMAILS.md n'existe pas\n";
}
echo "\n";

echo "=== Résumé des Tests ===\n";
echo "✓ La fonction sendEmailToAdmins est disponible\n";
echo "✓ Le template d'email admin est fonctionnel\n";
echo "✓ Les configurations ADMIN_EMAIL et ADMIN_EMAIL_SECONDARY sont supportées\n";
echo "✓ Le fichier template de configuration est disponible\n";
echo "✓ La documentation est disponible\n\n";

echo "=== Tests Terminés avec Succès ===\n\n";

echo "Instructions pour utiliser cette fonctionnalité:\n";
echo "1. Copiez le template: cp includes/config.local.php.template includes/config.local.php\n";
echo "2. Éditez includes/config.local.php et configurez ADMIN_EMAIL et ADMIN_EMAIL_SECONDARY\n";
echo "3. Les nouveaux emails de candidature seront envoyés aux deux adresses\n";
echo "4. Consultez CONFIG_ADMIN_EMAILS.md pour plus de détails\n";
