<?php
/**
 * Script to apply database migration for document types
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

echo "=== Applying database migration for document types ===\n\n";

try {
    // Read migration file
    $migrationFile = __DIR__ . '/migrations/update_document_types.sql';
    
    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: $migrationFile");
    }
    
    echo "Reading migration file...\n";
    $sql = file_get_contents($migrationFile);
    
    // Extract the ALTER TABLE statement (skip comments)
    $lines = explode("\n", $sql);
    $sqlStatements = [];
    $currentStatement = '';
    
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip empty lines and comments
        if (empty($line) || strpos($line, '--') === 0) {
            continue;
        }
        $currentStatement .= $line . ' ';
        if (substr($line, -1) === ';') {
            $sqlStatements[] = trim($currentStatement);
            $currentStatement = '';
        }
    }
    
    echo "Found " . count($sqlStatements) . " SQL statement(s) to execute\n\n";
    
    // Execute statements
    foreach ($sqlStatements as $statement) {
        echo "Executing: " . substr($statement, 0, 100) . "...\n";
        try {
            $pdo->exec($statement);
            echo "✓ Success\n\n";
        } catch (PDOException $e) {
            // Check if error is about duplicate column modification
            if (strpos($e->getMessage(), 'already exists') !== false || 
                strpos($e->getMessage(), 'Duplicate') !== false) {
                echo "⚠ Warning: Migration already applied or column already exists\n\n";
            } else {
                throw $e;
            }
        }
    }
    
    echo "=== Migration completed successfully ===\n\n";
    
    // Verify the migration
    echo "Verifying migration...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM candidature_documents WHERE Field = 'type_document'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($column) {
        echo "✓ Column 'type_document' definition:\n";
        echo "  Type: " . $column['Type'] . "\n";
        echo "\n";
        
        // Check if new values are present
        $hasNewTypes = (
            strpos($column['Type'], 'bulletins_salaire') !== false &&
            strpos($column['Type'], 'contrat_travail') !== false &&
            strpos($column['Type'], 'avis_imposition') !== false &&
            strpos($column['Type'], 'quittances_loyer') !== false
        );
        
        if ($hasNewTypes) {
            echo "✓ All new document types are present in ENUM\n";
        } else {
            echo "⚠ Warning: Some new document types may be missing from ENUM\n";
        }
    } else {
        echo "✗ Error: Could not verify column\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
