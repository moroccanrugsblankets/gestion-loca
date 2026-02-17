#!/usr/bin/env php
<?php
/**
 * Script de validation des corrections Admin-v2
 * 
 * Ce script vérifie que toutes les corrections ont été correctement appliquées
 * sans nécessiter de connexion à la base de données.
 */

echo "=== VALIDATION DES CORRECTIONS ADMIN-V2 ===\n\n";

$errors = 0;
$warnings = 0;
$success = 0;

// Test 1: Vérifier que edit-quittance.php n'utilise plus header.php
echo "Test 1: Vérification de edit-quittance.php...\n";
$file = file_get_contents(__DIR__ . '/admin-v2/edit-quittance.php');
if (strpos($file, "include 'header.php'") !== false || strpos($file, 'include "header.php"') !== false) {
    echo "  ❌ ERREUR: edit-quittance.php contient encore une référence à header.php\n";
    $errors++;
} else {
    echo "  ✅ OK: Pas de référence à header.php\n";
    $success++;
}

if (strpos($file, "require_once __DIR__ . '/includes/menu.php'") !== false) {
    echo "  ✅ OK: Utilise correctement includes/menu.php\n";
    $success++;
} else {
    echo "  ❌ ERREUR: N'utilise pas includes/menu.php\n";
    $errors++;
}

if (strpos($file, "require_once __DIR__ . '/includes/sidebar-styles.php'") !== false) {
    echo "  ✅ OK: Inclut sidebar-styles.php\n";
    $success++;
} else {
    echo "  ⚠️  AVERTISSEMENT: Ne semble pas inclure sidebar-styles.php\n";
    $warnings++;
}

if (strpos($file, '<div class="main-content">') !== false) {
    echo "  ✅ OK: Contient le wrapper main-content\n";
    $success++;
} else {
    echo "  ⚠️  AVERTISSEMENT: Ne contient pas le wrapper main-content\n";
    $warnings++;
}

// Test 2: Vérifier que resend-quittance-email.php n'utilise plus admin_id
echo "\nTest 2: Vérification de resend-quittance-email.php...\n";
$file = file_get_contents(__DIR__ . '/admin-v2/resend-quittance-email.php');
if (strpos($file, 'admin_id') !== false) {
    echo "  ❌ ERREUR: resend-quittance-email.php contient encore une référence à admin_id\n";
    $errors++;
} else {
    echo "  ✅ OK: Pas de référence à admin_id\n";
    $success++;
}

if (strpos($file, 'type_entite') !== false && strpos($file, 'entite_id') !== false) {
    echo "  ✅ OK: Utilise le nouveau schéma logs (type_entite, entite_id)\n";
    $success++;
} else {
    echo "  ❌ ERREUR: Ne semble pas utiliser le nouveau schéma logs\n";
    $errors++;
}

// Test 3: Vérifier que la migration existe
echo "\nTest 3: Vérification de la migration 058...\n";
if (file_exists(__DIR__ . '/migrations/058_add_rappel_loyer_locataire_template.sql')) {
    echo "  ✅ OK: Fichier de migration existe\n";
    $success++;
    
    $migration = file_get_contents(__DIR__ . '/migrations/058_add_rappel_loyer_locataire_template.sql');
    
    if (strpos($migration, "rappel_loyer_impaye_locataire") !== false) {
        echo "  ✅ OK: Template 'rappel_loyer_impaye_locataire' défini\n";
        $success++;
    } else {
        echo "  ❌ ERREUR: Template 'rappel_loyer_impaye_locataire' non trouvé\n";
        $errors++;
    }
    
    if (strpos($migration, "My Invest Immobilier - Rappel loyer non réceptionné") !== false) {
        echo "  ✅ OK: Sujet de l'email correct\n";
        $success++;
    } else {
        echo "  ❌ ERREUR: Sujet de l'email incorrect\n";
        $errors++;
    }
    
    // Vérifier les variables requises
    $requiredVars = ['locataire_nom', 'locataire_prenom', 'periode', 'adresse', 'montant_total', 'signature'];
    $missingVars = [];
    foreach ($requiredVars as $var) {
        if (strpos($migration, $var) === false) {
            $missingVars[] = $var;
        }
    }
    
    if (empty($missingVars)) {
        echo "  ✅ OK: Toutes les variables requises sont présentes\n";
        $success++;
    } else {
        echo "  ❌ ERREUR: Variables manquantes: " . implode(', ', $missingVars) . "\n";
        $errors++;
    }
} else {
    echo "  ❌ ERREUR: Fichier de migration non trouvé\n";
    $errors++;
}

// Test 4: Vérifier que cron/rappel-loyers.php a la nouvelle fonction
echo "\nTest 4: Vérification de cron/rappel-loyers.php...\n";
if (file_exists(__DIR__ . '/cron/rappel-loyers.php')) {
    $file = file_get_contents(__DIR__ . '/cron/rappel-loyers.php');
    
    if (strpos($file, 'function envoyerRappelLocataires') !== false) {
        echo "  ✅ OK: Fonction envoyerRappelLocataires existe\n";
        $success++;
    } else {
        echo "  ❌ ERREUR: Fonction envoyerRappelLocataires non trouvée\n";
        $errors++;
    }
    
    if (strpos($file, "rappel_loyer_impaye_locataire") !== false) {
        echo "  ✅ OK: Utilise le template rappel_loyer_impaye_locataire\n";
        $success++;
    } else {
        echo "  ❌ ERREUR: N'utilise pas le template rappel_loyer_impaye_locataire\n";
        $errors++;
    }
    
    if (strpos($file, 'envoyerRappelLocataires($pdo, $mois, $annee)') !== false) {
        echo "  ✅ OK: Fonction appelée dans le script principal\n";
        $success++;
    } else {
        echo "  ⚠️  AVERTISSEMENT: Fonction peut ne pas être appelée dans le script\n";
        $warnings++;
    }
} else {
    echo "  ❌ ERREUR: Fichier cron/rappel-loyers.php non trouvé\n";
    $errors++;
}

// Test 5: Vérifier la syntaxe PHP
echo "\nTest 5: Vérification de la syntaxe PHP...\n";
$files = [
    'admin-v2/edit-quittance.php',
    'admin-v2/resend-quittance-email.php',
    'cron/rappel-loyers.php'
];

foreach ($files as $file) {
    exec("php -l " . escapeshellarg(__DIR__ . '/' . $file) . " 2>&1", $output, $return);
    if ($return === 0) {
        echo "  ✅ OK: $file - syntaxe valide\n";
        $success++;
    } else {
        echo "  ❌ ERREUR: $file - erreur de syntaxe\n";
        echo "    " . implode("\n    ", $output) . "\n";
        $errors++;
    }
    $output = [];
}

// Résumé
echo "\n=== RÉSUMÉ ===\n";
echo "✅ Succès: $success\n";
if ($warnings > 0) {
    echo "⚠️  Avertissements: $warnings\n";
}
if ($errors > 0) {
    echo "❌ Erreurs: $errors\n";
}

echo "\n";
if ($errors === 0) {
    echo "🎉 TOUTES LES VALIDATIONS SONT RÉUSSIES!\n";
    echo "Les corrections peuvent être déployées en production.\n";
    exit(0);
} else {
    echo "⚠️  DES ERREURS ONT ÉTÉ DÉTECTÉES!\n";
    echo "Veuillez corriger les erreurs avant le déploiement.\n";
    exit(1);
}
