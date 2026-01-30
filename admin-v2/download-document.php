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

// Validate document path (security check - must not contain .. or absolute paths)
if (empty($documentPath) || strpos($documentPath, '..') !== false || strpos($documentPath, '/') === 0) {
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

// Security check: ensure the file is within uploads directory (prevent directory traversal)
$realUploadsDir = realpath($uploadsDir);
$realFilePath = realpath($fullPath);

if (!$realFilePath || strpos($realFilePath, $realUploadsDir) !== 0) {
    die('Chemin de fichier invalide.');
}

// Check if file exists
if (!file_exists($fullPath)) {
    die('Fichier non trouvé sur le serveur.');
}

// Get file info
$filename = $document['nom_fichier'];
$filesize = filesize($fullPath);
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

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
header('Content-Disposition: attachment; filename="' . $filename . '"');
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
