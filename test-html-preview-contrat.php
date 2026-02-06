<?php
/**
 * Script to preview HTML before TCPDF processing
 * Based on the user's request to view HTML before PDF execution
 * This helps diagnose TCPDF border issues
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/pdf/generate-contrat-pdf.php';

// Get contract ID from parameter or use a test ID
$contratId = isset($_GET['id']) ? (int)$_GET['id'] : 51;

// Fetch contract
$stmt = $pdo->prepare("
    SELECT c.*, 
           l.reference,
           l.adresse,
           l.appartement,
           l.type,
           l.surface,
           l.loyer,
           l.charges,
           l.depot_garantie,
           l.parking
    FROM contrats c
    INNER JOIN logements l ON c.logement_id = l.id
    WHERE c.id = ?
");
$stmt->execute([$contratId]);
$contrat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contrat) {
    die("Contrat #$contratId not found");
}

// Fetch tenants
$stmt = $pdo->prepare("SELECT * FROM locataires WHERE contrat_id = ? ORDER BY ordre ASC");
$stmt->execute([$contratId]);
$locataires = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($locataires)) {
    die("No tenants found for contract #$contratId");
}

// Get HTML template
$stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'contrat_template_html'");
$stmt->execute();
$templateHtml = $stmt->fetchColumn();

if (empty($templateHtml)) {
    $templateHtml = getDefaultContractTemplate();
}

// Replace variables
$html = replaceContratTemplateVariables($templateHtml, $contrat, $locataires);

// Inject signatures
$html = injectSignatures($html, $contrat, $locataires);

// Display raw HTML
header("Content-Type: text/html; charset=UTF-8");
echo $html;
