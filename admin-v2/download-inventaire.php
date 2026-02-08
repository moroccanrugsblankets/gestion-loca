<?php
require_once '../includes/config.php';
require_once 'auth.php';

if (!isset($_GET['id'])) {
    die('Inventaire non spécifié');
}

$inventaire_id = (int)$_GET['id'];
$download = isset($_GET['download']) && $_GET['download'] == '1';

// Include PDF generation
require_once '../pdf/generate-inventaire.php';

try {
    $pdfPath = generateInventairePDF($inventaire_id);
    
    if (!file_exists($pdfPath)) {
        die('Erreur: PDF non généré');
    }
    
    // Set headers for PDF display or download
    header('Content-Type: application/pdf');
    
    if ($download) {
        $filename = 'inventaire_' . $inventaire_id . '_' . date('Ymd') . '.pdf';
        header('Content-Disposition: attachment; filename="' . $filename . '"');
    } else {
        header('Content-Disposition: inline');
    }
    
    header('Content-Length: ' . filesize($pdfPath));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    readfile($pdfPath);
    
    // Clean up temp file
    if (strpos($pdfPath, '/tmp/') === 0) {
        @unlink($pdfPath);
    }
    
} catch (Exception $e) {
    error_log("Erreur génération PDF inventaire: " . $e->getMessage());
    die('Erreur lors de la génération du PDF: ' . $e->getMessage());
}
