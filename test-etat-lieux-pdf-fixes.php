<?php
/**
 * Test script to validate État des lieux PDF formatting fixes
 * 
 * Tests:
 * 1. h2 margin-top is set to 0 (no space above section titles)
 * 2. signature-box has no border-bottom (no line after signatures)
 * 3. Tenant signature size is 80px × 40px (matches contract)
 * 4. Signature data is included in INSERT statement
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Test État des lieux PDF Formatting Fixes ===\n\n";

// Track test failures
$failures = 0;

// Check that the file exists
$filePath = __DIR__ . '/pdf/generate-etat-lieux.php';
if (!file_exists($filePath)) {
    echo "❌ ERREUR: pdf/generate-etat-lieux.php non trouvé.\n";
    exit(1);
}

echo "✓ Fichier generate-etat-lieux.php trouvé\n";

// Read file content
$content = file_get_contents($filePath);

// Test 1: Check h2 margin-top is 0 (both entry and exit)
echo "\n--- Test 1: h2 margin-top ---\n";
$h2Pattern = '/h2\s*\{\s*font-size:\s*12pt;\s*margin-top:\s*0;/';
$matches = preg_match_all($h2Pattern, $content);
if ($matches >= 2) {
    echo "✓ h2 margin-top est défini à 0 (trouvé $matches fois - entry et exit)\n";
} else {
    echo "❌ ERREUR: h2 margin-top devrait être 0 (trouvé $matches fois)\n";
    $failures++;
}

// Test 2: Check signature-box has no border-bottom
echo "\n--- Test 2: signature-box border-bottom ---\n";
$noBorderPattern = '/\.signature-box\s*\{\s*min-height:\s*80px;\s*margin-bottom:\s*5px;\s*\}/';
$hasBorderPattern = '/\.signature-box\s*\{[^}]*border-bottom[^}]*\}/';

$noBorderMatches = preg_match_all($noBorderPattern, $content);
$hasBorderMatches = preg_match_all($hasBorderPattern, $content);

if ($noBorderMatches >= 2 && $hasBorderMatches == 0) {
    echo "✓ signature-box n'a pas de border-bottom (trouvé $noBorderMatches bonnes définitions)\n";
} else {
    echo "❌ ERREUR: signature-box devrait ne pas avoir de border-bottom\n";
    if ($hasBorderMatches > 0) {
        echo "   Trouvé $hasBorderMatches définitions avec border-bottom\n";
    }
    $failures++;
}

// Test 3: Check tenant signature size is 80px × 40px
echo "\n--- Test 3: Taille signature locataire ---\n";
$tenantSigPattern = '/max-width:\s*80px;\s*max-height:\s*40px/';
$wrongSigPattern = '/max-width:\s*120px;\s*max-height:\s*50px/';

$correctSizeMatches = preg_match_all($tenantSigPattern, $content);
$wrongSizeMatches = preg_match_all($wrongSigPattern, $content);

if ($correctSizeMatches >= 3 && $wrongSizeMatches == 0) {
    echo "✓ Taille signature locataire est 80px × 40px (trouvé $correctSizeMatches occurrences)\n";
} else {
    echo "❌ ERREUR: Taille signature locataire incorrecte\n";
    echo "   Bonnes tailles (80×40): $correctSizeMatches\n";
    echo "   Mauvaises tailles (120×50): $wrongSizeMatches\n";
    $failures++;
}

// Test 4: Check signature data is included in INSERT
echo "\n--- Test 4: Signature data dans INSERT ---\n";
$insertPattern = '/INSERT INTO etat_lieux_locataires\s*\([^)]*signature_data[^)]*signature_timestamp[^)]*signature_ip[^)]*\)/s';
if (preg_match($insertPattern, $content)) {
    echo "✓ signature_data, signature_timestamp et signature_ip sont inclus dans l'INSERT\n";
} else {
    echo "❌ ERREUR: signature_data devrait être inclus dans l'INSERT\n";
    $failures++;
}

// Test 5: Check VALUES includes signature fields
echo "\n--- Test 5: VALUES avec champs signature ---\n";
$valuesPattern = '/\$loc\[\'signature_data\'\]\s*\?\?\s*null/';
if (preg_match($valuesPattern, $content)) {
    echo "✓ signature_data est utilisé dans les VALUES\n";
} else {
    echo "❌ ERREUR: signature_data devrait être dans les VALUES\n";
    $failures++;
}

// Summary
echo "\n=== RÉSUMÉ ===\n";
if ($failures == 0) {
    echo "Tous les correctifs ont été appliqués:\n";
    echo "  1. Espace en haut des titres de section supprimé (margin-top: 0)\n";
    echo "  2. Bordure après signatures supprimée (pas de border-bottom)\n";
    echo "  3. Taille signatures ajustée à 80px × 40px pour correspondre au contrat\n";
    echo "  4. Données de signature client incluses dans l'insertion DB\n";
    echo "\n✓ Tests terminés avec succès!\n";
    exit(0);
} else {
    echo "❌ $failures test(s) échoué(s)\n";
    echo "Les correctifs ne sont pas tous appliqués correctement.\n";
    exit(1);
}
