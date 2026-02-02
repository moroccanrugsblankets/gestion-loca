#!/usr/bin/env php
<?php
/**
 * Test de Validation des Fichiers (Sans Base de Données)
 * Vérifie que tous les fichiers ont été correctement modifiés
 */

echo "=== Validation des Corrections - Templates Email Finalisation ===\n\n";

$errors = [];
$warnings = [];
$success = [];

// Test 1: Vérifier que les fichiers existent
echo "Test 1: Vérification de l'existence des fichiers...\n";

$requiredFiles = [
    'migrations/022_add_contract_finalisation_email_templates.sql',
    'signature/step3-documents.php',
    'pdf/generate-bail.php',
    'init-email-templates.php',
    'FIX_EMAIL_TEMPLATES_FINALISATION.md'
];

foreach ($requiredFiles as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        $success[] = "✓ Fichier existe: $file";
    } else {
        $errors[] = "✗ Fichier manquant: $file";
    }
}

// Test 2: Vérifier la migration 022
echo "\nTest 2: Validation de la migration 022...\n";
$migrationFile = __DIR__ . '/migrations/022_add_contract_finalisation_email_templates.sql';
if (file_exists($migrationFile)) {
    $content = file_get_contents($migrationFile);
    
    // Vérifier les templates
    if (strpos($content, 'contrat_finalisation_client') !== false) {
        $success[] = "✓ Migration contient le template client";
    } else {
        $errors[] = "✗ Migration ne contient pas le template client";
    }
    
    if (strpos($content, 'contrat_finalisation_admin') !== false) {
        $success[] = "✓ Migration contient le template admin";
    } else {
        $errors[] = "✗ Migration ne contient pas le template admin";
    }
    
    // Vérifier les variables
    $requiredVarsClient = ['{{nom}}', '{{prenom}}', '{{reference}}', '{{depot_garantie}}', '{{signature}}'];
    foreach ($requiredVarsClient as $var) {
        if (strpos($content, $var) !== false) {
            // Variable trouvée
        } else {
            $warnings[] = "⚠ Variable manquante dans migration: $var";
        }
    }
    
    // Vérifier la syntaxe SQL
    if (strpos($content, 'INSERT INTO email_templates') !== false) {
        $success[] = "✓ Migration contient INSERT INTO email_templates";
    } else {
        $errors[] = "✗ Migration ne contient pas INSERT INTO email_templates";
    }
    
    if (strpos($content, 'ON DUPLICATE KEY UPDATE') !== false) {
        $success[] = "✓ Migration utilise ON DUPLICATE KEY UPDATE (sécurisé)";
    }
}

// Test 3: Vérifier step3-documents.php
echo "\nTest 3: Validation de signature/step3-documents.php...\n";
$step3File = __DIR__ . '/signature/step3-documents.php';
if (file_exists($step3File)) {
    $content = file_get_contents($step3File);
    
    // Vérifier qu'il utilise sendTemplatedEmail
    if (preg_match('/sendTemplatedEmail\s*\(\s*[\'"]contrat_finalisation_client[\'"]/', $content)) {
        $success[] = "✓ Utilise sendTemplatedEmail() pour le client";
    } else {
        $errors[] = "✗ N'utilise pas sendTemplatedEmail() pour le client";
    }
    
    if (preg_match('/sendTemplatedEmail\s*\(\s*[\'"]contrat_finalisation_admin[\'"]/', $content)) {
        $success[] = "✓ Utilise sendTemplatedEmail() pour l'admin";
    } else {
        $errors[] = "✗ N'utilise pas sendTemplatedEmail() pour l'admin";
    }
    
    // Vérifier qu'il ne utilise plus l'ancienne fonction
    if (preg_match('/getFinalisationEmailTemplate\s*\(/', $content)) {
        $warnings[] = "⚠ Contient encore un appel à getFinalisationEmailTemplate()";
    } else {
        $success[] = "✓ N'utilise plus getFinalisationEmailTemplate()";
    }
    
    // Vérifier les variables passées
    if (strpos($content, '$variables') !== false) {
        $success[] = "✓ Prépare les variables pour le template";
    }
    
    if (strpos($content, "['nom']") !== false && strpos($content, "['prenom']") !== false) {
        $success[] = "✓ Passe les variables nom et prenom";
    }
}

