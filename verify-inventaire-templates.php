<?php
/**
 * Verification Script for Inventaire Templates Migration
 * Checks if templates are properly populated in the database
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

echo "=== Inventaire Templates Verification ===\n\n";

try {
    // Check if parametres table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'parametres'");
    if ($stmt->rowCount() === 0) {
        echo "❌ Table 'parametres' not found!\n";
        echo "   Please run migration 002_create_parametres_table.sql first.\n";
        exit(1);
    }
    echo "✓ Table 'parametres' exists\n\n";
    
    // Check for template entries
    $stmt = $pdo->prepare("
        SELECT cle, 
               LENGTH(valeur) as length, 
               CASE 
                   WHEN valeur IS NULL THEN 'NULL'
                   WHEN valeur = '' THEN 'EMPTY'
                   ELSE 'POPULATED'
               END as status
        FROM parametres 
        WHERE cle IN ('inventaire_template_html', 'inventaire_sortie_template_html')
        ORDER BY cle
    ");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($results) === 0) {
        echo "❌ No inventaire template entries found in database!\n";
        echo "   Please run migration 034_create_inventaire_tables.php first.\n";
        exit(1);
    }
    
    echo "Template Status:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-40s | %-10s | %-15s\n", "Template Key", "Status", "Length (chars)");
    echo str_repeat("-", 80) . "\n";
    
    $allPopulated = true;
    foreach ($results as $row) {
        $status = $row['status'];
        $statusSymbol = ($status === 'POPULATED') ? '✓' : '❌';
        $length = ($status === 'POPULATED') ? $row['length'] : 'N/A';
        
        printf("%s %-38s | %-10s | %-15s\n", 
            $statusSymbol, 
            $row['cle'], 
            $status, 
            $length
        );
        
        if ($status !== 'POPULATED') {
            $allPopulated = false;
        }
    }
    echo str_repeat("-", 80) . "\n\n";
    
    // Expected lengths
    $expectedLengths = [
        'inventaire_template_html' => 5088,
        'inventaire_sortie_template_html' => 6205
    ];
    
    if ($allPopulated) {
        echo "✓ All templates are populated!\n\n";
        
        // Verify lengths are reasonable
        echo "Length Verification:\n";
        foreach ($results as $row) {
            $expected = $expectedLengths[$row['cle']] ?? 0;
            $actual = $row['length'];
            $diff = abs($actual - $expected);
            $diffPercent = $expected > 0 ? ($diff / $expected * 100) : 0;
            
            if ($diffPercent < 5) {  // Within 5% is acceptable
                echo "  ✓ {$row['cle']}: {$actual} chars (expected ~{$expected})\n";
            } else {
                echo "  ⚠ {$row['cle']}: {$actual} chars (expected ~{$expected}, diff: " . 
                     round($diffPercent, 1) . "%)\n";
            }
        }
        
        echo "\n✅ Templates verification PASSED!\n";
        echo "\nYou can now access the configuration page at:\n";
        echo "  → /admin-v2/inventaire-configuration.php\n\n";
        
    } else {
        echo "❌ Some templates are not populated!\n\n";
        echo "To fix this, run the migration:\n";
        echo "  mysql -u [user] -p [database] < migrations/036_populate_inventaire_templates.sql\n\n";
        exit(1);
    }
    
    // Sample a bit of each template to verify it's HTML
    echo "\nTemplate Content Sample:\n";
    echo str_repeat("-", 80) . "\n";
    
    foreach ($results as $row) {
        $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = ?");
        $stmt->execute([$row['cle']]);
        $content = $stmt->fetchColumn();
        
        if ($content) {
            // Get first 200 characters
            $sample = substr($content, 0, 200);
            echo "Template: {$row['cle']}\n";
            echo "Sample: " . htmlspecialchars($sample) . "...\n\n";
        }
    }
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
