<?php
/**
 * Test script to verify the new features implementation
 * Tests:
 * 1. Administrator database operations
 * 2. Email sending with admin CC functionality
 */

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/mail-templates.php';

echo "=== Test des nouvelles fonctionnalités ===\n\n";

// Test 1: Check if administrateurs table exists
echo "Test 1: Vérification de la table administrateurs...\n";
try {
    $stmt = $pdo->query("DESCRIBE administrateurs");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✓ Table administrateurs existe avec " . count($columns) . " colonnes\n";
    echo "Colonnes: ";
    echo implode(", ", array_column($columns, 'Field')) . "\n";
} catch (Exception $e) {
    echo "✗ Erreur: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Count active administrators
echo "Test 2: Vérification des administrateurs actifs...\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM administrateurs WHERE actif = TRUE");
    $count = $stmt->fetchColumn();
    echo "✓ Nombre d'administrateurs actifs: $count\n";
    
    // List active admin emails
    $stmt = $pdo->query("SELECT email, nom, prenom FROM administrateurs WHERE actif = TRUE");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($admins as $admin) {
        echo "  - {$admin['prenom']} {$admin['nom']}: {$admin['email']}\n";
    }
} catch (Exception $e) {
    echo "✗ Erreur: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Check contrats table has token_signature column
echo "Test 3: Vérification de la colonne token_signature dans contrats...\n";
try {
    $stmt = $pdo->query("DESCRIBE contrats");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasToken = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'token_signature') {
            $hasToken = true;
            break;
        }
    }
    if ($hasToken) {
        echo "✓ Colonne token_signature existe dans la table contrats\n";
    } else {
        echo "✗ Colonne token_signature n'existe pas dans la table contrats\n";
    }
} catch (Exception $e) {
    echo "✗ Erreur: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Test email function signature
echo "Test 4: Vérification de la fonction sendEmail...\n";
try {
    if (function_exists('sendEmail')) {
        echo "✓ Fonction sendEmail existe\n";
        
        // Check if function accepts the isAdminEmail parameter
        $reflection = new ReflectionFunction('sendEmail');
        $params = $reflection->getParameters();
        echo "  Paramètres: " . count($params) . "\n";
        foreach ($params as $param) {
            $optional = $param->isOptional() ? " (optionnel)" : " (requis)";
            $default = $param->isOptional() ? " = " . var_export($param->getDefaultValue(), true) : "";
            echo "    - \${$param->getName()}{$optional}{$default}\n";
        }
    } else {
        echo "✗ Fonction sendEmail n'existe pas\n";
    }
} catch (Exception $e) {
    echo "✗ Erreur: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 5: Check files exist
echo "Test 5: Vérification des nouveaux fichiers...\n";
$files = [
    'admin-v2/administrateurs.php',
    'admin-v2/administrateurs-actions.php',
    'admin-v2/supprimer-contrat.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✓ $file existe (" . filesize($file) . " bytes)\n";
    } else {
        echo "✗ $file n'existe pas\n";
    }
}

echo "\n=== Tests terminés ===\n";
