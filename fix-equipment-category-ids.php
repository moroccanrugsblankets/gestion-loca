#!/usr/bin/env php
<?php
/**
 * Utility Script: Fix Existing Equipment Category IDs
 * 
 * This script updates existing equipment records that have categorie (name) but missing categorie_id.
 * This ensures backward compatibility with equipment added before the category system was implemented.
 * 
 * Usage: php fix-equipment-category-ids.php
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

echo "=== Fix Equipment Category IDs ===\n\n";

try {
    // Build category name to ID mapping
    $categoryIds = [];
    $stmt = $pdo->query("SELECT id, nom FROM inventaire_categories");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $categoryIds[$row['nom']] = $row['id'];
    }
    
    echo "Found " . count($categoryIds) . " categories.\n";
    echo "Categories: " . implode(', ', array_keys($categoryIds)) . "\n\n";
    
    // Find equipment with NULL categorie_id but valid categorie name
    $stmt = $pdo->query("
        SELECT id, logement_id, categorie, nom
        FROM inventaire_equipements
        WHERE categorie_id IS NULL AND categorie IS NOT NULL
    ");
    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($equipment)) {
        echo "No equipment records need fixing.\n";
        exit(0);
    }
    
    echo "Found " . count($equipment) . " equipment records to fix.\n\n";
    
    $pdo->beginTransaction();
    
    $updateStmt = $pdo->prepare("UPDATE inventaire_equipements SET categorie_id = ? WHERE id = ?");
    $fixed = 0;
    $notFound = 0;
    
    foreach ($equipment as $eq) {
        $categoryName = $eq['categorie'];
        
        if (isset($categoryIds[$categoryName])) {
            $updateStmt->execute([$categoryIds[$categoryName], $eq['id']]);
            echo "✓ Fixed equipment #{$eq['id']} (logement {$eq['logement_id']}): {$eq['nom']} -> category '{$categoryName}' (ID: {$categoryIds[$categoryName]})\n";
            $fixed++;
        } else {
            echo "✗ Cannot fix equipment #{$eq['id']} (logement {$eq['logement_id']}): {$eq['nom']} - category '{$categoryName}' not found in database\n";
            $notFound++;
        }
    }
    
    $pdo->commit();
    
    echo "\n=== Summary ===\n";
    echo "Total equipment records: " . count($equipment) . "\n";
    echo "Fixed: {$fixed}\n";
    echo "Not found in categories: {$notFound}\n";
    echo "\n=== Done ===\n";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n✗ Fatal Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
