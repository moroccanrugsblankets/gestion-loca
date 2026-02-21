<?php
/**
 * Compare Inventaire - Side-by-side comparison of entry and exit inventories
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once 'auth.php';
require_once '../includes/db.php';

$contrat_id = isset($_GET['contrat_id']) ? (int)$_GET['contrat_id'] : 0;
$entree_id = isset($_GET['entree']) ? (int)$_GET['entree'] : 0;
$sortie_id = isset($_GET['sortie']) ? (int)$_GET['sortie'] : 0;

if ($entree_id && $sortie_id) {
    // Direct inventory IDs provided (from contrat-detail.php)
    $stmt = $pdo->prepare("
        SELECT inv.*, l.reference as logement_reference, l.type as logement_type,
               c.reference_unique as contrat_ref
        FROM inventaires inv
        LEFT JOIN logements l ON inv.logement_id = l.id
        LEFT JOIN contrats c ON inv.contrat_id = c.id
        WHERE inv.id = ? AND inv.type = 'entree' LIMIT 1
    ");
    $stmt->execute([$entree_id]);
    $inv_entree = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT inv.*, l.reference as logement_reference, l.type as logement_type,
               c.reference_unique as contrat_ref
        FROM inventaires inv
        LEFT JOIN logements l ON inv.logement_id = l.id
        LEFT JOIN contrats c ON inv.contrat_id = c.id
        WHERE inv.id = ? AND inv.type = 'sortie' LIMIT 1
    ");
    $stmt->execute([$sortie_id]);
    $inv_sortie = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($contrat_id) {
    // Lookup by contract ID
    $stmt = $pdo->prepare("
        SELECT inv.*, l.reference as logement_reference, l.type as logement_type,
               c.reference_unique as contrat_ref
        FROM inventaires inv
        LEFT JOIN logements l ON inv.logement_id = l.id
        LEFT JOIN contrats c ON inv.contrat_id = c.id
        WHERE inv.contrat_id = ? AND inv.type = 'entree' ORDER BY inv.date_inventaire DESC LIMIT 1
    ");
    $stmt->execute([$contrat_id]);
    $inv_entree = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT inv.*, l.reference as logement_reference, l.type as logement_type,
               c.reference_unique as contrat_ref
        FROM inventaires inv
        LEFT JOIN logements l ON inv.logement_id = l.id
        LEFT JOIN contrats c ON inv.contrat_id = c.id
        WHERE inv.contrat_id = ? AND inv.type = 'sortie' ORDER BY inv.date_inventaire DESC LIMIT 1
    ");
    $stmt->execute([$contrat_id]);
    $inv_sortie = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $_SESSION['error'] = "Contrat non spécifié";
    header('Location: inventaires.php');
    exit;
}

if (!$inv_entree && !$inv_sortie) {
    $_SESSION['error'] = "Aucun inventaire trouvé";
    header('Location: inventaires.php');
    exit;
}

$contrat_ref = $inv_entree['contrat_ref'] ?? $inv_sortie['contrat_ref'] ?? 'N/A';
$adresse = $inv_entree['adresse'] ?? $inv_sortie['adresse'] ?? 'N/A';

// Decode equipment data
$equipements_entree = json_decode(($inv_entree ? $inv_entree['equipements_data'] : null) ?? '[]', true);
if (!is_array($equipements_entree)) $equipements_entree = [];

$equipements_sortie = json_decode(($inv_sortie ? $inv_sortie['equipements_data'] : null) ?? '[]', true);
if (!is_array($equipements_sortie)) $equipements_sortie = [];

// Index sortie equipment by name+category for easy lookup (using null byte as safe delimiter)
$sortie_index = [];
foreach ($equipements_sortie as $eq) {
    $key = ($eq['categorie'] ?? '') . "\0" . ($eq['nom'] ?? '');
    $sortie_index[$key] = $eq;
}

// Fetch category order from database
$category_order = [];
try {
    $stmt = $pdo->query("SELECT nom, ordre FROM inventaire_categories ORDER BY ordre ASC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $category_order[$row['nom']] = (int)$row['ordre'];
    }
} catch (Exception $e) {
    error_log("Failed to fetch category order: " . $e->getMessage());
}

// Group entry equipment by category
$equipements_by_category = [];
foreach ($equipements_entree as $eq) {
    $cat = $eq['categorie'] ?? 'Autre';
    if (!isset($equipements_by_category[$cat])) {
        $equipements_by_category[$cat] = [];
    }
    $equipements_by_category[$cat][] = $eq;
}

// Also add any categories present only in sortie
foreach ($equipements_sortie as $eq) {
    $cat = $eq['categorie'] ?? 'Autre';
    if (!isset($equipements_by_category[$cat])) {
        $equipements_by_category[$cat] = [];
    }
}

// Sort categories by their ordre field from database
uksort($equipements_by_category, function($a, $b) use ($category_order) {
    $orderA = $category_order[$a] ?? 999;
    $orderB = $category_order[$b] ?? 999;
    if ($orderA === $orderB) {
        return strcmp($a, $b);
    }
    return $orderA - $orderB;
});
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comparaison des inventaires - <?php echo htmlspecialchars($contrat_ref); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <?php require_once __DIR__ . '/includes/sidebar-styles.php'; ?>
    <style>
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .comparison-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .comparison-table {
            width: 100%;
        }
        .comparison-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: center;
            border: 1px solid #dee2e6;
        }
        .comparison-table td {
            padding: 12px;
            border: 1px solid #dee2e6;
            vertical-align: top;
        }
        .comparison-table td.field-name {
            font-weight: 600;
            background: #f8f9fa;
            width: 25%;
        }
        .value-entry {
            background: #e7f5ff;
        }
        .value-exit {
            background: #fff5e7;
        }
        .difference {
            background: #ffe7e7;
            padding: 5px;
            border-radius: 4px;
            font-weight: 600;
        }
        .match {
            background: #e7ffe7;
            padding: 5px;
            border-radius: 4px;
        }
        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #212529;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #0d6efd;
        }
        .row-diff td.value-entry,
        .row-diff td.value-exit {
            background: #fff3cd;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/menu.php'; ?>

    <div class="main-content">
        <div class="header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4><i class="bi bi-arrows-angle-contract"></i> Comparaison des inventaires</h4>
                    <p class="text-muted mb-0">
                        Contrat: <?php echo htmlspecialchars($contrat_ref); ?> - <?php echo htmlspecialchars($adresse); ?>
                    </p>
                </div>
                <div>
                    <a href="inventaires.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Retour
                    </a>
                </div>
            </div>
        </div>

        <?php if (!$inv_entree): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> Aucun inventaire d'entrée trouvé pour ce contrat.
            </div>
        <?php endif; ?>

        <?php if (!$inv_sortie): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> Aucun inventaire de sortie trouvé pour ce contrat.
            </div>
        <?php endif; ?>

        <?php if ($inv_entree && $inv_sortie): ?>

        <!-- Informations générales -->
        <div class="comparison-card">
            <div class="section-title"><i class="bi bi-info-circle"></i> Informations générales</div>
            <table class="comparison-table">
                <thead>
                    <tr>
                        <th style="width: 25%;"></th>
                        <th style="width: 37.5%;" class="value-entry">Entrée (<?php echo date('d/m/Y', strtotime($inv_entree['date_inventaire'])); ?>)</th>
                        <th style="width: 37.5%;" class="value-exit">Sortie (<?php echo date('d/m/Y', strtotime($inv_sortie['date_inventaire'])); ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="field-name">Référence</td>
                        <td class="value-entry"><?php echo htmlspecialchars($inv_entree['reference_unique']); ?></td>
                        <td class="value-exit"><?php echo htmlspecialchars($inv_sortie['reference_unique']); ?></td>
                    </tr>
                    <tr>
                        <td class="field-name">Locataire(s)</td>
                        <td class="value-entry"><?php echo htmlspecialchars($inv_entree['locataire_nom_complet'] ?? 'N/A'); ?></td>
                        <td class="value-exit"><?php echo htmlspecialchars($inv_sortie['locataire_nom_complet'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <td class="field-name">PDF</td>
                        <td class="value-entry">
                            <a href="download-inventaire.php?id=<?php echo $inv_entree['id']; ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                <i class="bi bi-file-pdf"></i> PDF entrée
                            </a>
                        </td>
                        <td class="value-exit">
                            <a href="download-inventaire.php?id=<?php echo $inv_sortie['id']; ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                <i class="bi bi-file-pdf"></i> PDF sortie
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Équipements par catégorie -->
        <?php foreach ($equipements_by_category as $categorie => $equipements_cat_entree): ?>
        <div class="comparison-card">
            <div class="section-title"><i class="bi bi-box-seam"></i> <?php echo htmlspecialchars($categorie); ?></div>
            <table class="comparison-table">
                <thead>
                    <tr>
                        <th style="width: 25%;">Élément</th>
                        <th style="width: 37.5%;" class="value-entry">Entrée</th>
                        <th style="width: 37.5%;" class="value-exit">Sortie</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Collect all item names in this category (union of entree and sortie)
                    $items_in_cat = [];
                    foreach ($equipements_cat_entree as $eq) {
                        $items_in_cat[$eq['nom'] ?? ''] = true;
                    }
                    foreach ($equipements_sortie as $eq) {
                        if (($eq['categorie'] ?? 'Autre') === $categorie) {
                            $items_in_cat[$eq['nom'] ?? ''] = true;
                        }
                    }

                    // Index entree items by name
                    $entree_items = [];
                    foreach ($equipements_cat_entree as $eq) {
                        $entree_items[$eq['nom'] ?? ''] = $eq;
                    }

                    foreach ($items_in_cat as $nom => $_): 
                        $eq_e = $entree_items[$nom] ?? null;
                        $key = $categorie . "\0" . $nom;
                        $eq_s = $sortie_index[$key] ?? null;

                        $qty_e = $eq_e ? getInventaireEquipmentQuantity($eq_e) : '';
                        $qty_s = $eq_s ? getInventaireEquipmentQuantity($eq_s) : '';
                        $comment_e = $eq_e ? ($eq_e['commentaires'] ?? ($eq_e['observations'] ?? '')) : '';
                        $comment_s = $eq_s ? ($eq_s['commentaires'] ?? ($eq_s['observations'] ?? '')) : '';

                        $is_diff = ($qty_e !== $qty_s) || ($comment_e !== $comment_s);
                        $row_class = $is_diff ? 'row-diff' : '';
                    ?>
                    <tr class="<?php echo $row_class; ?>">
                        <td class="field-name">
                            <?php echo htmlspecialchars($nom); ?>
                            <?php if ($is_diff): ?>
                                <span class="badge bg-warning text-dark ms-1"><i class="bi bi-exclamation-triangle"></i></span>
                            <?php endif; ?>
                        </td>
                        <td class="value-entry">
                            <?php if ($eq_e): ?>
                                <strong><?php echo htmlspecialchars($qty_e); ?></strong>
                                <?php if ($comment_e): ?><br><small class="text-muted"><?php echo nl2br(htmlspecialchars($comment_e)); ?></small><?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted fst-italic">Non renseigné</span>
                            <?php endif; ?>
                        </td>
                        <td class="value-exit">
                            <?php if ($eq_s): ?>
                                <strong><?php echo htmlspecialchars($qty_s); ?></strong>
                                <?php if ($qty_s !== $qty_e): ?>
                                    <span class="difference ms-1">⚠ <?php echo htmlspecialchars($qty_e); ?> → <?php echo htmlspecialchars($qty_s); ?></span>
                                <?php elseif ($qty_s === $qty_e && $qty_s !== ''): ?>
                                    <span class="match ms-1">✓</span>
                                <?php endif; ?>
                                <?php if ($comment_s): ?><br><small class="text-muted"><?php echo nl2br(htmlspecialchars($comment_s)); ?></small><?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted fst-italic">Non renseigné</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>

        <!-- Observations générales -->
        <?php if (!empty($inv_entree['observations_generales']) || !empty($inv_sortie['observations_generales'])): ?>
        <div class="comparison-card">
            <div class="section-title"><i class="bi bi-chat-text"></i> Observations générales</div>
            <table class="comparison-table">
                <thead>
                    <tr>
                        <th style="width: 50%;" class="value-entry">Entrée</th>
                        <th style="width: 50%;" class="value-exit">Sortie</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="value-entry">
                            <?php echo nl2br(htmlspecialchars($inv_entree['observations_generales'] ?? '')); ?>
                        </td>
                        <td class="value-exit">
                            <?php echo nl2br(htmlspecialchars($inv_sortie['observations_generales'] ?? '')); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
