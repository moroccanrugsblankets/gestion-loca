<?php
/**
 * Test script to verify expiration logic
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

echo "=== Testing Contract Expiration Logic ===\n\n";

// Test 1: Contract that should be VALID (future expiration)
echo "Test 1: Contract expires in the FUTURE (should be VALID)\n";
$contract_valid = [
    'id' => 1,
    'statut' => 'en_attente',
    'date_expiration' => date('Y-m-d H:i:s', strtotime('+12 hours'))
];
echo "  Expiration: " . $contract_valid['date_expiration'] . "\n";
echo "  Current time: " . date('Y-m-d H:i:s') . "\n";
$result = isContractValid($contract_valid);
echo "  isContractValid() returns: " . ($result ? 'TRUE (valid)' : 'FALSE (invalid)') . "\n";
echo "  Expected: TRUE\n";
echo "  Test: " . ($result ? 'PASS ✓' : 'FAIL ✗') . "\n\n";

// Test 2: Contract that should be EXPIRED (past expiration)
echo "Test 2: Contract expired in the PAST (should be INVALID)\n";
$contract_expired = [
    'id' => 2,
    'statut' => 'en_attente',
    'date_expiration' => date('Y-m-d H:i:s', strtotime('-1 hour'))
];
echo "  Expiration: " . $contract_expired['date_expiration'] . "\n";
echo "  Current time: " . date('Y-m-d H:i:s') . "\n";
$result = isContractValid($contract_expired);
echo "  isContractValid() returns: " . ($result ? 'TRUE (valid)' : 'FALSE (invalid)') . "\n";
echo "  Expected: FALSE\n";
echo "  Test: " . (!$result ? 'PASS ✓' : 'FAIL ✗') . "\n\n";

// Test 3: The exact scenario from the bug report
echo "Test 3: Bug report scenario (Feb 1 10:12 UTC, expires Feb 2 00:45)\n";
$current_utc = strtotime('2026-02-01 10:12:38');
$expiration_paris = strtotime('2026-02-02 00:45:00');
echo "  Current (UTC): " . date('Y-m-d H:i:s', $current_utc) . "\n";
echo "  Current (Paris): " . date('Y-m-d H:i:s', $current_utc + 3600) . " (UTC+1)\n";
echo "  Expiration (Paris): " . date('Y-m-d H:i:s', $expiration_paris) . "\n";
$contract_bug = [
    'id' => 3,
    'statut' => 'en_attente',
    'date_expiration' => '2026-02-02 00:45:00'
];
$result = isContractValid($contract_bug);
echo "  isContractValid() returns: " . ($result ? 'TRUE (valid)' : 'FALSE (invalid)') . "\n";
echo "  Expected: TRUE (link should still be valid)\n";
echo "  Test: " . ($result ? 'PASS ✓' : 'FAIL ✗') . "\n\n";

// Test 4: Contract with wrong status
echo "Test 4: Contract with 'signe' status (should be INVALID)\n";
$contract_signed = [
    'id' => 4,
    'statut' => 'signe',
    'date_expiration' => date('Y-m-d H:i:s', strtotime('+12 hours'))
];
$result = isContractValid($contract_signed);
echo "  isContractValid() returns: " . ($result ? 'TRUE (valid)' : 'FALSE (invalid)') . "\n";
echo "  Expected: FALSE (wrong status)\n";
echo "  Test: " . (!$result ? 'PASS ✓' : 'FAIL ✗') . "\n\n";

echo "=== End of Tests ===\n";
