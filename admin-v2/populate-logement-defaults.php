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
    
    // Get all active categories
    $stmt = $pdo->query("
        SELECT id, nom 
        FROM inventaire_categories 
        WHERE actif = TRUE 
        ORDER BY ordre
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Default equipment items by category name
    $defaultEquipment = [
        'Électroménager' => [
            ['nom' => 'Réfrigérateur', 'quantite' => 1],
            ['nom' => 'Plaques de cuisson', 'quantite' => 1],
            ['nom' => 'Four', 'quantite' => 1],
            ['nom' => 'Hotte', 'quantite' => 1]
        ],
        'Mobilier' => [
            ['nom' => 'Canapé', 'quantite' => 1],
            ['nom' => 'Table', 'quantite' => 1],
            ['nom' => 'Chaises', 'quantite' => 4]
        ],
        'Cuisine' => [
            ['nom' => 'Évier', 'quantite' => 1],
            ['nom' => 'Placards', 'quantite' => 1],
            ['nom' => 'Plan de travail', 'quantite' => 1]
        ],
        'Salle de bain' => [
            ['nom' => 'Lavabo', 'quantite' => 1],
            ['nom' => 'Douche/Baignoire', 'quantite' => 1],
            ['nom' => 'WC', 'quantite' => 1],
            ['nom' => 'Miroir', 'quantite' => 1]
        ],
        'Linge de maison' => [
            ['nom' => 'Rideaux', 'quantite' => 1]
        ]
    ];
    
    $insertStmt = $pdo->prepare("
        INSERT INTO inventaire_equipements (logement_id, categorie_id, categorie, nom, quantite, ordre)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $totalAdded = 0;
    $ordre = 0;
    
    foreach ($categories as $category) {
        $catName = $category['nom'];
        
        if (isset($defaultEquipment[$catName])) {
            foreach ($defaultEquipment[$catName] as $item) {
                $ordre++;
                $insertStmt->execute([
                    $logement_id,
                    $category['id'],
                    $catName,
                    $item['nom'],
                    $item['quantite'],
                    $ordre
                ]);
                $totalAdded++;
            }
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
