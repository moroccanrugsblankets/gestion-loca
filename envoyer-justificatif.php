<?php
/**
 * Upload justificatif de paiement
 * My Invest Immobilier
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mail-templates.php';

// Récupérer le token
$token = $_GET['token'] ?? '';

if (empty($token)) {
    die('Token manquant. Veuillez utiliser le lien fourni dans votre email.');
}

// Récupérer le contrat
$contrat = getContractByToken($token);

if (!$contrat) {
    die('Contrat non trouvé. Le lien est invalide.');
}

// Vérifier que le contrat est signé ou validé
if ($contrat['statut'] !== 'signe' && $contrat['statut'] !== 'valide') {
    die('Ce lien n\'est pas disponible. Le contrat doit être signé ou validé.');
}

$error = '';
$success = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Token CSRF invalide.';
    } else {
        // Vérifier le fichier uploadé
        $justificatifFile = $_FILES['justificatif'] ?? null;
        
        if (!$justificatifFile || $justificatifFile['error'] === UPLOAD_ERR_NO_FILE) {
            $error = 'Veuillez télécharger votre justificatif de paiement.';
        } else {
            // Valider le fichier
            $validation = validateUploadedFile($justificatifFile);
            if (!$validation['success']) {
                $error = $validation['error'];
            } else {
                // Sauvegarder le fichier
                if (saveUploadedFile($justificatifFile, $validation['filename'])) {
                    // Mettre à jour le contrat
                    $stmt = $pdo->prepare("
                        UPDATE contrats 
                        SET justificatif_paiement = ?, 
                            date_envoi_justificatif = NOW()
                        WHERE id = ?
                    ");
                    
                    if ($stmt->execute([$validation['filename'], $contrat['id']])) {
                        logAction($contrat['id'], 'justificatif_paiement_recu', 'Justificatif de paiement uploadé');
                        
                        // Récupérer les informations des locataires
                        $locataires = getTenantsByContract($contrat['id']);
                        
                        if (!empty($locataires)) {
                            // Préparer la liste des locataires
                            $locatairesNoms = array_map(function($loc) {
                                return $loc['prenom'] . ' ' . $loc['nom'];
                            }, $locataires);
                            $locatairesStr = implode(', ', $locatairesNoms);
                            
                            // Construire le lien admin vers le contrat
                            $lienAdmin = $config['SITE_URL'] . '/admin-v2/contrat-detail.php?id=' . $contrat['id'];
                            
                            // Préparer les variables pour l'email admin
                            $adminVariables = [
                                'reference' => $contrat['reference_unique'],
                                'logement' => $contrat['adresse'],
                                'locataires' => $locatairesStr,
                                'date_envoi' => date('d/m/Y à H:i'),
                                'lien_admin' => $lienAdmin
                            ];
                            
                            // Envoyer l'email de notification aux administrateurs
                            sendTemplatedEmail('notification_justificatif_paiement_admin', getAdminEmail(), $adminVariables, null, true);
                        }
                        
                        $success = true;
                    } else {
                        $error = 'Erreur lors de l\'enregistrement des informations.';
                    }
                } else {
                    $error = 'Erreur lors de la sauvegarde du fichier.';
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
    <title>Envoi du justificatif de paiement - My Invest Immobilier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="text-center mb-4">
            <img src="assets/images/logo.png" alt="My Invest Immobilier" class="logo mb-3" 
                 onerror="this.style.display='none'">
            <h1 class="h2">Envoi du justificatif de paiement</h1>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <?php if ($success): ?>
                    <div class="card shadow border-success">
                        <div class="card-body text-center p-5">
                            <div class="mb-4">
                                <svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" fill="currentColor" 
                                     class="bi bi-check-circle-fill text-success" viewBox="0 0 16 16">
                                    <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
                                </svg>
                            </div>

                            <h2 class="text-success mb-4">Justificatif envoyé avec succès !</h2>
                            
                            <p class="lead mb-4">
                                Votre justificatif de virement a été transmis à notre équipe.
                            </p>

                            <div class="alert alert-info text-start">
                                <h5>Prochaines étapes :</h5>
                                <ol class="mb-0">
                                    <li class="mb-2">Notre équipe va vérifier votre justificatif de paiement</li>
                                    <li class="mb-2">Vous recevrez une confirmation par email une fois la vérification effectuée</li>
                                    <li class="mb-2">La prise d'effet du bail et la remise des clés seront organisées</li>
                                </ol>
                            </div>

                            <p class="mt-4">
                                Pour toute question, n'hésitez pas à nous contacter :
                            </p>
                            <p>
                                <strong><?= $config['COMPANY_NAME'] ?></strong><br>
                                Email : <a href="mailto:<?= $config['COMPANY_EMAIL'] ?>"><?= $config['COMPANY_EMAIL'] ?></a>
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card shadow">
                        <div class="card-body">
                            <h4 class="card-title mb-3">
                                Justificatif de virement du dépôt de garantie
                            </h4>

                            <div class="alert alert-info mb-4">
                                <p class="mb-2">
                                    <strong>📋 Référence du contrat :</strong> <?= htmlspecialchars($contrat['reference_unique']) ?>
                                </p>
                                <p class="mb-0">
                                    Merci de transmettre le justificatif de virement du dépôt de garantie (capture d'écran ou PDF de la confirmation bancaire).
                                </p>
                            </div>

                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                            <?php endif; ?>

                            <form method="POST" action="" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                
                                <div class="mb-3">
                                    <label for="justificatif" class="form-label">
                                        Justificatif de virement *
                                    </label>
                                    <input type="file" class="form-control" id="justificatif" name="justificatif" 
                                           accept=".jpg,.jpeg,.png,.pdf" required>
                                    <small class="form-text text-muted">
                                        Formats acceptés : JPG, PNG, PDF - Taille max : 5 Mo
                                    </small>
                                </div>

                                <div class="alert alert-warning">
                                    <h5>Informations importantes :</h5>
                                    <ul class="mb-0">
                                        <li>Le justificatif doit clairement montrer le virement effectué</li>
                                        <li>Le montant doit correspondre au dépôt de garantie demandé</li>
                                        <li>La date du virement doit être visible</li>
                                    </ul>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        Envoyer le justificatif →
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
