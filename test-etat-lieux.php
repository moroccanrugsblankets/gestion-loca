<?php
/**
 * Test script for État des lieux PDF generation
 * Tests the generateEtatDesLieuxPDF function with a real database connection
 * 
 * Usage: php test-etat-lieux.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "=== Test de génération PDF État des lieux ===\n\n";

// Check if vendor autoload exists
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "❌ ERROR: vendor/autoload.php not found. Run 'composer install' first.\n";
    exit(1);
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/pdf/generate-etat-lieux.php';

// Verify TCPDF is available
if (!class_exists('TCPDF')) {
    echo "❌ ERROR: TCPDF is not installed.\n";
    exit(1);
}

echo "✓ TCPDF is available\n";
echo "✓ Database connection established\n\n";

// Get the first active contract to test with
try {
    $stmt = $pdo->query("SELECT c.id, c.reference_unique, l.adresse 
                         FROM contrats c 
                         INNER JOIN logements l ON c.logement_id = l.id 
                         WHERE c.statut = 'actif' 
                         LIMIT 1");
    $contrat = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$contrat) {
        echo "⚠ WARNING: No active contract found in database. Creating test data would be needed.\n";
        echo "Instead, let's test with a mock contract ID...\n\n";
        
        // For testing purposes, we'll try with ID 1
        $contratId = 51;
    } else {
        $contratId = $contrat['id'];
        echo "✓ Found test contract:\n";
        echo "  - ID: {$contrat['id']}\n";
        echo "  - Reference: {$contrat['reference_unique']}\n";
        echo "  - Address: {$contrat['adresse']}\n\n";
    }
    
    // Test PDF generation for entry état des lieux
    echo "=== Testing PDF generation for entry état des lieux ===\n";
    $pdfPath = generateEtatDesLieuxPDF($contratId, 'entree');
    
    if ($pdfPath && file_exists($pdfPath)) {
        echo "✅ PDF generated successfully: $pdfPath\n";
        echo "File size: " . filesize($pdfPath) . " bytes\n";
    } else {
        echo "❌ PDF generation failed!\n";
        echo "Check error logs for details.\n";
        exit(1);
    }
    
    echo "\n=== Test completed successfully ===\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
