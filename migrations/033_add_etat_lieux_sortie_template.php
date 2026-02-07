<?php
/**
 * Migration 033: Add État des Lieux de Sortie HTML Template
 * 
 * This migration adds the HTML template for "État des lieux de sortie" (Move-Out Inventory)
 * to the parametres table. The template includes all fields specific to exit inspections,
 * such as deposit guarantee status, property assessment, and degradation details.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/etat-lieux-template.php';

try {
    $pdo->beginTransaction();
    
    echo "=== Migration 033: Add État des Lieux de Sortie HTML Template ===\n\n";
    
    // Check if parametres table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'parametres'");
    if ($stmt->rowCount() == 0) {
        throw new Exception("Table parametres does not exist. Please run migration 002 first.");
    }
    
    // Get the template from the function
    if (!function_exists('getDefaultExitEtatLieuxTemplate')) {
        throw new Exception("Function getDefaultExitEtatLieuxTemplate() not found in includes/etat-lieux-template.php");
    }
    
    $templateHtml = getDefaultExitEtatLieuxTemplate();
    
    echo "Template loaded: " . strlen($templateHtml) . " characters\n";
    
    // Check if the template already exists
    $stmt = $pdo->prepare("SELECT id FROM parametres WHERE cle = ?");
    $stmt->execute(['etat_lieux_sortie_template_html']);
    $existingTemplate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingTemplate) {
        echo "  - Template already exists with ID: " . $existingTemplate['id'] . "\n";
        echo "  - Updating existing template...\n";
        
        $stmt = $pdo->prepare("
            UPDATE parametres 
            SET valeur = ?,
                type = 'string',
                description = 'Template HTML pour l\'état des lieux de sortie (exit inspection)',
                groupe = 'templates',
                updated_at = NOW()
            WHERE cle = 'etat_lieux_sortie_template_html'
        ");
        $stmt->execute([$templateHtml]);
        echo "  ✓ Template updated successfully\n";
    } else {
        echo "  - Creating new template entry...\n";
        
        $stmt = $pdo->prepare("
            INSERT INTO parametres (cle, valeur, type, description, groupe)
            VALUES (?, ?, 'string', ?, 'templates')
        ");
        $stmt->execute([
            'etat_lieux_sortie_template_html',
            $templateHtml,
            'Template HTML pour l\'état des lieux de sortie (exit inspection)'
        ]);
        echo "  ✓ Template created successfully with ID: " . $pdo->lastInsertId() . "\n";
    }
    
    // Verify the template was saved correctly
    $stmt = $pdo->prepare("SELECT cle, type, description, groupe, LENGTH(valeur) as template_length FROM parametres WHERE cle = ?");
    $stmt->execute(['etat_lieux_sortie_template_html']);
    $savedTemplate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$savedTemplate) {
        throw new Exception("Failed to verify saved template");
    }
    
    echo "\nTemplate verification:\n";
    echo "  - Key: " . $savedTemplate['cle'] . "\n";
    echo "  - Type: " . $savedTemplate['type'] . "\n";
    echo "  - Group: " . $savedTemplate['groupe'] . "\n";
    echo "  - Description: " . $savedTemplate['description'] . "\n";
    echo "  - Length: " . $savedTemplate['template_length'] . " characters\n";
    
    // Commit transaction
    $pdo->commit();
    
    echo "\n✅ Migration 033 completed successfully\n";
    echo "État des lieux de sortie HTML template has been added to the database.\n";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n❌ Error during migration 033: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
