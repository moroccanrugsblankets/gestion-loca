<?php
/**
 * Migration 058: Fix Équipement 2 (Linge / Entretien) Category Display
 * 
 * Ensures the category is active and all equipment items are properly linked
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->beginTransaction();
    
    echo "=== Migration 058: Fix Équipement 2 (Linge / Entretien) Category ===\n\n";
    
    $categoryName = 'Équipement 2 (Linge / Entretien)';
    
    // Step 1: Ensure category exists and is active
    echo "Step 1: Checking category existence and status...\n";
    $stmt = $pdo->prepare("SELECT id, actif FROM inventaire_categories WHERE nom = ?");
    $stmt->execute([$categoryName]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        echo "  Creating category '{$categoryName}'...\n";
        $stmt = $pdo->prepare("
            INSERT INTO inventaire_categories (nom, icone, ordre, actif)
            VALUES (?, 'bi-heart', 65, TRUE)
        ");
        $stmt->execute([$categoryName]);
        $categoryId = $pdo->lastInsertId();
        echo "  ✓ Category created with ID: {$categoryId}\n";
    } else {
        $categoryId = $category['id'];
        echo "  ✓ Category exists with ID: {$categoryId}\n";
        
        if (!$category['actif']) {
            echo "  Setting category to ACTIVE...\n";
            $stmt = $pdo->prepare("UPDATE inventaire_categories SET actif = TRUE WHERE id = ?");
            $stmt->execute([$categoryId]);
            echo "  ✓ Category is now active\n";
        } else {
            echo "  ✓ Category is already active\n";
        }
    }
    
    // Step 2: Link equipment items that have the category name but no categorie_id
    echo "\nStep 2: Linking equipment items to category...\n";
    $stmt = $pdo->prepare("
        UPDATE inventaire_equipements 
        SET categorie_id = ? 
        WHERE categorie = ? AND (categorie_id IS NULL OR categorie_id != ?)
    ");
    $stmt->execute([$categoryId, $categoryName, $categoryId]);
    $updated = $stmt->rowCount();
    
    if ($updated > 0) {
        echo "  ✓ Linked {$updated} equipment items to category ID {$categoryId}\n";
    } else {
        echo "  ✓ All equipment items already properly linked\n";
    }
    
    // Step 3: Verify equipment exists for this category
    echo "\nStep 3: Verifying equipment items...\n";
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM inventaire_equipements WHERE categorie_id = ?
    ");
    $stmt->execute([$categoryId]);
    $count = $stmt->fetchColumn();
    echo "  ✓ Found {$count} equipment items with this category\n";
    
    $pdo->commit();
    echo "\n=== Migration 058 completed successfully ===\n";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
