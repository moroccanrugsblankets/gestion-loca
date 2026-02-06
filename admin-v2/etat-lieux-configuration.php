<?php
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

// Constants for file upload limits
define('MAX_SIGNATURE_SIZE', 2 * 1024 * 1024); // 2 MB

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_template') {
        // Check if parametres table exists and has etat_lieux_template_html
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM parametres WHERE cle = 'etat_lieux_template_html'");
        $stmt->execute();
        $exists = $stmt->fetchColumn() > 0;
        
        if ($exists) {
            // Update existing
            $stmt = $pdo->prepare("UPDATE parametres SET valeur = ?, updated_at = NOW() WHERE cle = 'etat_lieux_template_html'");
            $stmt->execute([$_POST['template_html']]);
        } else {
            // Insert new
            $stmt = $pdo->prepare("INSERT INTO parametres (cle, valeur, type, groupe, description) VALUES ('etat_lieux_template_html', ?, 'text', 'etats_lieux', 'Template HTML de l''état des lieux avec variables dynamiques')");
            $stmt->execute([$_POST['template_html']]);
        }
        
        $_SESSION['success'] = "Template d'état des lieux mis à jour avec succès";
        header('Location: etat-lieux-configuration.php');
        exit;
    }
    elseif ($_POST['action'] === 'upload_signature') {
        // Handle signature image upload (same as contracts)
        if (isset($_FILES['signature_image']) && $_FILES['signature_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['signature_image'];
            
            // Validate file type
            $allowed_types = ['image/png', 'image/jpeg', 'image/jpg'];
            if (!in_array($file['type'], $allowed_types)) {
                $_SESSION['error'] = "Format d'image non valide. Utilisez PNG ou JPEG.";
                header('Location: etat-lieux-configuration.php');
                exit;
            }
            
            // Validate file size (max 2MB)
            if ($file['size'] > MAX_SIGNATURE_SIZE) {
                $_SESSION['error'] = "La taille de l'image ne doit pas dépasser 2 MB.";
                header('Location: etat-lieux-configuration.php');
                exit;
            }
            
            // Read and resize image for optimal display
            $maxWidth = 600;
            $maxHeight = 300;
            
            // Create image resource from uploaded file
            $sourceImage = null;
            if ($file['type'] === 'image/png') {
                $sourceImage = imagecreatefrompng($file['tmp_name']);
            } elseif ($file['type'] === 'image/jpeg' || $file['type'] === 'image/jpg') {
                $sourceImage = imagecreatefromjpeg($file['tmp_name']);
            }
            
            if ($sourceImage === false || $sourceImage === null) {
                $_SESSION['error'] = "Impossible de traiter l'image. Veuillez réessayer avec un autre fichier.";
                header('Location: etat-lieux-configuration.php');
                exit;
            }
            
            // Get original dimensions
            $originalWidth = imagesx($sourceImage);
            $originalHeight = imagesy($sourceImage);
            
            // Validate dimensions
            if ($originalWidth < 10 || $originalHeight < 10) {
                imagedestroy($sourceImage);
                $_SESSION['error'] = "L'image téléchargée est trop petite. Taille minimum : 10x10 pixels.";
                header('Location: etat-lieux-configuration.php');
                exit;
            }
            
            // Calculate new dimensions maintaining aspect ratio
            $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
            
            // Only resize if image is larger than max dimensions
            if ($ratio < 1) {
                $newWidth = round($originalWidth * $ratio);
                $newHeight = round($originalHeight * $ratio);
            } else {
                $newWidth = $originalWidth;
                $newHeight = $originalHeight;
            }
            
            // Create new image with white background
            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
            $white = imagecolorallocate($resizedImage, 255, 255, 255);
            imagefilledrectangle($resizedImage, 0, 0, $newWidth, $newHeight, $white);
            
            // Resize the image
            imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
            
            // Save resized image data as JPEG
            ob_start();
            imagejpeg($resizedImage, null, 90);
            $imageData = ob_get_clean();
            
            // Clean up resources
            imagedestroy($sourceImage);
            imagedestroy($resizedImage);
            
            // Create uploads directory if it doesn't exist
            $baseDir = dirname(__DIR__);
            $uploadsDir = $baseDir . '/uploads/signatures';
            if (!is_dir($uploadsDir)) {
                if (!mkdir($uploadsDir, 0755, true)) {
                    $_SESSION['error'] = "Impossible de créer le répertoire des signatures";
                    header('Location: etat-lieux-configuration.php');
                    exit;
                }
            }
            
            // Delete old signature file if exists
            $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'signature_societe_etat_lieux_image'");
            $stmt->execute();
            $oldSignature = $stmt->fetchColumn();
            if (!empty($oldSignature) && strpos($oldSignature, 'data:') !== 0 && strpos($oldSignature, 'uploads/signatures/') !== false) {
                $oldFilePath = $baseDir . '/' . $oldSignature;
                if (file_exists($oldFilePath)) {
                    unlink($oldFilePath);
                    error_log("Deleted old etat lieux signature file: $oldFilePath");
                }
            }
            
            // Generate unique filename
            $filename = "etat_lieux_signature_" . time() . ".jpg";
            $filepath = $uploadsDir . '/' . $filename;
            
            // Save physical file
            if (file_put_contents($filepath, $imageData) === false) {
                $_SESSION['error'] = "Impossible de sauvegarder le fichier de signature";
                header('Location: etat-lieux-configuration.php');
                exit;
            }
            
            // Store relative path
            $relativePath = 'uploads/signatures/' . $filename;
            error_log("Etat lieux signature saved as physical file: $relativePath");
            
            // Update or insert signature parameter
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM parametres WHERE cle = 'signature_societe_etat_lieux_image'");
            $stmt->execute();
            $exists = $stmt->fetchColumn() > 0;
            
            if ($exists) {
                $stmt = $pdo->prepare("UPDATE parametres SET valeur = ?, updated_at = NOW() WHERE cle = 'signature_societe_etat_lieux_image'");
                $stmt->execute([$relativePath]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO parametres (cle, valeur, type, groupe, description) VALUES ('signature_societe_etat_lieux_image', ?, 'string', 'etats_lieux', 'Chemin du fichier de la signature électronique de la société pour états des lieux')");
                $stmt->execute([$relativePath]);
            }
            
            // Update enabled status
            $enabled = isset($_POST['signature_enabled']) ? 'true' : 'false';
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM parametres WHERE cle = 'signature_societe_etat_lieux_enabled'");
            $stmt->execute();
            $exists = $stmt->fetchColumn() > 0;
            
            if ($exists) {
                $stmt = $pdo->prepare("UPDATE parametres SET valeur = ?, updated_at = NOW() WHERE cle = 'signature_societe_etat_lieux_enabled'");
                $stmt->execute([$enabled]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO parametres (cle, valeur, type, groupe, description) VALUES ('signature_societe_etat_lieux_enabled', ?, 'boolean', 'etats_lieux', 'Activer l''ajout automatique de la signature société pour états des lieux')");
                $stmt->execute([$enabled]);
            }
            
            $_SESSION['success'] = "Signature de la société pour états des lieux mise à jour avec succès";
        } else {
            $_SESSION['error'] = "Erreur lors du téléchargement de l'image";
        }
        header('Location: etat-lieux-configuration.php');
        exit;
    }
    elseif ($_POST['action'] === 'delete_signature') {
        // Get current signature path and delete physical file if exists
        $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'signature_societe_etat_lieux_image'");
        $stmt->execute();
        $signaturePath = $stmt->fetchColumn();
        
        if (!empty($signaturePath)) {
            if (strpos($signaturePath, 'data:') !== 0 && strpos($signaturePath, 'uploads/signatures/') !== false) {
                $baseDir = dirname(__DIR__);
                $filepath = $baseDir . '/' . $signaturePath;
                if (file_exists($filepath)) {
                    unlink($filepath);
                    error_log("Deleted etat lieux signature file: $filepath");
                }
            }
        }
        
        // Delete signature reference from database
        $stmt = $pdo->prepare("UPDATE parametres SET valeur = '', updated_at = NOW() WHERE cle = 'signature_societe_etat_lieux_image'");
        $stmt->execute();
        
        $_SESSION['success'] = "Signature supprimée avec succès";
        header('Location: etat-lieux-configuration.php');
        exit;
    }
}

// Get current template
$stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'etat_lieux_template_html'");
$stmt->execute();
$template = $stmt->fetchColumn();

// If no template exists, create a default one
if (!$template) {
    $template = getDefaultEtatLieuxTemplate();
}

// Get signature settings
$stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'signature_societe_etat_lieux_image'");
$stmt->execute();
$signatureImage = $stmt->fetchColumn() ?: '';
if (!empty($signatureImage) && 
    strpos($signatureImage, 'data:') !== 0 && 
    strpos($signatureImage, 'http://') !== 0 && 
    strpos($signatureImage, 'https://') !== 0 && 
    strpos($signatureImage, '//') !== 0 && 
    strpos($signatureImage, '/') !== 0) {
    $signatureImage = '/' . $signatureImage;
}

$stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'signature_societe_etat_lieux_enabled'");
$stmt->execute();
$signatureEnabled = $stmt->fetchColumn() === 'true';

function getDefaultEtatLieuxTemplate() {
    return <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>État des lieux {{type}} - {{reference}}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 9.5pt;
            line-height: 1.4;
            color: #000;
            margin: 0;
            padding: 15px;
        }
        h1 {
            text-align: center;
            font-size: 13pt;
            margin-bottom: 8px;
            font-weight: bold;
        }
        h2 {
            font-size: 11pt;
            margin-top: 12px;
            margin-bottom: 6px;
            font-weight: bold;
            border-bottom: 1px solid #333;
            padding-bottom: 3px;
        }
        h3 {
            font-size: 10pt;
            margin-top: 10px;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
        }
        .subtitle {
            text-align: center;
            font-style: italic;
            margin-bottom: 20px;
        }
        p {
            margin: 4px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
        }
        table td {
            padding: 4px 6px;
            vertical-align: top;
        }
        .info-label {
            font-weight: bold;
            width: 35%;
        }
        .observations {
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .signatures-section {
            margin-top: 20px;
        }
        .signature-block {
            display: inline-block;
            width: 48%;
            vertical-align: top;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>MY INVEST IMMOBILIER</h1>
    </div>
    
    <div class="subtitle">
        ÉTAT DES LIEUX {{type_label}}<br>
        Référence : {{reference}}
    </div>

    <h2>1. Informations générales</h2>
    
    <table>
        <tr>
            <td class="info-label">Date de l'état des lieux :</td>
            <td>{{date_etat}}</td>
        </tr>
        <tr>
            <td class="info-label">Type :</td>
            <td>{{type_label}}</td>
        </tr>
    </table>

    <h2>2. Bien loué</h2>
    
    <table>
        <tr>
            <td class="info-label">Adresse :</td>
            <td>{{adresse}}</td>
        </tr>
        {{appartement_row}}
        <tr>
            <td class="info-label">Type de logement :</td>
            <td>{{type_logement}}</td>
        </tr>
        <tr>
            <td class="info-label">Surface :</td>
            <td>{{surface}} m²</td>
        </tr>
    </table>

    <h2>3. Parties</h2>
    
    <h3>Bailleur</h3>
    <table>
        <tr>
            <td class="info-label">Nom :</td>
            <td>{{bailleur_nom}}</td>
        </tr>
        {{bailleur_representant_row}}
    </table>
    
    <h3>Locataire(s)</h3>
    <table>
        {{locataires_info}}
    </table>

    <h2>4. Description de l'état du logement</h2>
    
    <h3>Pièce principale</h3>
    <p class="observations">{{piece_principale}}</p>
    
    <h3>Coin cuisine</h3>
    <p class="observations">{{coin_cuisine}}</p>
    
    <h3>Salle d'eau / WC</h3>
    <p class="observations">{{salle_eau_wc}}</p>
    
    <h3>État général</h3>
    <p class="observations">{{etat_general}}</p>
    
    {{observations_section}}

    <h2>5. Signatures</h2>
    
    <p>Fait à {{lieu_signature}}, le {{date_signature}}</p>
    
    <div class="signatures-section">
        {{signatures_table}}
    </div>

    <div style="margin-top: 30px; font-size: 8pt; text-align: center; color: #666;">
        <p>Document généré électroniquement par MY Invest Immobilier</p>
        <p>État des lieux - Référence : {{reference}}</p>
    </div>
</body>
</html>
HTML;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration État des Lieux - My Invest Immobilier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- TinyMCE Cloud - API key is public and domain-restricted -->
    <script src="https://cdn.tiny.cloud/1/odjqanpgdv2zolpduplee65ntoou1b56hg6gvgxvrt8dreh0/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <?php require_once __DIR__ . '/includes/sidebar-styles.php'; ?>
    <style>
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .config-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .variables-info {
            background: #e8f4f8;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .variables-info h6 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-weight: 600;
        }
        .variable-tag {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            margin: 3px;
            font-family: 'Courier New', monospace;
            cursor: pointer;
            transition: background 0.2s;
        }
        .variable-tag:hover {
            background: #2980b9;
        }
        .code-editor {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            min-height: 500px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
        }
        .preview-section {
            max-height: 600px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            padding: 20px;
            background: white;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/menu.php'; ?>
    
    <div class="main-content">
        <div class="header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0"><i class="bi bi-file-earmark-text"></i> Configuration du Template d'État des Lieux</h1>
                    <p class="text-muted mb-0">Personnalisez le template HTML de l'état des lieux avec des variables dynamiques</p>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($_SESSION['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($_SESSION['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Signature Configuration Card -->
        <div class="config-card">
            <h5 class="mb-3"><i class="bi bi-pen"></i> Signature Électronique de la Société (États des Lieux)</h5>
            <p class="text-muted">
                Téléchargez l'image de la signature de la société qui sera automatiquement ajoutée aux états des lieux.
            </p>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_signature">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="signature_image" class="form-label">
                                <strong>Image de la signature</strong>
                            </label>
                            <input 
                                type="file" 
                                class="form-control" 
                                id="signature_image" 
                                name="signature_image" 
                                accept="image/png,image/jpeg,image/jpg"
                                <?php echo empty($signatureImage) ? 'required' : ''; ?>>
                            <small class="form-text text-muted">
                                Formats acceptés : PNG, JPEG. Taille maximum : 2 MB. Recommandation : fond transparent (PNG).
                            </small>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input 
                                type="checkbox" 
                                class="form-check-input" 
                                id="signature_enabled" 
                                name="signature_enabled"
                                <?php echo $signatureEnabled ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="signature_enabled">
                                Activer l'ajout automatique de la signature sur les états des lieux
                            </label>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-upload"></i> Télécharger la signature
                            </button>
                            <?php if (!empty($signatureImage)): ?>
                                <button type="button" class="btn btn-outline-danger" onclick="deleteSignature()">
                                    <i class="bi bi-trash"></i> Supprimer
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <?php if (!empty($signatureImage)): ?>
                            <div class="mb-3">
                                <label class="form-label"><strong>Aperçu actuel</strong></label>
                                <div class="border rounded p-3 bg-light text-center" style="min-height: 150px; display: flex; align-items: center; justify-content: center;">
                                    <img src="<?= htmlspecialchars($signatureImage) ?>" 
                                         alt="Signature de la société" 
                                         style="max-width: 100%; max-height: 250px; width: auto; height: auto; object-fit: contain;">
                                </div>
                                <small class="text-muted d-block mt-2">
                                    <i class="bi bi-info-circle"></i> Cette signature sera ajoutée automatiquement au PDF de l'état des lieux.
                                </small>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i> 
                                <strong>Aucune signature configurée</strong><br>
                                Téléchargez une image de signature pour l'utiliser dans les états des lieux.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Template Configuration Card -->
        <div class="config-card">
            <div class="variables-info">
                <h6><i class="bi bi-info-circle"></i> Variables disponibles</h6>
                <p class="mb-2">Cliquez sur une variable pour la copier. Utilisez ces variables dans le template HTML :</p>
                <div>
                    <span class="variable-tag" onclick="copyVariable('{{reference}}')">{{reference}}</span>
                    <span class="variable-tag" onclick="copyVariable('{{type}}')">{{type}}</span>
                    <span class="variable-tag" onclick="copyVariable('{{type_label}}')">{{type_label}}</span>
                    <span class="variable-tag" onclick="copyVariable('{{date_etat}}')">{{date_etat}}</span>
                    <span class="variable-tag" onclick="copyVariable('{{adresse}}')">{{adresse}}</span>
                    <span class="variable-tag" onclick="copyVariable('{{appartement}}')">{{appartement}}</span>
                    <span class="variable-tag" onclick="copyVariable('{{type_logement}}')">{{type_logement}}</span>
                    <span class="variable-tag" onclick="copyVariable('{{surface}}')">{{surface}}</span>
                    <span class="variable-tag" onclick="copyVariable('{{bailleur_nom}}')">{{bailleur_nom}}</span>
                    <span class="variable-tag" onclick="copyVariable('{{bailleur_representant}}')">{{bailleur_representant}}</span>
                    <span class="variable-tag" onclick="copyVariable('{{locataires_info}}')">{{locataires_info}}</span>
                    <span class="variable-tag" onclick="copyVariable('{{piece_principale}}')">{{piece_principale}}</span>
                    <span class="variable-tag" onclick="copyVariable('{{coin_cuisine}}')">{{coin_cuisine}}</span>
                    <span class="variable-tag" onclick="copyVariable('{{salle_eau_wc}}')">{{salle_eau_wc}}</span>
                    <span class="variable-tag" onclick="copyVariable('{{etat_general}}')">{{etat_general}}</span>
                    <span class="variable-tag" onclick="copyVariable('{{observations}}')">{{observations}}</span>
                    <span class="variable-tag" onclick="copyVariable('{{lieu_signature}}')">{{lieu_signature}}</span>
                    <span class="variable-tag" onclick="copyVariable('{{date_signature}}')">{{date_signature}}</span>
                    <span class="variable-tag" onclick="copyVariable('{{signatures_table}}')">{{signatures_table}}</span>
                    <span class="variable-tag" onclick="copyVariable('{{signature_agence}}')">{{signature_agence}}</span>
                </div>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="action" value="update_template">
                
                <div class="mb-3">
                    <label for="template_html" class="form-label">
                        <strong>Template HTML de l'État des Lieux</strong>
                    </label>
                    <textarea 
                        class="form-control code-editor" 
                        id="template_html" 
                        name="template_html" 
                        required><?= htmlspecialchars($template) ?></textarea>
                    <small class="form-text text-muted">
                        Modifiez le code HTML ci-dessus. Les variables seront remplacées automatiquement lors de la génération de l'état des lieux.
                    </small>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Enregistrer le Template
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="showPreview()">
                        <i class="bi bi-eye"></i> Prévisualiser
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="resetToDefault()">
                        <i class="bi bi-arrow-counterclockwise"></i> Réinitialiser par défaut
                    </button>
                </div>
            </form>
        </div>

        <div class="config-card" id="preview-card" style="display: none;">
            <h5><i class="bi bi-eye"></i> Prévisualisation</h5>
            <div class="preview-section" id="preview-content"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize TinyMCE
        tinymce.init({
            selector: '#template_html',
            height: 500,
            plugins: 'code preview searchreplace autolink directionality visualblocks visualchars fullscreen link table charmap hr pagebreak nonbreaking anchor insertdatetime advlist lists wordcount help charmap',
            toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | code preview | help',
            content_style: 'body { font-family: Arial, sans-serif; font-size: 10pt; }',
            menubar: false
        });

        function copyVariable(variable) {
            navigator.clipboard.writeText(variable).then(() => {
                // Show a small tooltip or notification
                const tooltip = document.createElement('div');
                tooltip.textContent = 'Copié !';
                tooltip.style.position = 'fixed';
                tooltip.style.top = '50%';
                tooltip.style.left = '50%';
                tooltip.style.transform = 'translate(-50%, -50%)';
                tooltip.style.background = '#28a745';
                tooltip.style.color = 'white';
                tooltip.style.padding = '10px 20px';
                tooltip.style.borderRadius = '5px';
                tooltip.style.zIndex = '10000';
                document.body.appendChild(tooltip);
                
                setTimeout(() => {
                    document.body.removeChild(tooltip);
                }, 1000);
            });
        }

        function showPreview() {
            const content = tinymce.get('template_html').getContent();
            document.getElementById('preview-content').innerHTML = content;
            document.getElementById('preview-card').style.display = 'block';
            document.getElementById('preview-card').scrollIntoView({ behavior: 'smooth' });
        }

        function resetToDefault() {
            if (confirm('Êtes-vous sûr de vouloir réinitialiser le template avec la version par défaut ? Toutes vos modifications seront perdues.')) {
                // Reload the page with a reset parameter
                window.location.href = 'etat-lieux-configuration.php?reset=1';
            }
        }

        function deleteSignature() {
            if (confirm('Êtes-vous sûr de vouloir supprimer la signature ?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'action';
                input.value = 'delete_signature';
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Handle reset parameter
        <?php if (isset($_GET['reset'])): ?>
            // Set template to default
            const defaultTemplate = <?= json_encode(getDefaultEtatLieuxTemplate()) ?>;
            tinymce.get('template_html').setContent(defaultTemplate);
        <?php endif; ?>
    </script>
</body>
</html>
