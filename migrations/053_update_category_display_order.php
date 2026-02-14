<?php
/**
 * Migration 053: Update Category Display Order
 * 
 * Updates the display order of equipment categories in inventories:
 * 1. Équipement 1 (Cuisine / Vaisselle) - ordre = 10
 * 2. Meubles - ordre = 20 (unchanged)
 * 3. Électroménager - ordre = 30
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->beginTransaction();
    
    echo "=== Migration 053: Update Category Display Order ===\n\n";
    
    // Update Équipement 1 (Cuisine / Vaisselle) from ordre 35 to 10
    echo "Updating 'Équipement 1 (Cuisine / Vaisselle)' order from 35 to 10...\n";
    $stmt = $pdo->prepare("
        UPDATE inventaire_categories 
        SET ordre = 10 
        WHERE nom = 'Équipement 1 (Cuisine / Vaisselle)'
    ");
    $stmt->execute();
    $updated = $stmt->rowCount();
    if ($updated === 0) {
        echo "  ⚠ Warning: Category 'Équipement 1 (Cuisine / Vaisselle)' not found or already has ordre = 10\n";
    } else {
        echo "  ✓ Updated {$updated} row(s)\n";
    }
    
    // Meubles already has ordre = 20, no change needed
    echo "\n'Meubles' already has ordre = 20, no change needed\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventaire_categories WHERE nom = 'Meubles'");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    if ($count === 0) {
        echo "  ⚠ Warning: Category 'Meubles' not found in database\n";
    } else {
        echo "  ✓ Category 'Meubles' exists\n";
    }
    
    // Update Électroménager from ordre 1 to 30
    echo "\nUpdating 'Électroménager' order from 1 to 30...\n";
    $stmt = $pdo->prepare("
        UPDATE inventaire_categories 
        SET ordre = 30 
        WHERE nom = 'Électroménager'
    ");
    $stmt->execute();
    $updated = $stmt->rowCount();
    if ($updated === 0) {
        echo "  ⚠ Warning: Category 'Électroménager' not found or already has ordre = 30\n";
    } else {
        echo "  ✓ Updated {$updated} row(s)\n";
    }
    
    // Display final order for verification
    echo "\n=== Final Category Order ===\n";
    $stmt = $pdo->query("
        SELECT nom, ordre 
        FROM inventaire_categories 
        WHERE nom IN ('Équipement 1 (Cuisine / Vaisselle)', 'Meubles', 'Électroménager')
        ORDER BY ordre ASC
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - {$row['nom']}: ordre = {$row['ordre']}\n";
    }
    
    $pdo->commit();
    echo "\n=== Migration 053 terminée avec succès ===\n";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n✗ Erreur lors de la migration: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
