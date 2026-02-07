<?php
/**
 * Migration 031: Add certifie_exact checkbox to etat_lieux_locataires table
 * This checkbox appears after the tenant signature in état des lieux
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

try {
    echo "Starting migration 031: Add certifie_exact to etat_lieux_locataires...\n";
    
    // Check if column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM etat_lieux_locataires LIKE 'certifie_exact'");
    if ($stmt->rowCount() > 0) {
        echo "Column certifie_exact already exists in etat_lieux_locataires table\n";
        exit(0);
    }
    
    // Add certifie_exact column to etat_lieux_locataires table
    $pdo->exec("
        ALTER TABLE etat_lieux_locataires 
        ADD COLUMN certifie_exact BOOLEAN DEFAULT FALSE AFTER signature_ip
    ");
    
    echo "✓ Added certifie_exact column to etat_lieux_locataires table\n";
    echo "Migration 031 completed successfully!\n";
    
} catch (PDOException $e) {
    echo "✗ Migration 031 failed: " . $e->getMessage() . "\n";
    exit(1);
}
