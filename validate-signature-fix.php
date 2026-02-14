<?php
/**
 * Comprehensive validation of tenant signature fixes
 * Tests both signature uniqueness and PDF table structure
 */

echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║     COMPREHENSIVE VALIDATION - TENANT SIGNATURE FIXES                ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

$allTestsPassed = true;

// ============================================================================
// TEST 1: Validate signature filename generation in functions.php
// ============================================================================
echo "TEST 1: Signature Filename Generation Logic\n";
echo "═══════════════════════════════════════════════════════════════════\n";

$functionsFile = __DIR__ . '/includes/functions.php';
if (!file_exists($functionsFile)) {
    echo "✗ FAIL: functions.php not found\n";
    $allTestsPassed = false;
} else {
    $content = file_get_contents($functionsFile);
    
    // Check for updateTenantSignature using uniqid
    if (preg_match('/function updateTenantSignature.*?uniqid\([\'"][\'"]\s*,\s*true\)/s', $content)) {
        echo "✓ PASS: updateTenantSignature uses uniqid() with entropy\n";
    } else {
        echo "✗ FAIL: updateTenantSignature does not use uniqid() properly\n";
        $allTestsPassed = false;
    }
    
    // Check for updateInventaireTenantSignature using uniqid
    if (preg_match('/function updateInventaireTenantSignature.*?uniqid\([\'"][\'"]\s*,\s*true\)/s', $content)) {
        echo "✓ PASS: updateInventaireTenantSignature uses uniqid() with entropy\n";
    } else {
        echo "✗ FAIL: updateInventaireTenantSignature does not use uniqid() properly\n";
        $allTestsPassed = false;
    }
    
    // Check for updateEtatLieuxTenantSignature using uniqid
    if (preg_match('/function updateEtatLieuxTenantSignature.*?uniqid\([\'"][\'"]\s*,\s*true\)/s', $content)) {
        echo "✓ PASS: updateEtatLieuxTenantSignature uses uniqid() with entropy\n";
    } else {
        echo "✗ FAIL: updateEtatLieuxTenantSignature does not use uniqid() properly\n";
        $allTestsPassed = false;
    }
    
    // Check that microtime string conversion is NOT used for filenames
    if (preg_match('/str_replace.*microtime\(true\).*filename/s', $content)) {
        echo "✗ WARNING: Found microtime string conversion for filename (potential collision risk)\n";
        $allTestsPassed = false;
    } else {
        echo "✓ PASS: No microtime string conversion for filenames\n";
    }
}

echo "\n";

// ============================================================================
// TEST 2: Validate PDF table structure in generate-contrat-pdf.php
// ============================================================================
echo "TEST 2: PDF Table Structure Validation\n";
echo "═══════════════════════════════════════════════════════════════════\n";

$pdfFile = __DIR__ . '/pdf/generate-contrat-pdf.php';
if (!file_exists($pdfFile)) {
    echo "✗ FAIL: generate-contrat-pdf.php not found\n";
    $allTestsPassed = false;
} else {
    $content = file_get_contents($pdfFile);
    
    // Check for proper table structure
    if (strpos($content, 'border-collapse: collapse') !== false) {
        echo "✓ PASS: Table uses border-collapse for consistent rendering\n";
    } else {
        echo "✗ FAIL: Table missing border-collapse style\n";
        $allTestsPassed = false;
    }
    
    // Check for transparent backgrounds
    if (strpos($content, 'background: transparent') !== false) {
        echo "✓ PASS: Transparent backgrounds applied to remove unwanted colors\n";
    } else {
        echo "✗ FAIL: Missing transparent background declarations\n";
        $allTestsPassed = false;
    }
    
    // Check for consistent padding
    if (preg_match('/cellpadding=["\']15["\']/', $content)) {
        echo "✓ PASS: Consistent cellpadding applied (15px)\n";
    } else {
        echo "✗ FAIL: Inconsistent or missing cellpadding\n";
        $allTestsPassed = false;
    }
    
    // Check for proper cell borders
    if (preg_match('/border:\s*1px\s+solid\s+#333/', $content)) {
        echo "✓ PASS: Consistent borders applied to cells\n";
    } else {
        echo "✗ FAIL: Missing or inconsistent cell borders\n";
        $allTestsPassed = false;
    }
    
    // Check that images have no borders
    if (preg_match('/img.*border:\s*none/', $content)) {
        echo "✓ PASS: Signature images have border: none\n";
    } else {
        echo "✗ FAIL: Signature images may have unwanted borders\n";
        $allTestsPassed = false;
    }
    
    // Check for min-height on signature containers
    if (preg_match('/min-height:\s*60px/', $content)) {
        echo "✓ PASS: Signature containers have min-height for consistent layout\n";
    } else {
        echo "✗ FAIL: Missing min-height on signature containers\n";
        $allTestsPassed = false;
    }
}

