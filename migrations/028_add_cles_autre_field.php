<?php
/**
 * Migration 028: Add cles_autre field to etats_lieux
 * 
 * This field was referenced in the code but missing from the database schema.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->beginTransaction();
    
    echo "=== Migration 028: Add cles_autre field ===\n\n";
    
    // Check if column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM etats_lieux LIKE 'cles_autre'");
    if ($stmt->rowCount() > 0) {
        echo "Column cles_autre already exists. Nothing to do.\n";
        $pdo->commit();
        exit(0);
    }
    
    // Add cles_autre column after cles_boite_lettres
    echo "Adding cles_autre column...\n";
    $pdo->exec("
        ALTER TABLE etats_lieux 
        ADD COLUMN cles_autre INT DEFAULT 0 AFTER cles_boite_lettres
    ");
    echo "  ✓ Column cles_autre added successfully\n";
    
    // Record migration
    $pdo->exec("INSERT INTO migrations (migration, executed_at) VALUES ('028_add_cles_autre_field', NOW())");
    
    $pdo->commit();
    
    echo "\n=== Migration 028 completed successfully ===\n";
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
