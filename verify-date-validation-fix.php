#!/usr/bin/env php
<?php
/**
 * Verification Script for date_validation Column Fix
 * 
 * This script checks if the migration 020 has been applied successfully
 * and if all required columns exist in the contrats table.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

echo "=== Verification Script - date_validation Column Fix ===\n\n";

try {
    // Check if migration tracking table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'migrations'");
    if ($stmt->rowCount() === 0) {
        echo "⚠️  WARNING: Migration tracking table doesn't exist.\n";
        echo "   Please run 'php run-migrations.php' first.\n\n";
    } else {
        echo "✓ Migration tracking table exists\n\n";
    }
    
    // Check if migration 020 has been executed
    $stmt = $pdo->prepare("SELECT * FROM migrations WHERE migration_file = ?");
    $stmt->execute(['020_add_contract_signature_and_workflow.sql']);
    $migration = $stmt->fetch();
    
    if ($migration) {
        echo "✓ Migration 020 has been executed\n";
        echo "  Executed at: " . $migration['executed_at'] . "\n\n";
    } else {
        echo "⚠️  WARNING: Migration 020 has NOT been executed yet\n";
        echo "   Please run 'php run-migrations.php' to apply it.\n\n";
    }
    
    // Check all required columns in contrats table
    echo "Checking required columns in 'contrats' table:\n";
    echo "------------------------------------------------\n";
    
    $requiredColumns = [
        'date_verification' => 'TIMESTAMP',
        'date_validation' => 'TIMESTAMP',
        'validation_notes' => 'TEXT',
        'motif_annulation' => 'TEXT',
        'verified_by' => 'INT',
        'validated_by' => 'INT'
    ];
    
    $allColumnsExist = true;
    
    foreach ($requiredColumns as $columnName => $expectedType) {
        $stmt = $pdo->prepare("
            SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_COMMENT
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'contrats' 
            AND COLUMN_NAME = ?
        ");
        $stmt->execute([$columnName]);
        $column = $stmt->fetch();
        
        if ($column) {
            $type = strtoupper($column['DATA_TYPE']);
            $expectedTypeUpper = strtoupper($expectedType);
            
            // Check if type matches (normalize for comparison)
            $typeMatch = (strpos($expectedTypeUpper, $type) !== false || strpos($type, $expectedTypeUpper) !== false);
            
            if ($typeMatch) {
                echo "  ✓ {$columnName}: {$column['DATA_TYPE']} (nullable: {$column['IS_NULLABLE']})";
                if (!empty($column['COLUMN_COMMENT'])) {
                    echo " - {$column['COLUMN_COMMENT']}";
                }
                echo "\n";
            } else {
                echo "  ⚠️  {$columnName}: Type mismatch - Expected {$expectedType}, got {$column['DATA_TYPE']}\n";
                $allColumnsExist = false;
            }
        } else {
            echo "  ✗ {$columnName}: MISSING\n";
            $allColumnsExist = false;
        }
    }
    
    echo "\n";
    
    // Check foreign keys
    echo "Checking foreign key constraints:\n";
    echo "------------------------------------------------\n";
    
    $requiredFKs = [
        'fk_contrats_verified_by' => 'verified_by -> administrateurs(id)',
        'fk_contrats_validated_by' => 'validated_by -> administrateurs(id)'
    ];
    
    $allFKsExist = true;
    
    foreach ($requiredFKs as $fkName => $description) {
        $stmt = $pdo->prepare("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.TABLE_CONSTRAINTS 
            WHERE CONSTRAINT_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'contrats' 
            AND CONSTRAINT_NAME = ?
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ");
        $stmt->execute([$fkName]);
        $fk = $stmt->fetch();
        
        if ($fk) {
            echo "  ✓ {$fkName}: {$description}\n";
        } else {
            echo "  ⚠️  {$fkName}: MISSING ({$description})\n";
            $allFKsExist = false;
        }
    }
    
    echo "\n";
    
    // Check statut ENUM values
    echo "Checking 'statut' column ENUM values:\n";
    echo "------------------------------------------------\n";
    
    $stmt = $pdo->query("
        SELECT COLUMN_TYPE 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'contrats' 
        AND COLUMN_NAME = 'statut'
    ");
    $column = $stmt->fetch();
    
    $requiredStatuses = ['en_verification', 'valide'];
    $allStatusesExist = true;
    
    if ($column) {
        $columnType = $column['COLUMN_TYPE'];
        echo "  Current ENUM values: {$columnType}\n\n";
        
        foreach ($requiredStatuses as $status) {
            if (strpos($columnType, "'{$status}'") !== false) {
                echo "  ✓ '{$status}' is available\n";
            } else {
                echo "  ✗ '{$status}' is MISSING\n";
                $allStatusesExist = false;
            }
        }
    } else {
        echo "  ✗ Could not retrieve statut column information\n";
        $allStatusesExist = false;
    }
    
    echo "\n";
    echo "=== Summary ===\n";
    echo "------------------------------------------------\n";
    
    if ($allColumnsExist && $allFKsExist && $allStatusesExist) {
        echo "✓ ALL CHECKS PASSED! The migration has been applied successfully.\n";
        echo "  The date_validation column and related fields are now available.\n";
        echo "  You can now use the contract validation feature in admin-v2/contrat-detail.php\n";
        exit(0);
    } else {
        echo "⚠️  SOME CHECKS FAILED!\n\n";
        
        if (!$allColumnsExist) {
            echo "  - Some required columns are missing\n";
        }
        if (!$allFKsExist) {
            echo "  - Some foreign key constraints are missing\n";
        }
        if (!$allStatusesExist) {
            echo "  - Some required status values are missing\n";
        }
        
        echo "\n";
        echo "RECOMMENDED ACTION:\n";
        echo "  Run 'php run-migrations.php' to apply the migration.\n";
        echo "  If the migration has already been run, there may be an issue with the migration file.\n";
        exit(1);
    }
    
} catch (PDOException $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "\n";
    echo "Please check:\n";
    echo "  1. Database connection settings in includes/config.php\n";
    echo "  2. Database exists and is accessible\n";
    echo "  3. User has proper permissions\n";
    exit(1);
}
