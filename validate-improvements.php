#!/usr/bin/env php
<?php
/**
 * Validation script for automatic response improvements
 * 
 * This script validates that the changes are correctly implemented:
 * 1. No syntax errors
 * 2. Key functions and queries are correct
 * 3. Logic flow is as expected
 */

echo "=== Validation des Améliorations - Réponses Automatiques ===\n\n";

$errors = [];
$warnings = [];
$success = [];

// Test 1: Verify files exist
echo "[1/5] Vérification de l'existence des fichiers modifiés...\n";
$files = [
    'candidature/submit.php',
    'admin-v2/cron-jobs.php',
    'cron/process-candidatures.php',
    'test-auto-refused-display.php'
];

foreach ($files as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        $success[] = "  ✓ $file existe";
    } else {
        $errors[] = "  ✗ $file n'existe pas";
    }
}
echo implode("\n", $success) . "\n";
if (!empty($errors)) {
    echo implode("\n", $errors) . "\n";
}
echo "\n";

// Test 2: Verify syntax in modified files
echo "[2/5] Vérification de la syntaxe PHP...\n";
$syntax_ok = true;
foreach ($files as $file) {
    $output = [];
    $return_var = 0;
    exec("php -l " . escapeshellarg(__DIR__ . '/' . $file) . " 2>&1", $output, $return_var);
    
    if ($return_var === 0) {
        echo "  ✓ $file - syntaxe correcte\n";
    } else {
        $errors[] = "  ✗ $file - erreur de syntaxe";
        $syntax_ok = false;
    }
}
echo "\n";

// Test 3: Verify submit.php changes
echo "[3/5] Vérification des changements dans submit.php...\n";
$submit_content = file_get_contents(__DIR__ . '/candidature/submit.php');

// Check that we no longer set statut based on evaluation
if (strpos($submit_content, "\$initialStatut = 'en_cours'") !== false) {
    echo "  ✓ Toutes les candidatures sont marquées 'en_cours'\n";
} else {
    $errors[] = "  ✗ Les candidatures ne sont pas toutes marquées 'en_cours'";
}

if (strpos($submit_content, "\$reponseAutomatique = 'en_attente'") !== false) {
    echo "  ✓ Toutes les candidatures ont reponse_automatique='en_attente'\n";
} else {
    $errors[] = "  ✗ Les candidatures n'ont pas reponse_automatique='en_attente'";
}

// Check that we removed the old evaluation logic
if (strpos($submit_content, 'evaluateCandidature($candidatureData)') === false) {
    echo "  ✓ L'évaluation immédiate a été supprimée\n";
} else {
    $warnings[] = "  ⚠ L'évaluation immédiate est toujours présente";
}

echo "\n";

// Test 4: Verify cron-jobs.php changes
echo "[4/5] Vérification des changements dans cron-jobs.php...\n";
$cron_jobs_content = file_get_contents(__DIR__ . '/admin-v2/cron-jobs.php');

// Check that query no longer filters by statut='en_cours'
if (strpos($cron_jobs_content, "WHERE c.reponse_automatique = 'en_attente'") !== false) {
    echo "  ✓ Requête mise à jour (sans filtre statut='en_cours')\n";
} else {
    $errors[] = "  ✗ Requête non mise à jour correctement";
}

// Check that "Candidatures Auto-Refusées Récemment" block is removed
if (strpos($cron_jobs_content, 'Candidatures Auto-Refusées Récemment') === false) {
    echo "  ✓ Bloc 'Candidatures Auto-Refusées Récemment' supprimé\n";
} else {
    $errors[] = "  ✗ Bloc 'Candidatures Auto-Refusées Récemment' toujours présent";
}

// Check that description is updated
if (strpos($cron_jobs_content, 'en attente d\'évaluation et d\'envoi de réponse automatique') !== false) {
    echo "  ✓ Description mise à jour\n";
} else {
    $warnings[] = "  ⚠ Description non mise à jour";
}

echo "\n";

// Test 5: Verify process-candidatures.php changes
echo "[5/5] Vérification des changements dans process-candidatures.php...\n";
$process_content = file_get_contents(__DIR__ . '/cron/process-candidatures.php');

// Check that query doesn't filter by statut
if (strpos($process_content, "WHERE c.reponse_automatique = 'en_attente'") !== false &&
    strpos($process_content, "c.statut = 'en_cours'") === false) {
    echo "  ✓ Requête cron mise à jour (sans filtre statut)\n";
} else {
    $errors[] = "  ✗ Requête cron contient toujours un filtre statut";
}

// Check that we removed the view dependency
if (strpos($process_content, 'v_candidatures_a_traiter') === false) {
    echo "  ✓ Dépendance à la vue supprimée\n";
} else {
    $warnings[] = "  ⚠ Dépendance à la vue toujours présente";
}

// Check unified delay calculation
if (strpos($process_content, 'TIMESTAMPDIFF(HOUR, c.created_at, NOW())') !== false) {
    echo "  ✓ Calcul de délai unifié en place\n";
} else {
    $warnings[] = "  ⚠ Calcul de délai unifié non trouvé";
}

echo "\n";

// Final summary
echo "=== Résumé de la Validation ===\n\n";

if (empty($errors)) {
    echo "✅ SUCCÈS: Toutes les validations critiques sont passées!\n";
    echo "   Nombre de succès: " . count($success) . "\n";
} else {
    echo "❌ ÉCHEC: Des erreurs ont été détectées:\n";
    echo implode("\n", $errors) . "\n";
}

if (!empty($warnings)) {
    echo "\n⚠️  Avertissements:\n";
    echo implode("\n", $warnings) . "\n";
}

echo "\n=== Prochaines Étapes ===\n\n";
echo "1. Déployer les changements sur le serveur de test\n";
echo "2. Créer une candidature de test avec des critères qui seront refusés\n";
echo "3. Vérifier dans admin-v2/cron-jobs.php que la candidature apparaît\n";
echo "4. Exécuter le cron manuellement ou attendre l'exécution automatique\n";
echo "5. Vérifier que l'email de refus a été envoyé après le délai configuré\n";

exit(empty($errors) ? 0 : 1);
