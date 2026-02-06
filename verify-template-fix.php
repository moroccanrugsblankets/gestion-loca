<?php
/**
 * Manual verification test
 * This simulates what happens when pdf/generate-etat-lieux.php is included by finalize-etat-lieux.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Manual Verification Test ===\n\n";

// Test 1: Simulate including the template file (as done by pdf/generate-etat-lieux.php)
echo "Test 1: Including etat-lieux-template.php (used by PDF generator)...\n";
ob_start();
require_once __DIR__ . '/includes/etat-lieux-template.php';
$template_output = ob_get_clean();

if (strlen($template_output) > 0) {
    echo "❌ FAIL: Including template produced " . strlen($template_output) . " bytes of output\n";
    echo "First 200 chars: " . substr($template_output, 0, 200) . "\n\n";
    exit(1);
} else {
    echo "✓ PASS: No HTML output when including template file\n\n";
}

// Test 2: Verify the function is available
echo "Test 2: Verifying getDefaultEtatLieuxTemplate function is available...\n";
if (!function_exists('getDefaultEtatLieuxTemplate')) {
    echo "❌ FAIL: Function not found\n";
    exit(1);
}
echo "✓ PASS: Function is available\n\n";

// Test 3: Get the template
echo "Test 3: Calling getDefaultEtatLieuxTemplate()...\n";
$template = getDefaultEtatLieuxTemplate();
if (empty($template)) {
    echo "❌ FAIL: Function returned empty template\n";
    exit(1);
}
echo "✓ PASS: Function returned template (" . strlen($template) . " bytes)\n\n";

// Test 4: Verify template structure
echo "Test 4: Verifying template structure...\n";
$checks = [
    '<!DOCTYPE html>' => 'DOCTYPE declaration',
    '{{reference}}' => 'Reference placeholder',
    '{{type}}' => 'Type placeholder',
    '{{adresse}}' => 'Address placeholder',
    '{{locataire' => 'Tenant placeholder',
    'MY INVEST IMMOBILIER' => 'Company name'
];

$failed = [];
foreach ($checks as $needle => $description) {
    if (strpos($template, $needle) === false) {
        $failed[] = $description;
    }
}

if (!empty($failed)) {
    echo "❌ FAIL: Template missing: " . implode(', ', $failed) . "\n";
    exit(1);
}
echo "✓ PASS: Template structure is valid\n\n";

echo "=== SUCCESS ===\n";
echo "The template file works correctly:\n";
echo "- No HTML output when included\n";
echo "- Function is available\n";
echo "- Template contains all required placeholders\n";
echo "\nThis means the finalize page will NOT display the configuration block.\n";
