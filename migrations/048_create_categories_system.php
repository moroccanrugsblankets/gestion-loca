<?php
/**
 * Migration 048: Create Categories System
 * 
 * Creates database-driven category and subcategory system for inventory management
 * Migrates existing categories to database structure
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->beginTransaction();
    
    echo "=== Migration 048: Create Categories System ===\n\n";
    
    // Create categories table
    echo "Creating table: inventaire_categories...\n";
    $sql = "
    CREATE TABLE IF NOT EXISTS inventaire_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL,
        icone VARCHAR(50) DEFAULT 'bi-box',
        ordre INT DEFAULT 0,
        actif BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        UNIQUE KEY unique_nom (nom),
        INDEX idx_ordre (ordre),
        INDEX idx_actif (actif)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql);
    echo "✓ Table inventaire_categories créée\n\n";
    
    // Create subcategories table
    echo "Creating table: inventaire_sous_categories...\n";
    $sql = "
    CREATE TABLE IF NOT EXISTS inventaire_sous_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        categorie_id INT NOT NULL,
        nom VARCHAR(100) NOT NULL,
        ordre INT DEFAULT 0,
        actif BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (categorie_id) REFERENCES inventaire_categories(id) ON DELETE CASCADE,
        INDEX idx_categorie (categorie_id),
        INDEX idx_ordre (ordre),
        INDEX idx_actif (actif)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql);
    echo "✓ Table inventaire_sous_categories créée\n\n";
    
    // Populate with existing categories from manage-inventory-equipements.php
    echo "Populating default categories...\n";
    $defaultCategories = [
        ['nom' => 'Électroménager', 'icone' => 'bi-plugin', 'ordre' => 1],
        ['nom' => 'Mobilier', 'icone' => 'bi-house-door', 'ordre' => 2],
        ['nom' => 'Cuisine', 'icone' => 'bi-cup-hot', 'ordre' => 3],
        ['nom' => 'Salle de bain', 'icone' => 'bi-droplet', 'ordre' => 4],
        ['nom' => 'Linge de maison', 'icone' => 'bi-basket', 'ordre' => 5],
        ['nom' => 'Électronique', 'icone' => 'bi-tv', 'ordre' => 6],
        ['nom' => 'Autre', 'icone' => 'bi-box', 'ordre' => 7]
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO inventaire_categories (nom, icone, ordre)
        VALUES (?, ?, ?)
    ");
    
    foreach ($defaultCategories as $cat) {
        $stmt->execute([$cat['nom'], $cat['icone'], $cat['ordre']]);
        echo "  ✓ Added category: {$cat['nom']}\n";
    }
    
    // Add standard inventory categories from inventaire-standard-items.php
    echo "\nAdding standard inventory categories...\n";
    $standardCategories = [
        ['nom' => 'État des pièces', 'icone' => 'bi-house', 'ordre' => 10],
        ['nom' => 'Meubles', 'icone' => 'bi-house-door', 'ordre' => 20],
        ['nom' => 'Vaisselle', 'icone' => 'bi-cup-hot', 'ordre' => 30],
        ['nom' => 'Couverts', 'icone' => 'bi-knife', 'ordre' => 40],
        ['nom' => 'Ustensiles', 'icone' => 'bi-tools', 'ordre' => 50],
        ['nom' => 'Literie et linge', 'icone' => 'bi-basket', 'ordre' => 60],
        ['nom' => 'Compteurs et équipements', 'icone' => 'bi-speedometer2', 'ordre' => 70],
        ['nom' => 'Clés et badges', 'icone' => 'bi-key', 'ordre' => 80],
        ['nom' => 'Autres équipements', 'icone' => 'bi-box', 'ordre' => 90]
    ];
    
    foreach ($standardCategories as $cat) {
        try {
            $stmt->execute([$cat['nom'], $cat['icone'], $cat['ordre']]);
            echo "  ✓ Added category: {$cat['nom']}\n";
        } catch (PDOException $e) {
            // Category might already exist, skip
            if ($e->getCode() != 23000) { // Not a duplicate key error
                throw $e;
            }
        }
    }
    
    // Add subcategories for "État des pièces"
    echo "\nAdding subcategories for 'État des pièces'...\n";
    $stmt = $pdo->prepare("SELECT id FROM inventaire_categories WHERE nom = 'État des pièces'");
    $stmt->execute();
    $etatPiecesId = $stmt->fetchColumn();
    
    if ($etatPiecesId) {
        $subcategories = [
            'Entrée',
            'Séjour/salle à manger',
            'Cuisine',
            'Chambre 1',
            'Chambre 2',
            'Chambre 3',
            'Salle de bain 1',
            'Salle de bain 2',
            'WC',
            'Dégagement',
            'Balcon/Terrasse',
            'Cave/Garage',
            'Autres espaces'
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO inventaire_sous_categories (categorie_id, nom, ordre)
            VALUES (?, ?, ?)
        ");
        
        foreach ($subcategories as $index => $subcat) {
            $stmt->execute([$etatPiecesId, $subcat, $index + 1]);
            echo "  ✓ Added subcategory: {$subcat}\n";
        }
    }
    
    // Add foreign key to inventaire_equipements if needed
    echo "\nChecking inventaire_equipements table structure...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM inventaire_equipements LIKE 'categorie_id'");
    if ($stmt->rowCount() == 0) {
        echo "Adding categorie_id and sous_categorie_id columns...\n";
        
        // Add new columns
        $pdo->exec("
            ALTER TABLE inventaire_equipements
            ADD COLUMN categorie_id INT NULL AFTER categorie,
            ADD COLUMN sous_categorie_id INT NULL AFTER categorie_id
        ");
        
        // Migrate existing data
        echo "Migrating existing equipment to new category system...\n";
        $stmt = $pdo->query("SELECT DISTINCT categorie FROM inventaire_equipements WHERE categorie IS NOT NULL");
        $existingCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($existingCategories as $catName) {
            // Find matching category ID
            $stmt = $pdo->prepare("SELECT id FROM inventaire_categories WHERE nom = ?");
            $stmt->execute([$catName]);
            $catId = $stmt->fetchColumn();
            
            if ($catId) {
                // Update equipment with category ID
                $stmt = $pdo->prepare("
                    UPDATE inventaire_equipements 
                    SET categorie_id = ? 
                    WHERE categorie = ?
                ");
                $stmt->execute([$catId, $catName]);
                echo "  ✓ Migrated equipment in category: {$catName}\n";
            }
        }
        
        // Add foreign keys
        echo "Adding foreign key constraints...\n";
        $pdo->exec("
            ALTER TABLE inventaire_equipements
            ADD FOREIGN KEY fk_categorie (categorie_id) REFERENCES inventaire_categories(id) ON DELETE CASCADE,
            ADD FOREIGN KEY fk_sous_categorie (sous_categorie_id) REFERENCES inventaire_sous_categories(id) ON DELETE SET NULL
        ");
        echo "✓ Foreign keys added\n";
    } else {
        echo "✓ categorie_id column already exists\n";
    }
    
    $pdo->commit();
    echo "\n=== Migration 048 terminée avec succès ===\n";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n✗ Erreur lors de la migration: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
