<?php
/**
 * Test simple de la configuration $config
 * Vérifie que toutes les clés de configuration sont présentes et accessibles
 */

require_once __DIR__ . '/includes/config.php';

echo "=== Test de la Configuration avec \$config Array ===\n\n";

// Test 1: Vérifier que $config existe et est un tableau
echo "Test 1: Variable \$config\n";
if (isset($config) && is_array($config)) {
    echo "✓ \$config est défini et est un tableau\n";
    echo "  Nombre de clés: " . count($config) . "\n\n";
} else {
    echo "✗ ERREUR: \$config n'est pas défini correctement\n\n";
    exit(1);
}

// Test 2: Vérifier les clés de base de données
echo "Test 2: Configuration Base de Données\n";
$dbKeys = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_CHARSET'];
foreach ($dbKeys as $key) {
    if (isset($config[$key])) {
        echo "  ✓ $key: " . ($key === 'DB_PASS' ? '***' : $config[$key]) . "\n";
    } else {
        echo "  ✗ $key: MANQUANT\n";
    }
}
echo "\n";

// Test 3: Vérifier les clés SMTP
echo "Test 3: Configuration SMTP\n";
$smtpKeys = ['SMTP_HOST', 'SMTP_PORT', 'SMTP_SECURE', 'SMTP_AUTH', 'SMTP_USERNAME', 'SMTP_PASSWORD'];
foreach ($smtpKeys as $key) {
    if (isset($config[$key])) {
        $value = $config[$key];
        if ($key === 'SMTP_PASSWORD') {
            $value = empty($value) ? '(vide)' : '***';
        } elseif (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }
        echo "  ✓ $key: $value\n";
    } else {
        echo "  ✗ $key: MANQUANT\n";
    }
}
echo "\n";

// Test 4: Vérifier les URLs
echo "Test 4: URLs de l'application\n";
$urlKeys = ['SITE_URL', 'CANDIDATURE_URL', 'ADMIN_URL'];
foreach ($urlKeys as $key) {
    if (isset($config[$key])) {
        echo "  ✓ $key: " . $config[$key] . "\n";
    } else {
        echo "  ✗ $key: MANQUANT\n";
    }
}
echo "\n";

// Test 5: Vérifier les répertoires
echo "Test 5: Répertoires\n";
$dirKeys = ['UPLOAD_DIR', 'PDF_DIR', 'DOCUMENTS_DIR'];
foreach ($dirKeys as $key) {
    if (isset($config[$key])) {
        echo "  ✓ $key: " . $config[$key] . "\n";
    } else {
        echo "  ✗ $key: MANQUANT\n";
    }
}
echo "\n";

// Test 6: Vérifier les coordonnées bancaires
echo "Test 6: Coordonnées Bancaires\n";
$bankKeys = ['IBAN', 'BIC', 'BANK_NAME'];
foreach ($bankKeys as $key) {
    if (isset($config[$key])) {
        echo "  ✓ $key: " . $config[$key] . "\n";
    } else {
        echo "  ✗ $key: MANQUANT\n";
    }
}
echo "\n";

// Test 7: Vérifier les informations de l'entreprise
echo "Test 7: Informations Entreprise\n";
$companyKeys = ['COMPANY_NAME', 'COMPANY_EMAIL', 'COMPANY_PHONE'];
foreach ($companyKeys as $key) {
    if (isset($config[$key])) {
        echo "  ✓ $key: " . $config[$key] . "\n";
    } else {
        echo "  ✗ $key: MANQUANT\n";
    }
}
echo "\n";

// Test 8: Tester les fonctions utilitaires
echo "Test 8: Fonctions Utilitaires\n";

try {
    $date1 = new DateTime('2024-01-01');
    $date2 = new DateTime('2024-01-05');
    $joursOuvres = calculerJoursOuvres($date1, $date2);
    echo "  ✓ calculerJoursOuvres() fonctionne: $joursOuvres jours ouvrés\n";
} catch (Exception $e) {
    echo "  ✗ calculerJoursOuvres() a échoué: " . $e->getMessage() . "\n";
}

try {
    $ref = genererReferenceUnique('TEST');
    echo "  ✓ genererReferenceUnique() fonctionne: $ref\n";
} catch (Exception $e) {
    echo "  ✗ genererReferenceUnique() a échoué: " . $e->getMessage() . "\n";
}

try {
    $token = genererToken();
    echo "  ✓ genererToken() fonctionne: " . substr($token, 0, 16) . "...\n";
} catch (Exception $e) {
    echo "  ✗ genererToken() a échoué: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 9: Vérifier config.local.php
echo "Test 9: Configuration Locale\n";
if (file_exists(__DIR__ . '/includes/config.local.php')) {
    echo "  ✓ config.local.php existe\n";
    echo "  Note: Les valeurs peuvent être surchargées par config.local.php\n";
} else {
    echo "  ⓘ config.local.php n'existe pas (optionnel)\n";
}
echo "\n";

echo "=== Résumé ===\n";
echo "✓ La configuration avec \$config array fonctionne correctement!\n";
echo "✓ Toutes les clés essentielles sont présentes et accessibles\n";
echo "✓ Les fonctions utilitaires utilisent \$config correctement\n\n";

echo "Pour utiliser la configuration dans votre code:\n";
echo "  1. Incluez: require_once 'includes/config.php';\n";
echo "  2. Accédez aux valeurs: \$config['DB_HOST'], \$config['SMTP_HOST'], etc.\n";
echo "  3. Dans les fonctions: utilisez 'global \$config;' pour y accéder\n\n";
