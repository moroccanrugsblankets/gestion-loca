<?php
/**
 * Standalone test for expiration logic (no DB needed)
 */

date_default_timezone_set('Europe/Paris');

function isContractValid_CURRENT($contract) {
    if (!$contract) {
        return false;
    }
    
    // Only 'en_attente' status is valid for unsigned contracts
    $validStatuses = ['en_attente'];
    if (!in_array($contract['statut'], $validStatuses)) {
        return false;
    }
    
    $expiration = strtotime($contract['date_expiration']);
    return time() < $expiration;  // CURRENT IMPLEMENTATION
}

function isContractValid_PROPOSED($contract) {
    if (!$contract) {
        return false;
    }
    
    // Only 'en_attente' status is valid for unsigned contracts
    $validStatuses = ['en_attente'];
    if (!in_array($contract['statut'], $validStatuses)) {
        return false;
    }
    
    $expiration = strtotime($contract['date_expiration']);
    return time() <= $expiration;  // PROPOSED CHANGE: <= instead of <
}

echo "=== Testing Contract Expiration Logic ===\n\n";
echo "Current server time: " . date('Y-m-d H:i:s') . " (Europe/Paris)\n\n";

// Test 1: Contract that expires in the FUTURE (should be VALID)
echo "Test 1: Contract expires in 12 hours (SHOULD BE VALID)\n";
$contract1 = [
    'statut' => 'en_attente',
    'date_expiration' => date('Y-m-d H:i:s', strtotime('+12 hours'))
];
echo "  Expiration: " . $contract1['date_expiration'] . "\n";
echo "  Current (< operator): " . (isContractValid_CURRENT($contract1) ? 'VALID ✓' : 'INVALID ✗') . "\n";
echo "  Proposed (<= operator): " . (isContractValid_PROPOSED($contract1) ? 'VALID ✓' : 'INVALID ✗') . "\n";
echo "  Correct result should be: VALID\n\n";

// Test 2: Contract that expired 1 hour ago (should be INVALID)
echo "Test 2: Contract expired 1 hour ago (SHOULD BE INVALID)\n";
$contract2 = [
    'statut' => 'en_attente',
    'date_expiration' => date('Y-m-d H:i:s', strtotime('-1 hour'))
];
echo "  Expiration: " . $contract2['date_expiration'] . "\n";
echo "  Current (< operator): " . (isContractValid_CURRENT($contract2) ? 'VALID ✓' : 'INVALID ✗') . "\n";
echo "  Proposed (<= operator): " . (isContractValid_PROPOSED($contract2) ? 'VALID ✓' : 'INVALID ✗') . "\n";
echo "  Correct result should be: INVALID\n\n";

// Test 3: Simulate the bug report scenario
// If we simulate current time as Feb 1, 11:12 Paris time
// And expiration as Feb 2, 00:45 Paris time
echo "Test 3: Bug report scenario simulation\n";
echo "  Simulated current: 2026-02-01 11:12:00 (Feb 1)\n";
echo "  Simulated expiration: 2026-02-02 00:45:00 (Feb 2)\n";

// We can't actually change time(), but we can simulate the comparison
$sim_current = strtotime('2026-02-01 11:12:00');
$sim_expiration = strtotime('2026-02-02 00:45:00');

echo "  With < operator: current < expiration = " . ($sim_current < $sim_expiration ? 'TRUE (VALID)' : 'FALSE (INVALID)') . "\n";
echo "  With <= operator: current <= expiration = " . ($sim_current <= $sim_expiration ? 'TRUE (VALID)' : 'FALSE (INVALID)') . "\n";
echo "  Link should be: VALID (not expired yet)\n";
echo "  Operator '<' is: " . ($sim_current < $sim_expiration ? 'CORRECT ✓' : 'WRONG ✗') . "\n";
echo "  Operator '<=' is: " . ($sim_current <= $sim_expiration ? 'CORRECT ✓' : 'WRONG ✗') . "\n\n";

echo "=== CONCLUSION ===\n";
echo "The operator '<' appears to be CORRECT based on logical analysis.\n";
echo "However, if users are seeing expired errors when links should be valid,\n";
echo "there may be a timezone issue or database timestamp storage issue.\n";
