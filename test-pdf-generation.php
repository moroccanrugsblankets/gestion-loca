<?php
/**
 * Script de test pour la génération de PDF de contrat
 * Test avec données fictives pour vérifier le format
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/pdf/generate-contrat-pdf.php';

echo "=== Test de génération de PDF de contrat ===\n\n";

// Test 1: Vérifier que TCPDF est chargé
echo "Test 1: Vérification TCPDF...\n";
if (class_exists('TCPDF')) {
    echo "✓ TCPDF est disponible\n";
} else {
    echo "✗ TCPDF n'est pas disponible\n";
    exit(1);
}

echo "\n";

// Test 2: Vérifier la connexion à la base de données
echo "Test 2: Connexion base de données...\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM contrats");
    $count = $stmt->fetchColumn();
    echo "✓ Base de données accessible ($count contrats)\n";
} catch (Exception $e) {
    echo "✗ Erreur base de données: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Créer un contrat de test si nécessaire
echo "Test 3: Recherche d'un contrat de test...\n";
$stmt = $pdo->query("
    SELECT c.id, c.reference_unique, c.statut,
           (SELECT COUNT(*) FROM locataires WHERE contrat_id = c.id) as nb_locataires
    FROM contrats c
    ORDER BY c.id DESC
    LIMIT 1
");
$testContrat = $stmt->fetch(PDO::FETCH_ASSOC);

if ($testContrat && $testContrat['nb_locataires'] > 0) {
    echo "✓ Contrat trouvé: #{$testContrat['id']} - {$testContrat['reference_unique']}\n";
    echo "  Statut: {$testContrat['statut']}\n";
    echo "  Locataires: {$testContrat['nb_locataires']}\n";
    
    // Test 4: Générer le PDF
    echo "\nTest 4: Génération du PDF...\n";
    try {
        $pdfPath = generateContratPDF($testContrat['id']);
        
        if ($pdfPath && file_exists($pdfPath)) {
            $fileSize = filesize($pdfPath);
            echo "✓ PDF généré avec succès!\n";
            echo "  Chemin: $pdfPath\n";
            echo "  Taille: " . number_format($fileSize) . " octets\n";
            
            // Vérifier que c'est un vrai PDF
            $fh = fopen($pdfPath, 'rb');
            $header = fread($fh, 4);
            fclose($fh);
            
            if ($header === '%PDF') {
                echo "✓ Format PDF valide\n";
            } else {
                echo "✗ Format PDF invalide (header: $header)\n";
            }
        } else {
            echo "✗ Échec de la génération du PDF\n";
        }
    } catch (Exception $e) {
        echo "✗ Erreur lors de la génération: " . $e->getMessage() . "\n";
        echo "  Stack trace: " . $e->getTraceAsString() . "\n";
    }
} else {
    echo "✗ Aucun contrat avec locataires trouvé\n";
    echo "  Créez un contrat avec locataires pour tester la génération PDF\n";
}

echo "\n=== Tests terminés ===\n";
