<?php
/**
 * Comprehensive validation test for État des Lieux fixes
 * Tests both signature saving and PDF generation
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

echo "=== Validation Complète des Corrections État des Lieux ===\n\n";

$errors = [];
$warnings = [];
$success = [];

// Test 1: Verify database connection
echo "Test 1: Vérifier la connexion à la base de données...\n";
try {
    if (!isset($pdo)) {
        $errors[] = "❌ ERREUR: Variable \$pdo non définie";
    } else {
        $stmt = $pdo->query("SELECT 1");
        $success[] = "✓ Connexion à la base de données OK";
    }
} catch (Exception $e) {
    $errors[] = "❌ ERREUR de connexion DB: " . $e->getMessage();
}

// Test 2: Check if etat_lieux_locataires table exists
echo "Test 2: Vérifier que la table etat_lieux_locataires existe...\n";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'etat_lieux_locataires'");
    if ($stmt->rowCount() > 0) {
        $success[] = "✓ Table etat_lieux_locataires existe";
    } else {
        $errors[] = "❌ ERREUR: Table etat_lieux_locataires n'existe pas";
    }
} catch (Exception $e) {
    $errors[] = "❌ ERREUR: " . $e->getMessage();
}

// Test 3: Check if signature_data column exists
echo "Test 3: Vérifier que la colonne signature_data existe...\n";
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM etat_lieux_locataires LIKE 'signature_data'");
    if ($stmt->rowCount() > 0) {
        $success[] = "✓ Colonne signature_data existe";
    } else {
        $errors[] = "❌ ERREUR: Colonne signature_data n'existe pas";
    }
} catch (Exception $e) {
    $errors[] = "❌ ERREUR: " . $e->getMessage();
}

// Test 4: Check uploads directory
echo "Test 4: Vérifier le répertoire uploads/signatures...\n";
$uploadsDir = __DIR__ . '/uploads/signatures';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
    $success[] = "✓ Répertoire uploads/signatures créé";
} else {
    $success[] = "✓ Répertoire uploads/signatures existe";
}

if (!is_writable($uploadsDir)) {
    $errors[] = "❌ ERREUR: Répertoire uploads/signatures non accessible en écriture";
} else {
    $success[] = "✓ Répertoire uploads/signatures accessible en écriture";
}

// Test 5: Verify updateEtatLieuxTenantSignature function
echo "Test 5: Vérifier la fonction updateEtatLieuxTenantSignature...\n";
if (file_exists(__DIR__ . '/includes/functions.php')) {
    require_once __DIR__ . '/includes/functions.php';
    if (function_exists('updateEtatLieuxTenantSignature')) {
        $success[] = "✓ Fonction updateEtatLieuxTenantSignature existe";
        
        // Check for global $pdo
        $funcCode = file_get_contents(__DIR__ . '/includes/functions.php');
        if (preg_match('/function updateEtatLieuxTenantSignature[^{]*\{[^}]*global\s+\$pdo/s', $funcCode)) {
            $success[] = "✓ Fonction utilise global \$pdo (fix appliqué)";
        } else {
            $errors[] = "❌ ERREUR: Fonction n'utilise pas global \$pdo - les signatures ne seront pas enregistrées";
        }
    } else {
        $errors[] = "❌ ERREUR: Fonction updateEtatLieuxTenantSignature n'existe pas";
    }
} else {
    $errors[] = "❌ ERREUR: Fichier includes/functions.php non trouvé";
}

// Test 6: Verify generateEtatDesLieuxPDF function
echo "Test 6: Vérifier la fonction generateEtatDesLieuxPDF...\n";
if (file_exists(__DIR__ . '/pdf/generate-etat-lieux.php')) {
    $pdfCode = file_get_contents(__DIR__ . '/pdf/generate-etat-lieux.php');
    if (preg_match('/function generateEtatDesLieuxPDF/', $pdfCode)) {
        $success[] = "✓ Fonction generateEtatDesLieuxPDF existe";
    } else {
        $errors[] = "❌ ERREUR: Fonction generateEtatDesLieuxPDF n'existe pas";
    }
    
    // Check no @ prefix with local paths
    if (!preg_match('/@.*fullPath|@.*dirname\(__DIR__\)/', $pdfCode)) {
        $success[] = "✓ Pas de préfixe @ avec chemins locaux (fix TCPDF appliqué)";
    } else {
        $errors[] = "❌ ERREUR: Préfixe @ trouvé - causera des erreurs TCPDF";
    }
    
    // Check for SITE_URL usage
    if (preg_match('/SITE_URL/', $pdfCode)) {
        $success[] = "✓ Utilisation de SITE_URL pour les URLs publiques";
    } else {
        $warnings[] = "⚠️ SITE_URL pourrait ne pas être utilisé";
    }
} else {
    $errors[] = "❌ ERREUR: Fichier pdf/generate-etat-lieux.php non trouvé";
}

// Test 7: Check TCPDF library
echo "Test 7: Vérifier la bibliothèque TCPDF...\n";
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    if (class_exists('TCPDF')) {
        $success[] = "✓ TCPDF est disponible";
    } else {
        $errors[] = "❌ ERREUR: Classe TCPDF non trouvée";
    }
} else {
    $warnings[] = "⚠️ vendor/autoload.php non trouvé - vérifier Composer";
}

// Test 8: Check config SITE_URL
echo "Test 8: Vérifier la configuration SITE_URL...\n";
if (isset($config['SITE_URL']) && !empty($config['SITE_URL'])) {
    $success[] = "✓ SITE_URL configuré: " . $config['SITE_URL'];
} else {
    $errors[] = "❌ ERREUR: SITE_URL non configuré - les URLs publiques ne fonctionneront pas";
}

// Display results
echo "\n=== RÉSUMÉ DE LA VALIDATION ===\n\n";

if (!empty($success)) {
    echo "✅ Succès (" . count($success) . "):\n";
    foreach ($success as $msg) {
        echo "  " . $msg . "\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "⚠️ Avertissements (" . count($warnings) . "):\n";
    foreach ($warnings as $msg) {
        echo "  " . $msg . "\n";
    }
    echo "\n";
}

if (!empty($errors)) {
    echo "❌ Erreurs (" . count($errors) . "):\n";
    foreach ($errors as $msg) {
        echo "  " . $msg . "\n";
    }
    echo "\n";
    echo "Des corrections sont nécessaires avant déploiement.\n";
    exit(1);
}

echo "✅ VALIDATION COMPLÈTE RÉUSSIE\n\n";
echo "Les corrections suivantes ont été appliquées:\n";
echo "  1. ✓ Fonction updateEtatLieuxTenantSignature utilise global \$pdo\n";
echo "  2. ✓ PDF génération utilise des URLs publiques au lieu de chemins locaux\n";
echo "  3. ✓ Répertoire uploads/signatures configuré correctement\n";
echo "\nProblèmes résolus:\n";
echo "  - Enregistrement de la signature sur /admin-v2/edit-etat-lieux.php\n";
echo "  - Erreur TCPDF sur /admin-v2/finalize-etat-lieux.php\n";
exit(0);
