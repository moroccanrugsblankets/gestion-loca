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
        
        // Handle signature data
        $bailleurSignature = null;
        $locataireSignature = null;
        
        if (!empty($_POST['bailleur_signature_data'])) {
            $bailleurSignature = $_POST['bailleur_signature_data'];
            // Validate it's a proper data URL
            if (!preg_match('/^data:image\/(jpeg|jpg);base64,/', $bailleurSignature)) {
                throw new Exception("Format de signature bailleur invalide");
            }
        }
        
        if (!empty($_POST['locataire_signature_data'])) {
            $locataireSignature = $_POST['locataire_signature_data'];
            // Validate it's a proper data URL
            if (!preg_match('/^data:image\/(jpeg|jpg);base64,/', $locataireSignature)) {
                throw new Exception("Format de signature locataire invalide");
            }
        }
        
        // Update état des lieux
        $stmt = $pdo->prepare("
            UPDATE etats_lieux SET
                date_etat = ?,
                locataire_nom_complet = ?,
                locataire_email = ?,
                compteur_electricite = ?,
                compteur_eau_froide = ?,
                cles_appartement = ?,
                cles_boite_lettres = ?,
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
                signature_bailleur = ?,
                signature_locataire = ?,
                date_signature = ?,
                statut = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        // Set date_signature if either signature is provided
        $dateSignature = ($bailleurSignature || $locataireSignature) ? date('Y-m-d H:i:s') : null;
        
        $stmt->execute([
            $_POST['date_etat'],
            $_POST['locataire_nom_complet'],
            $_POST['locataire_email'],
            $_POST['compteur_electricite'] ?? '',
            $_POST['compteur_eau_froide'] ?? '',
            (int)($_POST['cles_appartement'] ?? 0),
            (int)($_POST['cles_boite_lettres'] ?? 0),
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
            $bailleurSignature,
            $locataireSignature,
            $dateSignature,
            $_POST['statut'] ?? 'brouillon',
            $id
        ]);
        
        // Handle multiple tenants if provided
        if (isset($_POST['tenants']) && is_array($_POST['tenants'])) {
            // First, delete existing tenants for this état des lieux
            $stmt = $pdo->prepare("DELETE FROM etat_lieux_locataires WHERE etat_lieux_id = ?");
            $stmt->execute([$id]);
            
            // Insert new tenants
            $ordre = 1;
            foreach ($_POST['tenants'] as $tenantData) {
                if (empty($tenantData['nom']) || empty($tenantData['prenom'])) {
                    continue; // Skip incomplete entries
                }
                
                // Note: locataire_id is set to 0 as a placeholder since we're storing tenant info directly
                // in this table without requiring a reference to the main locataires table
                $stmt = $pdo->prepare("
                    INSERT INTO etat_lieux_locataires 
                    (etat_lieux_id, locataire_id, ordre, nom, prenom, email, signature_data, signature_timestamp, signature_ip)
                    VALUES (?, 0, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $signatureTimestamp = !empty($tenantData['signature']) ? date('Y-m-d H:i:s') : null;
                $signatureIp = $_SERVER['REMOTE_ADDR'] ?? null;
                
                $stmt->execute([
                    $id,
                    $ordre++,
                    $tenantData['nom'],
                    $tenantData['prenom'],
                    $tenantData['email'] ?? '',
                    $tenantData['signature'] ?? null,
                    $signatureTimestamp,
                    $signatureIp
                ]);
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
    $type = $etat['type'];
    $reference = 'EDL-' . strtoupper($type[0]) . '-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
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
    
    // Insert tenants into etat_lieux_locataires
    foreach ($contract_tenants as $tenant) {
        $stmt = $pdo->prepare("
            INSERT INTO etat_lieux_locataires (etat_lieux_id, locataire_id, ordre, nom, prenom, email)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
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
            <input type="hidden" name="bailleur_signature_data" id="bailleur_signature_data">
            <input type="hidden" name="locataire_signature_data" id="locataire_signature_data">
            <input type="hidden" id="existing_bailleur_signature" value="<?php echo htmlspecialchars($etat['signature_bailleur'] ?? ''); ?>">
            <input type="hidden" id="existing_locataire_signature" value="<?php echo htmlspecialchars($etat['signature_locataire'] ?? ''); ?>">
            
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
                        <label class="form-label required-field">Locataire(s)</label>
                        <input type="text" name="locataire_nom_complet" class="form-control" 
                               value="<?php echo htmlspecialchars($etat['locataire_nom_complet'] ?? ''); ?>" 
                               placeholder="Nom et prénom du ou des locataires" required>
                        <small class="text-muted">Pour un seul locataire. Pour plusieurs, utilisez la section ci-dessous.</small>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label required-field">Email du locataire</label>
                        <input type="email" name="locataire_email" class="form-control" 
                               value="<?php echo htmlspecialchars($etat['locataire_email'] ?? ''); ?>" 
                               placeholder="email@example.com" required>
                        <small class="text-muted">Le PDF sera envoyé à cette adresse</small>
                    </div>
                </div>
                
                <!-- Multi-tenant management section -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="bi bi-people"></i> 
                            <strong>Gestion multi-locataires</strong>
                            <p class="mb-0 mt-2">Si ce logement a plusieurs locataires, vous pouvez les ajouter ci-dessous. Chaque locataire pourra signer individuellement l'état des lieux.</p>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm mb-3" onclick="addTenant()">
                            <i class="bi bi-person-plus"></i> Ajouter un locataire
                        </button>
                        <div id="tenantsContainer">
                            <?php if (!empty($existing_tenants)): ?>
                                <?php foreach ($existing_tenants as $index => $tenant): ?>
                                <div class="card mb-3" id="tenant_<?php echo $index + 1; ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h6 class="mb-0"><i class="bi bi-person"></i> Locataire #<?php echo $index + 1; ?></h6>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeTenant(<?php echo $index + 1; ?>)">
                                                <i class="bi bi-trash"></i> Supprimer
                                            </button>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4 mb-2">
                                                <label class="form-label">Nom</label>
                                                <input type="text" name="tenants[<?php echo $index + 1; ?>][nom]" class="form-control" 
                                                       value="<?php echo htmlspecialchars($tenant['nom']); ?>" placeholder="Nom">
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <label class="form-label">Prénom</label>
                                                <input type="text" name="tenants[<?php echo $index + 1; ?>][prenom]" class="form-control" 
                                                       value="<?php echo htmlspecialchars($tenant['prenom']); ?>" placeholder="Prénom">
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <label class="form-label">Email</label>
                                                <input type="email" name="tenants[<?php echo $index + 1; ?>][email]" class="form-control" 
                                                       value="<?php echo htmlspecialchars($tenant['email']); ?>" placeholder="email@example.com">
                                            </div>
                                        </div>
                                        <div class="row mt-2">
                                            <div class="col-12">
                                                <label class="form-label">Signature</label>
                                                <?php if (!empty($tenant['signature_data'])): ?>
                                                    <div class="mb-2">
                                                        <img src="<?php echo htmlspecialchars($tenant['signature_data']); ?>" 
                                                             alt="Signature" style="max-width: 300px; border: 1px solid #dee2e6; padding: 5px;">
                                                        <p class="text-muted small mb-0">
                                                            Signé le <?php echo date('d/m/Y à H:i', strtotime($tenant['signature_timestamp'])); ?>
                                                        </p>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="signature-container" style="max-width: 300px;">
                                                    <canvas id="tenantCanvas_<?php echo $index + 1; ?>" width="300" height="150"></canvas>
                                                </div>
                                                <input type="hidden" name="tenants[<?php echo $index + 1; ?>][signature]" 
                                                       id="tenantSignature_<?php echo $index + 1; ?>" 
                                                       value="<?php echo htmlspecialchars($tenant['signature_data'] ?? ''); ?>">
                                                <button type="button" class="btn btn-warning btn-sm mt-2" onclick="clearTenantSignature(<?php echo $index + 1; ?>)">
                                                    <i class="bi bi-eraser"></i> Effacer signature
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
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
                    <div class="col-md-4 mb-3">
                        <label class="form-label required-field">Clés de l'appartement</label>
                        <input type="number" name="cles_appartement" class="form-control" 
                               value="<?php echo htmlspecialchars($etat['cles_appartement'] ?? ''); ?>" 
                               min="0" required oninput="calculateTotalKeys()">
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label required-field">Clé(s) de la boîte aux lettres</label>
                        <input type="number" name="cles_boite_lettres" class="form-control" 
                               value="<?php echo htmlspecialchars($etat['cles_boite_lettres'] ?? '1'); ?>" 
                               min="0" required oninput="calculateTotalKeys()">
                    </div>
                    
                    <div class="col-md-4 mb-3">
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
                        <textarea name="observations" class="form-control" rows="3"><?php echo htmlspecialchars($etat['observations'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="section-subtitle">Signature du bailleur</div>
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Veuillez signer dans le cadre ci-dessous :</label>
                        <div class="signature-container" style="max-width: 300px;">
                            <canvas id="signatureCanvasBailleur" width="300" height="150" style="background: transparent; border: none; outline: none; padding: 0;"></canvas>
                        </div>
                        <div class="mt-2">
                            <button type="button" class="btn btn-warning btn-sm" onclick="clearSignatureBailleur()">
                                <i class="bi bi-eraser"></i> Effacer
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="section-subtitle">Signature du locataire</div>
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Veuillez signer dans le cadre ci-dessous :</label>
                        <div class="signature-container" style="max-width: 300px;">
                            <canvas id="signatureCanvasLocataire" width="300" height="150" style="background: transparent; border: none; outline: none; padding: 0;"></canvas>
                        </div>
                        <div class="mt-2">
                            <button type="button" class="btn btn-warning btn-sm" onclick="clearSignatureLocataire()">
                                <i class="bi bi-eraser"></i> Effacer
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Les signatures sont capturées en format .jpg et seront incluses dans le PDF généré.
                </div>
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
        // Signature handling
        let canvasBailleur, ctxBailleur, isDrawingBailleur = false, lastXBailleur = 0, lastYBailleur = 0;
        let canvasLocataire, ctxLocataire, isDrawingLocataire = false, lastXLocataire = 0, lastYLocataire = 0;
        let tempCanvasBailleur, tempCtxBailleur, tempCanvasLocataire, tempCtxLocataire;
        
        function initSignatureBailleur() {
            canvasBailleur = document.getElementById('signatureCanvasBailleur');
            if (!canvasBailleur) return;
            
            ctxBailleur = canvasBailleur.getContext('2d');
            ctxBailleur.strokeStyle = '#000000';
            ctxBailleur.lineWidth = 2;
            ctxBailleur.lineCap = 'round';
            ctxBailleur.lineJoin = 'round';
            ctxBailleur.clearRect(0, 0, canvasBailleur.width, canvasBailleur.height);
            
            // Create temp canvas for JPEG conversion
            tempCanvasBailleur = document.createElement('canvas');
            tempCanvasBailleur.width = canvasBailleur.width;
            tempCanvasBailleur.height = canvasBailleur.height;
            tempCtxBailleur = tempCanvasBailleur.getContext('2d');
            
            // Load existing signature if available
            const existingSignature = document.getElementById('existing_bailleur_signature').value;
            if (existingSignature) {
                const img = new Image();
                img.onload = function() {
                    ctxBailleur.drawImage(img, 0, 0, canvasBailleur.width, canvasBailleur.height);
                };
                img.src = existingSignature;
            }
            
            // Mouse events
            canvasBailleur.addEventListener('mousedown', (e) => {
                isDrawingBailleur = true;
                const pos = getMousePos(canvasBailleur, e);
                lastXBailleur = pos.x;
                lastYBailleur = pos.y;
            });
            canvasBailleur.addEventListener('mousemove', (e) => {
                if (!isDrawingBailleur) return;
                e.preventDefault();
                const pos = getMousePos(canvasBailleur, e);
                ctxBailleur.beginPath();
                ctxBailleur.moveTo(lastXBailleur, lastYBailleur);
                ctxBailleur.lineTo(pos.x, pos.y);
                ctxBailleur.stroke();
                lastXBailleur = pos.x;
                lastYBailleur = pos.y;
            });
            canvasBailleur.addEventListener('mouseup', () => { isDrawingBailleur = false; });
            canvasBailleur.addEventListener('mouseout', () => { isDrawingBailleur = false; });
            
            // Touch events
            canvasBailleur.addEventListener('touchstart', (e) => {
                e.preventDefault();
                const touch = e.touches[0];
                const mouseEvent = new MouseEvent('mousedown', {
                    clientX: touch.clientX,
                    clientY: touch.clientY
                });
                canvasBailleur.dispatchEvent(mouseEvent);
            });
            canvasBailleur.addEventListener('touchmove', (e) => {
                e.preventDefault();
                const touch = e.touches[0];
                const mouseEvent = new MouseEvent('mousemove', {
                    clientX: touch.clientX,
                    clientY: touch.clientY
                });
                canvasBailleur.dispatchEvent(mouseEvent);
            });
            canvasBailleur.addEventListener('touchend', () => { isDrawingBailleur = false; });
        }
        
        function initSignatureLocataire() {
            canvasLocataire = document.getElementById('signatureCanvasLocataire');
            if (!canvasLocataire) return;
            
            ctxLocataire = canvasLocataire.getContext('2d');
            ctxLocataire.strokeStyle = '#000000';
            ctxLocataire.lineWidth = 2;
            ctxLocataire.lineCap = 'round';
            ctxLocataire.lineJoin = 'round';
            ctxLocataire.clearRect(0, 0, canvasLocataire.width, canvasLocataire.height);
            
            // Create temp canvas for JPEG conversion
            tempCanvasLocataire = document.createElement('canvas');
            tempCanvasLocataire.width = canvasLocataire.width;
            tempCanvasLocataire.height = canvasLocataire.height;
            tempCtxLocataire = tempCanvasLocataire.getContext('2d');
            
            // Load existing signature if available
            const existingSignature = document.getElementById('existing_locataire_signature').value;
            if (existingSignature) {
                const img = new Image();
                img.onload = function() {
                    ctxLocataire.drawImage(img, 0, 0, canvasLocataire.width, canvasLocataire.height);
                };
                img.src = existingSignature;
            }
            
            // Mouse events
            canvasLocataire.addEventListener('mousedown', (e) => {
                isDrawingLocataire = true;
                const pos = getMousePos(canvasLocataire, e);
                lastXLocataire = pos.x;
                lastYLocataire = pos.y;
            });
            canvasLocataire.addEventListener('mousemove', (e) => {
                if (!isDrawingLocataire) return;
                e.preventDefault();
                const pos = getMousePos(canvasLocataire, e);
                ctxLocataire.beginPath();
                ctxLocataire.moveTo(lastXLocataire, lastYLocataire);
                ctxLocataire.lineTo(pos.x, pos.y);
                ctxLocataire.stroke();
                lastXLocataire = pos.x;
                lastYLocataire = pos.y;
            });
            canvasLocataire.addEventListener('mouseup', () => { isDrawingLocataire = false; });
            canvasLocataire.addEventListener('mouseout', () => { isDrawingLocataire = false; });
            
            // Touch events
            canvasLocataire.addEventListener('touchstart', (e) => {
                e.preventDefault();
                const touch = e.touches[0];
                const mouseEvent = new MouseEvent('mousedown', {
                    clientX: touch.clientX,
                    clientY: touch.clientY
                });
                canvasLocataire.dispatchEvent(mouseEvent);
            });
            canvasLocataire.addEventListener('touchmove', (e) => {
                e.preventDefault();
                const touch = e.touches[0];
                const mouseEvent = new MouseEvent('mousemove', {
                    clientX: touch.clientX,
                    clientY: touch.clientY
                });
                canvasLocataire.dispatchEvent(mouseEvent);
            });
            canvasLocataire.addEventListener('touchend', () => { isDrawingLocataire = false; });
        }
        
        function getMousePos(canvas, e) {
            const rect = canvas.getBoundingClientRect();
            return {
                x: e.clientX - rect.left,
                y: e.clientY - rect.top
            };
        }
        
        function canvasToJPEG(canvas, tempCanvas, tempCtx) {
            // Fill with white background (JPEG doesn't support transparency)
            tempCtx.fillStyle = '#FFFFFF';
            tempCtx.fillRect(0, 0, tempCanvas.width, tempCanvas.height);
            // Draw signature on white background
            tempCtx.drawImage(canvas, 0, 0);
            // Convert to JPEG with 95% quality
            return tempCanvas.toDataURL('image/jpeg', 0.95);
        }
        
        function clearSignatureBailleur() {
            if (!ctxBailleur || !canvasBailleur) return;
            ctxBailleur.clearRect(0, 0, canvasBailleur.width, canvasBailleur.height);
            ctxBailleur.strokeStyle = '#000000';
            ctxBailleur.lineWidth = 2;
        }
        
        function clearSignatureLocataire() {
            if (!ctxLocataire || !canvasLocataire) return;
            ctxLocataire.clearRect(0, 0, canvasLocataire.width, canvasLocataire.height);
            ctxLocataire.strokeStyle = '#000000';
            ctxLocataire.lineWidth = 2;
        }
        
        // Calculate total keys
        function calculateTotalKeys() {
            const appart = parseInt(document.querySelector('[name="cles_appartement"]').value) || 0;
            const boite = parseInt(document.querySelector('[name="cles_boite_lettres"]').value) || 0;
            document.getElementById('cles_total').value = appart + boite;
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
        
        // Multi-tenant management
        let tenantCounter = 0;
        
        function addTenant() {
            tenantCounter++;
            const container = document.getElementById('tenantsContainer');
            const tenantDiv = document.createElement('div');
            tenantDiv.className = 'card mb-3';
            tenantDiv.id = `tenant_${tenantCounter}`;
            tenantDiv.innerHTML = `
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0"><i class="bi bi-person"></i> Locataire #${tenantCounter}</h6>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeTenant(${tenantCounter})">
                            <i class="bi bi-trash"></i> Supprimer
                        </button>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Nom</label>
                            <input type="text" name="tenants[${tenantCounter}][nom]" class="form-control" placeholder="Nom">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Prénom</label>
                            <input type="text" name="tenants[${tenantCounter}][prenom]" class="form-control" placeholder="Prénom">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Email</label>
                            <input type="email" name="tenants[${tenantCounter}][email]" class="form-control" placeholder="email@example.com">
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-12">
                            <label class="form-label">Signature</label>
                            <div class="signature-container" style="max-width: 300px;">
                                <canvas id="tenantCanvas_${tenantCounter}" width="300" height="150"></canvas>
                            </div>
                            <input type="hidden" name="tenants[${tenantCounter}][signature]" id="tenantSignature_${tenantCounter}">
                            <button type="button" class="btn btn-warning btn-sm mt-2" onclick="clearTenantSignature(${tenantCounter})">
                                <i class="bi bi-eraser"></i> Effacer signature
                            </button>
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(tenantDiv);
            
            // Initialize signature canvas for this tenant
            initTenantSignature(tenantCounter);
        }
        
        function removeTenant(id) {
            const tenant = document.getElementById(`tenant_${id}`);
            if (tenant) {
                tenant.remove();
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
            initSignatureBailleur();
            initSignatureLocataire();
            
            // Initialize existing tenant signatures
            <?php if (!empty($existing_tenants)): ?>
                <?php foreach ($existing_tenants as $index => $tenant): ?>
                    initTenantSignature(<?php echo $index + 1; ?>);
                    tenantCounter = Math.max(tenantCounter, <?php echo $index + 1; ?>);
                <?php endforeach; ?>
            <?php endif; ?>
        });
        
        // Handle form submission
        document.getElementById('etatLieuxForm').addEventListener('submit', function(e) {
            // Capture signatures before submission
            if (canvasBailleur && tempCanvasBailleur && tempCtxBailleur) {
                const bailleurSignature = canvasToJPEG(canvasBailleur, tempCanvasBailleur, tempCtxBailleur);
                document.getElementById('bailleur_signature_data').value = bailleurSignature;
            }
            
            if (canvasLocataire && tempCanvasLocataire && tempCtxLocataire) {
                const locataireSignature = canvasToJPEG(canvasLocataire, tempCanvasLocataire, tempCtxLocataire);
                document.getElementById('locataire_signature_data').value = locataireSignature;
            }
        });
    </script>
</body>
</html>
