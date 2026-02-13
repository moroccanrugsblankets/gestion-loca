<?php
/**
 * Migration 052: Add UNIQUE constraint to inventaire_locataires
 * 
 * Prevents duplicate tenant records for the same inventory
 * Cleans up any existing duplicates before adding the constraint
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->beginTransaction();
    
    echo "=== Migration 052: Add UNIQUE constraint to inventaire_locataires ===\n";
    
    // Step 1: Find and remove duplicate records
    echo "Checking for duplicate tenant records...\n";
    
    $sql = "
        SELECT inventaire_id, locataire_id, COUNT(*) as count, GROUP_CONCAT(id ORDER BY id ASC) as ids
        FROM inventaire_locataires
        WHERE locataire_id IS NOT NULL
        GROUP BY inventaire_id, locataire_id
        HAVING count > 1
    ";
    
    $stmt = $pdo->query($sql);
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($duplicates)) {
        echo "Found " . count($duplicates) . " duplicate tenant record groups\n";
        
        foreach ($duplicates as $dup) {
            $ids = explode(',', $dup['ids']);
            // Keep the first ID (oldest record), delete the rest
            $keepId = array_shift($ids);
            $deleteIds = $ids;
            
            if (!empty($deleteIds)) {
                echo "  - Keeping inventaire_locataires id=$keepId, deleting duplicates: " . implode(', ', $deleteIds) . "\n";
                $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
                $deleteStmt = $pdo->prepare("DELETE FROM inventaire_locataires WHERE id IN ($placeholders)");
                $deleteStmt->execute($deleteIds);
            }
        }
        echo "✓ Duplicate records cleaned up\n";
    } else {
        echo "✓ No duplicate records found\n";
    }
    
    // Step 2: Check if constraint already exists
    echo "Checking if UNIQUE constraint already exists...\n";
    $sql = "
        SELECT COUNT(*) as count
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'inventaire_locataires'
        AND CONSTRAINT_TYPE = 'UNIQUE'
        AND CONSTRAINT_NAME = 'unique_inventaire_locataire'
    ";
    $stmt = $pdo->query($sql);
    $constraintExists = $stmt->fetchColumn() > 0;
    
    if (!$constraintExists) {
        // Step 3: Add UNIQUE constraint
        echo "Adding UNIQUE constraint on (inventaire_id, locataire_id)...\n";
        $sql = "
            ALTER TABLE inventaire_locataires
            ADD UNIQUE KEY unique_inventaire_locataire (inventaire_id, locataire_id)
        ";
        $pdo->exec($sql);
        echo "✓ UNIQUE constraint added\n";
    } else {
        echo "ℹ UNIQUE constraint already exists\n";
    }
    
    $pdo->commit();
    echo "\n=== Migration 052 completed successfully ===\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n✗ Error during migration: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
