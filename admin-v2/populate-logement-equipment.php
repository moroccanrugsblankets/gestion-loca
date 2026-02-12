<?php
/**
 * Helper Script: Populate Equipment for a Logement
 * 
 * This script helps populate equipment items for a specific logement
 * based on the complete template stored in parametres table.
 * 
 * Usage: /admin-v2/populate-logement-equipment.php?logement_id=X
 */

require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

// Get logement_id from query param
$logement_id = isset($_GET['logement_id']) ? (int)$_GET['logement_id'] : 0;

if ($logement_id <= 0) {
    die("Error: Please provide a valid logement_id parameter. Example: ?logement_id=1");
}

// Check if logement exists
$stmt = $pdo->prepare("SELECT * FROM logements WHERE id = ?");
$stmt->execute([$logement_id]);
$logement = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$logement) {
    die("Error: Logement #$logement_id not found.");
}

echo "<h2>Populate Equipment for Logement #{$logement_id}</h2>";
echo "<p><strong>Logement:</strong> " . htmlspecialchars($logement['reference']) . " - " . htmlspecialchars($logement['adresse']) . "</p>";

// Check if equipment already exists
$stmt = $pdo->prepare("SELECT COUNT(*) FROM inventaire_equipements WHERE logement_id = ?");
$stmt->execute([$logement_id]);
$existingCount = $stmt->fetchColumn();

if ($existingCount > 0) {
    echo "<div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;'>";
    echo "<strong>Warning:</strong> This logement already has $existingCount equipment items defined.";
    echo "<br><a href='?logement_id={$logement_id}&force=1'>Click here to delete existing and repopulate</a>";
    echo "</div>";
    
    if (!isset($_GET['force'])) {
        exit;
    }
    
    // Delete existing equipment
    $stmt = $pdo->prepare("DELETE FROM inventaire_equipements WHERE logement_id = ?");
    $stmt->execute([$logement_id]);
    echo "<p style='color: green;'>✓ Deleted $existingCount existing equipment items.</p>";
}

// Get template from parametres
$stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE nom = 'inventaire_items_template'");
$stmt->execute();
$templateJson = $stmt->fetchColumn();

if (!$templateJson) {
    die("<p style='color: red;'>Error: Equipment template not found. Please run migration 046 first.</p>");
}

$template = json_decode($templateJson, true);
if (!is_array($template)) {
    die("<p style='color: red;'>Erreur : Format de template invalide.</p>");
}

echo "<h3>Populating Equipment Items...</h3>";

try {
    $pdo->beginTransaction();
    
    $insertStmt = $pdo->prepare("
        INSERT INTO inventaire_equipements 
        (logement_id, categorie, nom, description, quantite, ordre)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $totalInserted = 0;
    $ordre = 0;
    
    foreach ($template as $mainCategory => $items) {
        echo "<h4>" . htmlspecialchars($mainCategory) . "</h4>";
        echo "<ul>";
        
        if (is_array($items)) {
            foreach ($items as $subCategoryOrItemName => $itemData) {
                // Check if this is a subcategory (État des pièces) or direct item
                if (is_array($itemData) && !isset($itemData['type'])) {
                    // This is a subcategory
                    echo "<li><strong>" . htmlspecialchars($subCategoryOrItemName) . "</strong><ul>";
                    
                    foreach ($itemData as $itemName => $itemDetails) {
                        $itemType = $itemDetails['type'] ?? 'item';
                        $itemDesc = $itemDetails['description'] ?? '';
                        $defaultQty = ($itemType === 'countable') ? 0 : 1; // 0 for countable, 1 for fixed items
                        
                        $fullCategoryName = $mainCategory . ' - ' . $subCategoryOrItemName;
                        
                        $insertStmt->execute([
                            $logement_id,
                            $fullCategoryName,
                            $itemName,
                            $itemDesc,
                            $defaultQty,
                            $ordre++
                        ]);
                        
                        $totalInserted++;
                        echo "<li>" . htmlspecialchars($itemName) . "</li>";
                    }
                    
                    echo "</ul></li>";
                } else {
                    // This is a direct item
                    $itemType = $itemData['type'] ?? 'item';
                    $itemDesc = $itemData['description'] ?? '';
                    $defaultQty = ($itemType === 'countable') ? 0 : 1;
                    
                    $insertStmt->execute([
                        $logement_id,
                        $mainCategory,
                        $subCategoryOrItemName,
                        $itemDesc,
                        $defaultQty,
                        $ordre++
                    ]);
                    
                    $totalInserted++;
                    echo "<li>" . htmlspecialchars($subCategoryOrItemName) . "</li>";
                }
            }
        }
        
        echo "</ul>";
    }
    
    $pdo->commit();
    
    echo "<div style='background: #d1ecf1; padding: 15px; border-left: 4px solid #0c5460; margin: 20px 0;'>";
    echo "<h3 style='margin-top: 0; color: #0c5460;'>✓ Success!</h3>";
    echo "<p><strong>Total items inserted:</strong> $totalInserted</p>";
    echo "<p>Equipment has been successfully populated for logement #{$logement_id}.</p>";
    echo "<p><a href='logements.php' style='color: #0c5460;'>← Back to Logements</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Populate Equipment - Logement #<?php echo $logement_id; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h2, h3, h4 {
            color: #333;
        }
        ul {
            line-height: 1.6;
        }
        a {
            color: #007bff;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
</body>
</html>
