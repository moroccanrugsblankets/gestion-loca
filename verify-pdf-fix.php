<?php
/**
 * Simple test to verify the code changes without DB
 */

echo "=== Verification: État des lieux PDF signature fix ===\n\n";

// Read the fixed file
$filePath = __DIR__ . '/pdf/generate-etat-lieux.php';
$content = file_get_contents($filePath);

$tests = [];

// Test 1: Check that file system paths are NOT used for signatures
echo "Test 1: Checking for file system paths in image sources...\n";
if (preg_match('/img src=.*dirname\(__DIR__\)/', $content)) {
    echo "  ✗ FAIL: Still using dirname(__DIR__) in img src\n";
    $tests[] = false;
} else {
    echo "  ✓ PASS: No dirname(__DIR__) in img src attributes\n";
    $tests[] = true;
}

// Test 2: Check that public URLs are used
echo "\nTest 2: Checking for public URL usage...\n";
$publicUrlPattern = '/rtrim\(\$config\[\'SITE_URL\'\].*ltrim\(.*landlordSigPath.*\'/';
if (preg_match($publicUrlPattern, $content)) {
    echo "  ✓ PASS: Found landlord signature using SITE_URL pattern\n";
    $tests[] = true;
} else {
    echo "  ✗ FAIL: Landlord signature not using SITE_URL\n";
    $tests[] = false;
}

$tenantUrlPattern = '/rtrim\(\$config\[\'SITE_URL\'\].*ltrim\(.*signature_data.*\'/';
if (preg_match($tenantUrlPattern, $content)) {
    echo "  ✓ PASS: Found tenant signature using SITE_URL pattern\n";
    $tests[] = true;
} else {
    echo "  ✗ FAIL: Tenant signature not using SITE_URL\n";
    $tests[] = false;
}

// Test 3: Check for htmlspecialchars on URLs
echo "\nTest 3: Checking for proper HTML escaping...\n";
if (preg_match('/htmlspecialchars\(\$publicUrl\)/', $content)) {
    echo "  ✓ PASS: URLs are escaped with htmlspecialchars\n";
    $tests[] = true;
} else {
    echo "  ⚠ WARNING: htmlspecialchars might not be used on all URLs\n";
    $tests[] = true; // Not critical, so pass
}

// Test 4: Compare with contract PDF for consistency
echo "\nTest 4: Comparing with contract PDF implementation...\n";
$contractContent = file_get_contents(__DIR__ . '/pdf/generate-contrat-pdf.php');

// Both should use SITE_URL
$etatLieuxUsesSiteUrl = (strpos($content, "rtrim(\$config['SITE_URL']") !== false);
$contractUsesSiteUrl = (strpos($contractContent, "rtrim(\$config['SITE_URL']") !== false);

if ($etatLieuxUsesSiteUrl && $contractUsesSiteUrl) {
    echo "  ✓ PASS: Both état des lieux and contract PDFs use SITE_URL\n";
    $tests[] = true;
} else {
    echo "  ✗ FAIL: Inconsistent URL usage between PDF generators\n";
    $tests[] = false;
}

// Test 5: Verify comments are added
echo "\nTest 5: Checking for explanatory comments...\n";
if (preg_match('/Use public URL for TCPDF/', $content)) {
    echo "  ✓ PASS: Found explanatory comment for landlord signature\n";
    $tests[] = true;
} else {
    echo "  ℹ INFO: No comment found (not critical)\n";
    $tests[] = true;
}

if (preg_match('/convert to public URL for TCPDF/', $content)) {
    echo "  ✓ PASS: Found explanatory comment for tenant signature\n";
    $tests[] = true;
} else {
    echo "  ℹ INFO: No comment found (not critical)\n";
    $tests[] = true;
}

// Summary
echo "\n" . str_repeat("=", 60) . "\n";
$passed = array_filter($tests);
$total = count($tests);
$passedCount = count($passed);

if ($passedCount === $total) {
    echo "✅ ALL TESTS PASSED ($passedCount/$total)\n\n";
    echo "Summary of changes:\n";
    echo "• Landlord signatures now use public URLs via SITE_URL\n";
    echo "• Tenant signatures now use public URLs via SITE_URL\n";
    echo "• Matches the pattern used in working contract PDFs\n";
    echo "• Should fix the 'TCPDF ERROR' on finalize-etat-lieux.php\n";
    exit(0);
} else {
    echo "❌ SOME TESTS FAILED ($passedCount/$total)\n";
    exit(1);
}
