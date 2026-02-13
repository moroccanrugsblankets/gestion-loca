<?php
/**
 * Auto-populate Logement Equipment
 * Populates a logement with default equipment items based on categories
 */
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

// Support both GET and POST for better usability
$logement_id = null;
$action = 'populate';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $logement_id = isset($_POST['logement_id']) ? (int)$_POST['logement_id'] : 0;
    $action = $_POST['action'] ?? 'populate';
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $logement_id = isset($_GET['logement_id']) ? (int)$_GET['logement_id'] : 0;
    $action = $_GET['action'] ?? 'populate';
}

if (!$logement_id) {
    echo json_encode(['success' => false, 'message' => 'Requête invalide - logement_id manquant']);
    exit;
}

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
    
    // Get logement reference for property-specific equipment
    $stmt = $pdo->prepare("SELECT reference FROM logements WHERE id = ?");
    $stmt->execute([$logement_id]);
    $logement_reference = $stmt->fetchColumn() ?: '';
    
    // Get complete default equipment items from standardized template
    require_once '../includes/inventaire-standard-items.php';
    $standardItems = getStandardInventaireItems($logement_reference);
    
    // Build category name to ID mapping
    $categoryIds = [];
    $stmt = $pdo->query("SELECT id, nom FROM inventaire_categories");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $categoryIds[$row['nom']] = $row['id'];
    }
    
    $insertStmt = $pdo->prepare("
        INSERT INTO inventaire_equipements (logement_id, categorie_id, categorie, nom, quantite, ordre)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $totalAdded = 0;
    $ordre = 0;
    
    // Insert all default equipment
    foreach ($standardItems as $categoryName => $categoryItems) {
        $categorie_id = $categoryIds[$categoryName] ?? null;
        foreach ($categoryItems as $item) {
            $ordre++;
            $insertStmt->execute([
                $logement_id,
                $categorie_id,
                $categoryName,
                $item['nom'],
                $item['quantite'] ?? 0,
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
