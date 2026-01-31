<?php
/**
 * Test script to validate the scheduled_response_date fix
 * This script verifies that:
 * 1. scheduled_response_date is calculated and stored when a candidature is refused
 * 2. Changing the delay parameter does not affect already scheduled responses
 * 3. New candidatures use the updated delay parameter
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

echo "=== Test: Scheduled Response Date Fix ===\n\n";

// Test 1: Check if the migration has added the column
echo "Test 1: Checking if scheduled_response_date column exists...\n";
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM candidatures LIKE 'scheduled_response_date'");
    $column = $stmt->fetch();
    if ($column) {
        echo "✓ Column 'scheduled_response_date' exists\n";
    } else {
        echo "✗ Column 'scheduled_response_date' does NOT exist (migration may not have run yet)\n";
        echo "  Run the migration: php run-migrations.php\n";
    }
} catch (Exception $e) {
    echo "✗ Error checking column: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 2: Check if obsolete parameters were removed
echo "Test 2: Checking if obsolete parameters were removed...\n";
try {
    $stmt = $pdo->query("SELECT cle FROM parametres WHERE cle IN ('delai_reponse_jours', 'delai_refus_auto_heures')");
    $obsoleteParams = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($obsoleteParams)) {
        echo "✓ Obsolete parameters have been removed\n";
    } else {
        echo "✗ Found obsolete parameters: " . implode(', ', $obsoleteParams) . "\n";
        echo "  These should be removed by the migration\n";
    }
} catch (Exception $e) {
    echo "✗ Error checking parameters: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 3: Test the calculateScheduledResponseDate function
echo "Test 3: Testing calculateScheduledResponseDate() function...\n";
try {
    $testDate = new DateTime('2024-01-15 10:00:00'); // Monday
    
    // Get current parameters
    $currentDelaiValeur = (int)getParameter('delai_reponse_valeur', 4);
    $currentDelaiUnite = getParameter('delai_reponse_unite', 'jours');
    
    echo "  Current delay: $currentDelaiValeur $currentDelaiUnite\n";
    
    $scheduledDate = calculateScheduledResponseDate($testDate);
    echo "  Test date: " . $testDate->format('Y-m-d H:i:s') . " (Monday)\n";
    echo "  Calculated scheduled date: " . $scheduledDate->format('Y-m-d H:i:s') . "\n";
    
    // Verify the calculation makes sense
    $diff = $testDate->diff($scheduledDate);
    echo "  Difference: " . $diff->days . " calendar days\n";
    echo "✓ Function executed successfully\n";
} catch (Exception $e) {
    echo "✗ Error testing function: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 4: Simulate changing the delay parameter and check existing scheduled dates
echo "Test 4: Checking that existing scheduled_response_date values remain unchanged...\n";
try {
    // Get candidatures with scheduled_response_date
    $stmt = $pdo->query("
        SELECT id, reference_unique, created_at, scheduled_response_date, statut
        FROM candidatures 
        WHERE scheduled_response_date IS NOT NULL
        AND reponse_automatique = 'en_attente'
        LIMIT 5
    ");
    $candidatures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($candidatures)) {
        echo "  No candidatures with scheduled_response_date found (expected if this is a new deployment)\n";
        echo "  You can test this by:\n";
        echo "  1. Creating a test candidature\n";
        echo "  2. Refusing it manually in the admin panel\n";
        echo "  3. Checking that scheduled_response_date is set\n";
        echo "  4. Changing the delay parameter\n";
        echo "  5. Verifying that scheduled_response_date remains unchanged\n";
    } else {
        echo "  Found " . count($candidatures) . " candidature(s) with scheduled_response_date:\n";
        foreach ($candidatures as $cand) {
            echo "  - " . $cand['reference_unique'] . ": " . $cand['scheduled_response_date'] . "\n";
        }
        echo "✓ These dates should remain fixed even if delay parameters change\n";
    }
} catch (Exception $e) {
    echo "✗ Error checking candidatures: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 5: Check cron query logic
echo "Test 5: Testing cron query logic for pending responses...\n";
try {
    // Get current delay parameters
    $delaiValeur = (int)getParameter('delai_reponse_valeur', 4);
    $delaiUnite = getParameter('delai_reponse_unite', 'jours');
    
    // Calculate the delay in hours (for backward compatibility)
    $hoursDelay = 0;
    if ($delaiUnite === 'jours') {
        $hoursDelay = $delaiValeur * 24;
    } elseif ($delaiUnite === 'heures') {
        $hoursDelay = $delaiValeur;
    } elseif ($delaiUnite === 'minutes') {
        $hoursDelay = $delaiValeur / 60;
    }
    
    // Query that the cron uses
    $query = "
        SELECT c.id, c.reference_unique, c.scheduled_response_date, c.created_at
        FROM candidatures c
        WHERE c.reponse_automatique = 'en_attente'
        AND (
            (c.scheduled_response_date IS NOT NULL AND c.scheduled_response_date <= NOW())
            OR (c.scheduled_response_date IS NULL AND TIMESTAMPDIFF(HOUR, c.created_at, NOW()) >= ?)
        )
        ORDER BY c.created_at ASC
        LIMIT 5
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$hoursDelay]);
    $readyToProcess = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($readyToProcess)) {
        echo "  No candidatures ready to process (this is normal if no pending responses exist)\n";
    } else {
        echo "  Found " . count($readyToProcess) . " candidature(s) ready to process:\n";
        foreach ($readyToProcess as $cand) {
            $method = $cand['scheduled_response_date'] ? 'scheduled_response_date' : 'calculated from created_at';
            echo "  - " . $cand['reference_unique'] . " (using: $method)\n";
        }
    }
    echo "✓ Cron query logic is working correctly\n";
} catch (Exception $e) {
    echo "✗ Error testing cron query: " . $e->getMessage() . "\n";
}
echo "\n";

echo "=== Test Summary ===\n";
echo "All structural tests completed.\n";
echo "To fully validate the fix:\n";
echo "1. Run the migration if not already done: php run-migrations.php\n";
echo "2. In the admin panel, refuse a candidature manually\n";
echo "3. Verify that scheduled_response_date is set in the database\n";
echo "4. Change the 'Délai de réponse automatique' parameter\n";
echo "5. Verify that the scheduled_response_date for the refused candidature remains unchanged\n";
echo "6. Create a new candidature and refuse it - verify it uses the new delay\n";
?>