// Test 4: Vérifier pdf/generate-bail.php
echo "\nTest 4: Validation de pdf/generate-bail.php...\n";
$pdfFile = __DIR__ . '/pdf/generate-bail.php';
if (file_exists($pdfFile)) {
    $content = file_get_contents($pdfFile);
    
    // Vérifier qu'il utilise getParameter pour la signature
    if (strpos($content, "getParameter('signature_societe_enabled'") !== false) {
        $success[] = "✓ Vérifie le paramètre signature_societe_enabled";
    } else {
        $errors[] = "✗ Ne vérifie pas signature_societe_enabled";
    }
    
    if (strpos($content, "getParameter('signature_societe_image'") !== false) {
        $success[] = "✓ Récupère le paramètre signature_societe_image";
    } else {
        $errors[] = "✗ Ne récupère pas signature_societe_image";
    }
    
    // Vérifier qu'il vérifie le statut validé
    if (strpos($content, "statut") !== false && strpos($content, "valide") !== false) {
        $success[] = "✓ Vérifie le statut du contrat";
    } else {
        $warnings[] = "⚠ Ne semble pas vérifier le statut du contrat";
    }
    
    // Vérifier qu'il affiche la signature
    if (strpos($content, 'signature-image') !== false || strpos($content, 'Signature électronique') !== false) {
        $success[] = "✓ Affiche la signature électronique dans le PDF";
    } else {
        $warnings[] = "⚠ Ne semble pas afficher la signature électronique";
    }
}

// Test 5: Vérifier init-email-templates.php
echo "\nTest 5: Validation de init-email-templates.php...\n";
$initFile = __DIR__ . '/init-email-templates.php';
if (file_exists($initFile)) {
    $content = file_get_contents($initFile);
    
    if (strpos($content, "'identifiant' => 'contrat_finalisation_client'") !== false) {
        $success[] = "✓ init-email-templates.php contient le template client";
    } else {
        $errors[] = "✗ init-email-templates.php ne contient pas le template client";
    }
    
    if (strpos($content, "'identifiant' => 'contrat_finalisation_admin'") !== false) {
        $success[] = "✓ init-email-templates.php contient le template admin";
    } else {
        $errors[] = "✗ init-email-templates.php ne contient pas le template admin";
    }
}

// Test 6: Vérifier la documentation
echo "\nTest 6: Validation de la documentation...\n";
$docFile = __DIR__ . '/FIX_EMAIL_TEMPLATES_FINALISATION.md';
if (file_exists($docFile)) {
    $content = file_get_contents($docFile);
    
    if (strlen($content) > 5000) {
        $success[] = "✓ Documentation complète (" . number_format(strlen($content)) . " caractères)";
    } else {
        $warnings[] = "⚠ Documentation semble incomplète";
    }
    
    $sections = ['Installation', 'Configuration', 'Test', 'Dépannage'];
    foreach ($sections as $section) {
        if (stripos($content, $section) !== false) {
            // Section trouvée
        } else {
            $warnings[] = "⚠ Section '$section' manquante dans la documentation";
        }
    }
}

// Afficher les résultats
echo "\n" . str_repeat("=", 70) . "\n";
echo "RÉSULTATS DE LA VALIDATION\n";
echo str_repeat("=", 70) . "\n\n";

if (!empty($success)) {
    echo "✅ SUCCÈS (" . count($success) . "):\n";
    foreach ($success as $msg) {
        echo "  $msg\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "⚠️  AVERTISSEMENTS (" . count($warnings) . "):\n";
    foreach ($warnings as $msg) {
        echo "  $msg\n";
    }
    echo "\n";
}

if (!empty($errors)) {
    echo "❌ ERREURS (" . count($errors) . "):\n";
    foreach ($errors as $msg) {
        echo "  $msg\n";
    }
    echo "\n";
}

// Résumé final
echo str_repeat("=", 70) . "\n";
if (empty($errors)) {
    echo "✅ VALIDATION RÉUSSIE!\n\n";
    echo "Tous les fichiers ont été correctement modifiés.\n";
    if (!empty($warnings)) {
        echo "\n⚠️  Quelques avertissements mineurs, mais rien de bloquant.\n";
    }
    echo "\n";
    echo "PROCHAINES ÉTAPES POUR LE DÉPLOIEMENT:\n";
    echo "========================================\n";
    echo "1. Appliquer la migration:\n";
    echo "   php run-migrations.php\n\n";
    echo "2. OU initialiser les templates manuellement:\n";
    echo "   php init-email-templates.php\n\n";
    echo "3. Configurer la signature société:\n";
    echo "   - Aller sur /admin-v2/contrat-configuration.php\n";
    echo "   - Uploader l'image de signature\n";
    echo "   - Activer l'ajout automatique\n\n";
    echo "4. Vérifier les templates dans l'admin:\n";
    echo "   - Aller sur /admin-v2/email-templates.php\n";
    echo "   - Personnaliser si nécessaire\n\n";
    echo "5. Tester avec un contrat réel:\n";
    echo "   - Créer un contrat de test\n";
    echo "   - Le faire signer\n";
    echo "   - Vérifier l'email HTML reçu\n";
    echo "   - Valider le contrat dans l'admin\n";
    echo "   - Vérifier que la signature société est ajoutée au PDF\n\n";
    exit(0);
} else {
    echo "❌ VALIDATION ÉCHOUÉE\n\n";
    echo "Des erreurs ont été détectées dans les fichiers.\n";
    echo "Veuillez corriger les erreurs avant de continuer.\n\n";
    exit(1);
}
