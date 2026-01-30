<?php
/**
 * Test script to validate candidature status for applications with income < 2300€
 * This is a standalone test that doesn't require database connection
 */

// Mock getParameter function to avoid database dependency
function getParameter($cle, $default = null) {
    $params = [
        'revenus_min_requis' => 3000,  // This parameter represents the desired minimum income threshold
        'statuts_pro_acceptes' => ['CDI', 'CDD'],
        'type_revenus_accepte' => 'Salaires',
        'nb_occupants_acceptes' => ['1', '2'],
        'garantie_visale_requise' => true
    ];
    
    return isset($params[$cle]) ? $params[$cle] : $default;
}

// Copy of evaluateCandidature function for testing
function evaluateCandidature($candidature) {
    // Get parameters from database
    $revenusMinRequis = getParameter('revenus_min_requis', 3000);
    $statutsProAcceptes = getParameter('statuts_pro_acceptes', ['CDI', 'CDD']);
    $typeRevenusAccepte = getParameter('type_revenus_accepte', 'Salaires');
    $nbOccupantsAcceptes = getParameter('nb_occupants_acceptes', ['1', '2']);
    $garantieVisaleRequise = getParameter('garantie_visale_requise', true);
    
    $motifs = [];
    
    // RULE 1: Professional situation - must be CDI or CDD
    if (!in_array($candidature['statut_professionnel'], $statutsProAcceptes)) {
        $motifs[] = "Statut professionnel non accepté (doit être CDI ou CDD)";
    }
    
    // RULE 2: Monthly net income - must be >= 3000€
    // Note: The validation uses predefined income bracket enums (< 2300, 2300-3000, 3000+)
    // rather than directly comparing against the numeric threshold parameter.
    // This design allows for clear income categories in the database schema.
    $revenus = $candidature['revenus_mensuels'];
    if ($revenus === '< 2300' || $revenus === '2300-3000') {
        $motifs[] = "Revenus nets mensuels insuffisants (minimum 3000€ requis)";
    }
    
    // RULE 3: Income type - must be Salaires
    if ($candidature['type_revenus'] !== $typeRevenusAccepte) {
        $motifs[] = "Type de revenus non accepté (doit être: $typeRevenusAccepte)";
    }
    
    // RULE 4: Number of occupants - must be 1 or 2 (not "Autre")
    if (!in_array($candidature['nb_occupants'], $nbOccupantsAcceptes)) {
        $motifs[] = "Nombre d'occupants non accepté (doit être 1 ou 2)";
    }
    
    // RULE 5: Visale guarantee - must be "Oui"
    if ($garantieVisaleRequise && $candidature['garantie_visale'] !== 'Oui') {
        $motifs[] = "Garantie Visale requise";
    }
    
    // RULE 6: If CDI, trial period must be passed
    if ($candidature['statut_professionnel'] === 'CDI' && 
        isset($candidature['periode_essai']) && 
        $candidature['periode_essai'] === 'En cours') {
        $motifs[] = "Période d'essai en cours";
    }
    
    // All criteria must be met for acceptance
    $accepted = empty($motifs);
    $motif = $accepted ? '' : implode(', ', $motifs);
    
    // Determine the status value to use
    $statut = $accepted ? 'en_cours' : 'refuse';
    
    return [
        'accepted' => $accepted,
        'motif' => $motif,
        'statut' => $statut
    ];
}

$failCount = 0;
$passCount = 0;

function testResult($condition, $passMsg, $failMsg) {
    global $failCount, $passCount;
    if ($condition) {
        echo "   ✓ PASS: $passMsg\n";
        $passCount++;
    } else {
        echo "   ✗ FAIL: $failMsg\n";
        $failCount++;
    }
}

echo "=== Test de Statut de Candidature - Revenus < 2300€ ===\n\n";

// Test 1: Candidature with income < 2300€ should be refused
echo "1. Test candidature avec revenus < 2300€\n";
$candidatureData1 = [
    'statut_professionnel' => 'CDI',
    'periode_essai' => 'Passée',
    'revenus_mensuels' => '< 2300',
    'type_revenus' => 'Salaires',
    'nb_occupants' => '1',
    'garantie_visale' => 'Oui'
];

$evaluation1 = evaluateCandidature($candidatureData1);

testResult(
    !$evaluation1['accepted'],
    "Candidature refusée (revenus < 2300€)",
    "Candidature acceptée alors qu'elle devrait être refusée"
);

testResult(
    $evaluation1['statut'] === 'refuse',
    "Statut = 'refuse'",
    "Statut = '{$evaluation1['statut']}' (devrait être 'refuse')"
);

testResult(
    strpos($evaluation1['motif'], 'Revenus nets mensuels insuffisants') !== false,
    "Motif de refus contient 'Revenus nets mensuels insuffisants'",
    "Motif de refus incorrect: {$evaluation1['motif']}"
);

echo "\n";

// Test 2: Candidature with income 2300-3000€ should also be refused
echo "2. Test candidature avec revenus 2300-3000€\n";
$candidatureData2 = [
    'statut_professionnel' => 'CDI',
    'periode_essai' => 'Passée',
    'revenus_mensuels' => '2300-3000',
    'type_revenus' => 'Salaires',
    'nb_occupants' => '1',
    'garantie_visale' => 'Oui'
];

$evaluation2 = evaluateCandidature($candidatureData2);

testResult(
    !$evaluation2['accepted'],
    "Candidature refusée (revenus 2300-3000€)",
    "Candidature acceptée alors qu'elle devrait être refusée"
);

testResult(
    $evaluation2['statut'] === 'refuse',
    "Statut = 'refuse'",
    "Statut = '{$evaluation2['statut']}' (devrait être 'refuse')"
);

echo "\n";

// Test 3: Candidature with income >= 3000€ should be accepted (if all other criteria met)
echo "3. Test candidature avec revenus >= 3000€\n";
$candidatureData3 = [
    'statut_professionnel' => 'CDI',
    'periode_essai' => 'Passée',
    'revenus_mensuels' => '3000+',
    'type_revenus' => 'Salaires',
    'nb_occupants' => '1',
    'garantie_visale' => 'Oui'
];

$evaluation3 = evaluateCandidature($candidatureData3);

testResult(
    $evaluation3['accepted'],
    "Candidature acceptée (revenus >= 3000€)",
    "Candidature refusée alors qu'elle devrait être acceptée"
);

testResult(
    $evaluation3['statut'] === 'en_cours',
    "Statut = 'en_cours'",
    "Statut = '{$evaluation3['statut']}' (devrait être 'en_cours')"
);

testResult(
    $evaluation3['motif'] === '',
    "Aucun motif de refus",
    "Motif de refus présent: {$evaluation3['motif']}"
);

echo "\n";

// Summary
echo "=== Résumé ===\n";
echo "Tests réussis: $passCount\n";
echo "Tests échoués: $failCount\n";

if ($failCount === 0) {
    echo "\n✓ Tous les tests sont passés!\n";
    exit(0);
} else {
    echo "\n✗ Certains tests ont échoué.\n";
    exit(1);
}
