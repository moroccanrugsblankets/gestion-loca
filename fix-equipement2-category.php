<?php
/**
 * Fix: Ensure "Équipement 2 (Linge / Entretien)" category is active
 * and properly linked to equipment items
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

try {
    $pdo->beginTransaction();
    
    echo "=== Fixing Équipement 2 (Linge / Entretien) Category ===\n\n";
    
    // Category configuration (matches migrations 048, 051, and 058)
    $categoryName = 'Équipement 2 (Linge / Entretien)';
    $defaultIcon = 'bi-heart';
    $defaultOrder = 65;
    
    // Step 1: Check if the category exists
    $stmt = $pdo->prepare("SELECT id, nom, actif, ordre FROM inventaire_categories WHERE nom = ?");
    $stmt->execute([$categoryName]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        echo "❌ Category '{$categoryName}' not found in inventaire_categories table!\n";
        echo "Creating the category...\n";
        
        $stmt = $pdo->prepare("
            INSERT INTO inventaire_categories (nom, icone, ordre, actif)
            VALUES (?, ?, ?, TRUE)
        ");
        $stmt->execute([$categoryName, $defaultIcon, $defaultOrder]);
        $categoryId = $pdo->lastInsertId();
        
        echo "✅ Category created with ID: {$categoryId}\n\n";
    } else {
        $categoryId = $category['id'];
        echo "✅ Category found with ID: {$categoryId}\n";
        echo "   - Name: {$category['nom']}\n";
        echo "   - Active: " . ($category['actif'] ? 'YES' : 'NO') . "\n";
        echo "   - Order: {$category['ordre']}\n\n";
        
        // Step 2: Ensure the category is active
        if (!$category['actif']) {
            echo "⚠️  Category is INACTIVE. Setting to ACTIVE...\n";
            $stmt = $pdo->prepare("UPDATE inventaire_categories SET actif = TRUE WHERE id = ?");
            $stmt->execute([$categoryId]);
            echo "✅ Category is now ACTIVE\n\n";
        }
    }
    
    // Step 3: Check equipment items with this category name but no/wrong categorie_id
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM inventaire_equipements 
        WHERE categorie = ? AND COALESCE(categorie_id, 0) != ?
    ");
    $stmt->execute([$categoryName, $categoryId]);
    $unlinkedCount = $stmt->fetchColumn();
    
    if ($unlinkedCount > 0) {
        echo "⚠️  Found {$unlinkedCount} equipment items with category name but no/wrong categorie_id\n";
        echo "   Updating equipment items to link to category ID {$categoryId}...\n";
        
        $stmt = $pdo->prepare("
            UPDATE inventaire_equipements 
            SET categorie_id = ? 
            WHERE categorie = ?
        ");
        $stmt->execute([$categoryId, $categoryName]);
        
        echo "✅ Updated {$unlinkedCount} equipment items\n\n";
    } else {
        echo "✅ All equipment items are properly linked to the category\n\n";
    }
    
    // Step 4: Verify the fix
    echo "=== Verification ===\n";
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM inventaire_equipements 
        WHERE categorie_id = ?
    ");
    $stmt->execute([$categoryId]);
    $linkedCount = $stmt->fetchColumn();
    
    echo "Total equipment items linked to '{$categoryName}': {$linkedCount}\n";
    
    if ($linkedCount > 0) {
        echo "\nSample items:\n";
        $stmt = $pdo->prepare("
            SELECT e.nom, e.quantite, l.reference as logement_ref
            FROM inventaire_equipements e
            LEFT JOIN logements l ON e.logement_id = l.id
            WHERE e.categorie_id = ?
            LIMIT 5
        ");
        $stmt->execute([$categoryId]);
        $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($samples as $sample) {
            echo "  - {$sample['nom']} (qty: {$sample['quantite']}) in logement {$sample['logement_ref']}\n";
        }
    }
    
    $pdo->commit();
    echo "\n=== Fix completed successfully! ===\n";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
