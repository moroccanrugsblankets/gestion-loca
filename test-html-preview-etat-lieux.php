<?php
/**
 * Script to preview HTML from generate-etat-lieux.php before TCPDF processing
 * This helps diagnose TCPDF styling issues (borders, backgrounds on signatures)
 * 
 * Usage: http://localhost/test-html-preview-etat-lieux.php?id=1&type=entree
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/etat-lieux-template.php';
require_once __DIR__ . '/pdf/generate-etat-lieux.php';

// Get état des lieux ID from parameter
$etatLieuxId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$type = isset($_GET['type']) ? $_GET['type'] : 'entree';

// Validate type
if (!in_array($type, ['entree', 'sortie'])) {
    die("Type invalide. Utilisez 'entree' ou 'sortie'");
}

// If no ID provided, list available états des lieux
if (!$etatLieuxId) {
    echo "<h1>Test HTML Preview - État des Lieux</h1>";
    echo "<p>Sélectionnez un état des lieux pour voir le HTML avant génération du PDF:</p>";
    
    $stmt = $pdo->prepare("
        SELECT e.*, c.reference as contrat_reference, l.adresse
        FROM etats_lieux e
        INNER JOIN contrats c ON e.contrat_id = c.id
        INNER JOIN logements l ON c.logement_id = l.id
        WHERE e.type = ?
        ORDER BY e.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$type]);
    $etatsLieux = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($etatsLieux)) {
        echo "<p>Aucun état des lieux de type '$type' trouvé.</p>";
        echo "<p><a href='?type=" . ($type === 'entree' ? 'sortie' : 'entree') . "'>Voir états des lieux de " . ($type === 'entree' ? 'sortie' : 'entrée') . "</a></p>";
    } else {
        echo "<ul>";
        foreach ($etatsLieux as $edl) {
            echo "<li>";
            echo "<a href='?id=" . $edl['id'] . "&type=" . $edl['type'] . "'>";
            echo "État des lieux #" . $edl['id'] . " - " . htmlspecialchars($edl['adresse']) . " (Contrat: " . htmlspecialchars($edl['contrat_reference']) . ")";
            echo "</a>";
            echo " - " . date('d/m/Y', strtotime($edl['created_at']));
            echo "</li>";
        }
        echo "</ul>";
    }
    
    echo "<p><a href='?type=" . ($type === 'entree' ? 'sortie' : 'entree') . "'>Voir états des lieux de " . ($type === 'entree' ? 'sortie' : 'entrée') . "</a></p>";
    exit;
}

// Récupérer état des lieux
$stmt = $pdo->prepare("
    SELECT e.*, c.*, l.reference as logement_reference, l.adresse, l.type as type_logement, l.surface,
           l.appartement
    FROM etats_lieux e
    INNER JOIN contrats c ON e.contrat_id = c.id
    INNER JOIN logements l ON c.logement_id = l.id
    WHERE e.id = ?
");
$stmt->execute([$etatLieuxId]);
$etatLieux = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$etatLieux) {
    die("État des lieux #$etatLieuxId not found");
}

// Récupérer locataires
$stmt = $pdo->prepare("SELECT * FROM locataires WHERE contrat_id = ? ORDER BY ordre ASC");
$stmt->execute([$etatLieux['contrat_id']]);
$locataires = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($locataires)) {
    die("No tenants found for contract #" . $etatLieux['contrat_id']);
}

// Charger template approprié selon le type
if ($etatLieux['type'] === 'sortie') {
    $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'etat_lieux_sortie_template_html'");
    $stmt->execute();
    $templateHtml = $stmt->fetchColumn();
    
    if (empty($templateHtml) && function_exists('getDefaultExitEtatLieuxTemplate')) {
        $templateHtml = getDefaultExitEtatLieuxTemplate();
    }
} else {
    $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'etat_lieux_template_html'");
    $stmt->execute();
    $templateHtml = $stmt->fetchColumn();
    
    if (empty($templateHtml) && function_exists('getDefaultEtatLieuxTemplate')) {
        $templateHtml = getDefaultEtatLieuxTemplate();
    }
}

// Merge contrat and etatLieux data for the template function
$contratData = $etatLieux;
$type = $etatLieux['type'];

// Générer HTML avec les mêmes fonctions que le PDF
$html = replaceEtatLieuxTemplateVariables($templateHtml, $contratData, $locataires, $etatLieux, $type);

// Add a debug header
echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>
.debug-header {
    background: #fff3cd;
    border: 2px solid #ffc107;
    padding: 15px;
    margin: 20px;
    border-radius: 5px;
}
.debug-header h2 {
    margin-top: 0;
    color: #856404;
}
.debug-header p {
    margin: 5px 0;
    color: #856404;
}
.debug-header code {
    background: #fff;
    padding: 2px 6px;
    border-radius: 3px;
}
</style></head><body>";

echo "<div class='debug-header'>";
echo "<h2>🔍 Test HTML Preview - État des Lieux</h2>";
echo "<p><strong>Mode:</strong> Aperçu HTML avant traitement TCPDF</p>";
echo "<p><strong>État des lieux ID:</strong> " . $etatLieuxId . "</p>";
echo "<p><strong>Type:</strong> " . htmlspecialchars($etatLieux['type']) . "</p>";
echo "<p><strong>Référence:</strong> " . htmlspecialchars($etatLieux['logement_reference']) . "</p>";
echo "<p><strong>Adresse:</strong> " . htmlspecialchars($etatLieux['adresse']) . "</p>";
echo "<p><em>Inspectez les éléments de signature pour identifier les balises/CSS non supportées par TCPDF</em></p>";
echo "<p><a href='test-html-preview-etat-lieux.php?type=" . $etatLieux['type'] . "'>← Retour à la liste</a> | ";
echo "<a href='pdf/generate-etat-lieux.php?id=" . $etatLieuxId . "' target='_blank'>Voir le PDF</a></p>";
echo "</div>";

// Display the generated HTML
echo $html;

echo "</body></html>";
