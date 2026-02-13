<?php
/**
 * Migration 050: Add Équipement 2 (Linge / Entretien) Category
 * 
 * Adds the missing "Équipement 2 (Linge / Entretien)" category items
 * to all existing logements. This category includes:
 * - Matelas 1
 * - Oreillers 2
 * - Taies d'oreiller 2
 * - Draps du dessous 1
 * - Couette 1
 * - Housse de couette 1
 * - Alaise 1
 * - Plaid 1
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->beginTransaction();
    
    echo "=== Migration 050: Add Équipement 2 (Linge / Entretien) Category ===\n";
    
    // Define the new category items
    $equipement2Items = [
        ['nom' => 'Matelas', 'quantite' => 1],
        ['nom' => 'Oreillers', 'quantite' => 2],
        ['nom' => 'Taies d\'oreiller', 'quantite' => 2],
        ['nom' => 'Draps du dessous', 'quantite' => 1],
        ['nom' => 'Couette', 'quantite' => 1],
        ['nom' => 'Housse de couette', 'quantite' => 1],
        ['nom' => 'Alaise', 'quantite' => 1],
        ['nom' => 'Plaid', 'quantite' => 1],
    ];
    
    $categoryName = 'Équipement 2 (Linge / Entretien)';
    
    // Get all logements
    $stmt = $pdo->query("SELECT id, reference FROM logements ORDER BY id");
    $logements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($logements) . " logements to update\n\n";
    
    // Prepare insert statement
    $insertStmt = $pdo->prepare("
        INSERT INTO inventaire_equipements (logement_id, categorie, nom, quantite, ordre)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $totalAdded = 0;
    
    foreach ($logements as $logement) {
        $logement_id = $logement['id'];
        $logement_reference = $logement['reference'];
        
        echo "Processing Logement #{$logement_id} ({$logement_reference})...\n";
        
        // Check if any item from this category already exists
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) FROM inventaire_equipements 
            WHERE logement_id = ? AND categorie = ?
        ");
        $checkStmt->execute([$logement_id, $categoryName]);
        $existingCount = $checkStmt->fetchColumn();
        
        if ($existingCount > 0) {
            echo "  - Category already exists with {$existingCount} items, skipping...\n\n";
            continue;
        }
        
        // Get the maximum ordre value for this logement
        $maxOrdreStmt = $pdo->prepare("
            SELECT COALESCE(MAX(ordre), 0) FROM inventaire_equipements 
            WHERE logement_id = ?
        ");
        $maxOrdreStmt->execute([$logement_id]);
        $ordre = $maxOrdreStmt->fetchColumn();
        
        // Insert all items from Équipement 2 category
        $itemsAdded = 0;
        foreach ($equipement2Items as $item) {
            $ordre++;
            $insertStmt->execute([
                $logement_id,
                $categoryName,
                $item['nom'],
                $item['quantite'],
                $ordre
            ]);
            $itemsAdded++;
            $totalAdded++;
        }
        
        echo "  - Added {$itemsAdded} items from Équipement 2 category\n";
        echo "  ✓ Logement #{$logement_id} updated successfully\n\n";
    }
    
    $pdo->commit();
    
    echo "\n";
    echo "========================================\n";
    echo "Migration 050 completed successfully!\n";
    echo "Total items added: {$totalAdded}\n";
    echo "All logements now have Équipement 2 (Linge / Entretien) category\n";
    echo "========================================\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo "Migration 050 failed. Changes have been rolled back.\n";
    exit(1);
}
