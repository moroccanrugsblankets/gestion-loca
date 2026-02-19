<?php
/**
 * Delete Justificatif from Bilan du Logement
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

// Get parameters
$etat_lieux_id = isset($_POST['etat_lieux_id']) ? (int)$_POST['etat_lieux_id'] : 0;
$file_id = isset($_POST['file_id']) ? $_POST['file_id'] : '';

if ($etat_lieux_id < 1 || empty($file_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Paramètres invalides']);
    exit;
}

// Verify état des lieux exists
$stmt = $pdo->prepare("SELECT id, bilan_logement_justificatifs FROM etats_lieux WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$etat_lieux_id]);
$etat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$etat) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'État des lieux non trouvé']);
    exit;
}

try {
    // Get current justificatifs
    $justificatifs = [];
    if (!empty($etat['bilan_logement_justificatifs'])) {
        $justificatifs = json_decode($etat['bilan_logement_justificatifs'], true) ?: [];
    }
    
    // Find and remove the file
    $fileToDelete = null;
    $newJustificatifs = [];
    
    foreach ($justificatifs as $file) {
        if ($file['id'] === $file_id) {
            $fileToDelete = $file;
        } else {
            $newJustificatifs[] = $file;
        }
    }
    
    if (!$fileToDelete) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Fichier non trouvé']);
        exit;
    }
    
    // NOTE: Physical file is preserved (not deleted) as per requirements
    // Only removing from the justificatifs JSON array
    
    // Update database to remove file reference from JSON
    $stmt = $pdo->prepare("
        UPDATE etats_lieux 
        SET bilan_logement_justificatifs = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([
        empty($newJustificatifs) ? null : json_encode($newJustificatifs),
        $etat_lieux_id
    ]);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Fichier supprimé avec succès'
    ]);
    
} catch (Exception $e) {
    error_log("Error deleting justificatif: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur lors de la suppression: ' . $e->getMessage()]);
}
