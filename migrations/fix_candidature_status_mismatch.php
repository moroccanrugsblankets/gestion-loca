<?php
/**
 * Migration Script: Fix Candidature Status Mismatch
 * 
 * This script fixes candidatures that have been manually marked as 'accepte' or 'refuse'
 * but still have reponse_automatique = 'en_attente'. This mismatch caused them to not
 * appear correctly in the cron jobs page.
 * 
 * Run this script once after deploying the fix to clean up existing data.
 * 
 * Usage: php migrations/fix_candidature_status_mismatch.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// Only allow CLI execution for safety
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

echo "=== Candidature Status Mismatch Fix ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Find all candidatures with mismatched status
    $stmt = $pdo->query("
        SELECT id, reference_unique, statut, reponse_automatique
        FROM candidatures
        WHERE (statut = 'refuse' OR statut = 'accepte')
        AND reponse_automatique = 'en_attente'
    ");
    $mismatched = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($mismatched)) {
        echo "No mismatched candidatures found. Database is already clean.\n";
        exit(0);
    }
    
    echo "Found " . count($mismatched) . " candidatures with mismatched status:\n\n";
    
    foreach ($mismatched as $cand) {
        echo "  - ID: {$cand['id']}, Ref: {$cand['reference_unique']}\n";
        echo "    Current: statut={$cand['statut']}, reponse_automatique={$cand['reponse_automatique']}\n";
    }
    
    echo "\n";
    
    // Ask for confirmation (we're always in CLI mode)
    echo "Do you want to fix these records? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    $confirmation = trim(strtolower($line));
    fclose($handle);
    
    if ($confirmation !== 'yes' && $confirmation !== 'y') {
        echo "Operation cancelled.\n";
        exit(0);
    }
    
    echo "\nFixing records...\n";
    
    $pdo->beginTransaction();
    
    $fixed_count = 0;
    foreach ($mismatched as $cand) {
        $stmt = $pdo->prepare("
            UPDATE candidatures 
            SET reponse_automatique = ?, 
                date_reponse_auto = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$cand['statut'], $cand['id']]);
        
        echo "  ✓ Fixed candidature ID {$cand['id']} (Ref: {$cand['reference_unique']})\n";
        $fixed_count++;
    }
    
    $pdo->commit();
    
    echo "\n=== Migration Complete ===\n";
    echo "Fixed $fixed_count candidatures.\n";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
