<?php
/**
 * Download État des Lieux PDF
 * My Invest Immobilier
 */

require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';
require_once '../pdf/generate-etat-lieux.php';

// Get état des lieux ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id < 1) {
    die('ID de l\'état des lieux invalide.');
}

// Get état des lieux details
$stmt = $pdo->prepare("
    SELECT edl.*, 
           c.id as contrat_id,
           c.reference_unique as contrat_ref
    FROM etats_lieux edl
    LEFT JOIN contrats c ON edl.contrat_id = c.id
    WHERE edl.id = ?
");
$stmt->execute([$id]);
$etat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$etat) {
    die('État des lieux non trouvé.');
}

// Verify contract exists
if (empty($etat['contrat_id'])) {
    die('Contrat associé non trouvé.');
}

try {
    // Generate PDF using existing function
    $pdfPath = generateEtatDesLieuxPDF($etat['contrat_id'], $etat['type']);
    
    if (!$pdfPath || !file_exists($pdfPath)) {
        error_log("PDF generation failed for état des lieux ID: $id");
        die('Erreur lors de la génération du PDF.');
    }
    
    // Get file info
    $filename = 'etat_lieux_' . $etat['type'] . '_' . $etat['contrat_ref'] . '.pdf';
    $filesize = filesize($pdfPath);
    
    // Sanitize filename
    $safeFilename = preg_replace('/[^\w\s\-\.àâäéèêëïîôöùûüÿçÀÂÄÉÈÊËÏÎÔÖÙÛÜŸÇ]/u', '_', $filename);
    // Replace spaces with underscores for better compatibility
    $safeFilename = str_replace(' ', '_', $safeFilename);
    $safeFilename = str_replace(["\r", "\n"], '', $safeFilename);
    
    // Send headers to force download
    header('Content-Type: application/pdf');
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
    readfile($pdfPath);
    
    // Clean up temporary file if it exists in temp directory
    if (strpos($pdfPath, '/tmp/') !== false) {
        @unlink($pdfPath);
    }
    
    exit;
    
} catch (Exception $e) {
    error_log("Error downloading état des lieux PDF: " . $e->getMessage());
    die('Erreur lors du téléchargement du PDF: ' . htmlspecialchars($e->getMessage()));
}
