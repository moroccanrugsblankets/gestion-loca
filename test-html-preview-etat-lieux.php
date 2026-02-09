<?php
/**
 * Script to preview HTML from generate-etat-lieux.php before TCPDF processing
 * This helps diagnose TCPDF border issues
 * 
 * Based on user's request to view HTML before PDF execution
 * Usage: http://localhost/test-html-preview-etat-lieux.php?id=51&type=entree
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/etat-lieux-template.php';
require_once __DIR__ . '/pdf/generate-etat-lieux.php';

// Get contract ID from parameter or use a test ID
$contratId = isset($_GET['id']) ? (int)$_GET['id'] : 51;
$type = isset($_GET['type']) ? $_GET['type'] : 'entree';

// Validate type
if (!in_array($type, ['entree', 'sortie'])) {
    die("Type invalide. Utilisez 'entree' ou 'sortie'");
}

// Récupérer contrat
$stmt = $pdo->prepare("
    SELECT c.*, l.reference, l.adresse, l.type as type_logement, l.surface
    FROM contrats c
    INNER JOIN logements l ON c.logement_id = l.id
    WHERE c.id = ?
");
$stmt->execute([$contratId]);
$contrat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contrat) {
    die("Contrat #$contratId not found");
}

// Récupérer locataires
$stmt = $pdo->prepare("SELECT * FROM locataires WHERE contrat_id = ? ORDER BY ordre ASC");
$stmt->execute([$contratId]);
$locataires = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($locataires)) {
    die("No tenants found for contract #$contratId");
}

// Récupérer état des lieux
$stmt = $pdo->prepare("SELECT * FROM etats_lieux WHERE contrat_id = ? AND type = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$contratId, $type]);
$etatLieux = $stmt->fetch(PDO::FETCH_ASSOC);

// Charger template
$stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'etat_lieux_template_html'");
$stmt->execute();
$templateHtml = $stmt->fetchColumn();

if (empty($templateHtml) && function_exists('getDefaultEtatLieuxTemplate')) {
    $templateHtml = getDefaultEtatLieuxTemplate();
}

// Générer HTML
$html = replaceEtatLieuxTemplateVariables($templateHtml, $contrat, $locataires, $etatLieux, $type);

// Afficher brut
header("Content-Type: text/html; charset=UTF-8");
echo $html;
