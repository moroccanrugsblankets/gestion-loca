<?php
/**
 * Edit État des Lieux - Comprehensive Form
 * My Invest Immobilier
 */

require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

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
                depot_garantie_status = ?,
                depot_garantie_montant_retenu = ?,
                depot_garantie_motif_retenue = ?,
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
            $_POST['depot_garantie_status'] ?? 'non_applicable',
            isset($_POST['depot_garantie_montant_retenu']) && !empty($_POST['depot_garantie_montant_retenu']) ? (float)$_POST['depot_garantie_montant_retenu'] : null,
            $_POST['depot_garantie_motif_retenue'] ?? '',
            $_POST['lieu_signature'] ?? '',
            $_POST['statut'] ?? 'brouillon',
            $id
        ]);
        
        // Update tenant signatures
        if (isset($_POST['tenants']) && is_array($_POST['tenants'])) {
            foreach ($_POST['tenants'] as $tenantId => $tenantInfo) {
                if (!empty($tenantInfo['signature'])) {
                    // Validate signature format
                    if (!preg_match('/^data:image\/(jpeg|jpg|png);base64,/', $tenantInfo['signature'])) {
                        error_log("Invalid signature format for tenant ID $tenantId - skipping");
                        continue;
                    }
                    
                    $updateStmt = $pdo->prepare("
                        UPDATE etat_lieux_locataires 
                        SET signature_data = ?,
                            signature_timestamp = NOW(),
                            signature_ip = ?
                        WHERE id = ? AND etat_lieux_id = ?
                    ");
                    $updateStmt->execute([
                        $tenantInfo['signature'],
                        $_SERVER['REMOTE_ADDR'] ?? null,
                        $tenantId,
                        $id
                    ]);
                }
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


$isEntree = $etat['type'] === 'entree';
$isSortie = $etat['type'] === 'sortie';
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
                        <label class="form-label required-field">Index relevé (kWh)</label>
                        <input type="text" name="compteur_electricite" class="form-control" 
                               value="<?php echo htmlspecialchars($etat['compteur_electricite'] ?? ''); ?>" 
                               placeholder="Ex: 12345" required>
                        <small class="text-muted">Sous-compteur électrique privatif - Appartement n°<?php echo htmlspecialchars($etat['appartement'] ?? '...'); ?></small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Photo du compteur électrique <em>(optionnel)</em></label>
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
                        <label class="form-label required-field">Index relevé (m³)</label>
                        <input type="text" name="compteur_eau_froide" class="form-control" 
                               value="<?php echo htmlspecialchars($etat['compteur_eau_froide'] ?? ''); ?>" 
                               placeholder="Ex: 123.45" required>
                        <small class="text-muted">Sous-compteur d'eau privatif - Appartement n°<?php echo htmlspecialchars($etat['appartement'] ?? '...'); ?></small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Photo du compteur d'eau <em>(optionnel)</em></label>
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
                
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label required-field">Clés de l'appartement</label>
                        <input type="number" name="cles_appartement" class="form-control" 
                               value="<?php echo htmlspecialchars($etat['cles_appartement'] ?? ''); ?>" 
                               min="0" required oninput="calculateTotalKeys()">
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label required-field">Clé(s) de la boîte aux lettres</label>
                        <input type="number" name="cles_boite_lettres" class="form-control" 
                               value="<?php echo htmlspecialchars($etat['cles_boite_lettres'] ?? '1'); ?>" 
                               min="0" required oninput="calculateTotalKeys()">
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Autre</label>
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
                        <label class="form-label">Conformité</label>
                        <select name="cles_conformite" class="form-select">
                            <option value="non_applicable">Non applicable</option>
                            <option value="conforme" <?php echo ($etat['cles_conformite'] ?? '') === 'conforme' ? 'selected' : ''; ?>>☑ Conforme à l'entrée</option>
                            <option value="non_conforme" <?php echo ($etat['cles_conformite'] ?? '') === 'non_conforme' ? 'selected' : ''; ?>>☑ Non conforme</option>
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
                        <label class="form-label required-field">État</label>
                        <textarea name="piece_principale" class="form-control" rows="4" required><?php 
                            echo htmlspecialchars($etat['piece_principale'] ?? ($isEntree 
                                ? "• Revêtement de sol : parquet très bon état d'usage\n• Murs : peintures très bon état\n• Plafond : peintures très bon état\n• Installations électriques et plomberie : fonctionnelles"
                                : "• Revêtement de sol : bon état d'usage\n• Murs : bon état d'usage\n• Plafond : bon état d'usage\n• Installations électriques et plomberie : fonctionnelles")); 
                        ?></textarea>
                    </div>
                    
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Photos de la pièce principale <em>(optionnel)</em></label>
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
                        <label class="form-label required-field">État</label>
                        <textarea name="coin_cuisine" class="form-control" rows="4" required><?php 
                            echo htmlspecialchars($etat['coin_cuisine'] ?? ($isEntree 
                                ? "• Revêtement de sol : parquet très bon état d'usage\n• Murs : peintures très bon état\n• Plafond : peintures très bon état\n• Installations électriques et plomberie : fonctionnelles"
                                : "• Revêtement de sol : bon état d'usage\n• Murs : bon état d'usage\n• Plafond : bon état d'usage\n• Installations électriques et plomberie : fonctionnelles")); 
                        ?></textarea>
                    </div>
                    
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Photos du coin cuisine <em>(optionnel)</em></label>
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
                        <label class="form-label required-field">État</label>
                        <textarea name="salle_eau_wc" class="form-control" rows="4" required><?php 
                            echo htmlspecialchars($etat['salle_eau_wc'] ?? ($isEntree 
                                ? "• Revêtement de sol : carrelage très bon état d'usage\n• Faïence : très bon état\n• Plafond : peintures très bon état\n• Installations électriques et plomberie : fonctionnelles"
                                : "• Revêtement de sol : bon état d'usage\n• Faïence : bon état d'usage\n• Plafond : bon état d'usage\n• Installations électriques et plomberie : fonctionnelles")); 
                        ?></textarea>
                    </div>
                    
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Photos de la salle d'eau et WC <em>(optionnel)</em></label>
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
                        <label class="form-label required-field">Observations</label>
                        <textarea name="etat_general" class="form-control" rows="3" required><?php 
                            echo htmlspecialchars($etat['etat_general'] ?? ($isEntree 
                                ? "Le logement a fait l'objet d'une remise en état générale avant l'entrée dans les lieux.\nIl est propre, entretenu et ne présente aucune dégradation apparente au jour de l'état des lieux.\nAucune anomalie constatée."
                                : "À compléter lors de l'état des lieux de sortie (anomalies constatées, traces d'usage, dégradations éventuelles).")); 
                        ?></textarea>
                    </div>
                    
                    <?php if ($isSortie): ?>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Conformité à l'état d'entrée</label>
                        <select name="etat_general_conforme" class="form-select">
                            <option value="non_applicable">Non applicable</option>
                            <option value="conforme" <?php echo ($etat['etat_general_conforme'] ?? '') === 'conforme' ? 'selected' : ''; ?>>☑ Conforme à l'état des lieux d'entrée</option>
                            <option value="non_conforme" <?php echo ($etat['etat_general_conforme'] ?? '') === 'non_conforme' ? 'selected' : ''; ?>>☑ Non conforme à l'état des lieux d'entrée</option>
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
                                ☑ Dégradations constatées, pouvant donner lieu à retenue sur le dépôt de garantie
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

            <?php if ($isSortie): ?>
            <!-- 5. Conclusion - Dépôt de garantie (Sortie uniquement) -->
            <div class="form-card">
                <div class="section-title">
                    <i class="bi bi-cash-coin"></i> 5. Conclusion - Dépôt de garantie
                </div>
                
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Cette section permet de décider de la restitution du dépôt de garantie en fonction de l'état du logement.
                </div>
                
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label required-field">Décision concernant le dépôt de garantie</label>
                        <select name="depot_garantie_status" class="form-select" id="depot_garantie_status" onchange="toggleDepotDetails()" required>
                            <option value="non_applicable" <?php echo ($etat['depot_garantie_status'] ?? 'non_applicable') === 'non_applicable' ? 'selected' : ''; ?>>-- Sélectionner --</option>
                            <option value="restitution_totale" <?php echo ($etat['depot_garantie_status'] ?? '') === 'restitution_totale' ? 'selected' : ''; ?>>☑ Restitution totale du dépôt de garantie (aucune dégradation imputable)</option>
                            <option value="restitution_partielle" <?php echo ($etat['depot_garantie_status'] ?? '') === 'restitution_partielle' ? 'selected' : ''; ?>>☑ Restitution partielle du dépôt de garantie (dégradations mineures)</option>
                            <option value="retenue_totale" <?php echo ($etat['depot_garantie_status'] ?? '') === 'retenue_totale' ? 'selected' : ''; ?>>☑ Retenue totale du dépôt de garantie (dégradations importantes)</option>
                        </select>
                    </div>
                </div>
                
                <div id="depot_details_container" style="display: <?php echo (in_array($etat['depot_garantie_status'] ?? '', ['restitution_partielle', 'retenue_totale'])) ? 'block' : 'none'; ?>;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Montant retenu (€)</label>
                            <input type="number" name="depot_garantie_montant_retenu" class="form-control" 
                                   value="<?php echo htmlspecialchars($etat['depot_garantie_montant_retenu'] ?? ''); ?>" 
                                   step="0.01" min="0" placeholder="Ex: 150.00">
                            <small class="text-muted">Montant en euros (sans le symbole €)</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Justificatif / Motif de la retenue</label>
                            <textarea name="depot_garantie_motif_retenue" class="form-control" rows="4" 
                                      placeholder="Détailler les dégradations constatées et le calcul du montant retenu"><?php echo htmlspecialchars($etat['depot_garantie_motif_retenue'] ?? ''); ?></textarea>
                            <small class="text-muted">Exemple : Réparation de trous dans les murs (80€), remplacement de la peinture cuisine (120€), etc.</small>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- <?php echo $isSortie ? '6' : '5'; ?>. Signatures -->
            <div class="form-card">
                <div class="section-title">
                    <i class="bi bi-pen"></i> <?php echo $isSortie ? '6' : '5'; ?>. Signatures
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label required-field">Lieu de signature</label>
                        <input type="text" name="lieu_signature" class="form-control" 
                               value="<?php echo htmlspecialchars($etat['lieu_signature'] ?? 'Annemasse'); ?>" 
                               placeholder="Ex: Annemasse" required>
                    </div>
                    
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Observations complémentaires</label>
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
                            <div class="alert alert-success mb-2">
                                <i class="bi bi-check-circle"></i> 
                                Signé le <?php echo date('d/m/Y à H:i', strtotime($tenant['signature_timestamp'])); ?>
                            </div>
                            <div class="mb-2">
                                <img src="<?php echo htmlspecialchars($tenant['signature_data']); ?>" 
                                     alt="Signature" style="max-width: 200px; max-height: 80px; border: 1px solid #dee2e6; padding: 5px;">
                            </div>
                        <?php endif; ?>
                        <label class="form-label">Veuillez signer dans le cadre ci-dessous :</label>
                        <div class="signature-container" style="max-width: 200px;">
                            <canvas id="tenantCanvas_<?php echo $tenant['id']; ?>" width="200" height="80" style="background: transparent; border: none; outline: none; padding: 0;"></canvas>
                        </div>
                        <input type="hidden" name="tenants[<?php echo $tenant['id']; ?>][signature]" 
                               id="tenantSignature_<?php echo $tenant['id']; ?>" 
                               value="<?php echo htmlspecialchars($tenant['signature_data'] ?? ''); ?>">
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
        
        // Toggle deposit guarantee details
        function toggleDepotDetails() {
            const select = document.getElementById('depot_garantie_status');
            const container = document.getElementById('depot_details_container');
            const selectedValue = select.value;
            
            // Show details only if restitution_partielle or retenue_totale
            container.style.display = (selectedValue === 'restitution_partielle' || selectedValue === 'retenue_totale') ? 'block' : 'none';
        }
        
        // Preview photos
        function previewPhoto(input, previewId) {
            const preview = document.getElementById(previewId);
            preview.innerHTML = '';
            
            if (input.files && input.files.length > 0) {
                const fileList = document.createElement('div');
                fileList.className = 'alert alert-success mb-0';
                fileList.innerHTML = `<i class="bi bi-check-circle"></i> ${input.files.length} fichier(s) sélectionné(s)`;
                preview.appendChild(fileList);
            }
        }
        
        
        function initTenantSignature(id) {
            const canvas = document.getElementById(`tenantCanvas_${id}`);
            if (!canvas) return;
            
            const ctx = canvas.getContext('2d');
            let isDrawing = false;
            
            canvas.addEventListener('mousedown', (e) => {
                isDrawing = true;
                ctx.beginPath();
                ctx.moveTo(e.offsetX, e.offsetY);
            });
            
            canvas.addEventListener('mousemove', (e) => {
                if (!isDrawing) return;
                ctx.lineTo(e.offsetX, e.offsetY);
                ctx.stroke();
            });
            
            canvas.addEventListener('mouseup', () => {
                isDrawing = false;
                saveTenantSignature(id);
            });
            
            // Touch support
            canvas.addEventListener('touchstart', (e) => {
                e.preventDefault();
                const touch = e.touches[0];
                const rect = canvas.getBoundingClientRect();
                isDrawing = true;
                ctx.beginPath();
                ctx.moveTo(touch.clientX - rect.left, touch.clientY - rect.top);
            });
            
            canvas.addEventListener('touchmove', (e) => {
                e.preventDefault();
                if (!isDrawing) return;
                const touch = e.touches[0];
                const rect = canvas.getBoundingClientRect();
                ctx.lineTo(touch.clientX - rect.left, touch.clientY - rect.top);
                ctx.stroke();
            });
            
            canvas.addEventListener('touchend', (e) => {
                e.preventDefault();
                isDrawing = false;
                saveTenantSignature(id);
            });
        }
        
        function saveTenantSignature(id) {
            const canvas = document.getElementById(`tenantCanvas_${id}`);
            const signatureData = canvas.toDataURL('image/jpeg');
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
        });
    </script>
</body>
</html>
