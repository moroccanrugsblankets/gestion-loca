<?php
/**
 * Migration 029: Add cles_autre field to etats_lieux
 * 
 * Adds a field for "Autre" (other) keys in the key handover section
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->beginTransaction();
    
    echo "=== Migration 029: Add cles_autre field ===\n\n";
    
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'etats_lieux'");
    if ($stmt->rowCount() == 0) {
        throw new Exception("Table etats_lieux does not exist.");
    }
    
    // Get existing columns
    $stmt = $pdo->query("SHOW COLUMNS FROM etats_lieux");
    $existingColumns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[] = $row['Field'];
    }
    
    // Add cles_autre column if it doesn't exist
    if (!in_array('cles_autre', $existingColumns)) {
        $sql = "ALTER TABLE etats_lieux ADD COLUMN cles_autre INT DEFAULT 0 AFTER cles_boite_lettres";
        $pdo->exec($sql);
        echo "  ✓ Added column: cles_autre\n";
    } else {
        echo "  - Column already exists: cles_autre\n";
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo "\n✅ Migration 029 completed successfully\n";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n❌ Error during migration 029: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
