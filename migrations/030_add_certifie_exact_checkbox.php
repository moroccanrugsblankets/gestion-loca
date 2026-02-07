<?php
/**
 * Migration: Add certifie_exact checkbox field to locataires table
 * This checkbox is required and appears after the client signature
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

try {
    echo "Starting migration 030: Add certifie_exact checkbox...\n";
    
    // Add certifie_exact column to locataires table
    $pdo->exec("
        ALTER TABLE locataires 
        ADD COLUMN certifie_exact BOOLEAN DEFAULT FALSE AFTER mention_lu_approuve
    ");
    
    echo "✓ Added certifie_exact column to locataires table\n";
    echo "Migration 030 completed successfully!\n";
    
} catch (PDOException $e) {
    echo "✗ Migration 030 failed: " . $e->getMessage() . "\n";
    exit(1);
}
