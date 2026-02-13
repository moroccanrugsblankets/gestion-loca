<?php
/**
 * Edit Inventaire - Dynamic equipment loading from database
 * Equipment is loaded from inventaire_equipements table based on logement_id
 * Falls back to standard items if no equipment is defined for the logement
 * Enhanced interface with Entry/Exit grid and subcategory organization
 */
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/inventaire-standard-items.php';

$inventaire_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$inventaire_id) {
    $_SESSION['error'] = "Inventaire non spécifié";
    header('Location: inventaires.php');
    exit;
}

// Get inventaire data
$stmt = $pdo->prepare("
    SELECT inv.*, 
           l.reference as logement_reference,
           l.type as logement_type
    FROM inventaires inv
    INNER JOIN logements l ON inv.logement_id = l.id
    WHERE inv.id = ?
");
$stmt->execute([$inventaire_id]);
$inventaire = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inventaire) {
    $_SESSION['error'] = "Inventaire introuvable";
    header('Location: inventaires.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Process standardized inventory items
        $equipements_data = [];
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $itemData) {
                $equipements_data[] = [
                    'id' => (int)$itemData['id'],
                    'categorie' => $itemData['categorie'] ?? '',
                    'sous_categorie' => $itemData['sous_categorie'] ?? null,
                    'nom' => $itemData['nom'] ?? '',
                    'type' => $itemData['type'] ?? 'item',
                    'entree' => [
                        'nombre' => isset($itemData['entree_nombre']) && $itemData['entree_nombre'] !== '' ? (int)$itemData['entree_nombre'] : null,
                        'bon' => isset($itemData['entree_bon']),
                        'usage' => isset($itemData['entree_usage']),
                        'mauvais' => isset($itemData['entree_mauvais']),
                    ],
                    'sortie' => [
                        'nombre' => isset($itemData['sortie_nombre']) && $itemData['sortie_nombre'] !== '' ? (int)$itemData['sortie_nombre'] : null,
                        'bon' => isset($itemData['sortie_bon']),
                        'usage' => isset($itemData['sortie_usage']),
                        'mauvais' => isset($itemData['sortie_mauvais']),
                    ],
                    'commentaires' => $itemData['commentaires'] ?? ''
                ];
            }
        }
        
        $stmt = $pdo->prepare("
            UPDATE inventaires SET
                equipements_data = ?,
                observations_generales = ?,
                lieu_signature = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            json_encode($equipements_data, JSON_UNESCAPED_UNICODE),
            $_POST['observations_generales'] ?? null,
            $_POST['lieu_signature'] ?? null,
            $inventaire_id
        ]);
        
        // Update tenant signatures
        if (isset($_POST['tenants']) && is_array($_POST['tenants'])) {
            foreach ($_POST['tenants'] as $tenantId => $tenantInfo) {
                // Only update certifie_exact if finalizing (not for draft saves)
                // For draft saves, we preserve the existing value unless explicitly checked
                // For finalize, we always update it (validation ensures it's checked)
                $isFinalizing = isset($_POST['finalize']) && $_POST['finalize'] === '1';
                
                if ($isFinalizing) {
                    // Update certifie_exact status (only for finalize)
                    $certifieExact = isset($tenantInfo['certifie_exact']) ? 1 : 0;
                    
                    $stmt = $pdo->prepare("
                        UPDATE inventaire_locataires 
                        SET certifie_exact = ?
                        WHERE id = ? AND inventaire_id = ?
                    ");
                    $stmt->execute([$certifieExact, $tenantId, $inventaire_id]);
                } else {
                    // For draft saves, only update if explicitly checked (preserve existing value otherwise)
                    if (isset($tenantInfo['certifie_exact'])) {
                        $stmt = $pdo->prepare("
                            UPDATE inventaire_locataires 
                            SET certifie_exact = 1
                            WHERE id = ? AND inventaire_id = ?
                        ");
                        $stmt->execute([$tenantId, $inventaire_id]);
                    }
                }
                
                // Update signature if provided
                if (!empty($tenantInfo['signature'])) {
                    // Validate signature format
                    if (!preg_match('/^data:image\/(jpeg|jpg|png);base64,[A-Za-z0-9+\/=]+$/', $tenantInfo['signature'])) {
                        error_log("Invalid signature format for tenant ID: $tenantId");
                        continue;
                    }
                    
                    // Use the helper function from functions.php
                    $result = updateInventaireTenantSignature($tenantId, $tenantInfo['signature'], $inventaire_id);
                    
                    if (!$result) {
                        error_log("Failed to save signature for tenant ID: $tenantId");
                    }
                }
            }
        }
        
        $pdo->commit();
        $_SESSION['success'] = "Inventaire mis à jour avec succès";
        
        // If finalizing, redirect to finalize page
        if (isset($_POST['finalize']) && $_POST['finalize'] === '1') {
            header("Location: finalize-inventaire.php?id=$inventaire_id");
            exit;
        }
        
        header("Location: edit-inventaire.php?id=$inventaire_id");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Erreur lors de la mise à jour: " . $e->getMessage();
        error_log("Erreur update inventaire: " . $e->getMessage());
    }
}

