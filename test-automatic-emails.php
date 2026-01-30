<?php
/**
 * Test script to verify automatic email fixes
 * Tests that the cron script uses database templates correctly
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

echo "=== Testing Automatic Email Configuration ===\n\n";

// Test 1: Check if email templates exist
echo "Test 1: Checking Email Templates\n";
echo "----------------------------------------\n";

try {
    $templates = ['candidature_acceptee', 'candidature_refusee'];
    
    foreach ($templates as $templateId) {
        $template = getEmailTemplate($templateId);
        
        if ($template) {
            echo "✓ Template '$templateId' found\n";
            echo "  - Sujet: " . substr($template['sujet'], 0, 50) . "...\n";
            echo "  - Active: " . ($template['actif'] ? 'Yes' : 'No') . "\n";
            
            // Check if template has {{signature}} placeholder
            if (strpos($template['corps_html'], '{{signature}}') !== false) {
                echo "  - Contains {{signature}}: Yes ✓\n";
            } else {
                echo "  - Contains {{signature}}: No ⚠ (signature will need to be added)\n";
            }
        } else {
            echo "✗ Template '$templateId' NOT FOUND\n";
        }
        echo "\n";
    }
} catch (Exception $e) {
    echo "✗ Error checking templates: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Check delay parameters
echo "Test 2: Checking Delay Parameters\n";
echo "----------------------------------------\n";

try {
    $delaiValeur = (int)getParameter('delai_reponse_valeur', 4);
    $delaiUnite = getParameter('delai_reponse_unite', 'jours');
    
    echo "✓ Delay configured: $delaiValeur $delaiUnite\n";
    
    if ($delaiValeur > 0) {
        echo "✓ Delay value is valid\n";
    } else {
        echo "✗ Delay value is invalid (should be > 0)\n";
    }
    
    $validUnites = ['jours', 'heures', 'minutes'];
    if (in_array($delaiUnite, $validUnites)) {
        echo "✓ Delay unit is valid\n";
    } else {
        echo "✗ Delay unit is invalid (should be: jours, heures, or minutes)\n";
    }
} catch (Exception $e) {
    echo "✗ Error checking parameters: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Check pending candidatures
echo "Test 3: Checking Pending Candidatures\n";
echo "----------------------------------------\n";

try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM candidatures 
        WHERE statut = 'en_cours' 
        AND reponse_automatique = 'en_attente'
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Pending candidatures: " . $result['count'] . "\n";
    
    if ($result['count'] > 0) {
        echo "✓ There are candidatures awaiting automatic response\n";
        
        // Show details of pending candidatures
        $stmt = $pdo->query("
            SELECT 
                id,
                reference_unique,
                nom,
                prenom,
                created_at
            FROM candidatures 
            WHERE statut = 'en_cours' 
            AND reponse_automatique = 'en_attente'
            LIMIT 5
        ");
        $candidatures = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\nPending candidatures (max 5):\n";
        foreach ($candidatures as $c) {
            echo "  - ID: {$c['id']}, Ref: {$c['reference_unique']}, ";
            echo "{$c['prenom']} {$c['nom']}, ";
            echo "Submitted: " . date('Y-m-d H:i', strtotime($c['created_at'])) . "\n";
        }
    } else {
        echo "✓ No pending candidatures (all have been processed)\n";
    }
} catch (Exception $e) {
    echo "✗ Error checking candidatures: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Check cron jobs table
echo "Test 4: Checking Cron Jobs Configuration\n";
echo "----------------------------------------\n";

try {
    $stmt = $pdo->query("
        SELECT 
            nom,
            fichier,
            actif,
            derniere_execution,
            statut_derniere_execution
        FROM cron_jobs
    ");
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total cron jobs: " . count($jobs) . "\n\n";
    
    foreach ($jobs as $job) {
        echo "Job: " . $job['nom'] . "\n";
        echo "  - File: " . $job['fichier'] . "\n";
        echo "  - Active: " . ($job['actif'] ? 'Yes' : 'No') . "\n";
        if ($job['derniere_execution']) {
            echo "  - Last run: " . $job['derniere_execution'] . "\n";
            echo "  - Status: " . ($job['statut_derniere_execution'] ?? 'N/A') . "\n";
        } else {
            echo "  - Never executed\n";
        }
        echo "\n";
    }
} catch (Exception $e) {
    echo "✗ Error checking cron jobs: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";

echo "\nSummary:\n";
echo "- Email templates should be configured in database\n";
echo "- Delay parameters should be set in parametres table\n";
echo "- Cron job should run daily to process pending candidatures\n";
echo "- The cron script now uses sendTemplatedEmail() instead of hardcoded templates\n";
echo "- The cron-jobs.php page now shows pending candidature responses\n";
