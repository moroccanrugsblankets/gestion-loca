<?php
/**
 * Force download of candidature documents
 * My Invest Immobilier
 */

require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

// Get and validate parameters
$candidatureId = isset($_GET['candidature_id']) ? (int)$_GET['candidature_id'] : 0;
$documentPath = isset($_GET['path']) ? $_GET['path'] : '';

// Validate candidature ID
if ($candidatureId < 1) {
    die('ID de candidature invalide.');
}

// Validate document path (security check - must not contain .., backslashes, or absolute paths)
if (empty($documentPath) || 
    strpos($documentPath, '..') !== false || 
    strpos($documentPath, '\\') !== false || 
    strpos($documentPath, '/') === 0) {
    die('Chemin de document invalide.');
}

// Verify that the document belongs to this candidature
$stmt = $pdo->prepare("
    SELECT nom_fichier, chemin_fichier 
    FROM candidature_documents 
    WHERE candidature_id = ? AND chemin_fichier = ?
");
$stmt->execute([$candidatureId, $documentPath]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    die('Document non trouvé ou non autorisé.');
}

// Build full file path
$uploadsDir = dirname(__DIR__) . '/uploads/';
$fullPath = $uploadsDir . $documentPath;

// Check if file exists first (before realpath which returns false for non-existent files)
if (!file_exists($fullPath)) {
    error_log("File not found: $fullPath (Document path: $documentPath, Candidature ID: $candidatureId)");
    die('Fichier non trouvé sur le serveur. Le fichier a peut-être été supprimé ou n\'a jamais été uploadé correctement.');
}

// Security check: ensure the file is within uploads directory (prevent directory traversal)
$realUploadsDir = realpath($uploadsDir);
$realFilePath = realpath($fullPath);

// Normalize paths for cross-platform comparison
if (!$realFilePath || !$realUploadsDir) {
    error_log("Invalid path - realpath failed unexpectedly. Full path: $fullPath, Uploads dir: $uploadsDir");
    die('Erreur lors de la vérification du chemin de fichier.');
}

// Use DIRECTORY_SEPARATOR for cross-platform compatibility and ensure trailing separator
$normalizedUploadsDir = rtrim($realUploadsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$normalizedFilePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $realFilePath);

if (stripos($normalizedFilePath, $normalizedUploadsDir) !== 0) {
    error_log("Security: File path outside uploads directory. Normalized path: $normalizedFilePath, Uploads dir: $normalizedUploadsDir");
    die('Chemin de fichier invalide - accès refusé pour des raisons de sécurité.');
}

// Get file info
$filename = $document['nom_fichier'];
$filesize = filesize($fullPath);
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

// Sanitize filename for Content-Disposition header (prevent header injection)
// Remove or replace potentially dangerous characters
$safeFilename = preg_replace('/[^\w\s\-\.àâäéèêëïîôöùûüÿçÀÂÄÉÈÊËÏÎÔÖÙÛÜŸÇ]/u', '_', $filename);
// Remove any newlines or carriage returns
$safeFilename = str_replace(["\r", "\n"], '', $safeFilename);

// Determine MIME type
$mimeTypes = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'txt' => 'text/plain',
    'zip' => 'application/zip',
];

$mimeType = isset($mimeTypes[$extension]) ? $mimeTypes[$extension] : 'application/octet-stream';

// Send headers to force download
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
header('Content-Length: ' . $filesize);
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Clear output buffer
while (ob_get_level()) {
    ob_end_clean();
}

// Send the file
readfile($fullPath);
exit;
