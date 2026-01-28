<?php
/**
 * Téléchargement du PDF du bail signé
 * My Invest Immobilier
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$contratId = (int)($_GET['contrat_id'] ?? 0);

if ($contratId === 0) {
    die('ID de contrat invalide.');
}

// Vérifier que le contrat existe et est signé
$contrat = fetchOne("SELECT * FROM contrats WHERE id = ? AND statut = 'signe'", [$contratId]);

if (!$contrat) {
    die('Contrat non trouvé ou non signé.');
}

// Chercher le fichier PDF/HTML généré
$pattern = $config['PDF_DIR'] . 'bail_' . $contrat['reference'] . '_*';
$files = glob($pattern);

if (empty($files)) {
    // Générer le PDF s'il n'existe pas
    require_once __DIR__ . '/generate-bail.php';
    $filepath = generateBailPDF($contratId);
} else {
    // Prendre le fichier le plus récent
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    $filepath = $files[0];
}

if (!$filepath || !file_exists($filepath)) {
    die('Erreur lors de la génération du PDF.');
}

// Déterminer le type MIME
$extension = pathinfo($filepath, PATHINFO_EXTENSION);
$mimeType = $extension === 'pdf' ? 'application/pdf' : 'text/html';
$filename = basename($filepath);

// Envoyer les headers pour le téléchargement
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Envoyer le fichier
readfile($filepath);
exit;
