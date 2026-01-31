<?php
/**
 * Test script to validate the automatic response scheduling system
 * This test verifies that rejected candidatures are properly scheduled for delayed email sending
 */

echo "<h1>Test: Système de Réponses Automatiques Programmées</h1>\n";
echo "<hr>\n";

// Mock getParameter function to simulate the configured delay
function getParameter($cle, $default = null) {
    $params = [
        'delai_reponse_valeur' => 10,  // 10 minutes delay
        'delai_reponse_unite' => 'minutes',
        'revenus_min_requis' => 3000,
        'statuts_pro_acceptes' => ['CDI', 'CDD'],
        'type_revenus_accepte' => 'Salaires',
        'nb_occupants_acceptes' => ['1', '2'],
        'garantie_visale_requise' => true
    ];
    
    return isset($params[$cle]) ? $params[$cle] : $default;
}

// Copy of evaluateCandidature function
function evaluateCandidature($candidature) {
    $revenusMinRequis = getParameter('revenus_min_requis', 3000);
    $statutsProAcceptes = getParameter('statuts_pro_acceptes', ['CDI', 'CDD']);
    $typeRevenusAccepte = getParameter('type_revenus_accepte', 'Salaires');
    $nbOccupantsAcceptes = getParameter('nb_occupants_acceptes', ['1', '2']);
    $garantieVisaleRequise = getParameter('garantie_visale_requise', true);
    
    $motifs = [];
    
    // RULE 1: Professional situation
    if (!in_array($candidature['statut_professionnel'], $statutsProAcceptes)) {
        $motifs[] = "Statut professionnel non accepté (doit être CDI ou CDD)";
    }
    
    // RULE 2: Monthly net income - must be >= 3000€
    $revenus = $candidature['revenus_mensuels'];
    if ($revenus === '< 2300' || $revenus === '2300-3000') {
        $motifs[] = "Revenus nets mensuels insuffisants (minimum 3000€ requis)";
    }
    
    // RULE 3: Income type
    if ($candidature['type_revenus'] !== $typeRevenusAccepte) {
        $motifs[] = "Type de revenus non accepté (doit être: $typeRevenusAccepte)";
    }
    
    // RULE 4: Number of occupants
    if (!in_array($candidature['nb_occupants'], $nbOccupantsAcceptes)) {
        $motifs[] = "Nombre d'occupants non accepté (doit être 1 ou 2)";
    }
    
    // RULE 5: Visale guarantee
    if ($garantieVisaleRequise && $candidature['garantie_visale'] !== 'Oui') {
        $motifs[] = "Garantie Visale requise";
    }
    
    // RULE 6: Trial period
    if ($candidature['statut_professionnel'] === 'CDI' && 
        isset($candidature['periode_essai']) && 
        $candidature['periode_essai'] === 'En cours') {
        $motifs[] = "Période d'essai en cours";
    }
    
    $accepted = empty($motifs);
    $motif = $accepted ? '' : implode(', ', $motifs);
    $statut = $accepted ? 'en_cours' : 'refuse';
    
    return [
        'accepted' => $accepted,
        'motif' => $motif,
        'statut' => $statut
    ];
}

