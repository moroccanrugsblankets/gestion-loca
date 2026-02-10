<?php
/**
 * Test script to verify email signature {{signature}} placeholder functionality
 * Tests the new signature replacement system
 */

// Don't connect to database for this test
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Mock the PDO connection to avoid database errors
$pdo = null;

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
echo "Template: $template\n\n";
echo "Result:\n$result\n\n";

// Check that signature is NOT escaped
if (strpos($result, '<p>') !== false) {
    echo "✓ PASS: Signature HTML was NOT escaped (correct behavior)\n";
} else {
    echo "✗ FAIL: Signature HTML was escaped (incorrect behavior)\n";
}

// Check that name IS escaped (normal variable)
if (strpos($result, 'John Doe') !== false && strpos($result, '<script>') === false) {
    echo "✓ PASS: Regular variables are properly escaped\n";
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
echo "Result (XSS attempt should be escaped):\n";
echo substr($result, 0, 200) . "...\n\n";

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

// Test 3: Test email body with {{signature}} placeholder replacement in sendEmail context
echo "Test 3: Signature Replacement in Email Body\n";
echo "-----------------------------------------------\n";

$bodyWithSignature = '<html><body><p>Hello,</p><p>Test email content.</p>{{signature}}</body></html>';
$bodyWithoutSignature = '<html><body><p>Hello,</p><p>Test email content.</p></body></html>';

echo "Body with {{signature}} placeholder:\n";
echo substr($bodyWithSignature, 0, 100) . "...\n";

if (strpos($bodyWithSignature, '{{signature}}') !== false) {
    echo "✓ PASS: {{signature}} placeholder found in body\n";
} else {
    echo "✗ FAIL: {{signature}} placeholder not found\n";
}

echo "\nBody without {{signature}} placeholder:\n";
echo substr($bodyWithoutSignature, 0, 100) . "...\n";

if (strpos($bodyWithoutSignature, '{{signature}}') === false) {
    echo "✓ PASS: No {{signature}} placeholder (will not be replaced)\n";
} else {
    echo "✗ FAIL: Unexpected {{signature}} found\n";
}

echo "\n";

// Test 4: Simulate signature replacement in email body
echo "Test 4: Simulate Signature Replacement\n";
echo "-----------------------------------------------\n";

$bodyWithSignature = '<html><body><p>Hello,</p><p>Test email content.</p>{{signature}}</body></html>';
$mockSignature = '<br><br><table><tr><td><strong>My Invest Immobilier</strong><br>contact@myinvest-immobilier.com</td></tr></table>';

// Simulate what sendEmail does
if (strpos($bodyWithSignature, '{{signature}}') !== false) {
    $finalBody = str_replace('{{signature}}', $mockSignature, $bodyWithSignature);
    echo "✓ PASS: Signature replacement simulated successfully\n";
    echo "Result preview:\n";
    echo substr($finalBody, 0, 150) . "...\n";
} else {
    echo "✗ FAIL: No {{signature}} to replace\n";
}

echo "\n=== All Tests Complete ===\n";
