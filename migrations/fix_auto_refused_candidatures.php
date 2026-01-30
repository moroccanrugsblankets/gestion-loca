<?php
/**
 * Migration Script: Fix Automatically Refused Candidatures
 * 
 * This script fixes candidatures that were automatically refused upon creation
 * (e.g., salary < 3000€) but still have reponse_automatique = 'en_attente'.
 * 
 * This is different from fix_candidature_status_mismatch.php which handles
 * candidatures that were MANUALLY changed to 'accepte' or 'refuse'.
 * 
 * Run this script once after deploying the submit.php fix.
 * 
 * Usage: php migrations/fix_auto_refused_candidatures.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// Only allow CLI execution for safety
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

echo "=== Fix Automatically Refused Candidatures ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Find candidatures that were created with statut='refuse' but have reponse_automatique='en_attente'
    // These are candidatures that were automatically refused upon creation
    $stmt = $pdo->query("
        SELECT id, reference_unique, statut, reponse_automatique, motif_refus, created_at
        FROM candidatures
        WHERE statut = 'refuse'
        AND reponse_automatique = 'en_attente'
        AND motif_refus IS NOT NULL
        ORDER BY created_at ASC
    ");
    $mismatched = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($mismatched)) {
        echo "No mismatched candidatures found. Database is already clean.\n";
        exit(0);
    }
    
    echo "Found " . count($mismatched) . " candidatures that were automatically refused but not marked as processed:\n\n";
    
    foreach ($mismatched as $cand) {
        echo "  - ID: {$cand['id']}, Ref: {$cand['reference_unique']}\n";
        echo "    Created: {$cand['created_at']}\n";
        echo "    Status: statut={$cand['statut']}, reponse_automatique={$cand['reponse_automatique']}\n";
        echo "    Reason: " . substr($cand['motif_refus'], 0, 60) . (strlen($cand['motif_refus']) > 60 ? '...' : '') . "\n\n";
    }
    
    // Ask for confirmation
    echo "Do you want to mark these candidatures as automatically processed? (yes/no): ";
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
            SET reponse_automatique = 'refuse',
                date_reponse_auto = created_at
            WHERE id = ?
        ");
        $stmt->execute([$cand['id']]);
        
        echo "  ✓ Fixed candidature ID {$cand['id']} (Ref: {$cand['reference_unique']})\n";
        $fixed_count++;
    }
    
    $pdo->commit();
    
    echo "\n=== Migration Complete ===\n";
    echo "Fixed $fixed_count candidatures.\n";
    echo "These candidatures are now correctly marked as automatically processed (reponse_automatique='refuse').\n";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
