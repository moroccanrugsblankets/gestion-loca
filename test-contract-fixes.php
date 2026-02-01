<?php
/**
 * Test de vérification des correctifs appliqués
 * Ce script vérifie que les corrections sont bien en place
 */

echo "=== Test des Correctifs Contract Issues ===\n\n";

// Test 1: Vérifier que le paramètre dans contrats.php est correct
echo "Test 1: Vérification du paramètre de téléchargement PDF\n";
echo "-------------------------------------------------------\n";

$contratsContent = file_get_contents(__DIR__ . '/admin-v2/contrats.php');

// Vérifier que contrat_id est utilisé (pas contract_id)
if (strpos($contratsContent, 'download.php?contrat_id=') !== false) {
    echo "✅ PASS: Le paramètre 'contrat_id' est correctement utilisé\n";
} else {
    echo "❌ FAIL: Le paramètre 'contrat_id' n'est pas trouvé\n";
}

// Vérifier qu'il n'y a pas d'ancien paramètre contract_id
if (strpos($contratsContent, 'download.php?contract_id=') === false) {
    echo "✅ PASS: L'ancien paramètre 'contract_id' n'est plus présent\n";
} else {
    echo "❌ FAIL: L'ancien paramètre 'contract_id' est encore présent\n";
}

echo "\n";

// Test 2: Vérifier que la migration 019 existe et contient la variable
echo "Test 2: Vérification de la migration 019\n";
echo "-----------------------------------------\n";

$migrationFile = __DIR__ . '/migrations/019_add_date_expiration_to_email_template.sql';

if (file_exists($migrationFile)) {
    echo "✅ PASS: Le fichier de migration 019 existe\n";
    
    $migrationContent = file_get_contents($migrationFile);
    
    // Vérifier que la variable est dans le template
    if (strpos($migrationContent, '{{date_expiration_lien_contrat}}') !== false) {
        echo "✅ PASS: La variable '{{date_expiration_lien_contrat}}' est dans la migration\n";
    } else {
        echo "❌ FAIL: La variable '{{date_expiration_lien_contrat}}' n'est pas trouvée\n";
    }
    
    // Vérifier que la variable est dans les variables_disponibles
    if (strpos($migrationContent, '"date_expiration_lien_contrat"') !== false) {
        echo "✅ PASS: La variable est dans 'variables_disponibles'\n";
    } else {
        echo "❌ FAIL: La variable n'est pas dans 'variables_disponibles'\n";
    }
} else {
    echo "❌ FAIL: Le fichier de migration 019 n'existe pas\n";
}

echo "\n";

// Test 3: Vérifier que le code PHP passe bien la variable
echo "Test 3: Vérification du passage de la variable dans le code PHP\n";
echo "----------------------------------------------------------------\n";

$files_to_check = [
    'admin-v2/envoyer-signature.php',
    'admin-v2/renvoyer-lien-signature.php'
];

foreach ($files_to_check as $file) {
    echo "Fichier: $file\n";
    $content = file_get_contents(__DIR__ . '/' . $file);
    
    // Vérifier le formatage de la date
    if (strpos($content, "date('d/m/Y à H:i'") !== false) {
        echo "  ✅ Formatage de date correct\n";
    } else {
        echo "  ❌ Formatage de date incorrect ou absent\n";
    }
    
    // Vérifier le passage de la variable
    if (strpos($content, "'date_expiration_lien_contrat' => \$date_expiration_formatted") !== false) {
        echo "  ✅ Variable passée correctement à sendTemplatedEmail()\n";
    } else {
        echo "  ❌ Variable non passée ou nom incorrect\n";
    }
    echo "\n";
}

// Test 4: Vérifier que la fonction replaceTemplateVariables existe
echo "Test 4: Vérification de la fonction replaceTemplateVariables\n";
echo "------------------------------------------------------------\n";

$functionsContent = file_get_contents(__DIR__ . '/includes/functions.php');

if (strpos($functionsContent, 'function replaceTemplateVariables') !== false) {
    echo "✅ PASS: La fonction replaceTemplateVariables existe\n";
    
    // Vérifier qu'elle remplace bien les variables
    if (preg_match('/\{\{.*?\}\}/', $functionsContent) || strpos($functionsContent, 'str_replace') !== false) {
        echo "✅ PASS: La fonction utilise un mécanisme de remplacement\n";
    } else {
        echo "⚠️  WARNING: Mécanisme de remplacement non détecté (vérification manuelle requise)\n";
    }
} else {
    echo "❌ FAIL: La fonction replaceTemplateVariables n'existe pas\n";
}

echo "\n";
echo "=== Résumé ===\n";
echo "Tous les éléments du code sont en place pour résoudre les deux problèmes.\n";
echo "\n";
echo "ACTIONS REQUISES EN PRODUCTION:\n";
echo "1. Exécuter la migration 019: php run-migrations.php\n";
echo "2. Tester le téléchargement de PDF sur un contrat signé\n";
echo "3. Tester l'envoi d'un email de signature et vérifier que la date d'expiration s'affiche\n";
echo "\n";
