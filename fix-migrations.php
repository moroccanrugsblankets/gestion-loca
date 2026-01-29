#!/usr/bin/env php
<?php
/**
 * Fix Migration Tracking Issues
 * 
 * This script fixes the issue where migrations are marked as executed
 * but the actual database tables don't exist. This can happen if:
 * - A migration failed but was still tracked
 * - Tables were dropped manually after migration
 * - Transaction rollback didn't clear the tracking record
 * 
 * The script will:
 * 1. Check which migrations are marked as executed
 * 2. Verify if the corresponding tables actually exist
 * 3. Remove tracking records for missing tables
 * 4. Re-run those migrations
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

echo "=== Migration Fix Tool ===\n\n";

try {
    // Ensure migrations table exists
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
    
    // Get all tracked migrations
    $stmt = $pdo->query("SELECT * FROM migrations ORDER BY executed_at");
    $trackedMigrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($trackedMigrations) . " tracked migration(s)\n\n";
    
    // Define mapping of migrations to their expected tables
    $migrationToTable = [
        '002_create_parametres_table.sql' => 'parametres',
        '003_create_email_templates_table.sql' => 'email_templates',
    ];
    
    $toRerun = [];
    
    // Check each tracked migration
    foreach ($trackedMigrations as $migration) {
        $filename = $migration['migration_file'];
        
        // Check if this migration creates a table we can verify
        if (isset($migrationToTable[$filename])) {
            $tableName = $migrationToTable[$filename];
            
            // Check if table exists
            $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
            $tableExists = $stmt->fetch();
            
            if (!$tableExists) {
                echo "✗ ISSUE: Migration '$filename' is tracked but table '$tableName' doesn't exist\n";
                echo "  Will remove tracking record and re-run migration\n\n";
                $toRerun[] = $filename;
            } else {
                echo "✓ Migration '$filename' - table '$tableName' exists\n";
            }
        }
    }
    
    if (empty($toRerun)) {
        echo "\n✓ All migrations are correctly applied - no fixes needed\n";
        exit(0);
    }
    
    echo "\n=== Fixing Issues ===\n\n";
    
    // Remove incorrect tracking records and re-run migrations
    foreach ($toRerun as $filename) {
        echo "Fixing: $filename\n";
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Remove the tracking record
            $stmt = $pdo->prepare("DELETE FROM migrations WHERE migration_file = ?");
            $stmt->execute([$filename]);
            echo "  ✓ Removed incorrect tracking record\n";
            
            // Re-run the migration
            $migrationPath = __DIR__ . '/migrations/' . $filename;
            
            if (!file_exists($migrationPath)) {
                throw new Exception("Migration file not found: $migrationPath");
            }
            
            $sql = file_get_contents($migrationPath);
            
            // Split SQL into statements (simple split on semicolon)
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                function($stmt) {
                    return !empty($stmt) && !preg_match('/^--/', $stmt);
                }
            );
            
            foreach ($statements as $statement) {
                if (!empty(trim($statement))) {
                    $pdo->exec($statement);
                }
            }
            
            echo "  ✓ Re-executed migration SQL\n";
            
            // Add tracking record
            $stmt = $pdo->prepare("INSERT INTO migrations (migration_file) VALUES (?)");
            $stmt->execute([$filename]);
            echo "  ✓ Added new tracking record\n";
            
            // Commit transaction
            $pdo->commit();
            echo "  ✓ Migration fixed successfully\n\n";
            
        } catch (Exception $e) {
            // Rollback on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo "  ✗ Error fixing migration: " . $e->getMessage() . "\n";
            echo "  Changes rolled back\n\n";
        }
    }
    
    echo "=== Fix Complete ===\n";
    echo "You should now be able to access the parametres.php page\n";
    
} catch (PDOException $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
    exit(1);
}