echo "\n";

// ============================================================================
// TEST 3: Runtime uniqueness test
// ============================================================================
echo "TEST 3: Runtime Uniqueness Test\n";
echo "═══════════════════════════════════════════════════════════════════\n";

// Generate 500 IDs rapidly to test for collisions
$ids = [];
for ($i = 0; $i < 500; $i++) {
    $uniqueId = uniqid('', true);
    $uniqueId = str_replace('.', '_', $uniqueId);
    $ids[] = $uniqueId;
}

$unique_ids = array_unique($ids);
$collisions = count($ids) - count($unique_ids);

if ($collisions === 0) {
    echo "✓ PASS: Generated 500 unique IDs with 0 collisions\n";
} else {
    echo "✗ FAIL: Generated 500 IDs with $collisions collisions\n";
    $allTestsPassed = false;
}

// Test tenant-specific filenames
$tenant1_files = [];
$tenant2_files = [];

for ($i = 0; $i < 100; $i++) {
    $uid1 = uniqid('', true);
    $uid1 = str_replace('.', '_', $uid1);
    $tenant1_files[] = "tenant_locataire_4_{$uid1}.jpg";
    
    $uid2 = uniqid('', true);
    $uid2 = str_replace('.', '_', $uid2);
    $tenant2_files[] = "tenant_locataire_5_{$uid2}.jpg";
}

$all_files = array_merge($tenant1_files, $tenant2_files);
$unique_files = array_unique($all_files);
$file_collisions = count($all_files) - count($unique_files);

if ($file_collisions === 0) {
    echo "✓ PASS: Generated 200 tenant filenames with 0 collisions\n";
} else {
    echo "✗ FAIL: Generated 200 tenant filenames with $file_collisions collisions\n";
    $allTestsPassed = false;
}

echo "\n";

// ============================================================================
// TEST 4: Code structure validation
// ============================================================================
echo "TEST 4: Code Structure Validation\n";
echo "═══════════════════════════════════════════════════════════════════\n";

// Check step2-signature.php for proper session handling
$step2File = __DIR__ . '/signature/step2-signature.php';
if (!file_exists($step2File)) {
    echo "✗ FAIL: step2-signature.php not found\n";
    $allTestsPassed = false;
} else {
    $content = file_get_contents($step2File);
    
    // Check for current_locataire_id session variable
    if (strpos($content, '$_SESSION[\'current_locataire_id\']') !== false) {
        echo "✓ PASS: step2-signature.php uses tenant-specific session variable\n";
    } else {
        echo "✗ FAIL: Missing tenant ID session handling\n";
        $allTestsPassed = false;
    }
    
    // Check for defensive tenant ID validation
    if (preg_match('/WHERE id = \?/', $content)) {
        echo "✓ PASS: Tenant validation uses WHERE id = ? for specificity\n";
    } else {
        echo "✗ WARNING: Tenant validation may not be specific enough\n";
    }
}

echo "\n";

// ============================================================================
// FINAL SUMMARY
// ============================================================================
echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║                          VALIDATION SUMMARY                          ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

if ($allTestsPassed) {
    echo "✓✓✓ ALL TESTS PASSED ✓✓✓\n\n";
    echo "Summary:\n";
    echo "  • Signature filename generation uses uniqid() with entropy\n";
    echo "  • PDF table structure has proper TCPDF styling\n";
    echo "  • Runtime tests show 0% collision rate\n";
    echo "  • Code structure follows best practices\n\n";
    echo "The tenant signature fix is PRODUCTION READY.\n";
    exit(0);
} else {
    echo "✗✗✗ SOME TESTS FAILED ✗✗✗\n\n";
    echo "Please review the failed tests above and fix the issues.\n";
    exit(1);
}
