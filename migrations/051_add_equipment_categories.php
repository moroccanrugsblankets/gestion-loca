<?php
/**
 * Migration 051: Add Missing Equipment Categories
 * 
 * Adds categories matching the standard equipment list to ensure
 * proper display of equipment in manage-inventory-equipements.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->beginTransaction();
    
    echo "=== Migration 051: Add Missing Equipment Categories ===\n\n";
    
    // Add categories that match inventaire-standard-items.php
    echo "Adding equipment categories...\n";
    $equipmentCategories = [
        ['nom' => 'Équipement 1 (Cuisine / Vaisselle)', 'icone' => 'bi-cup-hot', 'ordre' => 35],
        ['nom' => 'Équipement 2 (Linge / Entretien)', 'icone' => 'bi-heart', 'ordre' => 65]
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO inventaire_categories (nom, icone, ordre, actif)
        VALUES (?, ?, ?, TRUE)
        ON DUPLICATE KEY UPDATE icone = VALUES(icone), ordre = VALUES(ordre)
    ");
    
    foreach ($equipmentCategories as $cat) {
        try {
            $stmt->execute([$cat['nom'], $cat['icone'], $cat['ordre']]);
            echo "  ✓ Added/Updated category: {$cat['nom']}\n";
        } catch (PDOException $e) {
            // If duplicate key error, that's fine - category already exists
            if ($e->getCode() == 23000) {
                echo "  ℹ Category already exists: {$cat['nom']}\n";
            } else {
                throw $e;
            }
        }
    }
    
    $pdo->commit();
    echo "\n=== Migration 051 terminée avec succès ===\n";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n✗ Erreur lors de la migration: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
