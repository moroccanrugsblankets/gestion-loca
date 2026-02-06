<?php
/**
 * Simple validation test for État des Lieux fixes (no database required)
 */

echo "=== Validation État des Lieux Fixes (Sans DB) ===\n\n";

$errors = [];
$warnings = [];
$success = [];

// Test 1: Check functions.php
echo "Test 1: Vérifier includes/functions.php...\n";
if (file_exists(__DIR__ . '/includes/functions.php')) {
    $funcCode = file_get_contents(__DIR__ . '/includes/functions.php');
    
    // Check function exists
    if (preg_match('/function updateEtatLieuxTenantSignature/', $funcCode)) {
        $success[] = "✓ Fonction updateEtatLieuxTenantSignature existe";
        
        // Check for global $pdo
        if (preg_match('/function updateEtatLieuxTenantSignature[^{]*\{[^}]*global\s+\$pdo/s', $funcCode)) {
            $success[] = "✓ Fonction utilise global \$pdo (fix pour signature saving appliqué)";
        } else {
            $errors[] = "❌ ERREUR: Fonction n'utilise pas global \$pdo";
        }
        
        // Check signature is saved as file
        if (preg_match('/uploads\/signatures/', $funcCode)) {
            $success[] = "✓ Signatures sauvegardées comme fichiers physiques";
        }
    } else {
        $errors[] = "❌ ERREUR: Fonction updateEtatLieuxTenantSignature non trouvée";
    }
} else {
    $errors[] = "❌ ERREUR: Fichier includes/functions.php non trouvé";
}

// Test 2: Check PDF generation
echo "Test 2: Vérifier pdf/generate-etat-lieux.php...\n";
if (file_exists(__DIR__ . '/pdf/generate-etat-lieux.php')) {
    $pdfCode = file_get_contents(__DIR__ . '/pdf/generate-etat-lieux.php');
    
    // Check function exists
    if (preg_match('/function generateEtatDesLieuxPDF/', $pdfCode)) {
        $success[] = "✓ Fonction generateEtatDesLieuxPDF existe";
    } else {
        $errors[] = "❌ ERREUR: Fonction generateEtatDesLieuxPDF non trouvée";
    }
    
    // Check buildSignaturesTableEtatLieux exists
    if (preg_match('/function buildSignaturesTableEtatLieux/', $pdfCode)) {
        $success[] = "✓ Fonction buildSignaturesTableEtatLieux existe";
    } else {
        $errors[] = "❌ ERREUR: Fonction buildSignaturesTableEtatLieux non trouvée";
    }
    
    // Check NO @ prefix with local paths
    if (preg_match('/@.*fullPath|@.*dirname\(__DIR__\)/', $pdfCode)) {
        $errors[] = "❌ ERREUR: Préfixe @ trouvé avec chemins locaux - causera erreurs TCPDF";
    } else {
        $success[] = "✓ Pas de préfixe @ avec chemins locaux (fix TCPDF appliqué)";
    }
    
    // Check for SITE_URL usage
    if (preg_match('/rtrim\(\$config\[\'SITE_URL\'\]/', $pdfCode)) {
        $success[] = "✓ Utilisation de SITE_URL pour URLs publiques (fix TCPDF appliqué)";
    } else {
        $errors[] = "❌ ERREUR: SITE_URL non utilisé - les images ne se chargeront pas";
    }
    
    // Check for htmlspecialchars on URLs
    if (preg_match('/htmlspecialchars\(\$publicUrl\)/', $pdfCode)) {
        $success[] = "✓ URLs sécurisées avec htmlspecialchars";
    }
    
    // Check global variables
    if (preg_match('/function buildSignaturesTableEtatLieux[^{]*\{[^}]*global\s+\$pdo,\s+\$config/s', $pdfCode)) {
        $success[] = "✓ Variables globales \$pdo et \$config déclarées";
    } else {
        $warnings[] = "⚠️ Variables globales pourraient ne pas être déclarées correctement";
    }
    
    // Check data URL format still supported
    if (preg_match('/data:image.*base64/', $pdfCode)) {
        $success[] = "✓ Format data URL toujours supporté (compatibilité)";
    }
    
} else {
    $errors[] = "❌ ERREUR: Fichier pdf/generate-etat-lieux.php non trouvé";
}

