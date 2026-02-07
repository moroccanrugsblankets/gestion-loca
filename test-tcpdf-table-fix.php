<?php
/**
 * Simple test to verify TCPDF table parsing works without errors
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/vendor/autoload.php';

echo "=== Testing TCPDF Table Parsing ===\n\n";

// Create a simple TCPDF instance
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Test');
$pdf->SetTitle('TCPDF Table Test');
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 15);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->AddPage();

// Test 1: Table with cellspacing, cellpadding, and border-collapse
echo "Test 1: Table with cellspacing, cellpadding, and border-collapse...\n";
$html1 = '<table cellspacing="0" cellpadding="0" style="width: 100%; border-collapse: collapse;"><tr><td>Test 1</td></tr></table>';
try {
    $pdf->writeHTML($html1, true, false, true, false, '');
    echo "  ❌ This combination should cause warnings\n";
} catch (Exception $e) {
    echo "  ⚠ Exception: " . $e->getMessage() . "\n";
}

// Test 2: Table with cellspacing, cellpadding, WITHOUT border-collapse
echo "\nTest 2: Table with cellspacing, cellpadding, WITHOUT border-collapse...\n";
$html2 = '<table cellspacing="0" cellpadding="10" border="0" style="width: 100%;"><tbody><tr><td>Test 2</td></tr></tbody></table>';
try {
    $pdf->writeHTML($html2, true, false, true, false, '');
    echo "  ✅ No errors expected\n";
} catch (Exception $e) {
    echo "  ❌ Exception: " . $e->getMessage() . "\n";
}

// Test 3: Table with proper tbody structure
echo "\nTest 3: Table with proper tbody structure...\n";
$html3 = '<table cellspacing="0" cellpadding="4"><tbody><tr><td>Cell 1</td><td>Cell 2</td></tr></tbody></table>';
try {
    $pdf->writeHTML($html3, true, false, true, false, '');
    echo "  ✅ No errors expected\n";
} catch (Exception $e) {
    echo "  ❌ Exception: " . $e->getMessage() . "\n";
}

// Test 4: Simulate the signature table structure from generate-etat-lieux.php
echo "\nTest 4: Signature table structure (after fix)...\n";
$html4 = '<table cellspacing="0" cellpadding="10" border="0" style="max-width: 500px;width: 80%; border: none; border-width: 0; border-style: none; margin-top: 20px;"><tbody><tr>';
$html4 .= '<td style="width:50%; vertical-align: top; text-align:center; padding:10px; border: none; border-width: 0; border-style: none;">';
$html4 .= '<p><strong>Le bailleur :</strong></p>';
$html4 .= '<p>Signature</p>';
$html4 .= '</td>';
$html4 .= '<td style="width:50%; vertical-align: top; text-align:center; padding:10px; border: none; border-width: 0; border-style: none;">';
$html4 .= '<p><strong>Le locataire :</strong></p>';
$html4 .= '<p>Signature</p>';
$html4 .= '</td>';
$html4 .= '</tr></tbody></table>';
try {
    $pdf->writeHTML($html4, true, false, true, false, '');
    echo "  ✅ No errors expected with new structure\n";
} catch (Exception $e) {
    echo "  ❌ Exception: " . $e->getMessage() . "\n";
}

// Save test PDF
$testFile = '/tmp/tcpdf-table-test.pdf';
$pdf->Output($testFile, 'F');

if (file_exists($testFile)) {
    echo "\n✅ Test PDF generated successfully: $testFile\n";
    echo "File size: " . filesize($testFile) . " bytes\n";
} else {
    echo "\n❌ Failed to generate test PDF\n";
}

echo "\n=== Test Complete ===\n";
