<?php
/**
 * Signature - Étape 3 : Versement du dépôt de garantie
 * My Invest Immobilier
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Vérifier la session
if (!isset($_SESSION['signature_token']) || !isset($_SESSION['contrat_id']) || !isset($_SESSION['current_locataire_id'])) {
    die('Session invalide. Veuillez recommencer la procédure.');
}

$contratId = $_SESSION['contrat_id'];
$locataireId = $_SESSION['current_locataire_id'];
$numeroLocataire = $_SESSION['current_locataire_numero'];

// Important: Select c.* first, then explicitly name logements columns to avoid column name collision
// Both tables have 'statut' column, and we need contrats.statut, not logements.statut
$contrat = fetchOne("
    SELECT c.*, 
           l.reference,
           l.adresse,
           l.appartement,
           l.type,
           l.surface,
           l.loyer,
           l.charges,
           l.depot_garantie,
           l.parking
    FROM contrats c 
    INNER JOIN logements l ON c.logement_id = l.id 
    WHERE c.id = ?
", [$contratId]);

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
        // Vérifier le fichier uploadé
        $preuveFile = $_FILES['preuve_paiement'] ?? null;
        
        if (!$preuveFile || $preuveFile['error'] === UPLOAD_ERR_NO_FILE) {
            $error = 'Veuillez télécharger la preuve de paiement.';
        } else {
            // Valider le fichier
            $preuveValidation = validateUploadedFile($preuveFile);
            if (!$preuveValidation['success']) {
                $error = 'Preuve de paiement : ' . $preuveValidation['error'];
            } else {
                // Sauvegarder le fichier
                if (saveUploadedFile($preuveFile, $preuveValidation['filename'])) {
                    // Mettre à jour le locataire
                    if (updateTenantPaymentProof($locataireId, $preuveValidation['filename'])) {
                        logAction($contratId, 'upload_preuve_paiement', "Locataire $numeroLocataire a uploadé la preuve de paiement");
                        
                        // Rediriger vers l'étape 4
                        header('Location: step4-documents.php');
                        exit;
                    } else {
                        $error = 'Erreur lors de l\'enregistrement de la preuve de paiement.';
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
    <title>Versement du dépôt de garantie - MY Invest Immobilier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="text-center mb-4">
            <img src="../assets/images/logo.png" alt="MY Invest Immobilier" class="logo mb-3" 
                 onerror="this.style.display='none'">
            <h1 class="h2">Versement du dépôt de garantie</h1>
        </div>

        <!-- Barre de progression -->
        <div class="mb-4">
            <div class="progress" style="height: 30px;">
                <div class="progress-bar bg-success" role="progressbar" style="width: 75%;">
                    Étape 3/4 - Dépôt de garantie
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-body">
                        <h4 class="card-title mb-4">
                            Versement du dépôt de garantie
                        </h4>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>

                        <div class="alert alert-info mb-4">
                            <p class="mb-2">
                                Afin de finaliser la prise d'effet du bail, nous vous remercions d'effectuer le virement bancaire immédiat d'un montant de <strong><?= formatMontant($contrat['depot_garantie']) ?></strong>, correspondant au dépôt de garantie.
                            </p>
                            <p class="mb-0">
                                Une fois le virement effectué, merci de nous transmettre la preuve de paiement en la téléchargeant ci-dessous.
                            </p>
                        </div>

                        <form method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            
                            <div class="mb-4">
                                <label for="preuve_paiement" class="form-label">
                                    👉 Télécharger la preuve de virement *
                                </label>
                                <input type="file" class="form-control" id="preuve_paiement" name="preuve_paiement" 
                                       accept=".jpg,.jpeg,.png,.pdf" required>
                                <small class="form-text text-muted">
                                    Formats acceptés : JPG, PNG, PDF - Taille max : 5 Mo
                                </small>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    Continuer →
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
