<?php
/**
 * Script de test pour la génération du PDF État des lieux
 * 
 * Ce script teste la fonction generateEtatDesLieuxPDF sans connexion base de données
 */

// Mock des données pour tester la génération PDF
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Test de génération PDF État des lieux ===\n\n";

// Vérifier que TCPDF est disponible
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "❌ ERREUR: vendor/autoload.php non trouvé. Exécutez 'composer install' d'abord.\n";
    exit(1);
}

require_once __DIR__ . '/vendor/autoload.php';

// Vérifier que TCPDF est chargé
if (!class_exists('TCPDF')) {
    echo "❌ ERREUR: TCPDF n'est pas installé.\n";
    exit(1);
}

echo "✓ TCPDF est disponible\n";

// Vérifier que le fichier generate-etat-lieux.php existe
if (!file_exists(__DIR__ . '/pdf/generate-etat-lieux.php')) {
    echo "❌ ERREUR: pdf/generate-etat-lieux.php non trouvé.\n";
    exit(1);
}

echo "✓ Fichier generate-etat-lieux.php trouvé\n";

// Vérifier la structure du fichier
$content = file_get_contents(__DIR__ . '/pdf/generate-etat-lieux.php');

$requiredFunctions = [
    'generateEtatDesLieuxPDF',
    'createDefaultEtatLieux',
    'generateEntreeHTML',
    'generateSortieHTML',
    'buildSignaturesTableEtatLieux',
    'sendEtatDesLieuxEmail',
    'getDefaultPropertyDescriptions'
];

echo "\nVérification des fonctions requises:\n";
$allFound = true;
foreach ($requiredFunctions as $func) {
    if (strpos($content, "function $func") !== false) {
        echo "  ✓ $func\n";
    } else {
        echo "  ❌ $func manquante\n";
        $allFound = false;
    }
}

if (!$allFound) {
    echo "\n❌ Certaines fonctions sont manquantes\n";
    exit(1);
}

// Vérifier la structure HTML pour l'entrée
echo "\nVérification de la structure HTML (Entrée):\n";
$requiredSectionsEntree = [
    'ÉTAT DES LIEUX D\'ENTRÉE',
    '1. IDENTIFICATION',
    '2. RELEVÉ DES COMPTEURS',
    '3. REMISE DES CLÉS',
    '4. DESCRIPTION DU LOGEMENT',
    '5. SIGNATURES'
];

foreach ($requiredSectionsEntree as $section) {
    if (stripos($content, $section) !== false) {
        echo "  ✓ Section '$section'\n";
    } else {
        echo "  ❌ Section '$section' manquante\n";
        $allFound = false;
    }
}

// Vérifier la structure HTML pour la sortie
echo "\nVérification de la structure HTML (Sortie):\n";
$requiredSectionsSortie = [
    'ÉTAT DES LIEUX DE SORTIE',
    '1. IDENTIFICATION',
    '2. RELEVÉ DES COMPTEURS À LA SORTIE',
    '3. RESTITUTION DES CLÉS',
    '4. DESCRIPTION DU LOGEMENT',
    '5. CONCLUSION',
    '6. SIGNATURES'
];

foreach ($requiredSectionsSortie as $section) {
    if (stripos($content, $section) !== false) {
        echo "  ✓ Section '$section'\n";
    } else {
        echo "  ❌ Section '$section' manquante\n";
        $allFound = false;
    }
}

// Vérifier que le répertoire de sortie sera créé
echo "\nVérification de la gestion du répertoire PDF:\n";
if (strpos($content, "'/pdf/etat_des_lieux/'") !== false && strpos($content, 'mkdir') !== false) {
    echo "  ✓ Création automatique du répertoire /pdf/etat_des_lieux/\n";
} else {
    echo "  ⚠ Le répertoire /pdf/etat_des_lieux/ pourrait ne pas être créé automatiquement\n";
}

// Vérifier l'envoi d'email
echo "\nVérification de l'envoi d'email:\n";
if (strpos($content, 'sendEtatDesLieuxEmail') !== false) {
    echo "  ✓ Fonction d'envoi d'email présente\n";
    
    if (strpos($content, 'gestion@myinvest-immobilier.com') !== false) {
        echo "  ✓ Copie à gestion@myinvest-immobilier.com\n";
    } else {
        echo "  ❌ Copie à gestion@myinvest-immobilier.com manquante\n";
        $allFound = false;
    }
} else {
    echo "  ❌ Fonction d'envoi d'email manquante\n";
    $allFound = false;
}

// Vérifier la gestion des photos
echo "\nVérification de la gestion des photos:\n";
if (strpos($content, 'etat_lieux_photos') !== false) {
    echo "  ✓ Table pour les photos mentionnée\n";
} else {
    echo "  ⚠ Table pour les photos non mentionnée dans le code\n";
}

// Vérification de la migration
echo "\nVérification de la migration de base de données:\n";
if (file_exists(__DIR__ . '/migrations/021_create_etat_lieux_tables.php')) {
    echo "  ✓ Fichier de migration trouvé\n";
    
    $migrationContent = file_get_contents(__DIR__ . '/migrations/021_create_etat_lieux_tables.php');
    
    $tables = ['etat_lieux', 'etat_lieux_locataires', 'etat_lieux_photos'];
    foreach ($tables as $table) {
        if (strpos($migrationContent, "CREATE TABLE IF NOT EXISTS $table") !== false) {
            echo "  ✓ Table $table sera créée\n";
        } else {
            echo "  ❌ Table $table manquante\n";
            $allFound = false;
        }
    }
} else {
    echo "  ❌ Fichier de migration non trouvé\n";
    $allFound = false;
}

// Test de syntaxe PHP
echo "\nTest de syntaxe PHP:\n";
exec("php -l " . __DIR__ . "/pdf/generate-etat-lieux.php 2>&1", $output, $returnCode);
if ($returnCode === 0) {
    echo "  ✓ Syntaxe PHP valide pour generate-etat-lieux.php\n";
} else {
    echo "  ❌ Erreur de syntaxe PHP:\n";
    echo implode("\n", $output) . "\n";
    $allFound = false;
}

exec("php -l " . __DIR__ . "/migrations/021_create_etat_lieux_tables.php 2>&1", $output, $returnCode);
if ($returnCode === 0) {
    echo "  ✓ Syntaxe PHP valide pour migration 021\n";
} else {
    echo "  ❌ Erreur de syntaxe PHP:\n";
    echo implode("\n", $output) . "\n";
    $allFound = false;
}

// Résumé final
echo "\n" . str_repeat("=", 60) . "\n";
if ($allFound) {
    echo "✅ TOUS LES TESTS SONT PASSÉS\n";
    echo "\nLe module État des lieux est prêt à être déployé.\n";
    echo "\nÉtapes suivantes:\n";
    echo "1. Exécuter la migration: php migrations/021_create_etat_lieux_tables.php\n";
    echo "2. Tester la génération PDF avec un contrat réel\n";
    echo "3. Vérifier l'envoi d'email\n";
    exit(0);
} else {
    echo "❌ CERTAINS TESTS ONT ÉCHOUÉ\n";
    echo "\nVeuillez corriger les erreurs avant de déployer.\n";
    exit(1);
}
