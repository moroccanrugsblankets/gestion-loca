<?php
/**
 * Test de configuration et diagnostic
 * My Invest Immobilier
 */

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Test</title></head><body>";
echo "<h1>Test de Configuration</h1>";

// Test 1: PHP Version
echo "<h2>1. Version PHP</h2>";
echo "<p>Version: " . phpversion() . "</p>";

// Test 2: Extensions
echo "<h2>2. Extensions PHP</h2>";
$required_extensions = ['pdo', 'pdo_mysql', 'mbstring', 'fileinfo', 'gd'];
foreach ($required_extensions as $ext) {
    $status = extension_loaded($ext) ? '✓ OK' : '✗ MANQUANT';
    echo "<p>$ext: $status</p>";
}

// Test 3: Config
echo "<h2>3. Configuration</h2>";
require_once __DIR__ . '/includes/config.php';
echo "<p>✓ config.php chargé</p>";

// Test 4: Base de données
echo "<h2>4. Connexion Base de Données</h2>";
try {
    require_once __DIR__ . '/includes/db.php';
    $pdo = getDbConnection();
    echo "<p>✓ Connexion réussie</p>";
    
    // Test une requête simple
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM logements");
    $result = $stmt->fetch();
    echo "<p>✓ Nombre de logements: " . $result['count'] . "</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 5: Functions
echo "<h2>5. Functions</h2>";
try {
    require_once __DIR__ . '/includes/functions.php';
    echo "<p>✓ functions.php chargé</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 6: Dossiers
echo "<h2>6. Dossiers</h2>";
$dirs = [
    'uploads' => UPLOAD_DIR,
    'pdf' => PDF_DIR
];
foreach ($dirs as $name => $dir) {
    $exists = is_dir($dir) ? '✓ Existe' : '✗ N\'existe pas';
    $writable = is_writable($dir) ? '✓ Writable' : '✗ Non writable';
    echo "<p>$name: $exists, $writable</p>";
}

// Test 7: Erreurs dans le log
echo "<h2>7. Dernières erreurs</h2>";
$error_log = dirname(__FILE__) . '/error.log';
if (file_exists($error_log)) {
    $errors = file_get_contents($error_log);
    $lines = explode("\n", $errors);
    $last_errors = array_slice($lines, -10);
    echo "<pre>" . htmlspecialchars(implode("\n", $last_errors)) . "</pre>";
} else {
    echo "<p>Pas de fichier error.log</p>";
}

echo "</body></html>";
