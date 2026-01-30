<?php
/**
 * Simplified test script to validate fixes
 */

$failCount = 0;
$passCount = 0;

function test($condition, $passMsg, $failMsg) {
    global $failCount, $passCount;
    if ($condition) {
        echo "   ✓ PASS: $passMsg\n";
        $passCount++;
        return true;
    } else {
        echo "   ✗ FAIL: $failMsg\n";
        $failCount++;
        return false;
    }
}

echo "=== Test des Corrections ===\n\n";

// Test 1: Email Signature
echo "1. Email Signature Management\n";
$sendEmailContent = file_get_contents(__DIR__ . '/admin-v2/send-email-candidature.php');
test(
    strpos($sendEmailContent, '{{signature}}') !== false,
    "Template utilise {{signature}}",
    "Template manque {{signature}}"
);
test(
    strpos($sendEmailContent, 'Cordialement,<br>') === false,
    "Pas de signature hardcodée",
    "Signature hardcodée présente"
);

$mailTemplatesContent = file_get_contents(__DIR__ . '/includes/mail-templates.php');
test(
    strpos($mailTemplatesContent, 'str_replace(\'{{signature}}\'') !== false,
    "Remplacement de {{signature}} implémenté",
    "Remplacement manquant"
);

echo "\n2. Document Download\n";
$downloadContent = file_get_contents(__DIR__ . '/admin-v2/download-document.php');
test(
    strpos($downloadContent, 'file_exists($fullPath)') !== false,
    "Vérification d'existence du fichier",
    "Vérification manquante"
);
test(
    strpos($downloadContent, 'error_log') !== false,
    "Logging d'erreurs activé",
    "Logging manquant"
);

echo "\n3. Revenue Field\n";
$candidatureDetailContent = file_get_contents(__DIR__ . '/admin-v2/candidature-detail.php');
test(
    strpos($candidatureDetailContent, 'Revenus & Solvabilité') !== false,
    "Section 'Revenus & Solvabilité'",
    "Section manquante"
);
test(
    strpos($candidatureDetailContent, 'Revenus nets mensuels:') !== false,
    "Label 'Revenus nets mensuels'",
    "Label manquant"
);

echo "\n=== Résumé ===\n";
if ($failCount > 0) {
    echo "❌ ÉCHEC: $failCount test(s) échoué(s), $passCount réussi(s)\n";
    exit(1);
} else {
    echo "✅ SUCCÈS: Tous les $passCount tests passent!\n";
}
