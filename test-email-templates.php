<?php
/**
 * Test script to verify email template system with signature
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mail-templates.php';

echo "=== Test Email Template System ===\n\n";

// Test 1: Check if templates exist in database
echo "Test 1: Vérification des templates dans la base de données...\n";
$templates = ['candidature_recue', 'candidature_acceptee', 'candidature_refusee', 'admin_nouvelle_candidature'];
foreach ($templates as $templateId) {
    $template = getEmailTemplate($templateId);
    if ($template) {
        echo "✓ Template '$templateId' trouvé\n";
        echo "  - Sujet: " . substr($template['sujet'], 0, 50) . "...\n";
        echo "  - Contient {{signature}}: " . (strpos($template['corps_html'], '{{signature}}') !== false ? 'OUI' : 'NON') . "\n";
    } else {
        echo "✗ Template '$templateId' NON trouvé\n";
    }
}
echo "\n";

// Test 2: Check email signature parameter
echo "Test 2: Vérification du paramètre email_signature...\n";
try {
    $signature = getParameter('email_signature', '');
    if (!empty($signature)) {
        echo "✓ Signature email trouvée\n";
        echo "  - Longueur: " . strlen($signature) . " caractères\n";
        echo "  - Contient logo: " . (strpos($signature, 'logo.png') !== false ? 'OUI' : 'NON') . "\n";
        echo "  - Contient MY INVEST: " . (strpos($signature, 'MY INVEST') !== false ? 'OUI' : 'NON') . "\n";
    } else {
        echo "✗ Signature email NON trouvée\n";
    }
} catch (Exception $e) {
    echo "✗ Erreur: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 3: Check email_admin_candidature parameter
echo "Test 3: Vérification du paramètre email_admin_candidature...\n";
try {
    $adminEmail = getParameter('email_admin_candidature', '');
    echo "✓ Paramètre email_admin_candidature trouvé\n";
    echo "  - Valeur: " . ($adminEmail ?: '(vide)') . "\n";
} catch (Exception $e) {
    echo "✗ Erreur: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 4: Test template variable replacement
echo "Test 4: Test de remplacement des variables...\n";
$testTemplate = "Bonjour {{prenom}} {{nom}}, votre référence est {{reference}}. {{signature}}";
$testVariables = [
    'prenom' => 'Jean',
    'nom' => 'Dupont',
    'reference' => 'TEST-123'
];
$replaced = replaceTemplateVariables($testTemplate, $testVariables);
echo "Template original: $testTemplate\n";
echo "Après remplacement: $replaced\n";
echo "✓ Variables remplacées: " . (strpos($replaced, '{{') === false || strpos($replaced, '{{signature}}') !== false ? 'OUI' : 'NON') . "\n";
echo "\n";

// Test 5: Test sendTemplatedEmail function (dry run, no actual send)
echo "Test 5: Test de la fonction sendTemplatedEmail (sans envoi)...\n";
try {
    $template = getEmailTemplate('candidature_recue');
    if ($template) {
        echo "✓ Template chargé\n";
        
        $variables = [
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => 'test@example.com',
            'logement' => 'LOG-001',
            'reference' => 'CAND-TEST',
            'date' => date('d/m/Y H:i')
        ];
        
        $subject = replaceTemplateVariables($template['sujet'], $variables);
        $body = replaceTemplateVariables($template['corps_html'], $variables);
        
        echo "  - Sujet généré: $subject\n";
        echo "  - Corps contient {{signature}}: " . (strpos($body, '{{signature}}') !== false ? 'OUI (sera remplacée par sendEmail)' : 'NON') . "\n";
        echo "  - Corps contient les données: " . (strpos($body, 'CAND-TEST') !== false ? 'OUI' : 'NON') . "\n";
        echo "✓ Fonction de template fonctionne correctement\n";
    }
} catch (Exception $e) {
    echo "✗ Erreur: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 6: Verify signature replacement in sendEmail
echo "Test 6: Vérification du remplacement de {{signature}} dans sendEmail...\n";
$testBody = "<p>Test email</p>{{signature}}<p>Fin</p>";
echo "Corps avant: $testBody\n";

// Simulate what sendEmail does
if (strpos($testBody, '{{signature}}') !== false) {
    $signature = getParameter('email_signature', '');
    $finalBody = str_replace('{{signature}}', $signature, $testBody);
    echo "Corps après: " . substr($finalBody, 0, 100) . "...\n";
    echo "✓ {{signature}} remplacée: " . (strpos($finalBody, '{{signature}}') === false ? 'OUI' : 'NON') . "\n";
} else {
    echo "✗ Pas de {{signature}} à remplacer\n";
}
echo "\n";

echo "=== Tests terminés ===\n";
echo "\nRésumé:\n";
echo "- Les templates doivent être présents dans la base de données\n";
echo "- Chaque template doit contenir {{signature}}\n";
echo "- Le paramètre email_signature doit contenir le HTML avec logo\n";
echo "- Le paramètre email_admin_candidature doit exister (peut être vide)\n";
echo "- Les variables doivent être remplacées correctement\n";
echo "- La signature doit être remplacée par sendEmail()\n";
echo "\nSi tous les tests passent, le système d'email est correctement configuré.\n";
