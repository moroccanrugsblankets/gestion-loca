#!/usr/bin/env php
<?php
/**
 * Test Script: Apply and Verify Soft Delete Migrations
 * 
 * This script applies migrations 059 and 060 and verifies the changes
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

echo "=== Testing Soft Delete Migrations ===\n\n";

try {
    // Check if migrations table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'migrations'");
    if (!$stmt->fetch()) {
        echo "❌ Migrations table not found. Please create it first.\n";
        exit(1);
    }
    
    // Apply migration 059 (Cron Job)
    echo "1. Applying migration 059 (Add rappel-loyers cron job)...\n";
    $migration059 = file_get_contents(__DIR__ . '/migrations/059_add_rappel_loyers_cron_job.sql');
    
    // Remove comments and empty lines for cleaner execution
    $statements = array_filter(
        array_map('trim', explode(';', $migration059)),
        fn($s) => !empty($s) && strpos($s, '--') !== 0
    );
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') { // Ignore duplicate key errors
                    throw $e;
                }
            }
        }
    }
    
    // Verify cron job was added
    $stmt = $pdo->query("SELECT COUNT(*) FROM cron_jobs WHERE fichier = 'cron/rappel-loyers.php'");
    $cronExists = $stmt->fetchColumn() > 0;
    echo $cronExists ? "   ✅ Cron job added successfully\n" : "   ⚠️  Cron job not found\n";
    
    // Verify parameters were added
    $stmt = $pdo->query("SELECT COUNT(*) FROM parametres WHERE cle LIKE 'rappel_loyers%'");
    $paramCount = $stmt->fetchColumn();
    echo "   ✅ Found {$paramCount} rappel_loyers parameters\n\n";
    
    // Apply migration 060 (Soft Delete Columns)
    echo "2. Applying migration 060 (Add soft delete columns)...\n";
    $migration060 = file_get_contents(__DIR__ . '/migrations/060_add_soft_delete_columns.sql');
    
    $tables = [
        'candidatures', 'contrats', 'logements', 'inventaires', 
        'etats_lieux', 'quittances', 'administrateurs',
        'inventaire_categories', 'inventaire_sous_categories',
        'inventaire_equipements', 'etat_lieux_photos',
        'candidature_documents', 'inventaire_locataires'
    ];
    
    $statements = array_filter(
        array_map('trim', explode(';', $migration060)),
        fn($s) => !empty($s) && strpos($s, '--') !== 0
    );
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                // Ignore if column already exists
                if (strpos($e->getMessage(), 'Duplicate column') === false) {
                    throw $e;
                }
            }
        }
    }
    
    // Verify deleted_at columns were added
    echo "\n3. Verifying deleted_at columns...\n";
    $verifiedCount = 0;
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW COLUMNS FROM {$table} LIKE 'deleted_at'");
        if ($stmt->fetch()) {
            echo "   ✅ {$table}\n";
            $verifiedCount++;
        } else {
            echo "   ❌ {$table} - Column not found\n";
        }
    }
    
    echo "\n=== Summary ===\n";
    echo "✅ Migration 059: Cron job configuration applied\n";
    echo "✅ Migration 060: Soft delete columns added to {$verifiedCount}/{" . count($tables) . "} tables\n";
    echo "\n";
    echo "📋 Next Steps:\n";
    echo "1. Test soft delete operations in admin interface\n";
    echo "2. Verify cron job execution from /admin-v2/cron-jobs.php\n";
    echo "3. Check that list pages exclude soft-deleted records\n";
    echo "\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