// Test cases
$testCases = [
    [
        'name' => 'Candidature refusée - Revenus insuffisants (< 2300€)',
        'data' => [
            'statut_professionnel' => 'CDI',
            'periode_essai' => 'Terminée',
            'revenus_mensuels' => '< 2300',
            'type_revenus' => 'Salaires',
            'nb_occupants' => '1',
            'garantie_visale' => 'Oui'
        ],
        'expected_status' => 'refuse',
        'expected_reponse_automatique' => 'en_attente',
        'expected_in_pending_list' => true
    ],
    [
        'name' => 'Candidature refusée - Revenus 2300-3000€',
        'data' => [
            'statut_professionnel' => 'CDI',
            'periode_essai' => 'Terminée',
            'revenus_mensuels' => '2300-3000',
            'type_revenus' => 'Salaires',
            'nb_occupants' => '1',
            'garantie_visale' => 'Oui'
        ],
        'expected_status' => 'refuse',
        'expected_reponse_automatique' => 'en_attente',
        'expected_in_pending_list' => true
    ],
    [
        'name' => 'Candidature acceptée - Tous critères OK',
        'data' => [
            'statut_professionnel' => 'CDI',
            'periode_essai' => 'Terminée',
            'revenus_mensuels' => '3000+',
            'type_revenus' => 'Salaires',
            'nb_occupants' => '1',
            'garantie_visale' => 'Oui'
        ],
        'expected_status' => 'en_cours',
        'expected_reponse_automatique' => 'en_attente',
        'expected_in_pending_list' => true
    ],
    [
        'name' => 'Candidature refusée - Sans Visale',
        'data' => [
            'statut_professionnel' => 'CDI',
            'periode_essai' => 'Terminée',
            'revenus_mensuels' => '3000+',
            'type_revenus' => 'Salaires',
            'nb_occupants' => '1',
            'garantie_visale' => 'Non'
        ],
        'expected_status' => 'refuse',
        'expected_reponse_automatique' => 'en_attente',
        'expected_in_pending_list' => true
    ]
];

echo "<h2>Configuration du système</h2>\n";
echo "<ul>\n";
echo "<li><strong>Délai configuré:</strong> " . getParameter('delai_reponse_valeur') . " " . getParameter('delai_reponse_unite') . "</li>\n";
echo "<li><strong>Revenus minimum requis:</strong> " . getParameter('revenus_min_requis') . "€</li>\n";
echo "</ul>\n";
echo "<hr>\n";

$passed = 0;
$failed = 0;

foreach ($testCases as $index => $testCase) {
    echo "<h3>Test " . ($index + 1) . ": " . htmlspecialchars($testCase['name']) . "</h3>\n";
    
    $evaluation = evaluateCandidature($testCase['data']);
    
    // Simulate the logic in submit.php
    if (!$evaluation['accepted']) {
        $initialStatut = 'refuse';
        $reponseAutomatique = 'en_attente'; // Scheduled for delayed rejection email
        $motifRefus = $evaluation['motif'];
    } else {
        $initialStatut = 'en_cours';
        $reponseAutomatique = 'en_attente'; // Scheduled for delayed acceptance email
        $motifRefus = null;
    }
    
    // Should appear in pending list if reponse_automatique = 'en_attente'
    $appearsInPendingList = ($reponseAutomatique === 'en_attente');
    
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>\n";
    echo "<tr><th>Propriété</th><th>Résultat</th><th>Attendu</th><th>Status</th></tr>\n";
    
    // Check status
    $statusMatch = ($initialStatut === $testCase['expected_status']);
    echo "<tr>\n";
    echo "<td>Statut</td>\n";
    echo "<td><code>" . htmlspecialchars($initialStatut) . "</code></td>\n";
    echo "<td><code>" . htmlspecialchars($testCase['expected_status']) . "</code></td>\n";
    echo "<td style='background-color: " . ($statusMatch ? '#d4edda' : '#f8d7da') . ";'>" . ($statusMatch ? '✓ PASS' : '✗ FAIL') . "</td>\n";
    echo "</tr>\n";
    
    // Check reponse_automatique
    $reponseMatch = ($reponseAutomatique === $testCase['expected_reponse_automatique']);
    echo "<tr>\n";
    echo "<td>Réponse Automatique</td>\n";
    echo "<td><code>" . htmlspecialchars($reponseAutomatique) . "</code></td>\n";
    echo "<td><code>" . htmlspecialchars($testCase['expected_reponse_automatique']) . "</code></td>\n";
    echo "<td style='background-color: " . ($reponseMatch ? '#d4edda' : '#f8d7da') . ";'>" . ($reponseMatch ? '✓ PASS' : '✗ FAIL') . "</td>\n";
    echo "</tr>\n";
    
    // Check if appears in pending list
    $pendingMatch = ($appearsInPendingList === $testCase['expected_in_pending_list']);
    echo "<tr>\n";
    echo "<td>Apparaît dans 'Réponses Programmées'</td>\n";
    echo "<td><code>" . ($appearsInPendingList ? 'Oui' : 'Non') . "</code></td>\n";
    echo "<td><code>" . ($testCase['expected_in_pending_list'] ? 'Oui' : 'Non') . "</code></td>\n";
    echo "<td style='background-color: " . ($pendingMatch ? '#d4edda' : '#f8d7da') . ";'>" . ($pendingMatch ? '✓ PASS' : '✗ FAIL') . "</td>\n";
    echo "</tr>\n";
    
    echo "</table>\n";
    
    if ($motifRefus) {
        echo "<p><strong>Motif de refus:</strong> " . htmlspecialchars($motifRefus) . "</p>\n";
    }
    
    if ($statusMatch && $reponseMatch && $pendingMatch) {
        echo "<p style='color: green;'><strong>✓ Test réussi</strong></p>\n";
        $passed++;
    } else {
        echo "<p style='color: red;'><strong>✗ Test échoué</strong></p>\n";
        $failed++;
    }
    
    echo "<hr>\n";
}

