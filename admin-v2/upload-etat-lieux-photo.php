<?php
/**
 * Upload Photo for État des Lieux
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

// Get état des lieux ID
$etat_lieux_id = isset($_POST['etat_lieux_id']) ? (int)$_POST['etat_lieux_id'] : 0;
$categorie = isset($_POST['categorie']) ? $_POST['categorie'] : '';

if ($etat_lieux_id < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID invalide']);
    exit;
}

// Validate category
$validCategories = ['compteur_electricite', 'compteur_eau', 'cles', 'piece_principale', 'cuisine', 'salle_eau', 'autre'];
if (!in_array($categorie, $validCategories)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Catégorie invalide']);
    exit;
}

// Verify état des lieux exists
$stmt = $pdo->prepare("SELECT id FROM etats_lieux WHERE id = ?");
$stmt->execute([$etat_lieux_id]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'État des lieux non trouvé']);
    exit;
}

// Handle file upload
if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Aucun fichier téléchargé ou erreur lors du téléchargement']);
    exit;
}

$file = $_FILES['photo'];

// Validate file type
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Type de fichier non autorisé. Seules les images sont acceptées.']);
    exit;
}

// Validate file size (max 5MB)
$maxSize = 5 * 1024 * 1024; // 5MB
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Fichier trop volumineux. Taille maximale: 5MB']);
    exit;
}

try {
    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/../uploads/etats_lieux/' . $etat_lieux_id;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . '/' . $filename;
    $relativePath = 'uploads/etats_lieux/' . $etat_lieux_id . '/' . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Erreur lors du déplacement du fichier');
    }
    
    // Save to database
    $stmt = $pdo->prepare("
        INSERT INTO etat_lieux_photos 
        (etat_lieux_id, categorie, nom_fichier, chemin_fichier, uploaded_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$etat_lieux_id, $categorie, $file['name'], $relativePath]);
    
    $photoId = $pdo->lastInsertId();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'photo_id' => $photoId,
        'filename' => $filename,
        'url' => '/' . $relativePath
    ]);
    
} catch (Exception $e) {
    error_log("Error uploading photo: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'enregistrement: ' . $e->getMessage()]);
}
