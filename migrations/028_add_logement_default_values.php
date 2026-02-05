<?php
/**
 * Migration 028: Add Default Values for Logements
 * 
 * Adds default values for keys and equipment that will be pre-filled
 * in entry inventory forms.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->beginTransaction();
    
    echo "=== Migration 028: Add Logement Default Values ===\n\n";
    
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'logements'");
    if ($stmt->rowCount() == 0) {
        throw new Exception("Table logements does not exist.");
    }
    
    // Get existing columns
    $stmt = $pdo->query("SHOW COLUMNS FROM logements");
    $existingColumns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[] = $row['Field'];
    }
    
    echo "Current columns in logements: " . count($existingColumns) . "\n";
    
    // Define columns to add for default values
    $columnsToAdd = [
        "ADD COLUMN default_cles_appartement INT DEFAULT 2 AFTER depot_garantie",
        "ADD COLUMN default_cles_boite_lettres INT DEFAULT 1 AFTER default_cles_appartement",
        "ADD COLUMN default_equipements JSON NULL AFTER default_cles_boite_lettres",
        "ADD COLUMN default_etat_piece_principale TEXT NULL AFTER default_equipements",
        "ADD COLUMN default_etat_cuisine TEXT NULL AFTER default_etat_piece_principale",
        "ADD COLUMN default_etat_salle_eau TEXT NULL AFTER default_etat_cuisine",
    ];
    
    // Add each column if it doesn't exist
    $addedCount = 0;
    foreach ($columnsToAdd as $columnDef) {
        preg_match('/ADD COLUMN (\w+)/', $columnDef, $matches);
        $columnName = $matches[1];
        
        if (!in_array($columnName, $existingColumns)) {
            try {
                $sql = "ALTER TABLE logements $columnDef";
                $pdo->exec($sql);
                echo "  ✓ Added column: $columnName\n";
                $addedCount++;
            } catch (PDOException $e) {
                echo "  ⚠ Could not add $columnName: " . $e->getMessage() . "\n";
            }
        } else {
            echo "  - Column already exists: $columnName\n";
        }
    }
    
    // Update existing logements with default values if they don't have them
    echo "\nSetting default values for existing logements...\n";
    $pdo->exec("
        UPDATE logements 
        SET default_cles_appartement = 2,
            default_cles_boite_lettres = 1
        WHERE default_cles_appartement IS NULL OR default_cles_boite_lettres IS NULL
    ");
    echo "  ✓ Default key values set\n";
    
    // Commit transaction
    $pdo->commit();
    
    echo "\n✅ Migration 028 completed successfully\n";
    echo "Added $addedCount new columns to logements table\n";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n❌ Error during migration 028: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
