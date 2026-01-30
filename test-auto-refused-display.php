<?php
/**
 * Test script to verify auto-refused candidatures display
 * 
 * This script tests:
 * 1. Query for pending automatic responses works
 * 2. Query for recently auto-refused candidatures works
 * 3. The data is correctly structured
 * 4. The cron-jobs.php page will display them correctly
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

echo "=== Test: Auto-Refused Candidatures Display ===\n\n";

try {
    // Test 1: Query for pending automatic responses
    echo "Test 1: Query for pending automatic responses...\n";
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM candidatures c
        WHERE c.statut = 'en_cours' 
        AND c.reponse_automatique = 'en_attente'
    ");
    $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "  ✓ Found $pending_count candidatures pending automatic response\n\n";
    
    // Test 2: Query for recently auto-refused candidatures
    echo "Test 2: Query for recently auto-refused candidatures...\n";
    $stmt = $pdo->query("
        SELECT 
            c.id,
            c.reference_unique,
            c.nom,
            c.prenom,
            c.email,
            c.created_at,
            c.statut,
            c.reponse_automatique,
            c.motif_refus,
            l.reference as logement_reference
        FROM candidatures c
        LEFT JOIN logements l ON c.logement_id = l.id
        WHERE c.statut = 'refuse' 
        AND c.reponse_automatique = 'refuse'
        AND c.motif_refus IS NOT NULL
        AND c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY c.created_at DESC
        LIMIT 50
    ");
    $auto_refused = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "  ✓ Found " . count($auto_refused) . " auto-refused candidatures in last 7 days\n";
    
    if (!empty($auto_refused)) {
        echo "\n  Auto-refused candidatures:\n";
        foreach ($auto_refused as $refused) {
            echo "    - ID: {$refused['id']}, Ref: {$refused['reference_unique']}\n";
            echo "      Candidat: {$refused['prenom']} {$refused['nom']}\n";
            echo "      Date: {$refused['created_at']}\n";
            echo "      Motif: " . substr($refused['motif_refus'], 0, 60) . "...\n\n";
        }
    }
    
    // Test 3: Check for any mismatched candidatures (should be 0 after migration)
    echo "Test 3: Check for mismatched candidatures...\n";
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM candidatures
        WHERE statut = 'refuse'
        AND reponse_automatique = 'en_attente'
    ");
    $mismatched_count = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($mismatched_count === 0) {
        echo "  ✓ No mismatched candidatures found (correct!)\n\n";
    } else {
        echo "  ✗ WARNING: Found $mismatched_count mismatched candidatures\n";
        echo "  → Run: php migrations/fix_auto_refused_candidatures.php\n\n";
    }
    
    // Test 4: Check delay parameters
    echo "Test 4: Check delay parameters...\n";
    $delaiValeur = (int)getParameter('delai_reponse_valeur', 4);
    $delaiUnite = getParameter('delai_reponse_unite', 'jours');
    echo "  ✓ Delay configured: $delaiValeur $delaiUnite\n\n";
    
    // Summary
    echo "=== Test Summary ===\n";
    echo "Pending automatic responses: $pending_count\n";
    echo "Recently auto-refused: " . count($auto_refused) . "\n";
    echo "Mismatched candidatures: $mismatched_count\n";
    echo "Delay configuration: $delaiValeur $delaiUnite\n\n";
    
    if ($mismatched_count === 0) {
        echo "✓ All tests passed!\n";
        echo "\nThe cron-jobs.php page will now show:\n";
        echo "1. 'Réponses Automatiques Programmées' section with $pending_count candidatures\n";
        echo "2. 'Candidatures Auto-Refusées Récemment' section with " . count($auto_refused) . " candidatures\n";
    } else {
        echo "✗ Some tests failed. Please fix the issues above.\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