// Load equipment from database for this logement
// First, try to get equipment defined specifically for this logement
$stmt = $pdo->prepare("
    SELECT e.*, 
           c.nom as categorie_nom, 
           c.icone as categorie_icone,
           sc.nom as sous_categorie_nom
    FROM inventaire_equipements e
    LEFT JOIN inventaire_categories c ON e.categorie_id = c.id
    LEFT JOIN inventaire_sous_categories sc ON e.sous_categorie_id = sc.id
    WHERE e.logement_id = ? 
    ORDER BY COALESCE(c.ordre, 999), e.ordre, e.nom
");
$stmt->execute([$inventaire['logement_id']]);
$logement_equipements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Transform database equipment into the structure expected by the view
// The view expects: $standardItems[$categoryName][$subcategoryName][] = ['nom' => '...', 'type' => '...']
// or $standardItems[$categoryName][] = ['nom' => '...', 'type' => '...'] for categories without subcategories
$standardItems = [];

if (!empty($logement_equipements)) {
    // Use equipment from database
    foreach ($logement_equipements as $eq) {
        $categoryName = $eq['categorie_nom'] ?: $eq['categorie']; // Use category name from join or fallback to text field
        $subcategoryName = $eq['sous_categorie_nom'];
        
        $item = [
            'nom' => $eq['nom'],
            // Note: Database equipment defaults to 'countable' type.
            // The type field is used by the view to determine rendering behavior.
            // All equipment from the database is treated as countable.
            'type' => 'countable'
        ];
        
        if ($subcategoryName) {
            // Equipment has a subcategory
            if (!isset($standardItems[$categoryName])) {
                $standardItems[$categoryName] = [];
            }
            if (!isset($standardItems[$categoryName][$subcategoryName])) {
                $standardItems[$categoryName][$subcategoryName] = [];
            }
            $standardItems[$categoryName][$subcategoryName][] = $item;
        } else {
            // Equipment without subcategory
            if (!isset($standardItems[$categoryName])) {
                $standardItems[$categoryName] = [];
            }
            $standardItems[$categoryName][] = $item;
        }
    }
} else {
    // Fallback to standard items if no equipment defined for this logement
    $standardItems = getStandardInventaireItems($inventaire['logement_reference']);
}

// Generate initial inventory data structure from equipment
// This will be used to initialize the form if no saved data exists
function generateInventoryDataFromEquipment($standardItems) {
    $data = [];
    $itemIndex = 0;
    
    // New simplified structure - no subcategories, flat list per category
    foreach ($standardItems as $categoryName => $categoryItems) {
        foreach ($categoryItems as $item) {
            $data[] = [
                'id' => ++$itemIndex,
                'categorie' => $categoryName,
                'sous_categorie' => null, // No subcategories in new structure
                'nom' => $item['nom'],
                'type' => $item['type'],
                'entree' => [
                    'nombre' => $item['quantite'] ?? 0,
                    'bon' => isset($item['default_etat']) && $item['default_etat'] === 'bon',
                    'usage' => false,
                    'mauvais' => false,
                ],
                'sortie' => [
                    'nombre' => null,
                    'bon' => false,
                    'usage' => false,
                    'mauvais' => false,
                ],
                'commentaires' => ''
            ];
        }
    }
    
    return $data;
}

// Decode equipment data
$equipements_data = json_decode($inventaire['equipements_data'], true);
if (!is_array($equipements_data)) {
    $equipements_data = [];
}

// If no data exists, generate from equipment (database or standard items)
if (empty($equipements_data)) {
    $equipements_data = generateInventoryDataFromEquipment($standardItems);
}

// Index existing data by ID for quick lookup
$existing_data_by_id = [];
foreach ($equipements_data as $item) {
    if (isset($item['id'])) {
        $existing_data_by_id[$item['id']] = $item;
    }
}

// Get existing tenants for this inventaire
$stmt = $pdo->prepare("SELECT * FROM inventaire_locataires WHERE inventaire_id = ? ORDER BY id ASC");
$stmt->execute([$inventaire_id]);
$existing_tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If no tenants linked yet, auto-populate from contract (if inventaire is linked to a contract)
if (empty($existing_tenants) && !empty($inventaire['contrat_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM locataires WHERE contrat_id = ? ORDER BY ordre ASC");
    $stmt->execute([$inventaire['contrat_id']]);
    $contract_tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get existing tenant-inventaire relationships to avoid duplicate inserts
    $existingLinkStmt = $pdo->prepare("
        SELECT locataire_id FROM inventaire_locataires 
        WHERE inventaire_id = ?
    ");
    $existingLinkStmt->execute([$inventaire_id]);
    $existing_tenant_ids = $existingLinkStmt->fetchAll(PDO::FETCH_COLUMN);
    $existing_tenant_lookup = array_flip($existing_tenant_ids); // Convert to associative array for O(1) lookup
    
    // Insert tenants into inventaire_locataires with duplicate check
    $insertStmt = $pdo->prepare("
        INSERT INTO inventaire_locataires (inventaire_id, locataire_id, nom, prenom, email)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($contract_tenants as $tenant) {
        // Check if this tenant is already linked to this inventaire using in-memory map
        if (!isset($existing_tenant_lookup[$tenant['id']])) {
            $insertStmt->execute([
                $inventaire_id,
                $tenant['id'],
                $tenant['nom'],
                $tenant['prenom'],
                $tenant['email']
            ]);
        }
    }
    
    // Reload tenants
    $stmt = $pdo->prepare("SELECT * FROM inventaire_locataires WHERE inventaire_id = ? ORDER BY id ASC");
    $stmt->execute([$inventaire_id]);
    $existing_tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Transform tenant signatures for display
foreach ($existing_tenants as &$tenant) {
    $tenant['signature_data'] = $tenant['signature'] ?? '';
    $tenant['signature_timestamp'] = $tenant['date_signature'] ?? '';
}

$isEntreeInventory = ($inventaire['type'] === 'entree');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier l'inventaire - <?php echo htmlspecialchars($inventaire['reference_unique']); ?></title>
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
        .form-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .category-section {
            margin-bottom: 30px;
        }
        .category-header {
            font-size: 1.3rem;
            font-weight: 600;
            color: #212529;
            margin-bottom: 1.5rem;
            padding: 12px 15px;
            background: linear-gradient(135deg, #0d6efd 0%, #0056b3 100%);
            color: white;
            border-radius: 6px;
            border-left: 5px solid #004085;
        }
        .subcategory-header {
            font-size: 1.1rem;
            font-weight: 600;
            color: #495057;
            margin-top: 1.5rem;
            margin-bottom: 1rem;
            padding: 8px 12px;
            background: #f8f9fa;
            border-left: 4px solid #6c757d;
            border-radius: 4px;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .inventory-table {
            font-size: 0.9rem;
        }
        .inventory-table thead th {
            font-weight: 600;
            text-align: center;
            vertical-align: middle;
            white-space: nowrap;
        }
        .inventory-table td {
            vertical-align: middle;
        }
        .inventory-table input[type="number"] {
            min-width: 60px;
        }
        .inventory-table input[type="text"] {
            min-width: 150px;
        }
        .readonly-column {
            background-color: #f8f9fa !important;
        }
        .signature-container {
            border: 2px solid #dee2e6;
            border-radius: 5px;
            background-color: #ffffff;
            display: inline-block;
            cursor: crosshair;
            margin-bottom: 10px;
        }
        .signature-container canvas {
            display: block;
        }
        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #212529;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e9ecef;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/menu.php'; ?>

    <div class="main-content">
        <div class="header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4>Modifier l'inventaire</h4>
                    <p class="text-muted mb-0">
                        <?php echo htmlspecialchars($inventaire['reference_unique']); ?> - 
                        Inventaire d'<?php echo $inventaire['type']; ?> - 
                        <?php echo htmlspecialchars($inventaire['logement_reference']); ?>
                    </p>
                </div>
                <div>
                    <?php if ($inventaire['type'] === 'sortie'): ?>
                    <button type="button" class="btn btn-warning" onclick="duplicateEntryToExit()" title="Copie les données d'entrée vers la sortie">
                        <i class="bi bi-copy"></i> Dupliquer Entrée → Sortie
                    </button>
                    <?php endif; ?>
                    <a href="download-inventaire.php?id=<?php echo $inventaire_id; ?>" class="btn btn-info" target="_blank">
                        <i class="bi bi-file-pdf"></i> Voir le PDF
                    </a>
                    <a href="inventaires.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Retour
                    </a>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" id="inventaireForm">
            <?php 
            $itemIndex = 0;
            foreach ($standardItems as $categoryName => $categoryContent): 
            ?>
                <div class="form-card">
                    <div class="category-section">
                        <div class="category-header">
                            <i class="bi bi-box-seam"></i> <?php echo htmlspecialchars($categoryName); ?>
                        </div>
                        
                        <?php if ($categoryName === 'État des pièces'): ?>
                            <!-- État des pièces has subcategories -->
                            <?php foreach ($categoryContent as $subcategoryName => $subcategoryItems): ?>
                                <div class="subcategory-header">
                                    <?php echo htmlspecialchars($subcategoryName); ?>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered inventory-table">
                                        <thead class="table-light">
                                            <tr>
                                                <th rowspan="2" style="vertical-align: middle; width: 25%;">Élément</th>
                                                <th colspan="4" class="text-center bg-primary text-white">Entrée</th>
                                                <?php if (!$isEntreeInventory): ?>
                                                <th colspan="4" class="text-center bg-success text-white">Sortie</th>
                                                <?php endif; ?>
                                                <th rowspan="2" style="vertical-align: middle; width: 15%;">Commentaires</th>
                                            </tr>
                                            <tr>
                                                <th class="text-center" style="width: 5%;">Nombre</th>
                                                <th class="text-center" style="width: 5%;">Bon</th>
                                                <th class="text-center" style="width: 5%;">D'usage</th>
                                                <th class="text-center" style="width: 5%;">Mauvais</th>
                                                <?php if (!$isEntreeInventory): ?>
                                                <th class="text-center" style="width: 5%;">Nombre</th>
                                                <th class="text-center" style="width: 5%;">Bon</th>
                                                <th class="text-center" style="width: 5%;">D'usage</th>
                                                <th class="text-center" style="width: 5%;">Mauvais</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($subcategoryItems as $item): 
                                                $itemIndex++;
                                                $existingData = $existing_data_by_id[$itemIndex] ?? null;
                                                $entree = $existingData['entree'] ?? [];
                                                $sortie = $existingData['sortie'] ?? [];
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($item['nom']); ?></strong>
                                                    <input type="hidden" name="items[<?php echo $itemIndex; ?>][id]" value="<?php echo $itemIndex; ?>">
                                                    <input type="hidden" name="items[<?php echo $itemIndex; ?>][categorie]" value="<?php echo htmlspecialchars($categoryName); ?>">
                                                    <input type="hidden" name="items[<?php echo $itemIndex; ?>][sous_categorie]" value="<?php echo htmlspecialchars($subcategoryName); ?>">
                                                    <input type="hidden" name="items[<?php echo $itemIndex; ?>][nom]" value="<?php echo htmlspecialchars($item['nom']); ?>">
                                                    <input type="hidden" name="items[<?php echo $itemIndex; ?>][type]" value="<?php echo htmlspecialchars($item['type']); ?>">
                                                </td>
                                                
                                                <!-- Entry columns -->
                                                <td class="text-center <?php echo !$isEntreeInventory ? 'readonly-column' : ''; ?>">
                                                    <input type="number" 
                                                           name="items[<?php echo $itemIndex; ?>][entree_nombre]" 
                                                           class="form-control form-control-sm text-center" 
                                                           value="<?php echo htmlspecialchars($entree['nombre'] ?? ''); ?>" 
                                                           min="0" 
                                                           <?php echo !$isEntreeInventory ? 'readonly' : ''; ?>>
                                                </td>
                                                <td class="text-center <?php echo !$isEntreeInventory ? 'readonly-column' : ''; ?>">
                                                    <input type="checkbox" 
                                                           name="items[<?php echo $itemIndex; ?>][entree_bon]" 
                                                           class="form-check-input"
                                                           <?php echo (!empty($entree['bon'])) ? 'checked' : ''; ?>
                                                           <?php echo !$isEntreeInventory ? 'disabled' : ''; ?>>
                                                </td>
                                                <td class="text-center <?php echo !$isEntreeInventory ? 'readonly-column' : ''; ?>">
                                                    <input type="checkbox" 
                                                           name="items[<?php echo $itemIndex; ?>][entree_usage]" 
                                                           class="form-check-input"
                                                           <?php echo (!empty($entree['usage'])) ? 'checked' : ''; ?>
                                                           <?php echo !$isEntreeInventory ? 'disabled' : ''; ?>>
                                                </td>
                                                <td class="text-center <?php echo !$isEntreeInventory ? 'readonly-column' : ''; ?>">
                                                    <input type="checkbox" 
                                                           name="items[<?php echo $itemIndex; ?>][entree_mauvais]" 
                                                           class="form-check-input"
                                                           <?php echo (!empty($entree['mauvais'])) ? 'checked' : ''; ?>
                                                           <?php echo !$isEntreeInventory ? 'disabled' : ''; ?>>
                                                </td>
                                                
                                                <!-- Exit columns -->
                                                <?php if (!$isEntreeInventory): ?>
                                                <td class="text-center">
                                                    <input type="number" 
                                                           name="items[<?php echo $itemIndex; ?>][sortie_nombre]" 
                                                           class="form-control form-control-sm text-center" 
                                                           value="<?php echo htmlspecialchars($sortie['nombre'] ?? ''); ?>" 
                                                           min="0">
                                                </td>
                                                <td class="text-center">
                                                    <input type="checkbox" 
                                                           name="items[<?php echo $itemIndex; ?>][sortie_bon]" 
                                                           class="form-check-input"
                                                           <?php echo (!empty($sortie['bon'])) ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input type="checkbox" 
                                                           name="items[<?php echo $itemIndex; ?>][sortie_usage]" 
                                                           class="form-check-input"
                                                           <?php echo (!empty($sortie['usage'])) ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input type="checkbox" 
                                                           name="items[<?php echo $itemIndex; ?>][sortie_mauvais]" 
                                                           class="form-check-input"
                                                           <?php echo (!empty($sortie['mauvais'])) ? 'checked' : ''; ?>>
                                                </td>
                                                <?php endif; ?>
                                                
                                                <!-- Comments -->
                                                <td>
                                                    <input type="text" 
                                                           name="items[<?php echo $itemIndex; ?>][commentaires]" 
                                                           class="form-control form-control-sm" 
                                                           value="<?php echo htmlspecialchars($existingData['commentaires'] ?? ''); ?>" 
                                                           placeholder="Commentaires...">
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <!-- Simple category (no subcategories) -->
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered inventory-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th rowspan="2" style="vertical-align: middle; width: 25%;">Élément</th>
                                            <th colspan="4" class="text-center bg-primary text-white">Entrée</th>
                                            <?php if (!$isEntreeInventory): ?>
                                            <th colspan="4" class="text-center bg-success text-white">Sortie</th>
                                            <?php endif; ?>
                                            <th rowspan="2" style="vertical-align: middle; width: 15%;">Commentaires</th>
                                        </tr>
                                        <tr>
                                            <th class="text-center" style="width: 5%;">Nombre</th>
                                            <th class="text-center" style="width: 5%;">Bon</th>
                                            <th class="text-center" style="width: 5%;">D'usage</th>
                                            <th class="text-center" style="width: 5%;">Mauvais</th>
                                            <?php if (!$isEntreeInventory): ?>
                                            <th class="text-center" style="width: 5%;">Nombre</th>
                                            <th class="text-center" style="width: 5%;">Bon</th>
                                            <th class="text-center" style="width: 5%;">D'usage</th>
                                            <th class="text-center" style="width: 5%;">Mauvais</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($categoryContent as $item): 
                                            $itemIndex++;
                                            $existingData = $existing_data_by_id[$itemIndex] ?? null;
                                            $entree = $existingData['entree'] ?? [];
                                            $sortie = $existingData['sortie'] ?? [];
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($item['nom']); ?></strong>
                                                <input type="hidden" name="items[<?php echo $itemIndex; ?>][id]" value="<?php echo $itemIndex; ?>">
                                                <input type="hidden" name="items[<?php echo $itemIndex; ?>][categorie]" value="<?php echo htmlspecialchars($categoryName); ?>">
                                                <input type="hidden" name="items[<?php echo $itemIndex; ?>][sous_categorie]" value="">
                                                <input type="hidden" name="items[<?php echo $itemIndex; ?>][nom]" value="<?php echo htmlspecialchars($item['nom']); ?>">
                                                <input type="hidden" name="items[<?php echo $itemIndex; ?>][type]" value="<?php echo htmlspecialchars($item['type']); ?>">
                                            </td>
                                            
                                            <!-- Entry columns -->
                                            <td class="text-center <?php echo !$isEntreeInventory ? 'readonly-column' : ''; ?>">
                                                <input type="number" 
                                                       name="items[<?php echo $itemIndex; ?>][entree_nombre]" 
                                                       class="form-control form-control-sm text-center" 
                                                       value="<?php echo htmlspecialchars($entree['nombre'] ?? ''); ?>" 
                                                       min="0" 
                                                       <?php echo !$isEntreeInventory ? 'readonly' : ''; ?>>
                                            </td>
                                            <td class="text-center <?php echo !$isEntreeInventory ? 'readonly-column' : ''; ?>">
                                                <input type="checkbox" 
                                                       name="items[<?php echo $itemIndex; ?>][entree_bon]" 
                                                       class="form-check-input"
                                                       <?php echo (!empty($entree['bon'])) ? 'checked' : ''; ?>
                                                       <?php echo !$isEntreeInventory ? 'disabled' : ''; ?>>
                                            </td>
                                            <td class="text-center <?php echo !$isEntreeInventory ? 'readonly-column' : ''; ?>">
                                                <input type="checkbox" 
                                                       name="items[<?php echo $itemIndex; ?>][entree_usage]" 
                                                       class="form-check-input"
                                                       <?php echo (!empty($entree['usage'])) ? 'checked' : ''; ?>
                                                       <?php echo !$isEntreeInventory ? 'disabled' : ''; ?>>
                                            </td>
                                            <td class="text-center <?php echo !$isEntreeInventory ? 'readonly-column' : ''; ?>">
                                                <input type="checkbox" 
                                                       name="items[<?php echo $itemIndex; ?>][entree_mauvais]" 
                                                       class="form-check-input"
                                                       <?php echo (!empty($entree['mauvais'])) ? 'checked' : ''; ?>
                                                       <?php echo !$isEntreeInventory ? 'disabled' : ''; ?>>
                                            </td>
                                            
                                            <!-- Exit columns -->
                                            <?php if (!$isEntreeInventory): ?>
                                            <td class="text-center">
                                                <input type="number" 
                                                       name="items[<?php echo $itemIndex; ?>][sortie_nombre]" 
                                                       class="form-control form-control-sm text-center" 
                                                       value="<?php echo htmlspecialchars($sortie['nombre'] ?? ''); ?>" 
                                                       min="0">
                                            </td>
                                            <td class="text-center">
                                                <input type="checkbox" 
                                                       name="items[<?php echo $itemIndex; ?>][sortie_bon]" 
                                                       class="form-check-input"
                                                       <?php echo (!empty($sortie['bon'])) ? 'checked' : ''; ?>>
                                            </td>
                                            <td class="text-center">
                                                <input type="checkbox" 
                                                       name="items[<?php echo $itemIndex; ?>][sortie_usage]" 
                                                       class="form-check-input"
                                                       <?php echo (!empty($sortie['usage'])) ? 'checked' : ''; ?>>
                                            </td>
                                            <td class="text-center">
                                                <input type="checkbox" 
                                                       name="items[<?php echo $itemIndex; ?>][sortie_mauvais]" 
                                                       class="form-check-input"
                                                       <?php echo (!empty($sortie['mauvais'])) ? 'checked' : ''; ?>>
                                            </td>
                                            <?php endif; ?>
                                            
                                            <!-- Comments -->
                                            <td>
                                                <input type="text" 
                                                       name="items[<?php echo $itemIndex; ?>][commentaires]" 
                                                       class="form-control form-control-sm" 
                                                       value="<?php echo htmlspecialchars($existingData['commentaires'] ?? ''); ?>" 
                                                       placeholder="Commentaires...">
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="form-card">
                <h5>Observations générales</h5>
                <textarea name="observations_generales" class="form-control" rows="4" 
                          placeholder="Observations générales sur l'inventaire..."><?php echo htmlspecialchars($inventaire['observations_generales'] ?? ''); ?></textarea>
            </div>

            <!-- Signatures Section -->
            <?php if (!empty($existing_tenants)): ?>
            <div class="form-card">
                <div class="section-title">
                    <i class="bi bi-pen"></i> Signatures des locataires
                </div>
                
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> 
                    <strong>Signatures</strong> : 
                    Les locataires peuvent signer ci-dessous pour confirmer l'inventaire.
                </div>
                
                <!-- Lieu de signature (common for all) -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label for="lieu_signature" class="form-label">Lieu de signature</label>
                        <input type="text" name="lieu_signature" id="lieu_signature" class="form-control" 
                               value="<?php echo htmlspecialchars($inventaire['lieu_signature'] ?? ''); ?>" 
                               placeholder="Ex: Paris">
                    </div>
                </div>
                
                <!-- Tenant Signatures -->
                <?php foreach ($existing_tenants as $index => $tenant): ?>
                <div class="mb-4 pb-4 border-bottom">
                    <h6 class="mb-3">
                        Signature locataire <?php echo $index + 1; ?> - <?php echo htmlspecialchars($tenant['prenom'] . ' ' . $tenant['nom']); ?>
                    </h6>
                    <div class="row">
                        <div class="col-md-12">
                            <?php if (!empty($tenant['signature_data'])): ?>
                                <div class="alert alert-success mb-2">
                                    <i class="bi bi-check-circle"></i> 
                                    Signé le <?php echo !empty($tenant['signature_timestamp']) ? date('d/m/Y à H:i', strtotime($tenant['signature_timestamp'])) : 'Date inconnue'; ?>
                                </div>
                                <div class="mb-2">
                                    <?php
                                    // Handle signature path - prepend ../ for relative paths since we're in admin-v2 directory
                                    $signatureSrc = $tenant['signature_data'];
                                    
                                    // Validate data URL format with length check (max 2MB)
                                    if (preg_match('/^data:image\/(jpeg|jpg|png);base64,(?:[A-Za-z0-9+\/=]+)$/', $signatureSrc)) {
                                        // Data URL - validate size
                                        if (strlen($signatureSrc) <= 2 * 1024 * 1024) {
                                            $displaySrc = $signatureSrc;
                                        } else {
                                            error_log("Oversized signature data URL for tenant ID: " . (int)$tenant['id']);
                                            $displaySrc = '';
                                        }
                                    } elseif (preg_match('/^uploads\/signatures\/[a-zA-Z0-9_\-]+\.(jpg|jpeg|png)$/', $signatureSrc)) {
                                        // Relative path - validate it's within expected directory and prepend ../
                                        $displaySrc = '../' . $signatureSrc;
                                    } else {
                                        // Invalid or unexpected format - don't display to prevent security issues
                                        error_log("Invalid signature path format detected for tenant ID: " . (int)$tenant['id']);
                                        $displaySrc = '';
                                    }
                                    ?>
                                    <?php if (!empty($displaySrc)): ?>
                                    <img src="<?php echo htmlspecialchars($displaySrc); ?>" 
                                         alt="Signature" style="max-width: 200px; max-height: 80px; border: 1px solid #dee2e6; padding: 5px;">
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <label class="form-label">Veuillez signer dans le cadre ci-dessous :</label>
                            <div class="signature-container" style="max-width: 300px;">
                                <canvas id="tenantCanvas_<?php echo $tenant['id']; ?>" width="300" height="150" style="background: transparent; border: none; outline: none; padding: 0;"></canvas>
                            </div>
                            <input type="hidden" name="tenants[<?php echo $tenant['id']; ?>][signature]" 
                                   id="tenantSignature_<?php echo $tenant['id']; ?>" 
                                   value="<?php echo htmlspecialchars($tenant['signature_data'] ?? ''); ?>">
                            <input type="hidden" name="tenants[<?php echo $tenant['id']; ?>][locataire_id]" 
                                   value="<?php echo $tenant['locataire_id'] ?? ''; ?>">
                            <input type="hidden" name="tenants[<?php echo $tenant['id']; ?>][nom]" 
                                   value="<?php echo htmlspecialchars($tenant['nom']); ?>">
                            <input type="hidden" name="tenants[<?php echo $tenant['id']; ?>][prenom]" 
                                   value="<?php echo htmlspecialchars($tenant['prenom']); ?>">
                            <input type="hidden" name="tenants[<?php echo $tenant['id']; ?>][email]" 
                                   value="<?php echo htmlspecialchars($tenant['email'] ?? ''); ?>">
                            <div class="mt-2">
                                <button type="button" class="btn btn-warning btn-sm" onclick="clearTenantSignature(<?php echo $tenant['id']; ?>)">
                                    <i class="bi bi-eraser"></i> Effacer
                                </button>
                            </div>
                            <div class="mt-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" 
                                           name="tenants[<?php echo $tenant['id']; ?>][certifie_exact]" 
                                           id="certifie_exact_<?php echo $tenant['id']; ?>" 
                                           value="1"
                                           <?php echo !empty($tenant['certifie_exact']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="certifie_exact_<?php echo $tenant['id']; ?>">
                                        <strong>Certifié exact</strong>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between">
                <a href="inventaires.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Annuler
                </a>
                <div>
                    <button type="submit" class="btn btn-secondary" aria-label="Enregistrer l'inventaire comme brouillon sans envoyer d'email">
                        <i class="bi bi-save"></i> Enregistrer le brouillon
                    </button>
                    <button type="submit" name="finalize" value="1" class="btn btn-primary" aria-label="Finaliser et envoyer l'inventaire par email au locataire">
                        <i class="bi bi-check-circle"></i> Finaliser et envoyer
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Configuration
        const SIGNATURE_JPEG_QUALITY = 0.95;
        
        // Initialize tenant signature canvases on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tenant signatures based on actual IDs in the page
            <?php if (!empty($existing_tenants)): ?>
                <?php foreach ($existing_tenants as $tenant): ?>
                    initTenantSignature(<?php echo $tenant['id']; ?>);
                <?php endforeach; ?>
            <?php endif; ?>
        });
        
        // Function to duplicate Entry data to Exit
        function duplicateEntryToExit() {
            if (!confirm('Voulez-vous copier toutes les données d\'entrée vers la sortie ? Cette action remplacera les données de sortie existantes.')) {
                return;
            }
            
            const rows = document.querySelectorAll('tbody tr');
            let copiedCount = 0;
            
            rows.forEach(row => {
                // Get Entry inputs
                const entreeNombre = row.querySelector('input[name*="[entree_nombre]"]');
                const entreeBon = row.querySelector('input[name*="[entree_bon]"]');
                const entreeUsage = row.querySelector('input[name*="[entree_usage]"]');
                const entreeMauvais = row.querySelector('input[name*="[entree_mauvais]"]');
                
                // Get Exit inputs
                const sortieNombre = row.querySelector('input[name*="[sortie_nombre]"]');
                const sortieBon = row.querySelector('input[name*="[sortie_bon]"]');
                const sortieUsage = row.querySelector('input[name*="[sortie_usage]"]');
                const sortieMauvais = row.querySelector('input[name*="[sortie_mauvais]"]');
                
                // Copy values if Entry inputs exist and are not empty
                if (entreeNombre && sortieNombre && !sortieNombre.hasAttribute('readonly')) {
                    if (entreeNombre.value) {
                        sortieNombre.value = entreeNombre.value;
                        copiedCount++;
                    }
                }
                
                if (entreeBon && sortieBon && !sortieBon.hasAttribute('disabled')) {
                    sortieBon.checked = entreeBon.checked;
                }
                
                if (entreeUsage && sortieUsage && !sortieUsage.hasAttribute('disabled')) {
                    sortieUsage.checked = entreeUsage.checked;
                }
                
                if (entreeMauvais && sortieMauvais && !sortieMauvais.hasAttribute('disabled')) {
                    sortieMauvais.checked = entreeMauvais.checked;
                }
            });
            
            // Show success message using Bootstrap alert
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-success alert-dismissible fade show';
            alertDiv.innerHTML = `
                <i class="bi bi-check-circle"></i> Données copiées avec succès ! ${copiedCount} éléments ont été dupliqués.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.querySelector('.main-content').insertBefore(alertDiv, document.querySelector('.header').nextSibling);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                alertDiv.classList.remove('show');
                setTimeout(() => alertDiv.remove(), 150);
            }, 5000);
        }
        
        function initTenantSignature(id) {
            const canvas = document.getElementById(`tenantCanvas_${id}`);
            if (!canvas) return;
            
            // Prevent duplicate initialization
            if (canvas.dataset.initialized === 'true') return;
            canvas.dataset.initialized = 'true';
            
            const ctx = canvas.getContext('2d');
            
            // Set drawing style for black signature lines
            ctx.strokeStyle = '#000000';
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            
            let isDrawing = false;
            let lastX = 0;
            let lastY = 0;
            
            // Helper function to get mouse/touch position
            function getPos(e) {
                const rect = canvas.getBoundingClientRect();
                const clientX = e.clientX || (e.touches && e.touches[0] ? e.touches[0].clientX : 0);
                const clientY = e.clientY || (e.touches && e.touches[0] ? e.touches[0].clientY : 0);
                return {
                    x: clientX - rect.left,
                    y: clientY - rect.top
                };
            }
            
            // Mouse events
            canvas.addEventListener('mousedown', (e) => {
                isDrawing = true;
                const pos = getPos(e);
                lastX = pos.x;
                lastY = pos.y;
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
            });
            
            canvas.addEventListener('mousemove', (e) => {
                if (!isDrawing) return;
                e.preventDefault();
                
                const pos = getPos(e);
                
                ctx.lineTo(pos.x, pos.y);
                ctx.stroke();
                ctx.beginPath();
                ctx.moveTo(pos.x, pos.y);
                
                lastX = pos.x;
                lastY = pos.y;
            });
            
            canvas.addEventListener('mouseup', () => {
                if (isDrawing) {
                    isDrawing = false;
                    saveTenantSignature(id);
                }
            });
            
            canvas.addEventListener('mouseleave', () => {
                if (isDrawing) {
                    isDrawing = false;
                    saveTenantSignature(id);
                }
            });
            
            // Touch support
            canvas.addEventListener('touchstart', (e) => {
                e.preventDefault();
                isDrawing = true;
                const pos = getPos(e);
                lastX = pos.x;
                lastY = pos.y;
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
            });
            
            canvas.addEventListener('touchmove', (e) => {
                if (!isDrawing) return;
                e.preventDefault();
                
                const pos = getPos(e);
                
                ctx.lineTo(pos.x, pos.y);
                ctx.stroke();
                ctx.beginPath();
                ctx.moveTo(pos.x, pos.y);
                
                lastX = pos.x;
                lastY = pos.y;
            });
            
            canvas.addEventListener('touchend', (e) => {
                e.preventDefault();
                if (isDrawing) {
                    isDrawing = false;
                    saveTenantSignature(id);
                }
            });
        }
        
        function saveTenantSignature(id) {
            const canvas = document.getElementById(`tenantCanvas_${id}`);
            
            // Create a temporary canvas to add white background before JPEG conversion
            // JPEG doesn't support transparency, so we need to fill with white
            const tempCanvas = document.createElement('canvas');
            tempCanvas.width = canvas.width;
            tempCanvas.height = canvas.height;
            const tempCtx = tempCanvas.getContext('2d');
            
            // Fill with white background (JPEG doesn't support transparency)
            tempCtx.fillStyle = '#FFFFFF';
            tempCtx.fillRect(0, 0, tempCanvas.width, tempCanvas.height);
            
            // Draw the signature on top of the white background
            tempCtx.drawImage(canvas, 0, 0);
            
            // Convert to JPEG with white background
            const signatureData = tempCanvas.toDataURL('image/jpeg', SIGNATURE_JPEG_QUALITY);
            document.getElementById(`tenantSignature_${id}`).value = signatureData;
        }
        
        function clearTenantSignature(id) {
            const canvas = document.getElementById(`tenantCanvas_${id}`);
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            document.getElementById(`tenantSignature_${id}`).value = '';
        }
        
        // Handle form submission with validation
        document.getElementById('inventaireForm').addEventListener('submit', function(e) {
            // Save all tenant signatures before submission
            <?php foreach ($existing_tenants as $tenant): ?>
                saveTenantSignature(<?php echo $tenant['id']; ?>);
            <?php endforeach; ?>
            
            // Check if this is a draft save (no finalize parameter)
            const isDraftSave = !e.submitter || e.submitter.name !== 'finalize';
            
            // Skip validation for draft saves
            if (isDraftSave) {
                return true;
            }
            
            // Validate that all tenants have signed and checked "Certifié exact" (only for finalization)
            let allValid = true;
            let errors = [];
            
            // Validate equipment: if state checkbox is checked, number must be provided
            const equipmentRows = document.querySelectorAll('tbody tr');
            equipmentRows.forEach((row, index) => {
                // Get all inputs in this row
                const entreeNombre = row.querySelector('input[name*="[entree_nombre]"]');
                const entreeBon = row.querySelector('input[name*="[entree_bon]"]');
                const entreeUsage = row.querySelector('input[name*="[entree_usage]"]');
                const entreeMauvais = row.querySelector('input[name*="[entree_mauvais]"]');
                
                const sortieNombre = row.querySelector('input[name*="[sortie_nombre]"]');
                const sortieBon = row.querySelector('input[name*="[sortie_bon]"]');
                const sortieUsage = row.querySelector('input[name*="[sortie_usage]"]');
                const sortieMauvais = row.querySelector('input[name*="[sortie_mauvais]"]');
                
                const itemName = row.querySelector('strong')?.textContent || 'Élément ' + (index + 1);
                
                // Check Entry: if any checkbox is checked, number is required
                if (!entreeNombre?.hasAttribute('readonly')) {
                    const entreeChecked = (entreeBon?.checked || entreeUsage?.checked || entreeMauvais?.checked);
                    const entreeNombreValue = entreeNombre?.value ? parseInt(entreeNombre.value) : 0;
                    
                    if (entreeChecked && entreeNombreValue <= 0) {
                        errors.push('Entrée - ' + itemName + ': Un nombre doit être renseigné si un état est coché');
                        allValid = false;
                    }
                }
                
                // Check Exit: if any checkbox is checked, number is required
                if (!sortieNombre?.hasAttribute('readonly')) {
                    const sortieChecked = (sortieBon?.checked || sortieUsage?.checked || sortieMauvais?.checked);
                    const sortieNombreValue = sortieNombre?.value ? parseInt(sortieNombre.value) : 0;
                    
                    if (sortieChecked && sortieNombreValue <= 0) {
                        errors.push('Sortie - ' + itemName + ': Un nombre doit être renseigné si un état est coché');
                        allValid = false;
                    }
                }
            });
            
            // Validate tenant signatures - using array to avoid duplicate identifier errors
            const tenantValidations = [
                <?php foreach ($existing_tenants as $index => $tenant): ?>
                {
                    id: <?php echo $tenant['id']; ?>,
                    name: <?php echo json_encode($tenant['prenom'] . ' ' . $tenant['nom']); ?>,
                    signatureId: 'tenantSignature_<?php echo $tenant['id']; ?>',
                    certifieId: 'certifie_exact_<?php echo $tenant['id']; ?>'
                }<?php echo ($index < count($existing_tenants) - 1) ? ',' : ''; ?>
                <?php endforeach; ?>
            ];
            
            tenantValidations.forEach(function(tenant) {
                const signatureValue = document.getElementById(tenant.signatureId).value;
                const certifieChecked = document.getElementById(tenant.certifieId).checked;
                
                if (!signatureValue || signatureValue.trim() === '') {
                    errors.push('La signature de ' + tenant.name + ' est obligatoire');
                    allValid = false;
                }
                
                if (!certifieChecked) {
                    errors.push('La case "Certifié exact" doit être cochée pour ' + tenant.name);
                    allValid = false;
                }
            });
            
            if (!allValid) {
                e.preventDefault();
                
                // Show errors using Bootstrap alert
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                alertDiv.innerHTML = `
                    <strong><i class="bi bi-exclamation-triangle"></i> Erreurs de validation :</strong>
                    <ul class="mb-0 mt-2">
                        ${errors.map(err => '<li>' + err + '</li>').join('')}
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.querySelector('.main-content').insertBefore(alertDiv, document.querySelector('.header').nextSibling);
                
                // Scroll to top to show errors
                window.scrollTo({ top: 0, behavior: 'smooth' });
                
                return false;
            }
        });
    </script>
</body>
</html>
