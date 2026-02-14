<?php
/**
 * Import equipment from exit inventory to bilan logement
 * Only imports equipment WITH comments
 */
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['contrat_id'])) {
    echo json_encode(['success' => false, 'message' => 'Requête invalide']);
    exit;
}

$contratId = (int)$_POST['contrat_id'];

try {
    // Get exit inventory for this contract
    $stmt = $pdo->prepare("
        SELECT * FROM inventaires
        WHERE contrat_id = ? AND type = 'sortie'
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$contratId]);
    $inventaire = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$inventaire) {
        echo json_encode(['success' => false, 'message' => 'Aucun inventaire de sortie trouvé pour ce contrat']);
        exit;
    }
    
    // Parse equipements_data JSON
    $equipements = json_decode($inventaire['equipements_data'], true) ?: [];
    
    // Filter to only equipment WITH comments
    $equipementsAvecCommentaires = [];
    foreach ($equipements as $item) {
        // Check if commentaires field exists and is not empty
        if (isset($item['commentaires']) && trim($item['commentaires']) !== '') {
            $equipementsAvecCommentaires[] = [
                'poste' => $item['nom'] ?? '',
                'commentaires' => $item['commentaires'],
                'categorie' => $item['categorie'] ?? '',
                'sous_categorie' => $item['sous_categorie'] ?? '',
                'sortie' => $item['sortie'] ?? []
            ];
        }
    }
    
    if (empty($equipementsAvecCommentaires)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Aucun équipement avec commentaires trouvé dans l\'inventaire de sortie'
        ]);
        exit;
    }
    
    // Convert to bilan rows format
    $bilanRows = [];
    foreach ($equipementsAvecCommentaires as $item) {
        // Use only the equipment name without category prefixes
        $poste = $item['poste'];
        
        // Analyze condition to suggest valeur/montant
        $sortie = $item['sortie'];
        $valeur = '';
        $montant_du = '';
        
        // If marked as "mauvais" (bad condition), suggest higher value
        if (isset($sortie['mauvais']) && $sortie['mauvais']) {
            $valeur = ''; // To be filled by admin
            $montant_du = ''; // To be filled by admin
        }
        
        $bilanRows[] = [
            'poste' => $poste,
            'commentaires' => $item['commentaires'],
            'valeur' => $valeur,
            'montant_du' => $montant_du
        ];
    }
    
    echo json_encode([
        'success' => true,
        'rows' => $bilanRows,
        'count' => count($bilanRows),
        'message' => count($bilanRows) . ' équipement(s) avec commentaires importé(s)'
    ]);
    
} catch (Exception $e) {
    error_log("Error importing inventory to bilan: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
