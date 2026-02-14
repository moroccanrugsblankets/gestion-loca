<?php
/**
 * Download Bilan de Logement PDF
 * My Invest Immobilier
 */

require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';
require_once '../pdf/generate-bilan-logement.php';

// Get contract ID
$contratId = isset($_GET['contrat_id']) ? (int)$_GET['contrat_id'] : 0;

if ($contratId < 1) {
    die('ID de contrat invalide.');
}

// Get contract details
$stmt = $pdo->prepare("
    SELECT c.*, 
           c.reference_unique as contrat_ref
    FROM contrats c
    WHERE c.id = ?
");
$stmt->execute([$contratId]);
$contrat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contrat) {
    die('Contrat non trouvé.');
}

// Verify bilan data exists
$stmt = $pdo->prepare("
    SELECT id FROM etats_lieux 
    WHERE contrat_id = ? AND type = 'sortie'
    AND bilan_logement_data IS NOT NULL
    ORDER BY created_at DESC 
    LIMIT 1
");
$stmt->execute([$contratId]);
$etatLieuxExists = $stmt->fetchColumn();

if (!$etatLieuxExists) {
    die('Aucun bilan de logement trouvé pour ce contrat.');
}

try {
    // Generate PDF using existing function
    $pdfPath = generateBilanLogementPDF($contratId);
    
    if (!$pdfPath || !file_exists($pdfPath)) {
        error_log("PDF generation failed for bilan logement, contract ID: $contratId");
        die('Erreur lors de la génération du PDF.');
    }
    
    // Get file info
    $filename = 'bilan_logement_' . $contrat['contrat_ref'] . '_' . date('Ymd') . '.pdf';
    $filesize = filesize($pdfPath);
    
    // Sanitize filename
    $safeFilename = preg_replace('/[^\w\s\-\.àâäéèêëïîôöùûüÿçÀÂÄÉÈÊËÏÎÔÖÙÛÜŸÇ]/u', '_', $filename);
    // Replace spaces with underscores for better compatibility
    $safeFilename = str_replace(' ', '_', $safeFilename);
    $safeFilename = str_replace(["\r", "\n"], '', $safeFilename);
    
    // Check if download is forced
    $forceDownload = isset($_GET['download']) && $_GET['download'] == '1';
    
    // Send headers - inline or attachment based on parameter
    header('Content-Type: application/pdf');
    if ($forceDownload) {
        header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
    } else {
        header('Content-Disposition: inline; filename="' . $safeFilename . '"');
    }
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
    
    // Clean up temporary file
    $realPath = realpath($pdfPath);
    $tempDir = realpath(sys_get_temp_dir());
    
    // Only delete if file is actually in temp directory (security check)
    if ($realPath !== false && $tempDir !== false && strpos($realPath, $tempDir) === 0) {
        @unlink($pdfPath);
    }
    
    exit;
    
} catch (Exception $e) {
    error_log("Error downloading bilan logement PDF: " . $e->getMessage());
    die('Erreur lors du téléchargement du PDF: ' . htmlspecialchars($e->getMessage()));
}
