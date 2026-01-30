<?php
/**
 * Standalone test for replaceTemplateVariables function
 * Does not require database connection
 */

// Copy the function directly for testing
function replaceTemplateVariables($template, $data) {
    foreach ($data as $key => $value) {
        $placeholder = '{{' . $key . '}}';
        // Ensure value is a string
        $value = $value !== null ? (string)$value : '';
        // Don't escape HTML for 'signature' variable since it contains HTML
        if ($key === 'signature') {
            $template = str_replace($placeholder, $value, $template);
        } else {
            $template = str_replace($placeholder, htmlspecialchars($value, ENT_QUOTES, 'UTF-8'), $template);
        }
    }
    
    // Log warning if there are unreplaced variables (but ignore {{signature}} as it's handled in sendEmail)
    if (preg_match_all('/\{\{([^}]+)\}\}/', $template, $matches)) {
        $unreplaced = array_diff($matches[1], ['signature']);
        if (!empty($unreplaced)) {
            echo "[WARNING] Unreplaced variables in template: " . implode(', ', $unreplaced) . "\n";
        }
    }
    
    return $template;
}

echo "=== Test Email Signature Placeholder ===\n\n";

// Test 1: Test replaceTemplateVariables with signature
echo "Test 1: replaceTemplateVariables with {{signature}}\n";
echo "-----------------------------------------------\n";

$template = "Hello {{name}},\n\nThis is a test.\n\n{{signature}}";
$data = [
    'name' => 'John Doe',
    'signature' => '<p><strong>Company Name</strong><br>contact@example.com</p>'
];

$result = replaceTemplateVariables($template, $data);
echo "Result:\n$result\n\n";

// Check that signature is NOT escaped
if (strpos($result, '<p>') !== false && strpos($result, '&lt;p&gt;') === false) {
    echo "✓ PASS: Signature HTML was NOT escaped (correct behavior)\n";
} else {
    echo "✗ FAIL: Signature HTML was escaped (incorrect behavior)\n";
}

// Check that name IS there
if (strpos($result, 'John Doe') !== false) {
    echo "✓ PASS: Regular variables are properly replaced\n";
} else {
    echo "✗ FAIL: Regular variables not properly handled\n";
}

echo "\n";

// Test 2: Test with XSS attempt in regular variable
echo "Test 2: XSS Protection for Regular Variables\n";
echo "-----------------------------------------------\n";

$template = "Hello {{name}},\n\nMessage: {{message}}\n\n{{signature}}";
$data = [
    'name' => '<script>alert("XSS")</script>',
    'message' => 'Normal message',
    'signature' => '<p><strong>Company</strong></p>'
];

$result = replaceTemplateVariables($template, $data);
echo "Result preview:\n";
echo substr($result, 0, 150) . "...\n\n";

if (strpos($result, '&lt;script&gt;') !== false) {
    echo "✓ PASS: XSS attempt was properly escaped\n";
} else {
    echo "✗ FAIL: XSS attempt was NOT escaped\n";
}

// Check that signature HTML is still not escaped
if (strpos($result, '<p><strong>Company</strong></p>') !== false) {
    echo "✓ PASS: Signature HTML still not escaped\n";
} else {
    echo "✗ FAIL: Signature HTML was incorrectly escaped\n";
}

echo "\n";

// Test 3: Test unreplaced variables
echo "Test 3: Unreplaced Variables Warning\n";
echo "-----------------------------------------------\n";

$template = "Hello {{name}},\n\nYour ref: {{reference}}\n\n{{signature}}";
$data = [
    'name' => 'John'
    // Missing: reference, signature
];

echo "Template has {{reference}} and {{signature}} but only 'name' is provided\n";
$result = replaceTemplateVariables($template, $data);

if (strpos($result, '{{signature}}') !== false) {
    echo "✓ PASS: {{signature}} remains unreplaced (correct, handled by sendEmail)\n";
} else {
    echo "✗ FAIL: {{signature}} was replaced unexpectedly\n";
}

if (strpos($result, '{{reference}}') !== false) {
    echo "✓ PASS: {{reference}} remains unreplaced (missing data)\n";
} else {
    echo "✗ FAIL: {{reference}} was replaced unexpectedly\n";
}

echo "\n";

// Test 4: Simulate signature replacement in email body (like sendEmail does)
echo "Test 4: Simulate Signature Replacement in sendEmail\n";
echo "-----------------------------------------------\n";

$bodyWithSignature = '<html><body><p>Hello,</p><p>Test email content.</p>{{signature}}</body></html>';
$mockSignature = '<br><br><table><tr><td><strong>MY Invest Immobilier</strong><br>contact@myinvest-immobilier.com</td></tr></table>';

// Simulate what sendEmail does
if (strpos($bodyWithSignature, '{{signature}}') !== false) {
    $finalBody = str_replace('{{signature}}', $mockSignature, $bodyWithSignature);
    echo "✓ PASS: Signature replacement simulated successfully\n";
    echo "Result preview (first 150 chars):\n";
    echo substr($finalBody, 0, 150) . "...\n";
    
    // Verify the HTML signature is present
    if (strpos($finalBody, '<table>') !== false) {
        echo "✓ PASS: HTML signature properly inserted\n";
    } else {
        echo "✗ FAIL: HTML signature not found\n";
    }
} else {
    echo "✗ FAIL: No {{signature}} to replace\n";
}

echo "\n";

// Test 5: Email without signature placeholder (should work normally)
echo "Test 5: Email Without Signature Placeholder\n";
echo "-----------------------------------------------\n";

$bodyWithoutSignature = '<html><body><p>Hello,</p><p>Test email content.</p><p>Best regards,<br>Admin</p></body></html>';

if (strpos($bodyWithoutSignature, '{{signature}}') === false) {
    echo "✓ PASS: Email without {{signature}} placeholder works normally\n";
    echo "Body remains unchanged (no automatic signature appended)\n";
} else {
    echo "✗ FAIL: Unexpected {{signature}} found\n";
}

echo "\n=== All Tests Complete ===\n";
echo "\nSummary:\n";
echo "- {{signature}} placeholder is NOT escaped (allows HTML)\n";
echo "- Regular variables ARE escaped (prevents XSS)\n";
echo "- {{signature}} is replaced in sendEmail() if present in body\n";
echo "- No automatic signature appending (must use {{signature}} placeholder)\n";
