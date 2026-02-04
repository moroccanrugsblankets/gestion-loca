<?php
/**
 * Migration 027: Enhance États des Lieux for Comprehensive Form
 * 
 * Adds additional fields needed for the full specification:
 * - Room-by-room detailed fields
 * - Conformity checkboxes for exit inspection
 * - Additional metadata fields
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->beginTransaction();
    
    echo "=== Migration 027: Enhance États des Lieux Comprehensive ===\n\n";
    
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'etats_lieux'");
    if ($stmt->rowCount() == 0) {
        throw new Exception("Table etats_lieux does not exist. Please run base schema first.");
    }
    
    // Get existing columns
    $stmt = $pdo->query("SHOW COLUMNS FROM etats_lieux");
    $existingColumns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[] = $row['Field'];
    }
    
    echo "Current columns in etats_lieux: " . count($existingColumns) . "\n";
    
    // Define columns to add for detailed room-by-room information
    $columnsToAdd = [
        // Detailed room fields (JSON for flexibility)
        "ADD COLUMN piece_principale_details JSON NULL AFTER piece_principale",
        "ADD COLUMN piece_principale_photos JSON NULL AFTER piece_principale_details",
        "ADD COLUMN coin_cuisine_details JSON NULL AFTER coin_cuisine",
        "ADD COLUMN coin_cuisine_photos JSON NULL AFTER coin_cuisine_details",
        "ADD COLUMN salle_eau_wc_details JSON NULL AFTER salle_eau_wc",
        "ADD COLUMN salle_eau_wc_photos JSON NULL AFTER salle_eau_wc_details",
        
        // General state photos
        "ADD COLUMN etat_general_photos JSON NULL AFTER etat_general",
        
        // For EXIT inspection - conformity checkboxes
        "ADD COLUMN etat_general_conforme ENUM('conforme', 'non_conforme', 'non_applicable') DEFAULT 'non_applicable' AFTER etat_general_photos",
        "ADD COLUMN degradations_constatees BOOLEAN DEFAULT FALSE AFTER etat_general_conforme",
        "ADD COLUMN degradations_details TEXT NULL AFTER degradations_constatees",
        
        // Locataire email (for sending PDF)
        "ADD COLUMN locataire_email VARCHAR(255) NULL AFTER bailleur_nom",
        "ADD COLUMN locataire_nom_complet VARCHAR(255) NULL AFTER locataire_email",
    ];
    
    // Add each column if it doesn't exist
    $addedCount = 0;
    foreach ($columnsToAdd as $columnDef) {
        // Extract column name from ADD COLUMN statement
        preg_match('/ADD COLUMN (\w+)/', $columnDef, $matches);
        $columnName = $matches[1];
        
        if (!in_array($columnName, $existingColumns)) {
            try {
                $sql = "ALTER TABLE etats_lieux $columnDef";
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
    
    // Commit transaction
    $pdo->commit();
    
    echo "\n✅ Migration 027 completed successfully\n";
    echo "Added $addedCount new columns to etats_lieux table\n";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n❌ Error during migration 027: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
