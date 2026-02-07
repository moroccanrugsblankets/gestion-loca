<?php
/**
 * Upload Justificatif for Bilan du Logement
 * My Invest Immobilier
 */

require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

// File upload constants for Bilan du logement
define('BILAN_MAX_FILE_SIZE', 20 * 1024 * 1024); // 20MB
define('BILAN_ALLOWED_TYPES', ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png']);

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

// Get état des lieux ID
$etat_lieux_id = isset($_POST['etat_lieux_id']) ? (int)$_POST['etat_lieux_id'] : 0;

if ($etat_lieux_id < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID invalide']);
    exit;
}

// Verify état des lieux exists and is of type 'sortie'
$stmt = $pdo->prepare("SELECT id, type FROM etats_lieux WHERE id = ?");
$stmt->execute([$etat_lieux_id]);
$etat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$etat) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'État des lieux non trouvé']);
    exit;
}

if ($etat['type'] !== 'sortie') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Le bilan du logement est uniquement disponible pour les états de sortie']);
    exit;
}

// Handle file upload
if (!isset($_FILES['justificatif']) || $_FILES['justificatif']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Aucun fichier téléchargé ou erreur lors du téléchargement']);
    exit;
}

$file = $_FILES['justificatif'];

// Validate file type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, BILAN_ALLOWED_TYPES)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Type de fichier non autorisé. Formats acceptés: PDF, JPG, PNG']);
    exit;
}

// Validate file size
if ($file['size'] > BILAN_MAX_FILE_SIZE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Fichier trop volumineux. Taille maximale: 20MB']);
    exit;
}

try {
    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/../uploads/etats_lieux/' . $etat_lieux_id . '/bilan_justificatifs';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . '/' . $filename;
    $relativePath = 'uploads/etats_lieux/' . $etat_lieux_id . '/bilan_justificatifs/' . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Erreur lors du déplacement du fichier');
    }
    
    // Get current justificatifs from database
    $stmt = $pdo->prepare("SELECT bilan_logement_justificatifs FROM etats_lieux WHERE id = ?");
    $stmt->execute([$etat_lieux_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $justificatifs = [];
    if (!empty($result['bilan_logement_justificatifs'])) {
        $justificatifs = json_decode($result['bilan_logement_justificatifs'], true) ?: [];
    }
    
    // Add new file to the list
    $fileInfo = [
        'id' => uniqid(),
        'original_name' => $file['name'],
        'filename' => $filename,
        'path' => $relativePath,
        'size' => $file['size'],
        'type' => $mimeType,
        'uploaded_at' => date('Y-m-d H:i:s')
    ];
    $justificatifs[] = $fileInfo;
    
    // Update database
    $stmt = $pdo->prepare("
        UPDATE etats_lieux 
        SET bilan_logement_justificatifs = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([json_encode($justificatifs), $etat_lieux_id]);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'file' => $fileInfo
    ]);
    
} catch (Exception $e) {
    error_log("Error uploading justificatif: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'enregistrement: ' . $e->getMessage()]);
}
