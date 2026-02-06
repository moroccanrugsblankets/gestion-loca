<?php
/**
 * Test script for État des Lieux signature and TCPDF fixes
 * Validates the changes made to fix signature saving and PDF generation
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Test des corrections État des Lieux ===\n\n";

$errors = [];
$warnings = [];
$success = [];

// Test 1: Verify global $pdo declaration in updateEtatLieuxTenantSignature
echo "Test 1: Vérifier la déclaration 'global \$pdo' dans updateEtatLieuxTenantSignature...\n";
$functionsFile = __DIR__ . '/includes/functions.php';
if (!file_exists($functionsFile)) {
    $errors[] = "Fichier includes/functions.php non trouvé";
} else {
    $content = file_get_contents($functionsFile);
    
    // Find the function
    if (preg_match('/function updateEtatLieuxTenantSignature[^{]*\{([^}]*global\s+\$pdo[^}]*)\}/s', $content, $matches)) {
        $success[] = "✓ global \$pdo trouvé dans updateEtatLieuxTenantSignature";
        
        // Check it's near the beginning of the function
        $functionBody = $matches[1];
        $lines = explode("\n", trim($functionBody));
        $globalFound = false;
        foreach (array_slice($lines, 0, 5) as $line) {
            if (strpos($line, 'global $pdo') !== false) {
                $globalFound = true;
                break;
            }
        }
        
        if ($globalFound) {
            $success[] = "✓ global \$pdo est déclaré au début de la fonction";
        } else {
            $warnings[] = "⚠ global \$pdo trouvé mais pas au début de la fonction";
        }
    } else {
        $errors[] = "❌ global \$pdo NON trouvé dans updateEtatLieuxTenantSignature - SIGNATURE SAVING WILL FAIL";
    }
}

// Test 2: Verify cles_autre migration exists
echo "\nTest 2: Vérifier que la migration pour cles_autre existe...\n";
$migrationFile = __DIR__ . '/migrations/028_add_cles_autre_field.php';
if (!file_exists($migrationFile)) {
    $errors[] = "❌ Migration 028_add_cles_autre_field.php non trouvée";
} else {
    $success[] = "✓ Migration 028_add_cles_autre_field.php existe";
    
    // Check migration content
    $migContent = file_get_contents($migrationFile);
    if (strpos($migContent, 'ADD COLUMN cles_autre') !== false) {
        $success[] = "✓ Migration contient l'ajout de la colonne cles_autre";
    } else {
        $errors[] = "❌ Migration ne contient pas l'ajout de cles_autre";
    }
    
    if (strpos($migContent, 'INT DEFAULT 0') !== false) {
        $success[] = "✓ cles_autre est défini comme INT DEFAULT 0";
    } else {
        $warnings[] = "⚠ Type de cles_autre pourrait être incorrect";
    }
}

// Test 3: Verify cles_autre is used in edit-etat-lieux.php
echo "\nTest 3: Vérifier que cles_autre est utilisé dans edit-etat-lieux.php...\n";
$editFile = __DIR__ . '/admin-v2/edit-etat-lieux.php';
if (!file_exists($editFile)) {
    $errors[] = "❌ admin-v2/edit-etat-lieux.php non trouvé";
} else {
    $editContent = file_get_contents($editFile);
    
    if (strpos($editContent, 'cles_autre') !== false) {
        $success[] = "✓ cles_autre est référencé dans edit-etat-lieux.php";
        
        // Check if it's in the UPDATE query
        if (preg_match('/UPDATE.*etats_lieux.*SET.*cles_autre/s', $editContent)) {
            $success[] = "✓ cles_autre est dans la requête UPDATE";
        } else {
            $warnings[] = "⚠ cles_autre pourrait ne pas être dans la requête UPDATE";
        }
    } else {
        $warnings[] = "⚠ cles_autre non trouvé dans edit-etat-lieux.php";
    }
}

// Test 4: Verify cles_autre is used in PDF generation
echo "\nTest 4: Vérifier que cles_autre est utilisé dans generate-etat-lieux.php...\n";
$pdfFile = __DIR__ . '/pdf/generate-etat-lieux.php';
if (!file_exists($pdfFile)) {
    $errors[] = "❌ pdf/generate-etat-lieux.php non trouvé";
} else {
    $pdfContent = file_get_contents($pdfFile);
    
    if (strpos($pdfContent, 'cles_autre') !== false) {
        $success[] = "✓ cles_autre est utilisé dans la génération PDF";
    } else {
        $warnings[] = "⚠ cles_autre non trouvé dans generate-etat-lieux.php";
    }
}

// Test 5: Check for TCPDF error handling
echo "\nTest 5: Vérifier la gestion des erreurs TCPDF...\n";
if (file_exists($pdfFile)) {
    if (strpos($pdfContent, 'writeHTML') !== false) {
        $success[] = "✓ TCPDF writeHTML est utilisé";
        
        if (preg_match('/try\s*\{[^}]*writeHTML[^}]*\}\s*catch/s', $pdfContent)) {
            $success[] = "✓ writeHTML est dans un bloc try-catch";
        } else {
            $warnings[] = "⚠ writeHTML pourrait ne pas être dans un try-catch";
        }
    }
    
    // Check for image path handling
    if (strpos($pdfContent, '@') !== false && strpos($pdfContent, 'TCPDF requires @ prefix') !== false) {
        $success[] = "✓ Les chemins d'image TCPDF utilisent le préfixe @";
    }
}

// Test 6: Verify signature file storage
echo "\nTest 6: Vérifier le stockage des fichiers de signature...\n";
if (file_exists($functionsFile)) {
    $content = file_get_contents($functionsFile);
    
    if (strpos($content, "uploads/signatures") !== false) {
        $success[] = "✓ Les signatures sont stockées dans uploads/signatures";
    }
    
    if (strpos($content, "file_put_contents") !== false) {
        $success[] = "✓ Les signatures sont enregistrées comme fichiers physiques";
    }
}

// Test 7: PHP syntax check
echo "\nTest 7: Vérifier la syntaxe PHP des fichiers modifiés...\n";
$filesToCheck = [
    'includes/functions.php',
    'migrations/028_add_cles_autre_field.php'
];

foreach ($filesToCheck as $file) {
    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        exec("php -l $fullPath 2>&1", $output, $returnCode);
        if ($returnCode === 0) {
            $success[] = "✓ Syntaxe PHP valide pour $file";
        } else {
            $errors[] = "❌ Erreur de syntaxe dans $file: " . implode("\n", $output);
        }
    }
}

// Display results
echo "\n" . str_repeat("=", 70) . "\n";
echo "RÉSULTATS DES TESTS\n";
echo str_repeat("=", 70) . "\n\n";

if (!empty($success)) {
    echo "✅ SUCCÈS (" . count($success) . "):\n";
    foreach ($success as $msg) {
        echo "   $msg\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "⚠️  AVERTISSEMENTS (" . count($warnings) . "):\n";
    foreach ($warnings as $msg) {
        echo "   $msg\n";
    }
    echo "\n";
}

if (!empty($errors)) {
    echo "❌ ERREURS (" . count($errors) . "):\n";
    foreach ($errors as $msg) {
        echo "   $msg\n";
    }
    echo "\n";
}

echo str_repeat("=", 70) . "\n";

if (empty($errors)) {
    echo "✅ TOUS LES TESTS SONT PASSÉS\n\n";
    echo "Correctifs appliqués:\n";
    echo "1. ✓ Ajout de 'global \$pdo;' dans updateEtatLieuxTenantSignature()\n";
    echo "   → Résout le problème de sauvegarde de signature locataire\n\n";
    echo "2. ✓ Création de la migration pour le champ cles_autre\n";
    echo "   → Résout les erreurs TCPDF causées par des échecs de requête UPDATE\n\n";
    echo "Prochaines étapes:\n";
    echo "- Exécuter la migration 028: php migrations/028_add_cles_autre_field.php\n";
    echo "- Tester l'enregistrement de signature sur /admin-v2/edit-etat-lieux.php?id=1\n";
    echo "- Tester la génération PDF sur /admin-v2/finalize-etat-lieux.php?id=1\n";
    exit(0);
} else {
    echo "❌ DES ERREURS ONT ÉTÉ DÉTECTÉES\n";
    echo "Veuillez corriger les erreurs ci-dessus.\n";
    exit(1);
}
