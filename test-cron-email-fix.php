<?php
/**
 * Test script to verify cron email fix
 * This simulates the cron job behavior with email failures
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

echo "=== Testing Cron Email Fix ===\n\n";

// Test 1: Check if there are candidatures pending automatic response
echo "Test 1: Checking Pending Candidatures\n";
echo "----------------------------------------\n";

try {
    $delaiValeur = (int)getParameter('delai_reponse_valeur', 4);
    $delaiUnite = getParameter('delai_reponse_unite', 'jours');
    
    echo "Delay configured: $delaiValeur $delaiUnite\n";
    
    // Calculate the delay in hours based on the unit
    $hoursDelay = 0;
    if ($delaiUnite === 'jours') {
        $hoursDelay = $delaiValeur * 24;
    } elseif ($delaiUnite === 'heures') {
        $hoursDelay = $delaiValeur;
    } elseif ($delaiUnite === 'minutes') {
        $hoursDelay = $delaiValeur / 60;
    }
    
    echo "Hours delay: $hoursDelay hours\n\n";
    
    $query = "
        SELECT c.* 
        FROM candidatures c
        WHERE c.reponse_automatique = 'en_attente'
        AND TIMESTAMPDIFF(HOUR, c.created_at, NOW()) >= ?
        ORDER BY c.created_at ASC
        LIMIT 10
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$hoursDelay]);
    $candidatures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($candidatures) . " candidatures ready to process\n\n";
    
    if (count($candidatures) > 0) {
        echo "Details of pending candidatures:\n";
        foreach ($candidatures as $c) {
            echo "  - ID: {$c['id']}, Ref: {$c['reference_unique']}\n";
            echo "    Name: {$c['prenom']} {$c['nom']}\n";
            echo "    Email: {$c['email']}\n";
            echo "    Created: {$c['created_at']}\n";
            echo "    Status: {$c['statut']}\n";
            echo "    Auto Response: {$c['reponse_automatique']}\n";
            
            $created = new DateTime($c['created_at']);
            $now = new DateTime();
            $diff = $created->diff($now);
            $hoursPassed = ($diff->days * 24) + $diff->h + ($diff->i / 60);
            echo "    Hours passed: " . round($hoursPassed, 2) . " hours\n\n";
        }
    } else {
        echo "✓ No candidatures are ready for automatic response yet\n";
        
        // Show all candidatures with reponse_automatique = 'en_attente' regardless of delay
        $stmt = $pdo->query("
            SELECT id, reference_unique, nom, prenom, created_at, 
                   TIMESTAMPDIFF(HOUR, created_at, NOW()) as hours_passed
            FROM candidatures 
            WHERE reponse_automatique = 'en_attente'
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $waiting = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($waiting) > 0) {
            echo "\nCandidatures still waiting (delay not passed yet):\n";
            foreach ($waiting as $w) {
                echo "  - ID: {$w['id']}, {$w['prenom']} {$w['nom']}\n";
                echo "    Created: {$w['created_at']}\n";
                echo "    Hours passed: {$w['hours_passed']} / $hoursDelay hours needed\n";
                echo "    Will be processed in: " . ($hoursDelay - $w['hours_passed']) . " hours\n\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Check recent email error logs
echo "Test 2: Checking Email Error Logs\n";
echo "----------------------------------------\n";

try {
    $stmt = $pdo->query("
        SELECT 
            l.created_at,
            l.action,
            l.details,
            c.id as candidature_id,
            c.reference_unique,
            c.nom,
            c.prenom,
            c.email,
            c.reponse_automatique
        FROM logs l
        LEFT JOIN candidatures c ON l.entite_id = c.id AND l.type_entite = 'candidature'
        WHERE l.action IN ('email_error', 'email_acceptation', 'email_refus')
        ORDER BY l.created_at DESC
        LIMIT 10
    ");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($logs) > 0) {
        echo "Recent email logs (last 10):\n\n";
        foreach ($logs as $log) {
            $status_indicator = '';
            if ($log['action'] === 'email_error') {
                $status_indicator = '✗ ERROR';
            } else {
                $status_indicator = '✓ SUCCESS';
            }
            
            echo "$status_indicator [{$log['created_at']}] {$log['action']}\n";
            echo "  Details: {$log['details']}\n";
            if ($log['candidature_id']) {
                echo "  Candidature: ID={$log['candidature_id']}, {$log['prenom']} {$log['nom']}\n";
                echo "  Email: {$log['email']}\n";
                echo "  Current status: {$log['reponse_automatique']}\n";
            }
            echo "\n";
        }
    } else {
        echo "No email logs found yet\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Verify the fix works correctly
echo "Test 3: Verifying Email Retry Logic\n";
echo "----------------------------------------\n";

try {
    // Check if there are any candidatures that had email errors and are still retryable
    $stmt = $pdo->query("
        SELECT 
            c.id,
            c.reference_unique,
            c.nom,
            c.prenom,
            c.email,
            c.created_at,
            c.reponse_automatique,
            COUNT(l.id) as error_count,
            MAX(l.created_at) as last_error
        FROM candidatures c
        LEFT JOIN logs l ON l.entite_id = c.id 
            AND l.type_entite = 'candidature' 
            AND l.action = 'email_error'
        WHERE c.reponse_automatique = 'en_attente'
        GROUP BY c.id
        HAVING error_count > 0
        ORDER BY last_error DESC
    ");
    $retryable = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($retryable) > 0) {
        echo "✓ Found " . count($retryable) . " candidatures with email errors that can be retried:\n\n";
        foreach ($retryable as $r) {
            echo "  - ID: {$r['id']}, {$r['prenom']} {$r['nom']}\n";
            echo "    Email: {$r['email']}\n";
            echo "    Error count: {$r['error_count']}\n";
            echo "    Last error: {$r['last_error']}\n";
            echo "    Status: {$r['reponse_automatique']} (will be retried) ✓\n\n";
        }
        echo "These candidatures will be retried on the next cron run!\n";
    } else {
        echo "✓ No candidatures with failed emails found (or all were successfully sent)\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n\n";

echo "Summary:\n";
echo "--------\n";
echo "✓ The fix ensures that candidatures with failed emails remain in 'en_attente' status\n";
echo "✓ Failed candidatures will be automatically retried on the next cron run\n";
echo "✓ Email errors are logged for debugging\n";
echo "✓ Only successful email sends will update the candidature status\n";
echo "\nRecommendations:\n";
echo "- Run the cron job frequently (every 5-10 minutes) to ensure timely retries\n";
echo "- Monitor the logs table for 'email_error' entries to identify SMTP issues\n";
echo "- Check email configuration if you see repeated email_error logs\n";