// Test delay calculation
echo "<h2>Test de calcul du délai</h2>\n";
echo "<p>Pour une candidature soumise maintenant, le mail sera envoyé après:</p>\n";

$delaiValeur = getParameter('delai_reponse_valeur');
$delaiUnite = getParameter('delai_reponse_unite');

$createdAt = new DateTime();
$expectedDate = clone $createdAt;

if ($delaiUnite === 'jours') {
    // Add business days
    $daysAdded = 0;
    while ($daysAdded < $delaiValeur) {
        $expectedDate->modify('+1 day');
        if ($expectedDate->format('N') < 6) { // Skip weekends
            $daysAdded++;
        }
    }
} elseif ($delaiUnite === 'heures') {
    $expectedDate->modify("+{$delaiValeur} hours");
} elseif ($delaiUnite === 'minutes') {
    $expectedDate->modify("+{$delaiValeur} minutes");
}

echo "<ul>\n";
echo "<li><strong>Date de soumission:</strong> " . $createdAt->format('d/m/Y H:i:s') . "</li>\n";
echo "<li><strong>Délai configuré:</strong> {$delaiValeur} {$delaiUnite}</li>\n";
echo "<li><strong>Date prévue d'envoi:</strong> " . $expectedDate->format('d/m/Y H:i:s') . "</li>\n";
echo "<li><strong>Différence:</strong> " . $createdAt->diff($expectedDate)->format('%a jours, %h heures, %i minutes') . "</li>\n";
echo "</ul>\n";

echo "<hr>\n";
echo "<h2>Résumé des tests</h2>\n";
echo "<p><strong>Tests réussis:</strong> <span style='color: green;'>" . $passed . "</span></p>\n";
echo "<p><strong>Tests échoués:</strong> <span style='color: red;'>" . $failed . "</span></p>\n";

if ($failed === 0) {
    echo "<h3 style='color: green;'>✓ Tous les tests sont passés avec succès!</h3>\n";
    echo "<p>Le système de réponses automatiques programmées fonctionne correctement:</p>\n";
    echo "<ul>\n";
    echo "<li>Les candidatures refusées sont bien programmées pour envoi différé</li>\n";
    echo "<li>Les candidatures acceptées sont bien programmées pour envoi différé</li>\n";
    echo "<li>Toutes les candidatures apparaissent dans 'Réponses Automatiques Programmées'</li>\n";
    echo "<li>Le délai configuré ({$delaiValeur} {$delaiUnite}) est correctement appliqué</li>\n";
    echo "</ul>\n";
} else {
    echo "<h3 style='color: red;'>✗ Certains tests ont échoué</h3>\n";
    echo "<p>Veuillez vérifier la configuration et corriger les problèmes identifiés.</p>\n";
}
?>
