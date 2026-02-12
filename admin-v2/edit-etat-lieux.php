<?php
/**
 * Edit État des Lieux - Comprehensive Form
 * My Invest Immobilier
 */

require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Get état des lieux ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id < 1) {
    $_SESSION['error'] = "ID de l'état des lieux invalide";
    header('Location: etats-lieux.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    try {
        $pdo->beginTransaction();
        
        // Update état des lieux (no more manual signature fields for bailleur/locataire)
        // Note: bilan_logement_data and bilan_logement_commentaire are now managed in edit-bilan-logement.php
        $stmt = $pdo->prepare("
            UPDATE etats_lieux SET
                date_etat = ?,
                locataire_nom_complet = ?,
                locataire_email = ?,
                compteur_electricite = ?,
                compteur_eau_froide = ?,
                cles_appartement = ?,
                cles_boite_lettres = ?,
                cles_autre = ?,
                cles_total = ?,
                cles_conformite = ?,
                cles_observations = ?,
                piece_principale = ?,
                coin_cuisine = ?,
                salle_eau_wc = ?,
                etat_general = ?,
                observations = ?,
                etat_general_conforme = ?,
                degradations_constatees = ?,
                degradations_details = ?,
                lieu_signature = ?,
                statut = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $_POST['date_etat'],
            $_POST['locataire_nom_complet'],
            $_POST['locataire_email'],
            $_POST['compteur_electricite'] ?? '',
            $_POST['compteur_eau_froide'] ?? '',
            (int)($_POST['cles_appartement'] ?? 0),
            (int)($_POST['cles_boite_lettres'] ?? 0),
            (int)($_POST['cles_autre'] ?? 0),
            (int)($_POST['cles_total'] ?? 0),
            $_POST['cles_conformite'] ?? 'non_applicable',
            $_POST['cles_observations'] ?? '',
            $_POST['piece_principale'] ?? '',
            $_POST['coin_cuisine'] ?? '',
            $_POST['salle_eau_wc'] ?? '',
            $_POST['etat_general'] ?? '',
            $_POST['observations'] ?? '',
            $_POST['etat_general_conforme'] ?? 'non_applicable',
            isset($_POST['degradations_constatees']) ? 1 : 0,
            $_POST['degradations_details'] ?? '',
            $_POST['lieu_signature'] ?? '',
            $_POST['statut'] ?? 'brouillon',
            $id
        ]);
        
        // Update tenant signatures and certifie_exact - save as physical files like contract signatures
        if (isset($_POST['tenants']) && is_array($_POST['tenants'])) {
            foreach ($_POST['tenants'] as $tenantId => $tenantInfo) {
                if (!empty($tenantInfo['signature'])) {
                    // Validate signature format
                    if (!preg_match('/^data:image\/(jpeg|jpg|png);base64,/', $tenantInfo['signature'])) {
                        error_log("Invalid signature format for tenant ID $tenantId - skipping");
                        continue;
                    }
                    
                    // Use the new function to save signature as physical file
                    if (!updateEtatLieuxTenantSignature($tenantId, $tenantInfo['signature'], $id)) {
                        error_log("Failed to save signature for etat_lieux_locataire ID: $tenantId");
                    }
                }
                
                // Update certifie_exact checkbox
                $certifieExact = isset($tenantInfo['certifie_exact']) ? 1 : 0;
                $stmt = $pdo->prepare("UPDATE etat_lieux_locataires SET certifie_exact = ? WHERE id = ?");
                $stmt->execute([$certifieExact, $tenantId]);
            }
        }
        
        $pdo->commit();
        $_SESSION['success'] = "État des lieux enregistré avec succès";
        
        // If finalizing, redirect to view page
        if (isset($_POST['finalize']) && $_POST['finalize'] === '1') {
            header("Location: finalize-etat-lieux.php?id=$id");
            exit;
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error updating état des lieux: " . $e->getMessage());
        $_SESSION['error'] = "Erreur lors de l'enregistrement: " . $e->getMessage();
    }
}

// Get état des lieux details
$stmt = $pdo->prepare("
    SELECT edl.*, 
           c.reference_unique as contrat_ref,
           l.adresse as logement_adresse
    FROM etats_lieux edl
    LEFT JOIN contrats c ON edl.contrat_id = c.id
    LEFT JOIN logements l ON c.logement_id = l.id
    WHERE edl.id = ?
");
$stmt->execute([$id]);
$etat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$etat) {
    $_SESSION['error'] = "État des lieux non trouvé";
    header('Location: etats-lieux.php');
    exit;
}

// Fix missing address from logement if available
$needsUpdate = false;
$fieldsToUpdate = [];

if (empty($etat['adresse']) && !empty($etat['logement_adresse'])) {
    $etat['adresse'] = $etat['logement_adresse'];
    $fieldsToUpdate['adresse'] = $etat['adresse'];
    $needsUpdate = true;
}

// Update database with all missing fields in a single query
if ($needsUpdate) {
    // Whitelist of allowed fields to prevent SQL injection
    $allowedFields = ['adresse'];
    
    $setParts = [];
    $params = [];
    foreach ($fieldsToUpdate as $field => $value) {
        // Only allow whitelisted fields
        if (in_array($field, $allowedFields, true)) {
            $setParts[] = "`$field` = ?";
            $params[] = $value;
        }
    }
    
    if (!empty($setParts)) {
        $params[] = $id;
        $sql = "UPDATE etats_lieux SET " . implode(', ', $setParts) . " WHERE id = ?";
        $updateStmt = $pdo->prepare($sql);
        $updateStmt->execute($params);
    }
}

// Generate reference_unique if missing
if (empty($etat['reference_unique'])) {
    $type = $etat['type'] ?? 'entree';  // Default to 'entree' if type is missing
    $typePrefix = strtoupper($type[0]);  // Safe to access since $type is guaranteed to have a value
    try {
        $randomPart = random_int(1, 9999);
    } catch (Exception $e) {
        // Fallback to time-based random if random_int fails
        $randomPart = (int)(microtime(true) * 1000) % 10000;
    }
    $reference = 'EDL-' . $typePrefix . '-' . date('Ymd') . '-' . str_pad($randomPart, 4, '0', STR_PAD_LEFT);
    $stmt = $pdo->prepare("UPDATE etats_lieux SET reference_unique = ? WHERE id = ?");
    $stmt->execute([$reference, $id]);
    $etat['reference_unique'] = $reference;
}

