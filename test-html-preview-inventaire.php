<?php
/**
 * Script to preview HTML from generate-inventaire.php before TCPDF processing
 * This helps diagnose TCPDF styling issues (borders, backgrounds on signatures)
 * 
 * Usage: http://localhost/test-html-preview-inventaire.php?id=1&type=entree
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/inventaire-template.php';
require_once __DIR__ . '/pdf/generate-inventaire.php';

// Get inventaire ID from parameter
$inventaireId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$type = isset($_GET['type']) ? $_GET['type'] : 'entree';

// Validate type
if (!in_array($type, ['entree', 'sortie'])) {
    die("Type invalide. Utilisez 'entree' ou 'sortie'");
}

// If no ID provided, list available inventaires
if (!$inventaireId) {
    echo "<h1>Test HTML Preview - Inventaire</h1>";
    echo "<p>Sélectionnez un inventaire pour voir le HTML avant génération du PDF:</p>";
    
    $stmt = $pdo->prepare("
        SELECT i.*, c.reference as contrat_reference, l.adresse
        FROM inventaires i
        INNER JOIN contrats c ON i.contrat_id = c.id
        INNER JOIN logements l ON c.logement_id = l.id
        WHERE i.type = ?
        ORDER BY i.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$type]);
    $inventaires = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($inventaires)) {
        echo "<p>Aucun inventaire de type '$type' trouvé.</p>";
        echo "<p><a href='?type=" . ($type === 'entree' ? 'sortie' : 'entree') . "'>Voir inventaires de " . ($type === 'entree' ? 'sortie' : 'entrée') . "</a></p>";
    } else {
        echo "<ul>";
        foreach ($inventaires as $inv) {
            echo "<li>";
            echo "<a href='?id=" . $inv['id'] . "&type=" . $inv['type'] . "'>";
            echo "Inventaire #" . $inv['id'] . " - " . htmlspecialchars($inv['adresse']) . " (Contrat: " . htmlspecialchars($inv['contrat_reference']) . ")";
            echo "</a>";
            echo " - " . date('d/m/Y', strtotime($inv['created_at']));
            echo "</li>";
        }
        echo "</ul>";
    }
    
    echo "<p><a href='?type=" . ($type === 'entree' ? 'sortie' : 'entree') . "'>Voir inventaires de " . ($type === 'entree' ? 'sortie' : 'entrée') . "</a></p>";
    exit;
}

// Récupérer inventaire
$stmt = $pdo->prepare("
    SELECT i.*, c.*, l.reference as logement_reference, l.adresse, l.type as type_logement, l.surface,
           l.appartement
    FROM inventaires i
    INNER JOIN contrats c ON i.contrat_id = c.id
    INNER JOIN logements l ON c.logement_id = l.id
    WHERE i.id = ?
");
$stmt->execute([$inventaireId]);
$inventaire = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inventaire) {
    die("Inventaire #$inventaireId not found");
}

// Récupérer locataires
$stmt = $pdo->prepare("SELECT * FROM locataires WHERE contrat_id = ? ORDER BY ordre ASC");
$stmt->execute([$inventaire['contrat_id']]);
$locataires = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($locataires)) {
    die("No tenants found for contract #" . $inventaire['contrat_id']);
}

// Charger template approprié selon le type
if ($inventaire['type'] === 'sortie') {
    $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'inventaire_sortie_template_html'");
    $stmt->execute();
    $templateHtml = $stmt->fetchColumn();
    
    if (empty($templateHtml) && function_exists('getDefaultInventaireSortieTemplate')) {
        $templateHtml = getDefaultInventaireSortieTemplate();
    }
} else {
    $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'inventaire_template_html'");
    $stmt->execute();
    $templateHtml = $stmt->fetchColumn();
    
    if (empty($templateHtml) && function_exists('getDefaultInventaireTemplate')) {
        $templateHtml = getDefaultInventaireTemplate();
    }
}

// Générer HTML avec les mêmes fonctions que le PDF
$html = replaceInventaireTemplateVariables($templateHtml, $inventaire, $locataires);

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
echo "<h2>🔍 Test HTML Preview - Inventaire</h2>";
echo "<p><strong>Mode:</strong> Aperçu HTML avant traitement TCPDF</p>";
echo "<p><strong>Inventaire ID:</strong> " . $inventaireId . "</p>";
echo "<p><strong>Type:</strong> " . htmlspecialchars($inventaire['type']) . "</p>";
echo "<p><strong>Référence:</strong> " . htmlspecialchars($inventaire['logement_reference']) . "</p>";
echo "<p><strong>Adresse:</strong> " . htmlspecialchars($inventaire['adresse']) . "</p>";
echo "<p><em>Inspectez les éléments de signature pour identifier les balises/CSS non supportées par TCPDF</em></p>";
echo "<p><a href='test-html-preview-inventaire.php?type=" . $inventaire['type'] . "'>← Retour à la liste</a> | ";
echo "<a href='pdf/generate-inventaire.php?id=" . $inventaireId . "' target='_blank'>Voir le PDF</a></p>";
echo "</div>";

// Display the generated HTML
echo $html;

echo "</body></html>";
