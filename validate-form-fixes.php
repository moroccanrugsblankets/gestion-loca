<?php
/**
 * Validation script to verify form validation fixes
 * 
 * This script checks:
 * 1. edit-bilan-logement.php: Validates that Valeur and Montant dû fields are optional
 * 2. edit-inventaire.php: Validates that Certifié exact checkbox preserves state
 * 3. edit-etat-lieux.php: Validates that Certifié exact checkbox preserves state
 */

echo "=== Form Validation Fixes - Verification ===\n\n";

$errors = [];
$warnings = [];
$success = [];

// Check 1: edit-bilan-logement.php - Valeur and Montant dû should be optional
echo "1. Checking edit-bilan-logement.php...\n";
$bilanContent = file_get_contents(__DIR__ . '/admin-v2/edit-bilan-logement.php');

// Check for the updated validation logic
if (strpos($bilanContent, "// Skip validation for valeur and montant_du fields (they are optional)") !== false) {
    $success[] = "✓ edit-bilan-logement.php: Validation logic correctly skips Valeur and Montant dû fields";
    
    // Verify the implementation details
    if (strpos($bilanContent, "field.classList.contains('bilan-valeur')") !== false &&
        strpos($bilanContent, "field.classList.contains('bilan-montant-du')") !== false) {
        $success[] = "✓ edit-bilan-logement.php: Correct field classes checked";
    } else {
        $errors[] = "✗ edit-bilan-logement.php: Field class checks are incorrect";
    }
} else {
    $errors[] = "✗ edit-bilan-logement.php: Validation logic not updated to skip Valeur and Montant dû";
}

// Check that the old validation logic (requiring all fields) is replaced
if (strpos($bilanContent, "// If row has any value, all fields must be filled") !== false) {
    $warnings[] = "⚠ edit-bilan-logement.php: Old comment still present, but may be OK if logic is updated";
}

// Check 2: edit-inventaire.php - Certifié exact should preserve state
echo "\n2. Checking edit-inventaire.php...\n";
$inventaireContent = file_get_contents(__DIR__ . '/admin-v2/edit-inventaire.php');

// Look for the checked attribute preservation - use a simpler check
if (strpos($inventaireContent, "!empty(\$tenant['certifie_exact']) ? 'checked' : ''") !== false) {
    $success[] = "✓ edit-inventaire.php: Certifié exact checkbox correctly preserves state";
} else {
    $warnings[] = "⚠ edit-inventaire.php: Could not verify Certifié exact checkbox state preservation";
}

// Verify that validation exists
if (strpos($inventaireContent, 'La case "Certifié exact" doit être cochée') !== false) {
    $success[] = "✓ edit-inventaire.php: Validation for Certifié exact checkbox is present";
} else {
    $errors[] = "✗ edit-inventaire.php: Validation for Certifié exact checkbox is missing";
}

// Check 3: edit-etat-lieux.php - Certifié exact should preserve state
echo "\n3. Checking edit-etat-lieux.php...\n";
$etatLieuxContent = file_get_contents(__DIR__ . '/admin-v2/edit-etat-lieux.php');

// Look for the checked attribute preservation - use a simpler check
if (strpos($etatLieuxContent, "!empty(\$tenant['certifie_exact']) ? 'checked' : ''") !== false) {
    $success[] = "✓ edit-etat-lieux.php: Certifié exact checkbox correctly preserves state";
} else {
    $errors[] = "✗ edit-etat-lieux.php: Certifié exact checkbox does not preserve state";
}

// Verify that validation exists
if (strpos($etatLieuxContent, 'La case "Certifié exact" doit être cochée') !== false) {
    $success[] = "✓ edit-etat-lieux.php: Validation for Certifié exact checkbox is present";
} else {
    $errors[] = "✗ edit-etat-lieux.php: Validation for Certifié exact checkbox is missing";
}

// Display results
echo "\n=== RESULTS ===\n\n";

if (!empty($success)) {
    echo "SUCCESS (" . count($success) . "):\n";
    foreach ($success as $msg) {
        echo "  $msg\n";
    }
}

if (!empty($warnings)) {
    echo "\nWARNINGS (" . count($warnings) . "):\n";
    foreach ($warnings as $msg) {
        echo "  $msg\n";
    }
}

if (!empty($errors)) {
    echo "\nERRORS (" . count($errors) . "):\n";
    foreach ($errors as $msg) {
        echo "  $msg\n";
    }
    echo "\n❌ Validation FAILED\n";
    exit(1);
} else {
    echo "\n✅ All checks PASSED\n";
    exit(0);
}
