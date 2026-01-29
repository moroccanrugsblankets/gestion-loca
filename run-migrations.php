#!/usr/bin/env php
<?php
/**
 * Migration Runner
 * Applies database migrations in order
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

echo "=== Migration Runner ===\n\n";

// Get all migration files
$migrationDir = __DIR__ . '/migrations';
$files = glob($migrationDir . '/*.sql');
sort($files);

if (empty($files)) {
    echo "No migration files found.\n";
    exit(0);
}

echo "Found " . count($files) . " migration file(s).\n\n";

foreach ($files as $file) {
    $filename = basename($file);
    echo "Applying migration: $filename\n";
    
    try {
        $sql = file_get_contents($file);
        
        // Split by semicolon to handle multiple statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (empty($statement) || strpos($statement, '--') === 0) {
                continue;
            }
            
            $pdo->exec($statement);
        }
        
        echo "  ✓ Success\n";
    } catch (PDOException $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n";
        echo "  Continuing with next migration...\n";
    }
    
    echo "\n";
}

echo "=== Migration complete ===\n";
