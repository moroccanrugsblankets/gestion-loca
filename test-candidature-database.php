<?php
/**
 * Script de test pour vérifier la connexion à la base de données
 * et l'état du système de candidatures
 * 
 * Exécutez ce script pour diagnostiquer les problèmes de candidature
 * Usage: php test-candidature-database.php
 * ou accédez via navigateur: http://votre-site.com/test-candidature-database.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test du Système de Candidatures</h1>\n";
echo "<pre>\n";

// Test 1: Charger la configuration
echo "=== Test 1: Configuration ===\n";
try {
    require_once __DIR__ . '/includes/config.php';
    echo "✓ Configuration chargée avec succès\n";
    echo "  DB_HOST: " . $config['DB_HOST'] . "\n";
    echo "  DB_NAME: " . $config['DB_NAME'] . "\n";
    echo "  DB_USER: " . $config['DB_USER'] . "\n";
    echo "  DB_PASS: " . (empty($config['DB_PASS']) ? '(vide)' : '(défini)') . "\n";
} catch (Exception $e) {
    echo "✗ Erreur lors du chargement de la configuration: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Connexion à la base de données
echo "\n=== Test 2: Connexion Base de Données ===\n";
try {
    require_once __DIR__ . '/includes/db.php';
    if (isset($pdo) && $pdo !== null) {
        echo "✓ Connexion à la base de données établie\n";
        echo "  Type de connexion: PDO MySQL\n";
    } else {
        echo "✗ \$pdo est null ou non défini\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ Erreur de connexion: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Vérifier que la table candidatures existe
echo "\n=== Test 3: Vérification Table candidatures ===\n";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'candidatures'");
    if ($stmt->fetch()) {
        echo "✓ Table 'candidatures' existe\n";
        
        // Récupérer le schéma de la table
        $stmt = $pdo->query("DESCRIBE candidatures");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "  Colonnes: " . implode(', ', array_column($columns, 'Field')) . "\n";
    } else {
        echo "✗ Table 'candidatures' n'existe pas\n";
        echo "  Vous devez exécuter le fichier database.sql ou les migrations\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ Erreur lors de la vérification de la table: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 4: Compter les candidatures
echo "\n=== Test 4: Statistiques Candidatures ===\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM candidatures");
    $result = $stmt->fetch();
    echo "✓ Nombre total de candidatures: " . $result['total'] . "\n";
    
    // Statistiques par statut
    $stmt = $pdo->query("SELECT statut, COUNT(*) as count FROM candidatures GROUP BY statut");
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($stats)) {
        echo "  Répartition par statut:\n";
        foreach ($stats as $stat) {
            echo "    - " . ($stat['statut'] ?? 'null') . ": " . $stat['count'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "✗ Erreur lors du comptage: " . $e->getMessage() . "\n";
}

// Test 5: Afficher les 5 dernières candidatures
echo "\n=== Test 5: Dernières Candidatures ===\n";
try {
    $stmt = $pdo->query("
        SELECT c.id, c.reference_unique, c.nom, c.prenom, c.email, c.statut, c.date_soumission
        FROM candidatures c 
        ORDER BY c.date_soumission DESC 
        LIMIT 5
    ");
    $candidatures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($candidatures)) {
        echo "ℹ Aucune candidature trouvée\n";
    } else {
        echo "✓ " . count($candidatures) . " dernière(s) candidature(s):\n";
        foreach ($candidatures as $cand) {
            echo "  #" . $cand['id'] . " - " . $cand['reference_unique'] . 
                 " | " . $cand['prenom'] . " " . $cand['nom'] . 
                 " (" . $cand['email'] . ")" .
                 " | Statut: " . $cand['statut'] . 
                 " | Date: " . $cand['date_soumission'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "✗ Erreur lors de la récupération: " . $e->getMessage() . "\n";
}

// Test 6: Vérifier la table candidature_documents
echo "\n=== Test 6: Vérification Table candidature_documents ===\n";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'candidature_documents'");
    if ($stmt->fetch()) {
        echo "✓ Table 'candidature_documents' existe\n";
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM candidature_documents");
        $result = $stmt->fetch();
        echo "  Nombre total de documents: " . $result['total'] . "\n";
    } else {
        echo "✗ Table 'candidature_documents' n'existe pas\n";
    }
} catch (Exception $e) {
    echo "✗ Erreur: " . $e->getMessage() . "\n";
}

// Test 7: Vérifier les permissions du dossier uploads
echo "\n=== Test 7: Permissions Dossier Uploads ===\n";
$uploadDir = __DIR__ . '/uploads/candidatures';
if (is_dir($uploadDir)) {
    echo "✓ Dossier uploads/candidatures existe\n";
    if (is_writable($uploadDir)) {
        echo "✓ Dossier uploads/candidatures est accessible en écriture\n";
    } else {
        echo "✗ Dossier uploads/candidatures n'est PAS accessible en écriture\n";
        echo "  Exécutez: chmod 755 uploads/candidatures\n";
    }
} else {
    echo "ℹ Dossier uploads/candidatures n'existe pas encore\n";
    echo "  Il sera créé automatiquement lors de la première soumission\n";
}

// Test 8: Vérifier que la table logements existe
echo "\n=== Test 8: Vérification Table logements ===\n";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'logements'");
    if ($stmt->fetch()) {
        echo "✓ Table 'logements' existe\n";
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM logements WHERE statut = 'disponible'");
        $result = $stmt->fetch();
        echo "  Logements disponibles: " . $result['total'] . "\n";
        
        if ($result['total'] == 0) {
            echo "  ⚠ ATTENTION: Aucun logement disponible! Les candidatures ne pourront pas être soumises.\n";
        }
    } else {
        echo "✗ Table 'logements' n'existe pas\n";
    }
} catch (Exception $e) {
    echo "✗ Erreur: " . $e->getMessage() . "\n";
}

echo "\n=== Résumé ===\n";
echo "Tests terminés. Vérifiez les résultats ci-dessus.\n";
echo "Si tous les tests passent mais les candidatures ne s'affichent toujours pas,\n";
echo "vérifiez les logs d'erreurs dans error.log\n";

echo "</pre>\n";
