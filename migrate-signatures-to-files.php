<?php
/**
 * Migration script to convert existing base64 signatures to physical files
 * This script should be run once after deploying the signature format changes
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

echo "=== Migration: Convert Base64 Signatures to Physical Files ===\n\n";

// Create uploads/signatures directory if it doesn't exist
$uploadsDir = __DIR__ . '/uploads/signatures';
if (!is_dir($uploadsDir)) {
    if (mkdir($uploadsDir, 0755, true)) {
        echo "✓ Created directory: uploads/signatures\n\n";
    } else {
        die("✗ Failed to create uploads/signatures directory\n");
    }
} else {
    echo "✓ Directory already exists: uploads/signatures\n\n";
}

// Find all locataires with base64 signatures
$sql = "SELECT id, signature_data FROM locataires 
        WHERE signature_data IS NOT NULL 
        AND signature_data LIKE 'data:image/%'";

$stmt = $pdo->query($sql);
$locataires = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($locataires)) {
    echo "No base64 signatures found. Migration not needed.\n";
    exit(0);
}

echo "Found " . count($locataires) . " locataire(s) with base64 signatures\n\n";

$converted = 0;
$failed = 0;

foreach ($locataires as $locataire) {
    $id = $locataire['id'];
    $signatureData = $locataire['signature_data'];
    
    echo "Processing locataire ID $id...\n";
    
    // Validate and extract base64 data
    if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,(.+)$/', $signatureData, $matches)) {
        echo "  ✗ Invalid data URI format\n";
        $failed++;
        continue;
    }
    
    $imageFormat = $matches[1];
    $base64Data = $matches[2];
    
    // Decode base64
    $imageData = base64_decode($base64Data, true);
    if ($imageData === false) {
        echo "  ✗ Failed to decode base64\n";
        $failed++;
        continue;
    }
    
    // Generate unique filename (always .jpg)
    $filename = "tenant_locataire_{$id}_migrated_" . time() . ".jpg";
    $filepath = $uploadsDir . '/' . $filename;
    
    // Save physical file
    if (file_put_contents($filepath, $imageData) === false) {
        echo "  ✗ Failed to save file: $filepath\n";
        $failed++;
        continue;
    }
    
    // Update database with relative path
    $relativePath = 'uploads/signatures/' . $filename;
    $updateSql = "UPDATE locataires SET signature_data = ? WHERE id = ?";
    $updateStmt = $pdo->prepare($updateSql);
    
    if ($updateStmt->execute([$relativePath, $id])) {
        echo "  ✓ Converted to physical file: $relativePath\n";
        $converted++;
    } else {
        echo "  ✗ Failed to update database\n";
        // Clean up file if database update failed
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        $failed++;
    }
}

echo "\n=== Migration Complete ===\n";
echo "Successfully converted: $converted\n";
echo "Failed: $failed\n";
echo "Total: " . count($locataires) . "\n";

if ($converted > 0) {
    echo "\n✓ Migration successful! All base64 signatures have been converted to physical files.\n";
}
if ($failed > 0) {
    echo "\n⚠ Some signatures failed to convert. Please check the errors above.\n";
}
