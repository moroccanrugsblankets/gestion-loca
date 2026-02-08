<?php
/**
 * Edit Inventaire - Simplified version
 * Allows editing equipment status and observations
 */
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

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
        
        // Update equipment data
        $equipements_data = [];
        if (isset($_POST['equipement'])) {
            foreach ($_POST['equipement'] as $eq_id => $eq_data) {
                $equipements_data[] = [
                    'equipement_id' => $eq_id,
                    'nom' => $eq_data['nom'] ?? '',
                    'categorie' => $eq_data['categorie'] ?? '',
                    'description' => $eq_data['description'] ?? '',
                    'quantite_attendue' => (int)($eq_data['quantite_attendue'] ?? 0),
                    'quantite_presente' => isset($eq_data['quantite_presente']) ? (int)$eq_data['quantite_presente'] : null,
                    'etat' => $eq_data['etat'] ?? null,
                    'observations' => $eq_data['observations'] ?? null,
                    'photos' => []
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
                // Update certifie_exact status
                $certifieExact = isset($tenantInfo['certifie_exact']) ? 1 : 0;
                
                $stmt = $pdo->prepare("
                    UPDATE inventaire_locataires 
                    SET certifie_exact = ?
                    WHERE id = ? AND inventaire_id = ?
                ");
                $stmt->execute([$certifieExact, $tenantId, $inventaire_id]);
                
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
        header("Location: edit-inventaire.php?id=$inventaire_id");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Erreur lors de la mise à jour: " . $e->getMessage();
        error_log("Erreur update inventaire: " . $e->getMessage());
    }
}

// Decode equipment data
$equipements_data = json_decode($inventaire['equipements_data'], true);
if (!is_array($equipements_data)) {
    $equipements_data = [];
}

// Group by category
$equipements_by_category = [];
foreach ($equipements_data as $eq) {
    $cat = $eq['categorie'] ?? 'Autre';
    if (!isset($equipements_by_category[$cat])) {
        $equipements_by_category[$cat] = [];
    }
    $equipements_by_category[$cat][] = $eq;
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
    
    // Insert tenants into inventaire_locataires
    $insertStmt = $pdo->prepare("
        INSERT INTO inventaire_locataires (inventaire_id, locataire_id, nom, prenom, email)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($contract_tenants as $tenant) {
        $insertStmt->execute([
            $inventaire_id,
            $tenant['id'],
            $tenant['nom'],
            $tenant['prenom'],
            $tenant['email']
        ]);
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
            border-left: 4px solid #0d6efd;
            padding-left: 15px;
        }
        .equipment-row {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 10px;
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
        .signature-container {
            border: 2px solid #000000;
            border-radius: 4px;
            display: inline-block;
            background: white;
            margin-bottom: 10px;
        }
        .signature-container canvas {
            display: block;
            cursor: crosshair;
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

        <form method="POST">
            <?php foreach ($equipements_by_category as $categorie => $equipements): ?>
                <div class="form-card">
                    <div class="category-section">
                        <h5><i class="bi bi-box-seam"></i> <?php echo htmlspecialchars($categorie); ?></h5>
                        
                        <?php foreach ($equipements as $eq): 
                            $eq_id = $eq['equipement_id'];
                        ?>
                            <div class="equipment-row">
                                <div class="row align-items-center">
                                    <div class="col-md-4">
                                        <strong><?php echo htmlspecialchars($eq['nom']); ?></strong>
                                        <?php if (!empty($eq['description'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($eq['description']); ?></small>
                                        <?php endif; ?>
                                        <input type="hidden" name="equipement[<?php echo $eq_id; ?>][nom]" value="<?php echo htmlspecialchars($eq['nom']); ?>">
                                        <input type="hidden" name="equipement[<?php echo $eq_id; ?>][categorie]" value="<?php echo htmlspecialchars($categorie); ?>">
                                        <input type="hidden" name="equipement[<?php echo $eq_id; ?>][description]" value="<?php echo htmlspecialchars($eq['description']); ?>">
                                        <input type="hidden" name="equipement[<?php echo $eq_id; ?>][quantite_attendue]" value="<?php echo $eq['quantite_attendue']; ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">Qté attendue</label>
                                        <input type="number" class="form-control form-control-sm" value="<?php echo $eq['quantite_attendue']; ?>" disabled>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">Qté présente</label>
                                        <input type="number" name="equipement[<?php echo $eq_id; ?>][quantite_presente]" 
                                               class="form-control form-control-sm" 
                                               value="<?php echo $eq['quantite_presente'] ?? ''; ?>" min="0">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">État</label>
                                        <select name="equipement[<?php echo $eq_id; ?>][etat]" class="form-select form-select-sm">
                                            <option value="">-</option>
                                            <option value="Bon" <?php echo ($eq['etat'] ?? '') === 'Bon' ? 'selected' : ''; ?>>Bon</option>
                                            <option value="Moyen" <?php echo ($eq['etat'] ?? '') === 'Moyen' ? 'selected' : ''; ?>>Moyen</option>
                                            <option value="Mauvais" <?php echo ($eq['etat'] ?? '') === 'Mauvais' ? 'selected' : ''; ?>>Mauvais</option>
                                            <option value="Manquant" <?php echo ($eq['etat'] ?? '') === 'Manquant' ? 'selected' : ''; ?>>Manquant</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">Observations</label>
                                        <input type="text" name="equipement[<?php echo $eq_id; ?>][observations]" 
                                               class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars($eq['observations'] ?? ''); ?>" 
                                               placeholder="Notes...">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
                <div class="section-subtitle">
                    Signature locataire <?php echo $index + 1; ?> - <?php echo htmlspecialchars($tenant['prenom'] . ' ' . $tenant['nom']); ?>
                </div>
                <div class="row mb-4">
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
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between">
                <a href="inventaires.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Annuler
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Enregistrer
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Configuration
        const SIGNATURE_JPEG_QUALITY = 0.95;
        
        // Initialize tenant signature canvases on page load
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($existing_tenants as $tenant): ?>
            initTenantSignature(<?php echo $tenant['id']; ?>);
            <?php endforeach; ?>
        });
        
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
            const signatureData = tempCanvas.toDataURL('image/jpeg', SIGNATURE_JPEG_QUALITY);
            document.getElementById(`tenantSignature_${id}`).value = signatureData;
        }
        
        function clearTenantSignature(id) {
            const canvas = document.getElementById(`tenantCanvas_${id}`);
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            document.getElementById(`tenantSignature_${id}`).value = '';
        }
    </script>
</body>
</html>