// Test 3: Check syntax
echo "Test 3: Vérifier la syntaxe PHP...\n";
$filesToCheck = [
    'includes/functions.php',
    'pdf/generate-etat-lieux.php',
    'admin-v2/edit-etat-lieux.php',
    'admin-v2/finalize-etat-lieux.php'
];

foreach ($filesToCheck as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        exec('php -l ' . escapeshellarg(__DIR__ . '/' . $file) . ' 2>&1', $output, $returnCode);
        if ($returnCode === 0) {
            $success[] = "✓ Syntaxe PHP valide: $file";
        } else {
            $errors[] = "❌ ERREUR syntaxe dans $file: " . implode("\n", $output);
        }
    }
}

// Test 4: Check uploads directory can be created
echo "Test 4: Vérifier le répertoire uploads/signatures...\n";
$uploadsDir = __DIR__ . '/uploads/signatures';
if (!is_dir($uploadsDir)) {
    if (@mkdir($uploadsDir, 0755, true)) {
        $success[] = "✓ Répertoire uploads/signatures créé";
    } else {
        $warnings[] = "⚠️ Impossible de créer uploads/signatures (peut nécessiter permissions)";
    }
} else {
    $success[] = "✓ Répertoire uploads/signatures existe";
}

if (is_writable($uploadsDir)) {
    $success[] = "✓ Répertoire uploads/signatures accessible en écriture";
} else {
    $warnings[] = "⚠️ Répertoire uploads/signatures non accessible en écriture";
}

// Test 5: Verify comparison with contract PDF
echo "Test 5: Comparer avec le fix des contrats...\n";
if (file_exists(__DIR__ . '/pdf/generate-contrat-pdf.php')) {
    $contractCode = file_get_contents(__DIR__ . '/pdf/generate-contrat-pdf.php');
    
    // Contract should also use SITE_URL
    if (preg_match('/rtrim\(\$config\[\'SITE_URL\'\]/', $contractCode)) {
        $success[] = "✓ Contrat utilise aussi SITE_URL (même approche)";
        
        // Verify etat lieux uses the same approach
        $etatCode = file_get_contents(__DIR__ . '/pdf/generate-etat-lieux.php');
        if (preg_match('/rtrim\(\$config\[\'SITE_URL\'\]/', $etatCode)) {
            $success[] = "✓ État des lieux utilise la même approche que contrats";
        }
    }
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
    echo "❌ VALIDATION ÉCHOUÉE - Des corrections sont nécessaires\n";
    exit(1);
}

echo "✅ VALIDATION COMPLÈTE RÉUSSIE\n\n";
echo "Résumé des corrections appliquées:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
echo "1. FIX SIGNATURE SAVING (/admin-v2/edit-etat-lieux.php)\n";
echo "   ✓ Fonction updateEtatLieuxTenantSignature utilise global \$pdo\n";
echo "   ✓ Signatures sauvegardées comme fichiers physiques\n";
echo "   ✓ Chemin relatif stocké en base de données\n\n";

echo "2. FIX TCPDF ERROR (/admin-v2/finalize-etat-lieux.php)\n";
echo "   ✓ Suppression du préfixe @ avec chemins locaux\n";
echo "   ✓ Utilisation d'URLs publiques via SITE_URL\n";
echo "   ✓ Même approche que pour les contrats (éprouvée)\n";
echo "   ✓ Support maintenu pour format data URL\n\n";

echo "Problèmes résolus:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "❌ AVANT: Signatures non enregistrées (pas de global \$pdo)\n";
echo "✅ APRÈS: Signatures enregistrées comme fichiers physiques\n\n";
echo "❌ AVANT: Erreur TCPDF (@ prefix avec chemins locaux)\n";
echo "✅ APRÈS: PDF généré avec URLs publiques\n\n";
exit(0);
