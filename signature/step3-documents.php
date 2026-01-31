<?php
/**
 * Signature - Étape 3 : Upload des documents
 * My Invest Immobilier
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mail-templates.php';

// Vérifier la session
if (!isset($_SESSION['signature_token']) || !isset($_SESSION['contrat_id']) || !isset($_SESSION['current_locataire_id'])) {
    die('Session invalide. Veuillez recommencer la procédure.');
}

$contratId = $_SESSION['contrat_id'];
$locataireId = $_SESSION['current_locataire_id'];
$numeroLocataire = $_SESSION['current_locataire_numero'];

$contrat = fetchOne("SELECT c.*, l.* FROM contrats c INNER JOIN logements l ON c.logement_id = l.id WHERE c.id = ?", [$contratId]);

if (!$contrat || !isContractValid($contrat)) {
    die('Contrat invalide ou expiré.');
}

$error = '';
$success = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Token CSRF invalide.';
    } else {
        $secondLocataire = $_POST['second_locataire'] ?? '';
        
        // Vérifier les fichiers uploadés
        $rectoFile = $_FILES['piece_recto'] ?? null;
        $versoFile = $_FILES['piece_verso'] ?? null;
        
        if (!$rectoFile || $rectoFile['error'] === UPLOAD_ERR_NO_FILE) {
            $error = 'Veuillez télécharger la pièce d\'identité recto.';
        } elseif (!$versoFile || $versoFile['error'] === UPLOAD_ERR_NO_FILE) {
            $error = 'Veuillez télécharger la pièce d\'identité verso.';
        } else {
            // Valider le fichier recto
            $rectoValidation = validateUploadedFile($rectoFile);
            if (!$rectoValidation['success']) {
                $error = 'Recto : ' . $rectoValidation['error'];
            } else {
                // Valider le fichier verso
                $versoValidation = validateUploadedFile($versoFile);
                if (!$versoValidation['success']) {
                    $error = 'Verso : ' . $versoValidation['error'];
                } else {
                    // Sauvegarder les fichiers
                    if (saveUploadedFile($rectoFile, $rectoValidation['filename']) && 
                        saveUploadedFile($versoFile, $versoValidation['filename'])) {
                        
                        // Mettre à jour le locataire
                        if (updateTenantDocuments($locataireId, $rectoValidation['filename'], $versoValidation['filename'])) {
                            logAction($contratId, 'upload_documents', "Locataire $numeroLocataire a uploadé ses documents");
                            
                            // Vérifier s'il y a un second locataire
                            if ($secondLocataire === 'oui' && $numeroLocataire < $contrat['nb_locataires']) {
                                // Retour au step1 pour le second locataire
                                unset($_SESSION['current_locataire_id']);
                                unset($_SESSION['current_locataire_numero']);
                                header('Location: step1-info.php');
                                exit;
                            } else {
                                // Finaliser le contrat
                                finalizeContract($contratId);
                                
                                // Générer le PDF
                                require_once __DIR__ . '/../pdf/generate-bail.php';
                                $pdfPath = generateBailPDF($contratId);
                                
                                // Envoyer l'email de finalisation aux locataires
                                $locataires = getTenantsByContract($contratId);
                                foreach ($locataires as $locataire) {
                                    $emailData = getFinalisationEmailTemplate($contrat, $contrat, $locataires);
                                    // Send with PDF attachment
                                    sendEmail($locataire['email'], $emailData['subject'], $emailData['body'], $pdfPath, true, false);
                                }
                                
                                // Envoyer une copie aux administrateurs avec le PDF
                                if ($pdfPath && file_exists($pdfPath)) {
                                    $adminEmailData = getFinalisationEmailTemplate($contrat, $contrat, $locataires);
                                    $adminSubject = "[ADMIN] Contrat signé - " . $contrat['reference_unique'];
                                    $adminBody = "Un contrat a été signé.\n\n" . $adminEmailData['body'];
                                    
                                    // Send to first locataire with admin CC (isAdminEmail = true)
                                    if (!empty($locataires)) {
                                        sendEmail($locataires[0]['email'], $adminSubject, $adminBody, $pdfPath, true, true);
                                    }
                                }
                                
                                logAction($contratId, 'finalisation_contrat', 'Contrat finalisé et emails envoyés');
                                
                                // Rediriger vers la confirmation
                                header('Location: confirmation.php');
                                exit;
                            }
                        } else {
                            $error = 'Erreur lors de l\'enregistrement des documents.';
                        }
                    } else {
                        $error = 'Erreur lors de la sauvegarde des fichiers.';
                    }
                }
            }
        }
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documents d'identité - MY Invest Immobilier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="text-center mb-4">
            <img src="../assets/images/logo.png" alt="MY Invest Immobilier" class="logo mb-3" 
                 onerror="this.style.display='none'">
            <h1 class="h2">Documents d'identité</h1>
        </div>

        <!-- Barre de progression -->
        <div class="mb-4">
            <div class="progress" style="height: 30px;">
                <div class="progress-bar bg-success" role="progressbar" style="width: 100%;">
                    Étape 3/3 - Documents
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-body">
                        <h4 class="card-title mb-4">
                            Upload de la pièce d'identité (Locataire <?= $numeroLocataire ?>)
                        </h4>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>

                        <form method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            
                            <div class="mb-3">
                                <label for="piece_recto" class="form-label">
                                    Pièce d'identité - Recto *
                                </label>
                                <input type="file" class="form-control" id="piece_recto" name="piece_recto" 
                                       accept=".jpg,.jpeg,.png,.pdf" required>
                                <small class="form-text text-muted">
                                    Formats acceptés : JPG, PNG, PDF - Taille max : 5 Mo
                                </small>
                            </div>

                            <div class="mb-3">
                                <label for="piece_verso" class="form-label">
                                    Pièce d'identité - Verso *
                                </label>
                                <input type="file" class="form-control" id="piece_verso" name="piece_verso" 
                                       accept=".jpg,.jpeg,.png,.pdf" required>
                                <small class="form-text text-muted">
                                    Formats acceptés : JPG, PNG, PDF - Taille max : 5 Mo
                                </small>
                            </div>

                            <?php if ($numeroLocataire === 1 && $contrat['nb_locataires'] > 1): ?>
                                <div class="mb-4">
                                    <label class="form-label">Y a-t-il un second locataire ? *</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="second_locataire" 
                                               id="second_oui" value="oui" required>
                                        <label class="form-check-label" for="second_oui">
                                            Oui
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="second_locataire" 
                                               id="second_non" value="non">
                                        <label class="form-check-label" for="second_non">
                                            Non
                                        </label>
                                    </div>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="second_locataire" value="non">
                            <?php endif; ?>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    Finaliser →
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
