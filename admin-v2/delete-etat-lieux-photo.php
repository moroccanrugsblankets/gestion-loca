<?php
/**
 * Delete Photo from État des Lieux
 * My Invest Immobilier
 */

require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

// Get photo ID
$photo_id = isset($_POST['photo_id']) ? (int)$_POST['photo_id'] : 0;

if ($photo_id < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID invalide']);
    exit;
}

try {
    // Get photo details
    $stmt = $pdo->prepare("SELECT * FROM etat_lieux_photos WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$photo_id]);
    $photo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$photo) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Photo non trouvée']);
        exit;
    }
    
    // Soft delete from database (set deleted_at timestamp instead of DELETE)
    // Photo file is preserved (not deleted from filesystem) as per requirements
    $stmt = $pdo->prepare("UPDATE etat_lieux_photos SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$photo_id]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log("Error deleting photo: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur lors de la suppression']);
}
