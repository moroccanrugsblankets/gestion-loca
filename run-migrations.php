#!/usr/bin/env php
<?php
/**
 * Migration Runner with tracking
 * Applies database migrations in order and tracks which ones have been executed
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

echo "=== Migration Runner ===\n\n";

try {
    // First, ensure the migrations tracking table exists
    $trackingTableSql = "
        CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration_file VARCHAR(255) UNIQUE NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_migration_file (migration_file)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($trackingTableSql);
    echo "✓ Migration tracking table ready\n\n";
} catch (PDOException $e) {
    echo "✗ Error creating migration tracking table: " . $e->getMessage() . "\n";
    exit(1);
}

// Get all migration files
$migrationDir = __DIR__ . '/migrations';
$files = glob($migrationDir . '/*.sql');
sort($files);

if (empty($files)) {
    echo "No migration files found.\n";
    exit(0);
}

echo "Found " . count($files) . " migration file(s).\n\n";

$executed = 0;
$skipped = 0;

foreach ($files as $file) {
    $filename = basename($file);
    
    // Skip the tracking table creation file since we handle it above
    if ($filename === '000_create_migrations_table.sql') {
        continue;
    }
    
    // Check if migration has already been executed
    try {
        $stmt = $pdo->prepare("SELECT id FROM migrations WHERE migration_file = ?");
        $stmt->execute([$filename]);
        
        if ($stmt->fetch()) {
            echo "⊘ Skipping (already executed): $filename\n";
            $skipped++;
            continue;
        }
    } catch (PDOException $e) {
        echo "✗ Error checking migration status: " . $e->getMessage() . "\n";
        continue;
    }
    
    echo "Applying migration: $filename\n";
    
    try {
        $sql = file_get_contents($file);
        
        // Start transaction for this migration
        $pdo->beginTransaction();
        
        // Split by semicolon to handle multiple statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            // Skip empty statements and pure comment lines
            if (empty($statement) || preg_match('/^\s*--/', $statement)) {
                continue;
            }
            
            $pdo->exec($statement);
        }
        
        // Record the migration as executed
        $stmt = $pdo->prepare("INSERT INTO migrations (migration_file) VALUES (?)");
        $stmt->execute([$filename]);
        
        // Commit transaction
        $pdo->commit();
        
        echo "  ✓ Success\n";
        $executed++;
    } catch (PDOException $e) {
        // Rollback on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        echo "  ✗ Error: " . $e->getMessage() . "\n";
        echo "  Migration failed - changes rolled back\n";
        echo "  Please fix the error and run migrations again.\n";
        exit(1);
    }
    
    echo "\n";
}

echo "=== Migration complete ===\n";
echo "Executed: $executed\n";
echo "Skipped: $skipped\n";

if ($executed > 0) {
    echo "\n✓ Database updated successfully!\n";
}
