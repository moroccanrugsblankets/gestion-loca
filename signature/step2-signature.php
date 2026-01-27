<?php
/**
 * Signature - Étape 2 : Signature électronique
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

$contrat = fetchOne("SELECT c.*, l.* FROM contrats c INNER JOIN logements l ON c.logement_id = l.id WHERE c.id = ?", [$contratId]);

if (!$contrat || !isContractValid($contrat)) {
    die('Contrat invalide ou expiré.');
}

$error = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Token CSRF invalide.';
    } else {
        $signatureData = $_POST['signature_data'] ?? '';
        $mentionLuApprouve = cleanInput($_POST['mention_lu_approuve'] ?? '');
        
        // Validation
        if (empty($signatureData)) {
            $error = 'Veuillez apposer votre signature.';
        } elseif ($mentionLuApprouve !== 'Lu et approuvé') {
            $error = 'Veuillez recopier exactement "Lu et approuvé".';
        } else {
            // Enregistrer la signature
            if (updateTenantSignature($locataireId, $signatureData, $mentionLuApprouve)) {
                logAction($contratId, 'signature_locataire', "Locataire $numeroLocataire a signé");
                
                // Rediriger vers l'étape 3
                header('Location: step3-documents.php');
                exit;
            } else {
                $error = 'Erreur lors de l\'enregistrement de la signature.';
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
    <title>Signature électronique - MY Invest Immobilier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="text-center mb-4">
            <img src="../assets/images/logo.png" alt="MY Invest Immobilier" class="logo mb-3" 
                 onerror="this.style.display='none'">
            <h1 class="h2">Signature électronique</h1>
        </div>

        <!-- Barre de progression -->
        <div class="mb-4">
            <div class="progress" style="height: 30px;">
                <div class="progress-bar bg-success" role="progressbar" style="width: 66%;">
                    Étape 2/3 - Signature
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-body">
                        <h4 class="card-title mb-4">
                            Signature du locataire <?= $numeroLocataire ?>
                        </h4>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <form method="POST" action="" id="signatureForm">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="signature_data" id="signature_data">
                            
                            <div class="mb-4">
                                <label class="form-label">Veuillez signer dans le cadre ci-dessous :</label>
                                <div class="signature-container">
                                    <canvas id="signatureCanvas" width="700" height="300"></canvas>
                                </div>
                                <div class="mt-2">
                                    <button type="button" class="btn btn-warning" onclick="clearSignature()">
                                        Effacer
                                    </button>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="mention_lu_approuve" class="form-label">
                                    Merci de recopier : <strong>"Lu et approuvé"</strong>
                                </label>
                                <input type="text" class="form-control" id="mention_lu_approuve" 
                                       name="mention_lu_approuve" required
                                       placeholder="Lu et approuvé">
                            </div>

                            <div class="alert alert-info">
                                <small>
                                    <strong>Information :</strong> Votre signature sera horodatée et votre adresse IP enregistrée 
                                    pour des raisons de sécurité et de conformité légale.
                                </small>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    Valider la signature →
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/signature.js"></script>
    <script>
        // Initialiser le canvas de signature au chargement
        window.addEventListener('DOMContentLoaded', function() {
            initSignature();
        });

        // Valider le formulaire
        document.getElementById('signatureForm').addEventListener('submit', function(e) {
            const signatureData = getSignatureData();
            
            if (!signatureData || signatureData === getEmptyCanvasData()) {
                e.preventDefault();
                alert('Veuillez apposer votre signature avant de continuer.');
                return false;
            }
            
            document.getElementById('signature_data').value = signatureData;
        });
    </script>
</body>
</html>
