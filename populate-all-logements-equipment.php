#!/usr/bin/env php
<?php
/**
 * Utility Script: Populate Equipment for All Logements
 * 
 * This script populates equipment for all logements that don't have any equipment defined.
 * Useful after running migration 051 to ensure all properties have the correct equipment.
 * 
 * Usage: php populate-all-logements-equipment.php
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/inventaire-standard-items.php';

echo "=== Populate Equipment for All Logements ===\n\n";

try {
    // Get all logements
    $stmt = $pdo->query("SELECT id, reference FROM logements ORDER BY id");
    $logements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($logements)) {
        echo "No logements found.\n";
        exit(0);
    }
    
    echo "Found " . count($logements) . " logements.\n\n";
    
    // Build category name to ID mapping
    $categoryIds = [];
    $stmt = $pdo->query("SELECT id, nom FROM inventaire_categories");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $categoryIds[$row['nom']] = $row['id'];
    }
    
    $totalProcessed = 0;
    $totalAdded = 0;
    $totalSkipped = 0;
    
    foreach ($logements as $logement) {
        $logement_id = $logement['id'];
        $reference = $logement['reference'];
        
        // Check if equipment already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventaire_equipements WHERE logement_id = ?");
        $stmt->execute([$logement_id]);
        $existingCount = $stmt->fetchColumn();
        
        if ($existingCount > 0) {
            echo "Logement #{$logement_id} ({$reference}): Already has {$existingCount} equipment items. Skipping.\n";
            $totalSkipped++;
            continue;
        }
        
        echo "Logement #{$logement_id} ({$reference}): Populating equipment...\n";
        
        $pdo->beginTransaction();
        
        try {
            // Get standardized equipment for this property
            $standardItems = getStandardInventaireItems($reference);
            
            $insertStmt = $pdo->prepare("
                INSERT INTO inventaire_equipements (logement_id, categorie_id, categorie, nom, quantite, ordre)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $ordre = 0;
            $itemsAdded = 0;
            
            foreach ($standardItems as $categoryName => $categoryItems) {
                $categorie_id = $categoryIds[$categoryName] ?? null;
                foreach ($categoryItems as $item) {
                    $ordre++;
                    $insertStmt->execute([
                        $logement_id,
                        $categorie_id,
                        $categoryName,
                        $item['nom'],
                        $item['quantite'] ?? 0,
                        $ordre
                    ]);
                    $itemsAdded++;
                }
            }
            
            $pdo->commit();
            
            echo "  ✓ Added {$itemsAdded} equipment items\n";
            $totalAdded += $itemsAdded;
            $totalProcessed++;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "  ✗ Error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n=== Summary ===\n";
    echo "Total logements: " . count($logements) . "\n";
    echo "Processed: {$totalProcessed}\n";
    echo "Skipped (already had equipment): {$totalSkipped}\n";
    echo "Total equipment items added: {$totalAdded}\n";
    echo "\n=== Done ===\n";
    
} catch (Exception $e) {
    echo "\n✗ Fatal Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
