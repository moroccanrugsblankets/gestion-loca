#!/usr/bin/env php
<?php
/**
 * Verification Script: Test Equipment Management Fix
 * 
 * This script verifies that the equipment management fix is working correctly.
 * Run this after deployment to ensure everything is set up properly.
 * 
 * Usage: php verify-equipment-fix.php
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

echo "=== Equipment Management Fix Verification ===\n\n";

$errors = [];
$warnings = [];
$passed = 0;

// Test 1: Check if required categories exist
echo "Test 1: Checking required categories...\n";
$requiredCategories = [
    'Meubles',
    'Électroménager',
    'Équipement 1 (Cuisine / Vaisselle)',
    'Équipement 2 (Linge / Entretien)'
];

$stmt = $pdo->query("SELECT nom FROM inventaire_categories WHERE actif = TRUE");
$existingCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($requiredCategories as $catName) {
    if (in_array($catName, $existingCategories)) {
        echo "  ✓ Category exists: {$catName}\n";
        $passed++;
    } else {
        echo "  ✗ MISSING category: {$catName}\n";
        $errors[] = "Missing category: {$catName}. Run migration 051.";
    }
}

// Test 2: Check for equipment with NULL category_id
echo "\nTest 2: Checking for equipment with NULL category_id...\n";
$stmt = $pdo->query("
    SELECT COUNT(*) 
    FROM inventaire_equipements 
    WHERE categorie_id IS NULL AND categorie IS NOT NULL
");
$nullCategoryCount = $stmt->fetchColumn();

if ($nullCategoryCount == 0) {
    echo "  ✓ No equipment with NULL category_id\n";
    $passed++;
} else {
    echo "  ⚠ Found {$nullCategoryCount} equipment records with NULL category_id\n";
    $warnings[] = "{$nullCategoryCount} equipment records need category_id. Run: php fix-equipment-category-ids.php";
}

// Test 3: Check equipment counts per logement
echo "\nTest 3: Checking equipment distribution...\n";
$stmt = $pdo->query("
    SELECT l.id, l.reference, COUNT(e.id) as equipment_count
    FROM logements l
    LEFT JOIN inventaire_equipements e ON l.id = e.logement_id
    GROUP BY l.id
    ORDER BY l.id
");
$logements = $stmt->fetchAll(PDO::FETCH_ASSOC);

$emptyCount = 0;
foreach ($logements as $logement) {
    if ($logement['equipment_count'] == 0) {
        echo "  ⚠ Logement #{$logement['id']} ({$logement['reference']}): No equipment\n";
        $emptyCount++;
    } else {
        echo "  ✓ Logement #{$logement['id']} ({$logement['reference']}): {$logement['equipment_count']} items\n";
    }
}

if ($emptyCount > 0) {
    $warnings[] = "{$emptyCount} logement(s) without equipment. Run: php populate-all-logements-equipment.php";
} else {
    echo "  ✓ All logements have equipment\n";
    $passed++;
}

// Test 4: Verify category icons are unique
echo "\nTest 4: Checking for duplicate category icons...\n";
$stmt = $pdo->query("
    SELECT icone, GROUP_CONCAT(nom SEPARATOR ', ') as categories, COUNT(*) as count
    FROM inventaire_categories
    WHERE actif = TRUE
    GROUP BY icone
    HAVING count > 1
");
$duplicateIcons = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($duplicateIcons)) {
    echo "  ✓ All category icons are unique\n";
    $passed++;
} else {
    foreach ($duplicateIcons as $dup) {
        echo "  ⚠ Icon '{$dup['icone']}' used by: {$dup['categories']}\n";
        $warnings[] = "Icon '{$dup['icone']}' is shared by multiple categories";
    }
}

// Test 5: Check if standard items file exists
echo "\nTest 5: Checking standard items configuration...\n";
$standardItemsPath = __DIR__ . '/includes/inventaire-standard-items.php';
if (file_exists($standardItemsPath)) {
    echo "  ✓ Standard items file exists\n";
    require_once $standardItemsPath;
    
    // Try to get items for a test reference
    try {
        $items = getStandardInventaireItems('RC-01');
        $totalItems = 0;
        foreach ($items as $categoryItems) {
            $totalItems += count($categoryItems);
        }
        echo "  ✓ Standard items function works ({$totalItems} items for RC-01)\n";
        $passed += 2;
    } catch (Exception $e) {
        echo "  ✗ Error calling getStandardInventaireItems(): " . $e->getMessage() . "\n";
        $errors[] = "Standard items function error";
    }
} else {
    echo "  ✗ Standard items file not found\n";
    $errors[] = "Missing file: includes/inventaire-standard-items.php";
}

// Summary
echo "\n=== Verification Summary ===\n";
echo "Tests passed: {$passed}\n";
echo "Errors: " . count($errors) . "\n";
echo "Warnings: " . count($warnings) . "\n\n";

if (!empty($errors)) {
    echo "❌ ERRORS FOUND:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "⚠️  WARNINGS:\n";
    foreach ($warnings as $warning) {
        echo "  - {$warning}\n";
    }
    echo "\n";
}

if (empty($errors)) {
    if (empty($warnings)) {
        echo "✅ ALL CHECKS PASSED - System is ready!\n";
        exit(0);
    } else {
        echo "⚠️  System functional but has warnings. Address them for optimal operation.\n";
        exit(0);
    }
} else {
    echo "❌ CRITICAL ISSUES FOUND - Please fix errors before using the system.\n";
    exit(1);
}
