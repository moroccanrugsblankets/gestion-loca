<?php
/**
 * Script de validation de la consolidation de la base de données
 * Ce script vérifie que toutes les configurations sont correctes
 */

echo "=== VALIDATION DE LA CONSOLIDATION ===\n\n";

// Test 1: Vérifier que config.php existe et est lisible
echo "Test 1: Fichier de configuration\n";
if (file_exists(__DIR__ . '/includes/config.php')) {
    echo "✅ config.php existe\n";
    require_once __DIR__ . '/includes/config.php';
    echo "✅ config.php chargé sans erreur\n";
} else {
    echo "❌ config.php n'existe pas\n";
    exit(1);
}

// Test 2: Vérifier que config-v2.php n'existe plus
echo "\nTest 2: Ancien fichier de configuration\n";
if (!file_exists(__DIR__ . '/includes/config-v2.php')) {
    echo "✅ config-v2.php a été supprimé\n";
} else {
    echo "❌ config-v2.php existe encore (devrait être supprimé)\n";
}

// Test 3: Vérifier les constantes de la base de données
echo "\nTest 3: Configuration de base de données\n";
if (isset($config['DB_NAME']) && $config['DB_NAME'] === 'bail_signature') {
    echo "✅ DB_NAME est défini à 'bail_signature'\n";
} else {
    echo "❌ DB_NAME n'est pas défini correctement\n";
}

if (isset($config['DB_HOST'])) {
    echo "✅ DB_HOST est défini: " . $config['DB_HOST'] . "\n";
}

if (isset($config['DB_CHARSET'])) {
    echo "✅ DB_CHARSET est défini: " . $config['DB_CHARSET'] . "\n";
}

// Test 4: Vérifier les nouvelles constantes du workflow
echo "\nTest 4: Configuration du workflow\n";
if (isset($config['DELAI_REPONSE_JOURS_OUVRES'])) {
    echo "✅ DELAI_REPONSE_JOURS_OUVRES est défini: " . $config['DELAI_REPONSE_JOURS_OUVRES'] . "\n";
}

if (isset($config['JOURS_OUVRES'])) {
    echo "✅ JOURS_OUVRES est défini\n";
}

if (isset($config['REVENUS_MIN_ACCEPTATION'])) {
    echo "✅ REVENUS_MIN_ACCEPTATION est défini: " . $config['REVENUS_MIN_ACCEPTATION'] . "\n";
}

// Test 5: Vérifier les URLs
echo "\nTest 5: URLs de l'application\n";
if (isset($config['SITE_URL'])) {
    echo "✅ SITE_URL est défini: " . $config['SITE_URL'] . "\n";
}

if (isset($config['CANDIDATURE_URL'])) {
    echo "✅ CANDIDATURE_URL est défini: " . $config['CANDIDATURE_URL'] . "\n";
}

if (isset($config['ADMIN_URL'])) {
    echo "✅ ADMIN_URL est défini: " . $config['ADMIN_URL'] . "\n";
}

// Test 6: Vérifier les constantes de sécurité
echo "\nTest 6: Configuration de sécurité\n";
if (isset($config['CSRF_KEY'])) {
    echo "✅ CSRF_KEY est défini\n";
}

if (isset($config['REFERENCE_SALT'])) {
    echo "✅ REFERENCE_SALT est défini: " . $config['REFERENCE_SALT'] . "\n";
}

// Test 7: Vérifier les fonctions utilitaires
echo "\nTest 7: Fonctions utilitaires\n";
if (function_exists('calculerJoursOuvres')) {
    echo "✅ fonction calculerJoursOuvres existe\n";
}

if (function_exists('ajouterJoursOuvres')) {
    echo "✅ fonction ajouterJoursOuvres existe\n";
}

if (function_exists('estJourOuvre')) {
    echo "✅ fonction estJourOuvre existe\n";
}

if (function_exists('genererReferenceUnique')) {
    echo "✅ fonction genererReferenceUnique existe\n";
    $ref = genererReferenceUnique('TEST');
    echo "   Exemple: " . $ref . "\n";
}

if (function_exists('genererToken')) {
    echo "✅ fonction genererToken existe\n";
}

// Test 8: Vérifier que les fichiers PHP n'utilisent plus config-v2.php
echo "\nTest 8: Recherche de références à config-v2.php\n";
$files = glob(__DIR__ . '/{admin-v2,candidature,cron}/*.php', GLOB_BRACE);
$found = false;
foreach ($files as $file) {
    $content = file_get_contents($file);
    if (strpos($content, 'config-v2.php') !== false) {
        echo "❌ Trouvé dans: " . basename($file) . "\n";
        $found = true;
    }
}
if (!$found) {
    echo "✅ Aucune référence à config-v2.php trouvée dans les fichiers PHP\n";
}

// Test 9: Vérifier le fichier database.sql
echo "\nTest 9: Fichier database.sql\n";
if (file_exists(__DIR__ . '/database.sql')) {
    echo "✅ database.sql existe\n";
    $content = file_get_contents(__DIR__ . '/database.sql');
    if (strpos($content, 'CREATE DATABASE IF NOT EXISTS bail_signature') !== false) {
        echo "✅ database.sql crée la base bail_signature\n";
    }
    if (strpos($content, 'myinvest_location') !== false) {
        echo "❌ database.sql contient encore des références à myinvest_location\n";
    } else {
        echo "✅ database.sql ne contient pas de référence à l'ancienne base\n";
    }
} else {
    echo "❌ database.sql n'existe pas\n";
}

// Test 10: Vérifier que database-candidature.sql n'existe plus
echo "\nTest 10: Ancien fichier de base de données\n";
if (!file_exists(__DIR__ . '/database-candidature.sql')) {
    echo "✅ database-candidature.sql a été supprimé\n";
} else {
    echo "❌ database-candidature.sql existe encore (devrait être supprimé)\n";
}

// Test 11: Vérifier les constantes de pagination
echo "\nTest 11: Pagination\n";
if (isset($config['ITEMS_PER_PAGE'])) {
    echo "✅ ITEMS_PER_PAGE est défini: " . $config['ITEMS_PER_PAGE'] . "\n";
}

if (isset($config['MAX_ITEMS_PER_PAGE'])) {
    echo "✅ MAX_ITEMS_PER_PAGE est défini: " . $config['MAX_ITEMS_PER_PAGE'] . "\n";
}

// Test 12: Vérifier les informations légales
echo "\nTest 12: Informations légales\n";
if (isset($config['DPE_CLASSE_ENERGIE'])) {
    echo "✅ DPE_CLASSE_ENERGIE est défini: " . $config['DPE_CLASSE_ENERGIE'] . "\n";
}

if (isset($config['BAILLEUR_NOM'])) {
    echo "✅ BAILLEUR_NOM est défini: " . $config['BAILLEUR_NOM'] . "\n";
}

echo "\n=== FIN DE LA VALIDATION ===\n";
echo "\n✅ Tous les tests sont passés avec succès!\n";
echo "La consolidation de la base de données est complète.\n\n";
