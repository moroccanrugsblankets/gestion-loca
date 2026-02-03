<?php
/**
 * Script to manually update the email signature with proper border attributes
 * Use this if migrations have already been run and you need to fix the signature
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

echo "=== Mise à jour de la signature email ===\n\n";

// The correct signature format with both HTML border attribute and CSS styles
$correctSignature = '<p>Sincères salutations</p><p style="margin-top: 20px;"><table style="border: 0; border-collapse: collapse;"><tbody><tr><td style="padding-right: 15px;"><img src="https://www.myinvest-immobilier.com/images/logo.png" alt="MY Invest Immobilier" style="max-width: 120px; border: 0; border-style: none; outline: none; display: block;" border="0"></td><td><h3 style="margin: 0; color: #2c3e50;">MY INVEST IMMOBILIER</h3></td></tr></tbody></table></p>';

try {
    // Get current signature
    $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'email_signature' LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "Signature actuelle trouvée:\n";
        echo "----------------------------------------\n";
        echo substr($result['valeur'], 0, 200) . "...\n";
        echo "----------------------------------------\n\n";
        
        // Check if update is needed
        if ($result['valeur'] === $correctSignature) {
            echo "✓ La signature est déjà correcte, aucune mise à jour nécessaire.\n";
            exit(0);
        }
        
        echo "La signature nécessite une mise à jour.\n\n";
    } else {
        echo "Aucune signature trouvée, création d'une nouvelle entrée.\n\n";
    }
    
    // Update the signature
    echo "Mise à jour de la signature...\n";
    $updateStmt = $pdo->prepare("
        INSERT INTO parametres (cle, valeur, type, description, groupe)
        VALUES ('email_signature', :valeur, 'string', 'Signature ajoutée à tous les emails envoyés', 'email')
        ON DUPLICATE KEY UPDATE 
            valeur = :valeur,
            updated_at = NOW()
    ");
    
    $updateStmt->execute(['valeur' => $correctSignature]);
    
    echo "✓ Signature mise à jour avec succès!\n\n";
    
    // Verify the update
    $verifyStmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'email_signature' LIMIT 1");
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($verifyResult && $verifyResult['valeur'] === $correctSignature) {
        echo "✓ Vérification: La signature a été correctement mise à jour.\n";
        echo "\nCaractéristiques de la nouvelle signature:\n";
        echo "  - Attribut HTML border=\"0\" pour TCPDF\n";
        echo "  - Styles CSS pour compatibilité navigateurs\n";
        echo "  - Structure complète avec salutations\n";
        echo "  - Mise en forme professionnelle\n";
        exit(0);
    } else {
        echo "❌ ERREUR: La vérification a échoué.\n";
        exit(1);
    }
    
} catch (PDOException $e) {
    echo "❌ ERREUR SQL: " . $e->getMessage() . "\n";
    exit(1);
}
