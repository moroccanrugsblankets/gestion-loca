<?php
/**
 * Migration 032: Add Bilan du Logement Fields
 * 
 * Adds fields for the "Bilan du logement" section which appears only in exit state forms.
 * This section includes:
 * - Dynamic table of degradations/equipment issues
 * - Upload of justificatifs (supporting documents)
 * - General comment field
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->beginTransaction();
    
    echo "=== Migration 032: Add Bilan du Logement Fields ===\n\n";
    
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
    
    // Define columns to add for Bilan du logement
    $columnsToAdd = [
        // Dynamic table data (array of rows with: poste, commentaires, valeur, montant_du)
        "ADD COLUMN bilan_logement_data JSON NULL AFTER depot_garantie_motif_retenue",
        
        // Uploaded justificatifs (array of file paths)
        "ADD COLUMN bilan_logement_justificatifs JSON NULL AFTER bilan_logement_data",
        
        // General comment
        "ADD COLUMN bilan_logement_commentaire TEXT NULL AFTER bilan_logement_justificatifs",
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
    
    echo "\n✅ Migration 032 completed successfully\n";
    echo "Added $addedCount new columns to etats_lieux table\n";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n❌ Error during migration 032: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
