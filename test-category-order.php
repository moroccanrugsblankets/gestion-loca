<?php
/**
 * Test script to verify category ordering
 * Shows categories before and after migration
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

echo "=== Category Order Test ===\n\n";

// Show current order
echo "Current category order:\n";
echo str_repeat("=", 80) . "\n";

$stmt = $pdo->query("
    SELECT id, nom, ordre, icone 
    FROM inventaire_categories 
    WHERE nom IN ('Équipement 1 (Cuisine / Vaisselle)', 'Meubles', 'Électroménager')
    ORDER BY ordre ASC, nom ASC
");

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($categories)) {
    echo "⚠ Categories not found in database. Migration 048 may not have run.\n\n";
} else {
    foreach ($categories as $row) {
        printf("%-3d | Ordre: %-3d | %s\n", 
            $row['id'], 
            $row['ordre'], 
            $row['nom']
        );
    }
    
    echo "\n";
    echo "Expected order after migration:\n";
    echo str_repeat("=", 80) . "\n";
    echo "  1. Équipement 1 (Cuisine / Vaisselle) - ordre = 10\n";
    echo "  2. Meubles - ordre = 20\n";
    echo "  3. Électroménager - ordre = 30\n";
    
    // Check if migration is needed
    $needsMigration = false;
    foreach ($categories as $cat) {
        if ($cat['nom'] === 'Équipement 1 (Cuisine / Vaisselle)' && $cat['ordre'] != 10) {
            $needsMigration = true;
        }
        if ($cat['nom'] === 'Électroménager' && $cat['ordre'] != 30) {
            $needsMigration = true;
        }
    }
    
    echo "\n";
    if ($needsMigration) {
        echo "⚠ Migration 053 needs to be run to update the order.\n";
        echo "Run: php migrations/053_update_category_display_order.php\n";
    } else {
        echo "✓ Categories are already in the correct order!\n";
    }
}

echo "\n=== Test Complete ===\n";
