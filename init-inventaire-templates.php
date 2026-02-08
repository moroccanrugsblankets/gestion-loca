<?php
/**
 * Initialization Script for Inventaire Templates
 * Populates the database with default HTML templates for inventaire d'entrée and inventaire de sortie
 * My Invest Immobilier
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/inventaire-template.php';

echo "=== Initialization of Inventaire Templates ===\n\n";

try {
    $pdo->beginTransaction();
    
    // Get default templates
    echo "Loading default templates...\n";
    $inventaireEntreeTemplate = getDefaultInventaireTemplate();
    $inventaireSortieTemplate = getDefaultInventaireSortieTemplate();
    
    echo "- Entry template loaded: " . strlen($inventaireEntreeTemplate) . " characters\n";
    echo "- Exit template loaded: " . strlen($inventaireSortieTemplate) . " characters\n\n";
    
    // Check if templates already exist
    echo "Checking existing templates in database...\n";
    $stmt = $pdo->prepare("SELECT cle, LENGTH(valeur) as length FROM parametres WHERE cle IN ('inventaire_template_html', 'inventaire_sortie_template_html')");
    $stmt->execute();
    $existing = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Update or insert inventaire_template_html (entry)
    if (isset($existing['inventaire_template_html'])) {
        if ($existing['inventaire_template_html'] === null || $existing['inventaire_template_html'] == 0) {
            echo "- inventaire_template_html exists but is empty, updating...\n";
            $stmt = $pdo->prepare("UPDATE parametres SET valeur = ? WHERE cle = 'inventaire_template_html'");
            $stmt->execute([$inventaireEntreeTemplate]);
            echo "  ✓ Entry template updated\n";
        } else {
            echo "- inventaire_template_html already populated (" . $existing['inventaire_template_html'] . " characters), skipping\n";
        }
    } else {
        echo "- inventaire_template_html not found, inserting...\n";
        $stmt = $pdo->prepare("INSERT INTO parametres (cle, valeur, description) VALUES ('inventaire_template_html', ?, 'Template HTML personnalisé pour l''inventaire d''entrée')");
        $stmt->execute([$inventaireEntreeTemplate]);
        echo "  ✓ Entry template inserted\n";
    }
    
    // Update or insert inventaire_sortie_template_html (exit)
    if (isset($existing['inventaire_sortie_template_html'])) {
        if ($existing['inventaire_sortie_template_html'] === null || $existing['inventaire_sortie_template_html'] == 0) {
            echo "- inventaire_sortie_template_html exists but is empty, updating...\n";
            $stmt = $pdo->prepare("UPDATE parametres SET valeur = ? WHERE cle = 'inventaire_sortie_template_html'");
            $stmt->execute([$inventaireSortieTemplate]);
            echo "  ✓ Exit template updated\n";
        } else {
            echo "- inventaire_sortie_template_html already populated (" . $existing['inventaire_sortie_template_html'] . " characters), skipping\n";
        }
    } else {
        echo "- inventaire_sortie_template_html not found, inserting...\n";
        $stmt = $pdo->prepare("INSERT INTO parametres (cle, valeur, description) VALUES ('inventaire_sortie_template_html', ?, 'Template HTML personnalisé pour l''inventaire de sortie')");
        $stmt->execute([$inventaireSortieTemplate]);
        echo "  ✓ Exit template inserted\n";
    }
    
    $pdo->commit();
    
    echo "\n=== Templates initialization completed successfully ===\n\n";
    
    // Verify
    echo "Verifying templates in database...\n";
    $stmt = $pdo->prepare("SELECT cle, LENGTH(valeur) as length FROM parametres WHERE cle IN ('inventaire_template_html', 'inventaire_sortie_template_html')");
    $stmt->execute();
    $final = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($final as $row) {
        echo "- {$row['cle']}: " . ($row['length'] ?? '0') . " characters\n";
    }
    
    echo "\nYou can now access the configuration page at: /admin-v2/inventaire-configuration.php\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n✗ Error during initialization: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
