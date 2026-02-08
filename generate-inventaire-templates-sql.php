<?php
/**
 * Generate SQL migration to populate inventaire templates
 * This script reads the default templates and creates a SQL file
 */

require_once __DIR__ . '/includes/inventaire-template.php';

// Get the templates
$inventaireEntreeTemplate = getDefaultInventaireTemplate();
$inventaireSortieTemplate = getDefaultInventaireSortieTemplate();

// Prepare SQL with proper escaping (using addslashes for SQL string literals)
$inventaireEntreeEscaped = addslashes($inventaireEntreeTemplate);
$inventaireSortieEscaped = addslashes($inventaireSortieTemplate);

// Create SQL migration content
$sql = <<<SQL
-- Migration: Populate inventaire templates
-- Date: 2026-02-08
-- Description: Populate inventaire_template_html and inventaire_sortie_template_html in parametres table
-- This addresses the issue: /admin-v2/inventaire-configuration.php - no editable templates

-- Update inventaire d'entrée template
UPDATE parametres 
SET valeur = '$inventaireEntreeEscaped'
WHERE cle = 'inventaire_template_html';

-- Update inventaire de sortie template  
UPDATE parametres 
SET valeur = '$inventaireSortieEscaped'
WHERE cle = 'inventaire_sortie_template_html';

-- If the rows don't exist, insert them
INSERT INTO parametres (cle, valeur, description)
SELECT 'inventaire_template_html', '$inventaireEntreeEscaped', 'Template HTML personnalisé pour l\\'inventaire d\\'entrée'
WHERE NOT EXISTS (SELECT 1 FROM parametres WHERE cle = 'inventaire_template_html');

INSERT INTO parametres (cle, valeur, description)
SELECT 'inventaire_sortie_template_html', '$inventaireSortieEscaped', 'Template HTML personnalisé pour l\\'inventaire de sortie'
WHERE NOT EXISTS (SELECT 1 FROM parametres WHERE cle = 'inventaire_sortie_template_html');

SQL;

// Write to migration file
$filename = __DIR__ . '/migrations/036_populate_inventaire_templates.sql';
file_put_contents($filename, $sql);

echo "✓ SQL migration file generated: $filename\n";
echo "  - Entry template: " . strlen($inventaireEntreeTemplate) . " characters\n";
echo "  - Exit template: " . strlen($inventaireSortieTemplate) . " characters\n";
echo "  - Total SQL file size: " . strlen($sql) . " bytes\n";
