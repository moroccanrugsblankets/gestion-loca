<?php
/**
 * Auto-populate Logement Equipment
 * Populates a logement with default equipment items based on categories
 */
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['logement_id'])) {
    echo json_encode(['success' => false, 'message' => 'Requête invalide']);
    exit;
}

$logement_id = (int)$_POST['logement_id'];
$action = $_POST['action'] ?? 'populate';

// Verify logement exists
$stmt = $pdo->prepare("SELECT id FROM logements WHERE id = ?");
$stmt->execute([$logement_id]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Logement introuvable']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // If action is 'reset', delete existing equipment first
    if ($action === 'reset') {
        $stmt = $pdo->prepare("DELETE FROM inventaire_equipements WHERE logement_id = ?");
        $stmt->execute([$logement_id]);
    }
    
    // Get complete default equipment items from standardized template
    require_once '../includes/inventaire-standard-items.php';
    $standardItems = getStandardInventaireItems();
    
    // Convert standardized items to flat equipment list
    $defaultEquipment = [];
    
    foreach ($standardItems as $categoryName => $categoryContent) {
        $defaultEquipment[$categoryName] = [];
        
        if ($categoryName === 'État des pièces') {
            // État des pièces has subcategories
            foreach ($categoryContent as $subcategoryName => $subcategoryItems) {
                $fullCategoryName = $categoryName . ' - ' . $subcategoryName;
                $defaultEquipment[$fullCategoryName] = [];
                foreach ($subcategoryItems as $item) {
                    // For non-countable items, default to 1; for countable items, default to 0
                    $quantite = ($item['type'] === 'countable') ? 0 : 1;
                    $defaultEquipment[$fullCategoryName][] = [
                        'nom' => $item['nom'],
                        'quantite' => $quantite
                    ];
                }
            }
        } else {
            // Other categories are flat
            foreach ($categoryContent as $item) {
                // For non-countable items, default to 1; for countable items, default to 0
                $quantite = ($item['type'] === 'countable') ? 0 : 1;
                $defaultEquipment[$categoryName][] = [
                    'nom' => $item['nom'],
                    'quantite' => $quantite
                ];
            }
        }
    }
    
    $insertStmt = $pdo->prepare("
        INSERT INTO inventaire_equipements (logement_id, categorie, nom, quantite, ordre)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $totalAdded = 0;
    $ordre = 0;
    
    // Insert all default equipment
    foreach ($defaultEquipment as $catName => $items) {
        foreach ($items as $item) {
            $ordre++;
            $insertStmt->execute([
                $logement_id,
                $catName,
                $item['nom'],
                $item['quantite'],
                $ordre
            ]);
            $totalAdded++;
        }
    }
    
    $pdo->commit();
    
    $actionMessage = $action === 'reset' ? 'réinitialisé et repopulé' : 'populé';
    echo json_encode([
        'success' => true, 
        'message' => "Logement {$actionMessage} avec {$totalAdded} équipements par défaut"
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error populating equipment: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la population: ' . $e->getMessage()]);
}
