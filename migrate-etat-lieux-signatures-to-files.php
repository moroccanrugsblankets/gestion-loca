<?php
/**
 * Migration script to convert existing base64 signatures in etat_lieux_locataires to physical JPG files
 * This script should be run once to migrate all existing base64 signatures
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

echo "=== Migration: Convert État des Lieux Base64 Signatures to Physical JPG Files ===\n\n";

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

// Part 1: Migrate tenant signatures from etat_lieux_locataires table
echo "=== Part 1: Migrating Tenant Signatures ===\n\n";

$sql = "SELECT id, etat_lieux_id, signature_data FROM etat_lieux_locataires 
        WHERE signature_data IS NOT NULL 
        AND signature_data LIKE 'data:image/%'";

$stmt = $pdo->query($sql);
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($tenants)) {
    echo "No tenant base64 signatures found in etat_lieux_locataires.\n\n";
} else {
    echo "Found " . count($tenants) . " tenant(s) with base64 signatures\n\n";
    
    $converted = 0;
    $failed = 0;
    
    foreach ($tenants as $tenant) {
        $id = $tenant['id'];
        $etatLieuxId = $tenant['etat_lieux_id'];
        $signatureData = $tenant['signature_data'];
        
        echo "Processing etat_lieux_locataire ID $id (etat_lieux ID: $etatLieuxId)...\n";
        
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
        $filename = "tenant_etat_lieux_{$etatLieuxId}_{$id}_migrated_" . time() . ".jpg";
        $filepath = $uploadsDir . '/' . $filename;
        
        // Save physical file
        if (file_put_contents($filepath, $imageData) === false) {
            echo "  ✗ Failed to save file: $filepath\n";
            $failed++;
            continue;
        }
        
        // Update database with relative path
        $relativePath = 'uploads/signatures/' . $filename;
        $updateSql = "UPDATE etat_lieux_locataires SET signature_data = ? WHERE id = ?";
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
        
        // Small delay to ensure unique timestamps
        usleep(10000); // 10ms
    }
    
    echo "\n--- Tenant Signatures Migration Summary ---\n";
    echo "Successfully converted: $converted\n";
    echo "Failed: $failed\n";
    echo "Total: " . count($tenants) . "\n\n";
}

// Part 2: Migrate landlord signatures from parametres table
echo "=== Part 2: Migrating Landlord Signatures ===\n\n";

$paramKeys = ['signature_societe_etat_lieux_image', 'signature_societe_image'];
$convertedParams = 0;
$failedParams = 0;

foreach ($paramKeys as $paramKey) {
    echo "Checking parameter: $paramKey...\n";
    
    $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = ?");
    $stmt->execute([$paramKey]);
    $signatureData = $stmt->fetchColumn();
    
    if (empty($signatureData)) {
        echo "  ℹ No value found\n";
        continue;
    }
    
    if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $signatureData)) {
        echo "  ℹ Already a file path: $signatureData\n";
        continue;
    }
    
    echo "  Converting base64 signature...\n";
    
    // Validate and extract base64 data
    if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,(.+)$/', $signatureData, $matches)) {
        echo "  ✗ Invalid data URI format\n";
        $failedParams++;
        continue;
    }
    
    $imageFormat = $matches[1];
    $base64Data = $matches[2];
    
    // Decode base64
    $imageData = base64_decode($base64Data, true);
    if ($imageData === false) {
        echo "  ✗ Failed to decode base64\n";
        $failedParams++;
        continue;
    }
    
    // Generate unique filename (always .jpg)
    $filename = "landlord_{$paramKey}_migrated_" . time() . ".jpg";
    $filepath = $uploadsDir . '/' . $filename;
    
    // Save physical file
    if (file_put_contents($filepath, $imageData) === false) {
        echo "  ✗ Failed to save file: $filepath\n";
        $failedParams++;
        continue;
    }
    
    // Update database with relative path
    $relativePath = 'uploads/signatures/' . $filename;
    $updateSql = "UPDATE parametres SET valeur = ? WHERE cle = ?";
    $updateStmt = $pdo->prepare($updateSql);
    
    if ($updateStmt->execute([$relativePath, $paramKey])) {
        echo "  ✓ Converted to physical file: $relativePath\n";
        $convertedParams++;
    } else {
        echo "  ✗ Failed to update database\n";
        // Clean up file if database update failed
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        $failedParams++;
    }
    
    // Small delay to ensure unique timestamps
    usleep(10000); // 10ms
}

echo "\n--- Landlord Signatures Migration Summary ---\n";
echo "Successfully converted: $convertedParams\n";
echo "Failed: $failedParams\n\n";

// Final summary
echo "=== Migration Complete ===\n";
$totalConverted = ($converted ?? 0) + $convertedParams;
$totalFailed = ($failed ?? 0) + $failedParams;
echo "Total signatures converted: $totalConverted\n";
echo "Total failures: $totalFailed\n";

if ($totalConverted > 0) {
    echo "\n✓ Migration successful! All base64 signatures have been converted to physical JPG files.\n";
    echo "  Signatures are now stored in: uploads/signatures/\n";
    echo "  TCPDF will use these files without borders.\n";
}
if ($totalFailed > 0) {
    echo "\n⚠ Some signatures failed to convert. Please check the errors above.\n";
}