// Get existing tenants for this état des lieux
$stmt = $pdo->prepare("SELECT * FROM etat_lieux_locataires WHERE etat_lieux_id = ? ORDER BY ordre ASC");
$stmt->execute([$id]);
$existing_tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If no tenants linked yet, auto-populate from contract
if (empty($existing_tenants) && !empty($etat['contrat_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM locataires WHERE contrat_id = ? ORDER BY ordre ASC");
    $stmt->execute([$etat['contrat_id']]);
    $contract_tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Prepare statement once, outside the loop
    $insertStmt = $pdo->prepare("
        INSERT INTO etat_lieux_locataires (etat_lieux_id, locataire_id, ordre, nom, prenom, email)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    // Insert tenants into etat_lieux_locataires
    foreach ($contract_tenants as $tenant) {
        $insertStmt->execute([
            $id,
            $tenant['id'],
            $tenant['ordre'],
            $tenant['nom'],
            $tenant['prenom'],
            $tenant['email']
        ]);
    }
    
    // Reload tenants
    $stmt = $pdo->prepare("SELECT * FROM etat_lieux_locataires WHERE etat_lieux_id = ? ORDER BY ordre ASC");
    $stmt->execute([$id]);
    $existing_tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Load existing photos for this état des lieux
$stmt = $pdo->prepare("SELECT * FROM etat_lieux_photos WHERE etat_lieux_id = ? ORDER BY categorie, ordre ASC");
$stmt->execute([$id]);
$existing_photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group photos by category for easy access
$photos_by_category = [];
foreach ($existing_photos as $photo) {
    if (!isset($photos_by_category[$photo['categorie']])) {
        $photos_by_category[$photo['categorie']] = [];
    }
    $photos_by_category[$photo['categorie']][] = $photo;
}


$isEntree = $etat['type'] === 'entree';
$isSortie = $etat['type'] === 'sortie';

// For exit state: fetch entry state data for visual reference display
$etat_entree = null;
$etat_entree_photos = [];
if ($isSortie && !empty($etat['contrat_id'])) {
    // Fetch the entry state for this contract
    $stmt = $pdo->prepare("
        SELECT * FROM etats_lieux 
        WHERE contrat_id = ? AND type = 'entree' 
        ORDER BY date_etat DESC 
        LIMIT 1
    ");
    $stmt->execute([$etat['contrat_id']]);
    $etat_entree = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Fetch entry state photos for visual reference
    if ($etat_entree) {
        $stmt = $pdo->prepare("SELECT * FROM etat_lieux_photos WHERE etat_lieux_id = ? ORDER BY categorie, ordre ASC");
        $stmt->execute([$etat_entree['id']]);
        $etat_entree_photos_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group entry photos by category
        foreach ($etat_entree_photos_list as $photo) {
            if (!isset($etat_entree_photos[$photo['categorie']])) {
                $etat_entree_photos[$photo['categorie']] = [];
            }
            $etat_entree_photos[$photo['categorie']][] = $photo;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Éditer État des lieux - <?php echo htmlspecialchars($etat['reference_unique'] ?? 'N/A'); ?></title>
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
        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #212529;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e9ecef;
        }
        .section-subtitle {
            font-size: 1rem;
            font-weight: 600;
            color: #495057;
            margin-top: 1.5rem;
            margin-bottom: 1rem;
        }
        .required-field::after {
            content: " *";
            color: #dc3545;
        }
        /* Visual reference styles for exit state */
        .entry-reference {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        .entry-reference .icon-green {
            color: #28a745;
            font-size: 1.1rem;
            margin-right: 5px;
        }
        .entry-reference-label {
            font-weight: 600;
            color: #155724;
        }
        .entry-reference-value {
            color: #155724;
            margin-left: 5px;
        }
        .exit-input-label {
            color: #dc3545;
            font-weight: 600;
        }
        .exit-input-label .icon-red {
            color: #dc3545;
            margin-right: 5px;
        }
        .entry-photo-thumbnail {
            border: 2px solid #28a745;
            border-radius: 4px;
            margin: 5px;
            display: inline-block;
            position: relative;
        }
        .entry-photo-thumbnail img {
            max-width: 100px;
            max-height: 100px;
            display: block;
        }
        .entry-photo-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #28a745;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        .photo-upload-zone {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s;
        }
        .photo-upload-zone:hover {
            border-color: #0d6efd;
            background: #e7f1ff;
        }
        .sticky-actions {
            position: sticky;
            bottom: 0;
            background: white;
            padding: 15px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            margin: 0 -15px -15px -15px;
            border-radius: 0 0 10px 10px;
        }
        .signature-container {
            border: 2px solid #dee2e6;
            border-radius: 5px;
            background-color: #ffffff;
            display: inline-block;
            cursor: crosshair;
        }
        #signatureCanvasBailleur, #signatureCanvasLocataire {
            display: block;
        }
        
        /* Bilan du logement styles */
        #bilanTable .bilan-field.is-invalid {
            border-color: #dc3545;
            background-color: #f8d7da;
        }
        #bilanTable .bilan-field.is-valid {
            border-color: #28a745;
            background-color: #d4edda;
        }
        #bilanTable thead th {
            background-color: #f8f9fa;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }
        #bilanTable tfoot td {
            font-weight: 600;
            background-color: #f8f9fa;
        }
        .bilan-row:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/menu.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4>
                        <?php if ($isEntree): ?>
                            <i class="bi bi-box-arrow-in-right text-success"></i> État des lieux d'entrée
                        <?php else: ?>
                            <i class="bi bi-box-arrow-right text-danger"></i> État des lieux de sortie
                        <?php endif; ?>
                    </h4>
                    <p class="text-muted mb-0">
                        Référence: <?php echo htmlspecialchars($etat['reference_unique'] ?? 'N/A'); ?>
                    </p>
                </div>
                <div>
                    <a href="etats-lieux.php" class="btn btn-outline-secondary">
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

        <?php if ($isSortie): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                <strong>État de sortie :</strong> Les données affichées en <span class="text-success fw-bold">🟢 VERT</span> proviennent de l'état d'entrée et servent de référence. 
                Veuillez saisir l'état de sortie dans les champs marqués en <span class="text-danger fw-bold">🔴 ROUGE</span>.
            </div>
            <?php if (!$etat_entree): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Attention :</strong> Aucun état d'entrée trouvé pour ce contrat. Les références ne pourront pas être affichées.
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <form method="POST" action="" id="etatLieuxForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save">
            
            <!-- 1. Identification -->
            <div class="form-card">
                <div class="section-title">
                    <i class="bi bi-file-text"></i> 1. Identification
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label required-field">Date de l'état des lieux</label>
                        <input type="date" name="date_etat" class="form-control" 
                               value="<?php echo htmlspecialchars($etat['date_etat']); ?>" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Adresse</label>
                        <input type="text" class="form-control" 
                               value="<?php echo htmlspecialchars($etat['adresse'] ?? $etat['logement_adresse'] ?? '15 rue de la Paix - 74100'); ?>" 
                               readonly>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Bailleur</label>
                        <input type="text" class="form-control" 
                               value="<?php echo htmlspecialchars($etat['bailleur_nom'] ?? 'SCI My Invest Immobilier, représentée par Maxime ALEXANDRE'); ?>" 
                               readonly>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Locataire(s)</label>
                        <?php 
                        // Build tenant names from etat_lieux_locataires
                        $locataire_noms = array_map(function($t) {
                            return $t['prenom'] . ' ' . $t['nom'];
                        }, $existing_tenants);
                        $locataire_nom_complet = implode(' et ', $locataire_noms);
                        ?>
                        <input type="text" name="locataire_nom_complet" class="form-control" 
                               value="<?php echo htmlspecialchars($locataire_nom_complet); ?>" 
                               readonly>
                        <input type="hidden" name="locataire_email" value="<?php echo htmlspecialchars($existing_tenants[0]['email'] ?? ''); ?>">
                        <small class="text-muted">
                            <?php if (count($existing_tenants) > 1): ?>
                                Emails : <?php echo implode(', ', array_map(function($t) { return $t['email']; }, $existing_tenants)); ?>
                            <?php elseif(!empty($existing_tenants[0]['email'])): ?>
                                Email : <?php echo htmlspecialchars($existing_tenants[0]['email']); ?>
                            <?php else: ?>
                                Email : Non renseigné
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            </div>

            <!-- 2. Relevé des compteurs -->
            <div class="form-card">
                <div class="section-title">
                    <i class="bi bi-speedometer2"></i> 2. Relevé des compteurs
                </div>
                
                <div class="section-subtitle">Électricité</div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <?php if ($isSortie && $etat_entree): ?>
                            <!-- Entry state reference for exit state -->
                            <div class="entry-reference mb-2">
                                <span class="icon-green">🟢</span>
                                <span class="entry-reference-label">État d'entrée :</span>
                                <span class="entry-reference-value">
                                    <?php echo htmlspecialchars($etat_entree['compteur_electricite'] ?? 'Non renseigné'); ?> kWh
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <label class="form-label required-field <?php echo $isSortie ? 'exit-input-label' : ''; ?>">
                            <?php if ($isSortie): ?><span class="icon-red">🔴</span><?php endif; ?>
                            Index relevé (kWh)<?php echo $isSortie ? ' - Sortie' : ''; ?>
                        </label>
                        <input type="text" name="compteur_electricite" class="form-control" 
                               value="<?php echo htmlspecialchars($etat['compteur_electricite'] ?? ''); ?>" 
                               placeholder="Ex: 12345" required>
                        <small class="text-muted">Sous-compteur électrique privatif</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Photo du compteur électrique <em>(optionnel)</em></label>
                        
                        <?php if ($isSortie && $etat_entree && isset($etat_entree_photos['compteur_electricite']) && !empty($etat_entree_photos['compteur_electricite'])): ?>
                            <!-- Entry photos as reference -->
                            <div class="mb-2">
                                <small class="text-success fw-bold"><span class="icon-green">🟢</span> Photos de l'état d'entrée (référence) :</small>
                                <div class="d-flex flex-wrap gap-2 mt-1">
                                    <?php foreach ($etat_entree_photos['compteur_electricite'] as $photo): ?>
                                        <div class="entry-photo-thumbnail">
                                            <img src="../<?php echo htmlspecialchars($photo['chemin_fichier']); ?>" 
                                                 alt="Photo compteur électrique (entrée)" 
                                                 title="Photo de l'état d'entrée">
                                            <div class="entry-photo-badge">🟢</div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <hr class="my-2">
                        <?php endif; ?>
                        
                        <?php if (isset($photos_by_category['compteur_electricite']) && !empty($photos_by_category['compteur_electricite'])): ?>
                            <div class="mb-2">
                                <div class="alert alert-success d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-check-circle"></i> <?php echo count($photos_by_category['compteur_electricite']); ?> photo(s) enregistrée(s)</span>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($photos_by_category['compteur_electricite'] as $photo): ?>
                                        <div class="position-relative">
                                            <img src="../<?php echo htmlspecialchars($photo['chemin_fichier']); ?>" 
                                                 alt="Photo compteur électrique" 
                                                 style="max-width: 150px; max-height: 100px; border: 1px solid #dee2e6; border-radius: 4px;">
                                            <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0" 
                                                    style="padding: 2px 6px; font-size: 10px;"
                                                    onclick="deletePhoto(<?php echo $photo['id']; ?>, this)">
                                                <i class="bi bi-x"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="photo-upload-zone" onclick="document.getElementById('photo_compteur_elec').click()">
                            <i class="bi bi-camera" style="font-size: 2rem; color: #6c757d;"></i>
                            <p class="mb-0 mt-2">Cliquer pour ajouter une photo</p>
                            <input type="file" id="photo_compteur_elec" name="photo_compteur_elec" 
                                   accept="image/*" style="display: none;" onchange="previewPhoto(this, 'preview_elec')">
                        </div>
                        <div id="preview_elec" class="mt-2"></div>
                    </div>
                </div>
                
                <div class="section-subtitle">Eau froide</div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <?php if ($isSortie && $etat_entree): ?>
                            <!-- Entry state reference for exit state -->
                            <div class="entry-reference mb-2">
                                <span class="icon-green">🟢</span>
                                <span class="entry-reference-label">État d'entrée :</span>
                                <span class="entry-reference-value">
                                    <?php echo htmlspecialchars($etat_entree['compteur_eau_froide'] ?? 'Non renseigné'); ?> m³
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <label class="form-label required-field <?php echo $isSortie ? 'exit-input-label' : ''; ?>">
                            <?php if ($isSortie): ?><span class="icon-red">🔴</span><?php endif; ?>
                            Index relevé (m³)<?php echo $isSortie ? ' - Sortie' : ''; ?>
                        </label>
                        <input type="text" name="compteur_eau_froide" class="form-control" 
                               value="<?php echo htmlspecialchars($etat['compteur_eau_froide'] ?? ''); ?>" 
                               placeholder="Ex: 123.45" required>
                        <small class="text-muted">Sous-compteur d'eau privatif</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Photo du compteur d'eau <em>(optionnel)</em></label>
                        
                        <?php if ($isSortie && $etat_entree && isset($etat_entree_photos['compteur_eau']) && !empty($etat_entree_photos['compteur_eau'])): ?>
                            <!-- Entry photos as reference -->
                            <div class="mb-2">
                                <small class="text-success fw-bold"><span class="icon-green">🟢</span> Photos de l'état d'entrée (référence) :</small>
                                <div class="d-flex flex-wrap gap-2 mt-1">
                                    <?php foreach ($etat_entree_photos['compteur_eau'] as $photo): ?>
                                        <div class="entry-photo-thumbnail">
                                            <img src="../<?php echo htmlspecialchars($photo['chemin_fichier']); ?>" 
                                                 alt="Photo compteur eau (entrée)" 
                                                 title="Photo de l'état d'entrée">
                                            <div class="entry-photo-badge">🟢</div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <hr class="my-2">
                        <?php endif; ?>
                        
                        <?php if (isset($photos_by_category['compteur_eau']) && !empty($photos_by_category['compteur_eau'])): ?>
                            <div class="mb-2">
                                <div class="alert alert-success d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-check-circle"></i> <?php echo count($photos_by_category['compteur_eau']); ?> photo(s) enregistrée(s)</span>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($photos_by_category['compteur_eau'] as $photo): ?>
                                        <div class="position-relative">
                                            <img src="../<?php echo htmlspecialchars($photo['chemin_fichier']); ?>" 
                                                 alt="Photo compteur eau" 
                                                 style="max-width: 150px; max-height: 100px; border: 1px solid #dee2e6; border-radius: 4px;">
                                            <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0" 
                                                    style="padding: 2px 6px; font-size: 10px;"
                                                    onclick="deletePhoto(<?php echo $photo['id']; ?>, this)">
                                                <i class="bi bi-x"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="photo-upload-zone" onclick="document.getElementById('photo_compteur_eau').click()">
                            <i class="bi bi-camera" style="font-size: 2rem; color: #6c757d;"></i>
                            <p class="mb-0 mt-2">Cliquer pour ajouter une photo</p>
                            <input type="file" id="photo_compteur_eau" name="photo_compteur_eau" 
                                   accept="image/*" style="display: none;" onchange="previewPhoto(this, 'preview_eau')">
                        </div>
                        <div id="preview_eau" class="mt-2"></div>
                    </div>
                </div>
            </div>

            <!-- 3. Remise/Restitution des clés -->
            <div class="form-card">
                <div class="section-title">
                    <i class="bi bi-key"></i> 3. <?php echo $isEntree ? 'Remise' : 'Restitution'; ?> des clés
                </div>
                
                <?php if ($isSortie && $etat_entree): ?>
                    <!-- Entry state reference for keys -->
                    <div class="entry-reference mb-3">
                        <span class="icon-green">🟢</span>
                        <span class="entry-reference-label">État d'entrée :</span>
                        <span class="entry-reference-value">
                            Appartement: <?php echo (int)($etat_entree['cles_appartement'] ?? 0); ?>, 
                            Boîte lettres: <?php echo (int)($etat_entree['cles_boite_lettres'] ?? 0); ?>, 
                            Autre: <?php echo (int)($etat_entree['cles_autre'] ?? 0); ?>, 
                            <strong>Total: <?php echo (int)($etat_entree['cles_total'] ?? 0); ?></strong>
                        </span>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label required-field <?php echo $isSortie ? 'exit-input-label' : ''; ?>">
                            <?php if ($isSortie): ?><span class="icon-red">🔴</span><?php endif; ?>
                            Clés de l'appartement
                        </label>
                        <input type="number" name="cles_appartement" class="form-control" 
                               value="<?php echo htmlspecialchars($etat['cles_appartement'] ?? ''); ?>" 
                               min="0" required oninput="calculateTotalKeys()">
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label required-field <?php echo $isSortie ? 'exit-input-label' : ''; ?>">
                            <?php if ($isSortie): ?><span class="icon-red">🔴</span><?php endif; ?>
                            Clé(s) boîte aux lettres
                        </label>
                        <input type="number" name="cles_boite_lettres" class="form-control" 
                               value="<?php echo htmlspecialchars($etat['cles_boite_lettres'] ?? '1'); ?>" 
                               min="0" required oninput="calculateTotalKeys()">
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label <?php echo $isSortie ? 'exit-input-label' : ''; ?>">
                            <?php if ($isSortie): ?><span class="icon-red">🔴</span><?php endif; ?>
                            Autre
                        </label>
                        <input type="number" name="cles_autre" class="form-control" 
                               value="<?php echo htmlspecialchars($etat['cles_autre'] ?? '0'); ?>" 
                               min="0" oninput="calculateTotalKeys()">
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Total des clés</label>
                        <input type="number" name="cles_total" id="cles_total" class="form-control" 
                               value="<?php echo htmlspecialchars($etat['cles_total'] ?? ''); ?>" 
                               readonly>
                    </div>
                </div>
                
                <?php if ($isSortie): ?>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label required-field">Conformité</label>
                        <select name="cles_conformite" class="form-select" required>
                            <option value="">-- Sélectionner --</option>
                            <option value="non_applicable" <?php echo ($etat['cles_conformite'] ?? '') === 'non_applicable' ? 'selected' : ''; ?>>Non applicable</option>
                            <option value="conforme" <?php echo ($etat['cles_conformite'] ?? '') === 'conforme' ? 'selected' : ''; ?>>Conforme à l'entrée</option>
                            <option value="non_conforme" <?php echo ($etat['cles_conformite'] ?? '') === 'non_conforme' ? 'selected' : ''; ?>>Non conforme</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Observations</label>
                        <textarea name="cles_observations" class="form-control" rows="2"><?php echo htmlspecialchars($etat['cles_observations'] ?? ''); ?></textarea>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-12 mb-3">
                        <label class="form-label">Photo des clés <em>(optionnel)</em></label>
                        
                        <?php if (isset($photos_by_category['cles']) && !empty($photos_by_category['cles'])): ?>
                            <div class="mb-2">
                                <div class="alert alert-success d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-check-circle"></i> <?php echo count($photos_by_category['cles']); ?> photo(s) enregistrée(s)</span>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($photos_by_category['cles'] as $photo): ?>
                                        <div class="position-relative">
                                            <img src="../<?php echo htmlspecialchars($photo['chemin_fichier']); ?>" 
                                                 alt="Photo clés" 
                                                 style="max-width: 150px; max-height: 100px; border: 1px solid #dee2e6; border-radius: 4px;">
                                            <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0" 
                                                    style="padding: 2px 6px; font-size: 10px;"
                                                    onclick="deletePhoto(<?php echo $photo['id']; ?>, this)">
                                                <i class="bi bi-x"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="photo-upload-zone" onclick="document.getElementById('photo_cles').click()">
                            <i class="bi bi-camera" style="font-size: 2rem; color: #6c757d;"></i>
                            <p class="mb-0 mt-2">Cliquer pour ajouter une photo</p>
                            <input type="file" id="photo_cles" name="photo_cles" 
                                   accept="image/*" style="display: none;" onchange="previewPhoto(this, 'preview_cles')">
                        </div>
                        <div id="preview_cles" class="mt-2"></div>
                    </div>
                </div>
            </div>

            <!-- 4. Description du logement -->
            <div class="form-card">
                <div class="section-title">
                    <i class="bi bi-house"></i> 4. Description du logement - État <?php echo $isEntree ? 'd\'entrée' : 'de sortie'; ?>
                </div>
                
                <!-- Pièce principale -->
                <div class="section-subtitle">Pièce principale</div>
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <?php if ($isSortie && $etat_entree && !empty($etat_entree['piece_principale'])): ?>
                            <!-- Entry state reference -->
                            <div class="entry-reference mb-2">
                                <span class="icon-green">🟢</span>
                                <span class="entry-reference-label">État d'entrée :</span>
                                <div class="entry-reference-value mt-1" style="white-space: pre-line; font-size: 0.9rem;">
                                    <?php echo htmlspecialchars($etat_entree['piece_principale']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <label class="form-label required-field <?php echo $isSortie ? 'exit-input-label' : ''; ?>">
                            <?php if ($isSortie): ?><span class="icon-red">🔴</span><?php endif; ?>
                            État<?php echo $isSortie ? ' de sortie' : ''; ?>
                        </label>
                        <textarea name="piece_principale" class="form-control" rows="4" required><?php 
                            echo htmlspecialchars($etat['piece_principale'] ?? ($isEntree 
                                ? "• Revêtement de sol : parquet très bon état d'usage\n• Murs : peintures très bon état\n• Plafond : peintures très bon état\n• Installations électriques et plomberie : fonctionnelles"
                                : "")); // Empty for exit state - user must fill
                        ?></textarea>
                    </div>
                    
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Photos de la pièce principale <em>(optionnel)</em></label>
                        
                        <?php if ($isSortie && $etat_entree && isset($etat_entree_photos['piece_principale']) && !empty($etat_entree_photos['piece_principale'])): ?>
                            <!-- Entry photos as reference -->
                            <div class="mb-2">
                                <small class="text-success fw-bold"><span class="icon-green">🟢</span> Photos de l'état d'entrée (référence) :</small>
                                <div class="d-flex flex-wrap gap-2 mt-1">
                                    <?php foreach ($etat_entree_photos['piece_principale'] as $photo): ?>
                                        <div class="entry-photo-thumbnail">
                                            <img src="../<?php echo htmlspecialchars($photo['chemin_fichier']); ?>" 
                                                 alt="Photo pièce principale (entrée)" 
                                                 title="Photo de l'état d'entrée">
                                            <div class="entry-photo-badge">🟢</div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <hr class="my-2">
                        <?php endif; ?>
                        
                        <?php if (isset($photos_by_category['piece_principale']) && !empty($photos_by_category['piece_principale'])): ?>
                            <div class="mb-2">
                                <div class="alert alert-success d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-check-circle"></i> <?php echo count($photos_by_category['piece_principale']); ?> photo(s) enregistrée(s)</span>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($photos_by_category['piece_principale'] as $photo): ?>
                                        <div class="position-relative">
                                            <img src="../<?php echo htmlspecialchars($photo['chemin_fichier']); ?>" 
                                                 alt="Photo pièce principale" 
                                                 style="max-width: 150px; max-height: 100px; border: 1px solid #dee2e6; border-radius: 4px;">
                                            <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0" 
                                                    style="padding: 2px 6px; font-size: 10px;"
                                                    onclick="deletePhoto(<?php echo $photo['id']; ?>, this)">
                                                <i class="bi bi-x"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="photo-upload-zone" onclick="document.getElementById('photo_piece_principale').click()">
                            <i class="bi bi-camera" style="font-size: 2rem; color: #6c757d;"></i>
                            <p class="mb-0 mt-2">Cliquer pour ajouter des photos</p>
                            <input type="file" id="photo_piece_principale" name="photo_piece_principale[]" 
                                   accept="image/*" multiple style="display: none;" onchange="previewPhoto(this, 'preview_piece')">
                        </div>
                        <div id="preview_piece" class="mt-2"></div>
                    </div>
                </div>
                
                <!-- Coin cuisine -->
                <div class="section-subtitle">Coin cuisine</div>
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <?php if ($isSortie && $etat_entree && !empty($etat_entree['coin_cuisine'])): ?>
                            <!-- Entry state reference -->
                            <div class="entry-reference mb-2">
                                <span class="icon-green">🟢</span>
                                <span class="entry-reference-label">État d'entrée :</span>
                                <div class="entry-reference-value mt-1" style="white-space: pre-line; font-size: 0.9rem;">
                                    <?php echo htmlspecialchars($etat_entree['coin_cuisine']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <label class="form-label required-field <?php echo $isSortie ? 'exit-input-label' : ''; ?>">
                            <?php if ($isSortie): ?><span class="icon-red">🔴</span><?php endif; ?>
                            État<?php echo $isSortie ? ' de sortie' : ''; ?>
                        </label>
                        <textarea name="coin_cuisine" class="form-control" rows="4" required><?php 
                            echo htmlspecialchars($etat['coin_cuisine'] ?? ($isEntree 
                                ? "• Revêtement de sol : parquet très bon état d'usage\n• Murs : peintures très bon état\n• Plafond : peintures très bon état\n• Installations électriques et plomberie : fonctionnelles"
                                : "")); // Empty for exit state - user must fill
                        ?></textarea>
                    </div>
                    
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Photos du coin cuisine <em>(optionnel)</em></label>
                        
                        <?php if ($isSortie && $etat_entree && isset($etat_entree_photos['cuisine']) && !empty($etat_entree_photos['cuisine'])): ?>
                            <!-- Entry photos as reference -->
                            <div class="mb-2">
                                <small class="text-success fw-bold"><span class="icon-green">🟢</span> Photos de l'état d'entrée (référence) :</small>
                                <div class="d-flex flex-wrap gap-2 mt-1">
                                    <?php foreach ($etat_entree_photos['cuisine'] as $photo): ?>
                                        <div class="entry-photo-thumbnail">
                                            <img src="../<?php echo htmlspecialchars($photo['chemin_fichier']); ?>" 
                                                 alt="Photo cuisine (entrée)" 
                                                 title="Photo de l'état d'entrée">
                                            <div class="entry-photo-badge">🟢</div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <hr class="my-2">
                        <?php endif; ?>
                        
                        <?php if (isset($photos_by_category['cuisine']) && !empty($photos_by_category['cuisine'])): ?>
                            <div class="mb-2">
                                <div class="alert alert-success d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-check-circle"></i> <?php echo count($photos_by_category['cuisine']); ?> photo(s) enregistrée(s)</span>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($photos_by_category['cuisine'] as $photo): ?>
                                        <div class="position-relative">
                                            <img src="../<?php echo htmlspecialchars($photo['chemin_fichier']); ?>" 
                                                 alt="Photo cuisine" 
                                                 style="max-width: 150px; max-height: 100px; border: 1px solid #dee2e6; border-radius: 4px;">
                                            <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0" 
                                                    style="padding: 2px 6px; font-size: 10px;"
                                                    onclick="deletePhoto(<?php echo $photo['id']; ?>, this)">
                                                <i class="bi bi-x"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="photo-upload-zone" onclick="document.getElementById('photo_cuisine').click()">
                            <i class="bi bi-camera" style="font-size: 2rem; color: #6c757d;"></i>
                            <p class="mb-0 mt-2">Cliquer pour ajouter des photos</p>
                            <input type="file" id="photo_cuisine" name="photo_cuisine[]" 
                                   accept="image/*" multiple style="display: none;" onchange="previewPhoto(this, 'preview_cuisine')">
                        </div>
                        <div id="preview_cuisine" class="mt-2"></div>
                    </div>
                </div>
                
                <!-- Salle d'eau et WC -->
                <div class="section-subtitle">Salle d'eau et WC</div>
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <?php if ($isSortie && $etat_entree && !empty($etat_entree['salle_eau_wc'])): ?>
                            <!-- Entry state reference -->
                            <div class="entry-reference mb-2">
                                <span class="icon-green">🟢</span>
                                <span class="entry-reference-label">État d'entrée :</span>
                                <div class="entry-reference-value mt-1" style="white-space: pre-line; font-size: 0.9rem;">
                                    <?php echo htmlspecialchars($etat_entree['salle_eau_wc']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <label class="form-label required-field <?php echo $isSortie ? 'exit-input-label' : ''; ?>">
                            <?php if ($isSortie): ?><span class="icon-red">🔴</span><?php endif; ?>
                            État<?php echo $isSortie ? ' de sortie' : ''; ?>
                        </label>
                        <textarea name="salle_eau_wc" class="form-control" rows="4" required><?php 
                            echo htmlspecialchars($etat['salle_eau_wc'] ?? ($isEntree 
                                ? "• Revêtement de sol : carrelage très bon état d'usage\n• Faïence : très bon état\n• Plafond : peintures très bon état\n• Installations électriques et plomberie : fonctionnelles"
                                : "")); // Empty for exit state - user must fill
                        ?></textarea>
                    </div>
                    
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Photos de la salle d'eau et WC <em>(optionnel)</em></label>
                        
                        <?php if ($isSortie && $etat_entree && isset($etat_entree_photos['salle_eau']) && !empty($etat_entree_photos['salle_eau'])): ?>
                            <!-- Entry photos as reference -->
                            <div class="mb-2">
                                <small class="text-success fw-bold"><span class="icon-green">🟢</span> Photos de l'état d'entrée (référence) :</small>
                                <div class="d-flex flex-wrap gap-2 mt-1">
                                    <?php foreach ($etat_entree_photos['salle_eau'] as $photo): ?>
                                        <div class="entry-photo-thumbnail">
                                            <img src="../<?php echo htmlspecialchars($photo['chemin_fichier']); ?>" 
                                                 alt="Photo salle d'eau (entrée)" 
                                                 title="Photo de l'état d'entrée">
                                            <div class="entry-photo-badge">🟢</div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <hr class="my-2">
                        <?php endif; ?>
                        
                        <?php if (isset($photos_by_category['salle_eau']) && !empty($photos_by_category['salle_eau'])): ?>
                            <div class="mb-2">
                                <div class="alert alert-success d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-check-circle"></i> <?php echo count($photos_by_category['salle_eau']); ?> photo(s) enregistrée(s)</span>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($photos_by_category['salle_eau'] as $photo): ?>
                                        <div class="position-relative">
                                            <img src="../<?php echo htmlspecialchars($photo['chemin_fichier']); ?>" 
                                                 alt="Photo salle d'eau" 
                                                 style="max-width: 150px; max-height: 100px; border: 1px solid #dee2e6; border-radius: 4px;">
                                            <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0" 
                                                    style="padding: 2px 6px; font-size: 10px;"
                                                    onclick="deletePhoto(<?php echo $photo['id']; ?>, this)">
                                                <i class="bi bi-x"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="photo-upload-zone" onclick="document.getElementById('photo_salle_eau').click()">
                            <i class="bi bi-camera" style="font-size: 2rem; color: #6c757d;"></i>
                            <p class="mb-0 mt-2">Cliquer pour ajouter des photos</p>
                            <input type="file" id="photo_salle_eau" name="photo_salle_eau[]" 
                                   accept="image/*" multiple style="display: none;" onchange="previewPhoto(this, 'preview_salle_eau')">
                        </div>
                        <div id="preview_salle_eau" class="mt-2"></div>
                    </div>
                </div>
                
                <!-- État général -->
                <div class="section-subtitle">État général du logement</div>
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <?php if ($isSortie && $etat_entree && !empty($etat_entree['etat_general'])): ?>
                            <!-- Entry state reference -->
                            <div class="entry-reference mb-2">
                                <span class="icon-green">🟢</span>
                                <span class="entry-reference-label">État d'entrée :</span>
                                <div class="entry-reference-value mt-1" style="white-space: pre-line; font-size: 0.9rem;">
                                    <?php echo htmlspecialchars($etat_entree['etat_general']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <label class="form-label required-field <?php echo $isSortie ? 'exit-input-label' : ''; ?>">
                            <?php if ($isSortie): ?><span class="icon-red">🔴</span><?php endif; ?>
                            Observations<?php echo $isSortie ? ' de sortie' : ''; ?>
                        </label>
                        <textarea name="etat_general" class="form-control" rows="3" required><?php 
                            echo htmlspecialchars($etat['etat_general'] ?? ($isEntree 
                                ? "Le logement a fait l'objet d'une remise en état générale avant l'entrée dans les lieux.\nIl est propre, entretenu et ne présente aucune dégradation apparente au jour de l'état des lieux.\nAucune anomalie constatée."
                                : "")); // Empty for exit state - user must fill
                        ?></textarea>
                    </div>
                    
                    <?php if ($isSortie): ?>
                    <div class="col-md-6 mb-3">
                        <label class="form-label required-field">Conformité à l'état d'entrée</label>
                        <select name="etat_general_conforme" class="form-select" required>
                            <option value="">-- Sélectionner --</option>
                            <option value="non_applicable" <?php echo ($etat['etat_general_conforme'] ?? '') === 'non_applicable' ? 'selected' : ''; ?>>Non applicable</option>
                            <option value="conforme" <?php echo ($etat['etat_general_conforme'] ?? '') === 'conforme' ? 'selected' : ''; ?>>Conforme à l'état des lieux d'entrée</option>
                            <option value="non_conforme" <?php echo ($etat['etat_general_conforme'] ?? '') === 'non_conforme' ? 'selected' : ''; ?>>Non conforme à l'état des lieux d'entrée</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Dégradations imputables au(x) locataire(s)</label>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="degradations_constatees" 
                                   id="degradations_constatees" value="1" 
                                   <?php echo !empty($etat['degradations_constatees']) ? 'checked' : ''; ?>
                                   onchange="toggleDegradationsDetails()">
                            <label class="form-check-label" for="degradations_constatees">
                                Dégradations constatées, pouvant donner lieu à retenue sur le dépôt de garantie
                            </label>
                        </div>
                    </div>
                    
                    <div class="col-md-12 mb-3" id="degradations_details_container" style="display: <?php echo !empty($etat['degradations_constatees']) ? 'block' : 'none'; ?>;">
                        <label class="form-label">Détails des dégradations</label>
                        <textarea name="degradations_details" class="form-control" rows="3"><?php echo htmlspecialchars($etat['degradations_details'] ?? ''); ?></textarea>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Photos de l'état général <em>(optionnel)</em></label>
                        <div class="photo-upload-zone" onclick="document.getElementById('photo_etat_general').click()">
                            <i class="bi bi-camera" style="font-size: 2rem; color: #6c757d;"></i>
                            <p class="mb-0 mt-2">Cliquer pour ajouter des photos</p>
                            <input type="file" id="photo_etat_general" name="photo_etat_general[]" 
                                   accept="image/*" multiple style="display: none;" onchange="previewPhoto(this, 'preview_general')">
                        </div>
                        <div id="preview_general" class="mt-2"></div>
                    </div>
                </div>
            </div>

            <?php
            // Initialize $bilanRows for use in JavaScript (line 1927)
            // Will be populated if $isSortie is true
            $bilanRows = [];
            ?>

            <?php if ($isSortie): ?>
            <!-- Bilan du logement externalisé - accessible via bouton "Bilan du logement" -->
            <div class="form-card">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> 
                    <strong>Bilan du logement :</strong> 
                    Le bilan détaillé des dégradations et frais est maintenant accessible via le bouton 
                    <a href="edit-bilan-logement.php?id=<?php echo $id; ?>" class="btn btn-sm btn-info">
                        <i class="bi bi-clipboard-check"></i> Bilan du logement
                    </a> 
                    dans les actions de visualisation.
                </div>
            </div>
            <?php endif; ?>


            <!-- <?php echo $isSortie ? '7' : '5'; ?>. Signatures -->
            <div class="form-card">
                <div class="section-title">
                    <i class="bi bi-pen"></i> <?php echo $isSortie ? '7' : '5'; ?>. Signatures
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label required-field">Lieu de signature</label>
                        <input type="text" name="lieu_signature" class="form-control" 
                               value="<?php echo htmlspecialchars($etat['lieu_signature'] ?? 'Annemasse'); ?>" 
                               placeholder="Ex: Annemasse" required>
                    </div>
                    
                    <div class="col-md-12 mb-3">
                        <?php if ($isSortie && $etat_entree && !empty($etat_entree['observations'])): ?>
                            <!-- Entry state reference -->
                            <div class="entry-reference mb-2">
                                <span class="icon-green">🟢</span>
                                <span class="entry-reference-label">Observations d'entrée :</span>
                                <div class="entry-reference-value mt-1" style="white-space: pre-line; font-size: 0.9rem;">
                                    <?php echo htmlspecialchars($etat_entree['observations']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <label class="form-label <?php echo $isSortie ? 'exit-input-label' : ''; ?>">
                            <?php if ($isSortie): ?><span class="icon-red">🔴</span><?php endif; ?>
                            Observations complémentaires<?php echo $isSortie ? ' de sortie' : ''; ?>
                        </label>
                        <textarea name="observations" class="form-control" rows="3" 
                                  placeholder="Remarques ou observations supplémentaires..."><?php echo htmlspecialchars($etat['observations'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> 
                    <strong>Signatures</strong> : 
                    La signature du bailleur sera ajoutée automatiquement depuis les paramètres de l'entreprise. 
                    Les locataires peuvent signer ci-dessous.
                </div>
                
                <!-- Tenant Signatures (1 or 2) -->
                <?php foreach ($existing_tenants as $index => $tenant): ?>
                <div class="section-subtitle">
                    Signature locataire <?php echo $index + 1; ?> - <?php echo htmlspecialchars($tenant['prenom'] . ' ' . $tenant['nom']); ?>
                </div>
                <div class="row mb-4">
                    <div class="col-md-12">
                        <?php if (!empty($tenant['signature_data'])): ?>
                            <div class="alert alert-warning mb-2">
                                <i class="bi bi-exclamation-triangle"></i> 
                                <strong>Signature précédente détectée</strong> (signée le <?php echo date('d/m/Y à H:i', strtotime($tenant['signature_timestamp'])); ?>)<br>
                                Pour des raisons de sécurité et d'audit, toute modification du formulaire nécessite une nouvelle signature. 
                                <strong>Veuillez signer à nouveau ci-dessous pour que la signature apparaisse correctement dans le PDF.</strong>
                            </div>
                            <div class="mb-2">
                                <?php
                                // Handle signature path - prepend ../ for relative paths since we're in admin-v2 directory
                                $signatureSrc = $tenant['signature_data'];
                                
                                // Validate data URL format with length check (max 2MB)
                                if (preg_match('/^data:image\/(jpeg|jpg|png);base64,([A-Za-z0-9+\/=]+)$/', $signatureSrc, $matches)) {
                                    // Data URL - validate base64 content and size
                                    if (strlen($signatureSrc) <= 2 * 1024 * 1024 && base64_decode($matches[2], true) !== false) {
                                        $displaySrc = $signatureSrc;
                                    } else {
                                        error_log("Invalid or oversized signature data URL for tenant ID: " . (int)$tenant['id']);
                                        $displaySrc = '';
                                    }
                                } elseif (preg_match('/^uploads\/signatures\/[a-zA-Z0-9_][a-zA-Z0-9_\-]*\.(jpg|jpeg|png)$/', $signatureSrc)) {
                                    // Relative path - validate it's within expected directory and prepend ../
                                    // Pattern ensures no directory traversal, no leading hyphen, no multiple dots, and only allowed file extensions
                                    $displaySrc = '../' . $signatureSrc;
                                } else {
                                    // Invalid or unexpected format - don't display to prevent security issues
                                    error_log("Invalid signature path format detected for tenant ID: " . (int)$tenant['id']);
                                    $displaySrc = '';
                                }
                                ?>
                                <?php if (!empty($displaySrc)): ?>
                                <div style="opacity: 0.5;">
                                    <img src="<?php echo htmlspecialchars($displaySrc); ?>" 
                                         alt="Ancienne signature" style="max-width: 200px; max-height: 80px; border: 1px solid #dee2e6; padding: 5px;">
                                    <p class="text-muted small">Ancienne signature (pour référence)</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <label class="form-label required-field">Veuillez signer dans le cadre ci-dessous :</label>
                        <div class="signature-container" style="max-width: 300px;">
                            <canvas id="tenantCanvas_<?php echo $tenant['id']; ?>" width="300" height="150" style="background: transparent; border: none; outline: none; padding: 0;"></canvas>
                        </div>
                        <!-- IMPORTANT: Signature field is intentionally empty (even if previously signed)
                             This is by design - requirement states that editing must force re-signing
                             to ensure signatures appear correctly in the PDF and for audit purposes -->
                        <input type="hidden" name="tenants[<?php echo $tenant['id']; ?>][signature]" 
                               id="tenantSignature_<?php echo $tenant['id']; ?>" 
                               value="">
                        <input type="hidden" name="tenants[<?php echo $tenant['id']; ?>][locataire_id]" 
                               value="<?php echo $tenant['locataire_id']; ?>">
                        <input type="hidden" name="tenants[<?php echo $tenant['id']; ?>][ordre]" 
                               value="<?php echo $tenant['ordre']; ?>">
                        <input type="hidden" name="tenants[<?php echo $tenant['id']; ?>][nom]" 
                               value="<?php echo htmlspecialchars($tenant['nom']); ?>">
                        <input type="hidden" name="tenants[<?php echo $tenant['id']; ?>][prenom]" 
                               value="<?php echo htmlspecialchars($tenant['prenom']); ?>">
                        <input type="hidden" name="tenants[<?php echo $tenant['id']; ?>][email]" 
                               value="<?php echo htmlspecialchars($tenant['email']); ?>">
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
                                       value="1">
                                <label class="form-check-label" for="certifie_exact_<?php echo $tenant['id']; ?>">
                                    <strong>Certifié exact</strong>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Sticky Actions -->
            <div class="sticky-actions">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted">
                            <i class="bi bi-info-circle"></i> 
                            Les champs marqués d'un <span class="text-danger">*</span> sont obligatoires
                        </span>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-secondary">
                            <i class="bi bi-save"></i> Enregistrer le brouillon
                        </button>
                        <button type="submit" name="finalize" value="1" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Finaliser et envoyer
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ========================================
        // Photo Upload Functions (must be defined first for inline event handlers)
        // ========================================
        
        // Helper function to create photo HTML element
        function createPhotoElement(photoData) {
            const photoDiv = document.createElement('div');
            photoDiv.className = 'position-relative';
            
            // Create img element safely
            const img = document.createElement('img');
            img.setAttribute('src', '../' + photoData.url.replace(/^\//, ''));
            img.setAttribute('alt', 'Photo');
            img.setAttribute('style', 'max-width: 150px; max-height: 100px; border: 1px solid #dee2e6; border-radius: 4px;');
            
            // Create delete button safely
            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'btn btn-danger btn-sm position-absolute top-0 end-0';
            deleteBtn.setAttribute('style', 'padding: 2px 6px; font-size: 10px;');
            deleteBtn.innerHTML = '<i class="bi bi-x"></i>';
            
            // Add event listener instead of inline onclick (safer)
            const photoId = parseInt(photoData.photo_id, 10);
            if (!isNaN(photoId)) {
                deleteBtn.addEventListener('click', function() {
                    deletePhoto(photoId, this);
                });
            }
            
            photoDiv.appendChild(img);
            photoDiv.appendChild(deleteBtn);
            
            return photoDiv;
        }
        
        // Upload and preview photos
        function previewPhoto(input, previewId) {
            const preview = document.getElementById(previewId);
            preview.innerHTML = '';
            
            if (!input.files || input.files.length === 0) {
                return;
            }
            
            // Determine category from input ID
            const categoryMap = {
                'photo_compteur_elec': 'compteur_electricite',
                'photo_compteur_eau': 'compteur_eau',
                'photo_cles': 'cles',
                'photo_piece_principale': 'piece_principale',
                'photo_cuisine': 'cuisine',
                'photo_salle_eau': 'salle_eau',
                'photo_etat_general': 'autre'
            };
            
            const category = categoryMap[input.id];
            if (!category) {
                console.error('Unknown category for input:', input.id);
                return;
            }
            
            // Show uploading message
            preview.innerHTML = '<div class="alert alert-info mb-0"><i class="bi bi-hourglass-split"></i> Téléchargement en cours...</div>';
            
            // Upload each file
            const uploadPromises = [];
            for (let i = 0; i < input.files.length; i++) {
                const formData = new FormData();
                formData.append('photo', input.files[i]);
                formData.append('etat_lieux_id', <?php echo json_encode((int)$id); ?>);
                formData.append('categorie', category);
                
                const uploadPromise = fetch('upload-etat-lieux-photo.php', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json'
                    },
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(data => {
                            throw new Error(data.error || 'Erreur serveur');
                        }, () => {
                            // JSON parsing failed - not a JSON response
                            throw new Error('Erreur de connexion au serveur');
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.error || 'Erreur inconnue');
                    }
                    // Check for redirect (session expired)
                    if (data.redirect) {
                        window.location.href = data.redirect;
                        return;
                    }
                    return data;
                });
                
                uploadPromises.push(uploadPromise);
            }
            
            // Wait for all uploads to complete
            Promise.all(uploadPromises)
                .then(results => {
                    // Clear uploading message
                    preview.innerHTML = '';
                    
                    // Find the upload zone (parent of input)
                    const uploadZone = input.closest('.photo-upload-zone');
                    
                    // Look for existing photos container (previous sibling with mb-2 class)
                    let photosContainer = null;
                    let sibling = uploadZone.previousElementSibling;
                    while (sibling) {
                        if (sibling.nodeType === Node.ELEMENT_NODE && sibling.classList.contains('mb-2')) {
                            photosContainer = sibling;
                            break;
                        }
                        sibling = sibling.previousElementSibling;
                    }
                    
                    // Check if we need to create the container structure
                    if (!photosContainer) {
                        photosContainer = document.createElement('div');
                        photosContainer.className = 'mb-2';
                        
                        // Create alert
                        const alert = document.createElement('div');
                        alert.className = 'alert alert-success d-flex justify-content-between align-items-center';
                        alert.innerHTML = '<span><i class="bi bi-check-circle"></i> <span class="photo-count">0</span> photo(s) enregistrée(s)</span>';
                        photosContainer.appendChild(alert);
                        
                        // Create photos wrapper
                        const photosWrapper = document.createElement('div');
                        photosWrapper.className = 'd-flex flex-wrap gap-2';
                        photosContainer.appendChild(photosWrapper);
                        
                        // Insert before upload zone
                        uploadZone.parentNode.insertBefore(photosContainer, uploadZone);
                    }
                    
                    // Get the photos wrapper (second child of photosContainer)
                    const photosWrapper = photosContainer.querySelector('.d-flex.flex-wrap');
                    
                    // Add each uploaded photo to the DOM
                    results.forEach(photoData => {
                        const photoElement = createPhotoElement(photoData);
                        photosWrapper.appendChild(photoElement);
                    });
                    
                    // Update the count in the alert
                    const totalPhotos = photosWrapper.querySelectorAll('.position-relative').length;
                    const alertSpan = photosContainer.querySelector('.alert span');
                    if (alertSpan) {
                        // Try to find existing photo-count span, or update the entire text
                        const countSpan = alertSpan.querySelector('.photo-count');
                        if (countSpan) {
                            countSpan.textContent = totalPhotos;
                        } else {
                            // Update entire span content (for PHP-generated containers)
                            alertSpan.innerHTML = `<i class="bi bi-check-circle"></i> ${totalPhotos} photo(s) enregistrée(s)`;
                        }
                    }
                    
                    // Show success message in preview
                    preview.innerHTML = '<div class="alert alert-success mb-0"><i class="bi bi-check-circle"></i> ' + results.length + ' photo(s) téléchargée(s) avec succès</div>';
                    
                    // Auto-dismiss success message after 3 seconds
                    const SUCCESS_MESSAGE_DURATION_MS = 3000;
                    setTimeout(() => {
                        preview.innerHTML = '';
                    }, SUCCESS_MESSAGE_DURATION_MS);
                    
                    // Clear the file input so the same file can be uploaded again if needed
                    input.value = '';
                })
                .catch(error => {
                    console.error('Upload error:', error);
                    preview.innerHTML = '<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle"></i> Erreur: ' + error.message + '</div>';
                });
        }
        
        // Delete photo
        function deletePhoto(photoId, button) {
            if (!confirm('Voulez-vous vraiment supprimer cette photo ?')) {
                return;
            }
            
            fetch('delete-etat-lieux-photo.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json'
                },
                body: 'photo_id=' + encodeURIComponent(photoId)
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data.error || 'Erreur serveur');
                    }, () => {
                        // JSON parsing failed - not a JSON response
                        throw new Error('Erreur de connexion au serveur');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (!data.success) {
                    throw new Error(data.error || 'Erreur inconnue');
                }
                // Check for redirect (session expired)
                if (data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                
                // Get the parent container
                const photoContainer = button.closest('.position-relative');
                const photosWrapper = photoContainer.parentElement;
                
                // Remove the photo element
                photoContainer.remove();
                
                // Update count or remove alert if no photos left
                const alertElement = photosWrapper.previousElementSibling;
                if (alertElement && alertElement.classList.contains('alert-success')) {
                    const remainingPhotos = photosWrapper.querySelectorAll('.position-relative').length;
                    if (remainingPhotos === 0) {
                        // Remove both alert and photos wrapper
                        alertElement.parentElement.remove();
                    } else {
                        // Update count
                        const countSpan = alertElement.querySelector('span');
                        if (countSpan) {
                            countSpan.innerHTML = `<i class="bi bi-check-circle"></i> ${remainingPhotos} photo(s) enregistrée(s)`;
                        }
                    }
                }
                
                // Show success message
                const alert = document.createElement('div');
                alert.className = 'alert alert-success alert-dismissible fade show';
                alert.innerHTML = '<i class="bi bi-check-circle"></i> Photo supprimée avec succès <button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                document.querySelector('.main-content').insertBefore(alert, document.querySelector('.form-card'));
                
                // Auto-dismiss after 3 seconds
                setTimeout(() => alert.remove(), 3000);
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Erreur lors de la suppression de la photo: ' + error.message);
            });
        }
        
        // ========================================
        // End Photo Upload Functions
        // ========================================
        
        // Calculate total keys
        function calculateTotalKeys() {
            const appart = parseInt(document.querySelector('[name="cles_appartement"]').value) || 0;
            const boite = parseInt(document.querySelector('[name="cles_boite_lettres"]').value) || 0;
            const autre = parseInt(document.querySelector('[name="cles_autre"]').value) || 0;
            document.getElementById('cles_total').value = appart + boite + autre;
        }
        
        // Toggle degradations details
        function toggleDegradationsDetails() {
            const checkbox = document.getElementById('degradations_constatees');
            const container = document.getElementById('degradations_details_container');
            container.style.display = checkbox.checked ? 'block' : 'none';
        }
        
        function initTenantSignature(id) {
            const canvas = document.getElementById(`tenantCanvas_${id}`);
            if (!canvas) return;
            
            const ctx = canvas.getContext('2d');
            
            // Set drawing style for black signature lines
            ctx.strokeStyle = '#000000';
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            
            let isDrawing = false;
            let lastX = 0;
            let lastY = 0;
            
            // Helper function to get mouse position
            function getMousePos(e) {
                const rect = canvas.getBoundingClientRect();
                return {
                    x: e.clientX - rect.left,
                    y: e.clientY - rect.top
                };
            }
            
            canvas.addEventListener('mousedown', (e) => {
                isDrawing = true;
                const pos = getMousePos(e);
                lastX = pos.x;
                lastY = pos.y;
            });
            
            canvas.addEventListener('mousemove', (e) => {
                if (!isDrawing) return;
                e.preventDefault();
                
                const pos = getMousePos(e);
                
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
                ctx.lineTo(pos.x, pos.y);
                ctx.stroke();
                
                lastX = pos.x;
                lastY = pos.y;
            });
            
            canvas.addEventListener('mouseup', () => {
                isDrawing = false;
                saveTenantSignature(id);
            });
            
            // Touch support
            canvas.addEventListener('touchstart', (e) => {
                e.preventDefault();
                const touch = e.touches[0];
                const mouseEvent = new MouseEvent('mousedown', {
                    clientX: touch.clientX,
                    clientY: touch.clientY
                });
                canvas.dispatchEvent(mouseEvent);
            });
            
            canvas.addEventListener('touchmove', (e) => {
                e.preventDefault();
                const touch = e.touches[0];
                const mouseEvent = new MouseEvent('mousemove', {
                    clientX: touch.clientX,
                    clientY: touch.clientY
                });
                canvas.dispatchEvent(mouseEvent);
            });
            
            canvas.addEventListener('touchend', (e) => {
                e.preventDefault();
                const mouseEvent = new MouseEvent('mouseup');
                canvas.dispatchEvent(mouseEvent);
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
            const signatureData = tempCanvas.toDataURL('image/jpeg', 0.95);
            document.getElementById(`tenantSignature_${id}`).value = signatureData;
        }
        
        function clearTenantSignature(id) {
            const canvas = document.getElementById(`tenantCanvas_${id}`);
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            document.getElementById(`tenantSignature_${id}`).value = '';
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            calculateTotalKeys();
            
            // Initialize tenant signatures based on actual IDs in the page
            <?php if (!empty($existing_tenants)): ?>
                <?php foreach ($existing_tenants as $tenant): ?>
                    initTenantSignature(<?php echo $tenant['id']; ?>);
                <?php endforeach; ?>
            <?php endif; ?>
        });
        
        // Handle form submission
        document.getElementById('etatLieuxForm').addEventListener('submit', function(e) {
            // Save all tenant signatures before submission
            <?php if (!empty($existing_tenants)): ?>
                <?php foreach ($existing_tenants as $tenant): ?>
                    saveTenantSignature(<?php echo $tenant['id']; ?>);
                <?php endforeach; ?>
            <?php endif; ?>
            
            // Validate that all tenants have signed and checked "Certifié exact"
            <?php if (!empty($existing_tenants)): ?>
                let allValid = true;
                let errors = [];
                
                <?php foreach ($existing_tenants as $tenant): ?>
                    const signature_<?php echo $tenant['id']; ?> = document.getElementById('tenantSignature_<?php echo $tenant['id']; ?>').value;
                    const certifie_<?php echo $tenant['id']; ?> = document.getElementById('certifie_exact_<?php echo $tenant['id']; ?>').checked;
                    const tenantName_<?php echo $tenant['id']; ?> = <?php echo json_encode($tenant['prenom'] . ' ' . $tenant['nom']); ?>;
                    
                    if (!signature_<?php echo $tenant['id']; ?> || signature_<?php echo $tenant['id']; ?>.trim() === '') {
                        errors.push('La signature de ' + tenantName_<?php echo $tenant['id']; ?> + ' est obligatoire');
                        allValid = false;
                    }
                    
                    if (!certifie_<?php echo $tenant['id']; ?>) {
                        errors.push('La case "Certifié exact" doit être cochée pour ' + tenantName_<?php echo $tenant['id']; ?>);
                        allValid = false;
                    }
                <?php endforeach; ?>
                
                if (!allValid) {
                    e.preventDefault();
                    alert('Erreurs de validation:\n\n' + errors.join('\n'));
                    return false;
                }
            <?php endif; ?>
        });
    </script>
</body>
</html>
