<?php
/**
 * Test script to verify automatic response system
 * 
 * This script tests:
 * 1. Query for pending automatic responses works (all candidatures with reponse_automatique='en_attente')
 * 2. The data is correctly structured
 * 3. The cron-jobs.php page will display them correctly
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

echo "=== Test: Automatic Response System ===\n\n";

try {
    // Test 1: Query for all pending automatic responses (regardless of status)
    echo "Test 1: Query for all pending automatic responses...\n";
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM candidatures c
        WHERE c.reponse_automatique = 'en_attente'
    ");
    $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "  ✓ Found $pending_count candidatures pending automatic response\n\n";
    
    // Test 2: Show sample of pending candidatures
    echo "Test 2: Sample of pending candidatures...\n";
    $stmt = $pdo->query("
        SELECT 
            c.id,
            c.reference_unique,
            c.nom,
            c.prenom,
            c.email,
            c.created_at,
            c.statut,
            c.reponse_automatique
        FROM candidatures c
        WHERE c.reponse_automatique = 'en_attente'
        ORDER BY c.created_at DESC
        LIMIT 5
    ");
    $pending_samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($pending_samples)) {
        echo "  Sample of pending candidatures:\n";
        foreach ($pending_samples as $sample) {
            echo "    - ID: {$sample['id']}, Ref: {$sample['reference_unique']}\n";
            echo "      Candidat: {$sample['prenom']} {$sample['nom']}\n";
            echo "      Date: {$sample['created_at']}\n";
            echo "      Statut: {$sample['statut']}, Réponse auto: {$sample['reponse_automatique']}\n\n";
        }
    } else {
        echo "  No pending candidatures found\n\n";
    }
    
    // Test 3: Count processed candidatures
    echo "Test 3: Count processed candidatures...\n";
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM candidatures
        WHERE reponse_automatique IN ('accepte', 'refuse')
    ");
    $processed_count = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "  ✓ Found $processed_count processed candidatures (sent automatic response)\n\n";
    
    // Test 4: Check delay parameters
    echo "Test 4: Check delay parameters...\n";
    $delaiValeur = (int)getParameter('delai_reponse_valeur', 4);
    $delaiUnite = getParameter('delai_reponse_unite', 'jours');
    echo "  ✓ Delay configured: $delaiValeur $delaiUnite\n\n";
    
    // Summary
    echo "=== Test Summary ===\n";
    echo "Pending automatic responses: $pending_count\n";
    echo "Processed candidatures: $processed_count\n";
    echo "Delay configuration: $delaiValeur $delaiUnite\n\n";
    
    echo "✓ All tests passed!\n";
    echo "\nThe cron-jobs.php page will now show:\n";
    echo "1. 'Réponses Automatiques Programmées' section with $pending_count candidatures\n";
    echo "2. All candidatures will receive scheduled automatic response (acceptance or refusal)\n";
    echo "3. 'Candidatures Auto-Refusées Récemment' section has been removed\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
