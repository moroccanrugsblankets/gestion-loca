<?php
/**
 * Migration 037: Remove appartement field from logements table
 * 
 * This migration removes the appartement column from the logements table
 * as it is no longer needed in the application.
 */

require_once __DIR__ . '/../includes/db.php';

try {
    echo "Starting Migration 037: Remove appartement field...\n";
    
    // Drop appartement column from logements table
    $sql = "ALTER TABLE logements DROP COLUMN appartement";
    
    if ($conn->query($sql)) {
        echo "✓ Successfully removed appartement column from logements table\n";
    } else {
        throw new Exception("Error removing appartement column: " . $conn->error);
    }
    
    echo "Migration 037 completed successfully!\n";
    
} catch (Exception $e) {
    echo "ERROR in Migration 037: " . $e->getMessage() . "\n";
    exit(1);
}
?>
