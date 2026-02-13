<?php
/**
 * Migration 049: Update Inventory to New Simplified Structure
 * 
 * Updates all logements to use the new simplified inventory structure:
 * - No subcategories
 * - Property-specific equipment based on reference codes
 * - Auto-populated with default quantities
 * - MEUBLES category items set to "Bon État" by default
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/inventaire-standard-items.php';

try {
    $pdo->beginTransaction();
    
    echo "=== Migration 049: Update Inventory to New Simplified Structure ===\n";
    
    // Get all logements
    $stmt = $pdo->query("SELECT id, reference FROM logements ORDER BY id");
    $logements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($logements) . " logements to update\n\n";
    
    foreach ($logements as $logement) {
        $logement_id = $logement['id'];
        $logement_reference = $logement['reference'];
        
        echo "Processing Logement #{$logement_id} ({$logement_reference})...\n";
        
        // Delete existing equipment for this logement
        $stmt = $pdo->prepare("DELETE FROM inventaire_equipements WHERE logement_id = ?");
        $stmt->execute([$logement_id]);
        $deleted = $stmt->rowCount();
        echo "  - Deleted {$deleted} old equipment items\n";
        
        // Get new standardized equipment for this property
        $standardItems = getStandardInventaireItems($logement_reference);
        
        // Insert new equipment
        $insertStmt = $pdo->prepare("
            INSERT INTO inventaire_equipements (logement_id, categorie, nom, quantite, ordre)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $ordre = 0;
        $totalAdded = 0;
        
        foreach ($standardItems as $categoryName => $categoryItems) {
            foreach ($categoryItems as $item) {
                $ordre++;
                $insertStmt->execute([
                    $logement_id,
                    $categoryName,
                    $item['nom'],
                    $item['quantite'] ?? 0,
                    $ordre
                ]);
                $totalAdded++;
            }
        }
        
        echo "  - Added {$totalAdded} new equipment items\n";
        echo "  ✓ Logement #{$logement_id} updated successfully\n\n";
    }
    
    $pdo->commit();
    
    echo "\n";
    echo "========================================\n";
    echo "Migration 049 completed successfully!\n";
    echo "All logements updated with new inventory structure\n";
    echo "========================================\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo "Migration 049 failed. Changes have been rolled back.\n";
    exit(1);
}
