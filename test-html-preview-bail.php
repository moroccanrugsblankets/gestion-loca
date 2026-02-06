<?php
/**
 * Script to preview HTML from generate-bail.php before TCPDF processing
 * This helps diagnose TCPDF border issues
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/pdf/generate-bail.php';

// Get contract ID from parameter or use a test ID
$contratId = isset($_GET['id']) ? (int)$_GET['id'] : 51;

// Fetch contract
$stmt = $pdo->prepare("
    SELECT c.*, l.reference, l.adresse, l.appartement, l.type as type_logement, l.surface, l.parking
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

// Generate HTML
$html = generateBailHTML($contrat, $locataires);

// Display raw HTML
header("Content-Type: text/html; charset=UTF-8");
echo $html;
