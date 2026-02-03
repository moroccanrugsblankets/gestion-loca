<?php
/**
 * Migration script to convert existing base64 company signature to physical file
 * This script migrates the signature_societe_image parameter from base64 to file path
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

echo "=== Migration: Convert Company Signature from Base64 to Physical File ===\n\n";

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

// Get the current company signature from parametres
$sql = "SELECT valeur FROM parametres WHERE cle = 'signature_societe_image'";
$stmt = $pdo->query($sql);
$signatureData = $stmt->fetchColumn();

if (empty($signatureData)) {
    echo "No company signature found in database. Migration not needed.\n";
    exit(0);
}

// Check if it's already a file path
if (strpos($signatureData, 'uploads/signatures/') !== false) {
    echo "Company signature is already stored as a file path: $signatureData\n";
    echo "Migration not needed.\n";
    exit(0);
}

// Validate and extract base64 data
if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,(.+)$/', $signatureData, $matches)) {
    echo "✗ Invalid data URI format in signature_societe_image\n";
    echo "Current value: " . substr($signatureData, 0, 100) . "...\n";
    exit(1);
}

$imageFormat = $matches[1];
$base64Data = $matches[2];

echo "Found base64 company signature (format: $imageFormat, size: " . strlen($base64Data) . " bytes)\n";
echo "Converting to physical file...\n\n";

// Decode base64
$imageData = base64_decode($base64Data, true);
if ($imageData === false) {
    echo "✗ Failed to decode base64 data\n";
    exit(1);
}

// Generate unique filename
$filename = "company_signature_migrated_" . time() . ".png";
$filepath = $uploadsDir . '/' . $filename;

// Save physical file
if (file_put_contents($filepath, $imageData) === false) {
    echo "✗ Failed to save file: $filepath\n";
    exit(1);
}

echo "✓ Saved physical file: $filepath\n";

// Update database with relative path
$relativePath = 'uploads/signatures/' . $filename;
$updateSql = "UPDATE parametres SET valeur = ?, updated_at = NOW() WHERE cle = 'signature_societe_image'";
$updateStmt = $pdo->prepare($updateSql);

if ($updateStmt->execute([$relativePath])) {
    echo "✓ Updated database with file path: $relativePath\n";
    echo "\n=== Migration Complete ===\n";
    echo "Company signature successfully converted to physical file!\n";
} else {
    echo "✗ Failed to update database\n";
    // Clean up file if database update failed
    if (file_exists($filepath)) {
        unlink($filepath);
        echo "✓ Cleaned up physical file\n";
    }
    exit(1);
}
