<?php
/**
 * Signature - Page d'accueil avec validation du lien
 * My Invest Immobilier
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

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

// Vérifier si le contrat est valide
if (!isContractValid($contrat)) {
    if ($contrat['statut'] === 'signe') {
        die('Ce contrat a déjà été signé.');
    } elseif ($contrat['statut'] === 'expire') {
        die('Ce lien a expiré. Veuillez contacter MY Invest Immobilier.');
    } else {
        die('Ce lien a expiré. Il était valide jusqu\'au ' . formatDateFr($contrat['date_expiration'], 'd/m/Y à H:i'));
    }
}

// Stocker le token en session
$_SESSION['signature_token'] = $token;
$_SESSION['contrat_id'] = $contrat['id'];

// Traitement de l'acceptation/refus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Token CSRF invalide.';
    } else {
        $choix = $_POST['choix'] ?? '';
        
        if ($choix === 'refuse') {
            // Enregistrer le refus
            executeQuery("UPDATE contrats SET statut = 'annule' WHERE id = ?", [$contrat['id']]);
            logAction($contrat['id'], 'refus_contrat', 'Le locataire a refusé le contrat');
            
            echo '<!DOCTYPE html>
            <html lang="fr">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Refus du contrat</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            </head>
            <body>
                <div class="container mt-5">
                    <div class="text-center">
                        <h1>Refus enregistré</h1>
                        <p class="lead">Votre refus a été enregistré. La procédure est terminée.</p>
                        <p>Si vous avez des questions, veuillez contacter MY Invest Immobilier.</p>
                    </div>
                </div>
            </body>
            </html>';
            exit;
        } elseif ($choix === 'accepte') {
            // Rediriger vers l'étape 1
            logAction($contrat['id'], 'acceptation_contrat', 'Le locataire a accepté de poursuivre');
            header('Location: step1-info.php');
            exit;
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
    <title>Signature de bail - MY Invest Immobilier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="text-center mb-4">
            <img src="../assets/images/logo.png" alt="MY Invest Immobilier" class="logo mb-3" 
                 onerror="this.style.display='none'">
            <h1 class="h2">Signature de contrat de bail</h1>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-body">
                        <h4 class="card-title mb-4">Bienvenue dans le processus de signature</h4>
                        
                        <div class="alert alert-info">
                            <strong>Logement :</strong> <?= htmlspecialchars($contrat['reference']) ?><br>
                            <strong>Adresse :</strong> <?= htmlspecialchars($contrat['adresse']) ?><br>
                            <strong>Type :</strong> <?= htmlspecialchars($contrat['type']) ?><br>
                            <strong>Loyer :</strong> <?= formatMontant($contrat['loyer']) ?> + <?= formatMontant($contrat['charges']) ?> de charges
                        </div>

                        <div class="mb-4">
                            <h5>Procédure de signature</h5>
                            <p>Pour finaliser votre bail, vous devrez :</p>
                            <ol>
                                <li>Renseigner vos informations personnelles</li>
                                <li>Apposer votre signature électronique</li>
                                <li>Télécharger vos pièces d'identité (recto et verso)</li>
                            </ol>
                            <p class="text-danger">
                                <strong>⚠️ Important :</strong> Ce lien expire le 
                                <?= formatDateFr($contrat['date_expiration'], 'd/m/Y à H:i') ?>
                            </p>
                        </div>

                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            
                            <div class="mb-4">
                                <p><strong>Souhaitez-vous poursuivre la procédure de signature ?</strong></p>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" name="choix" value="accepte" class="btn btn-success btn-lg">
                                        ✓ J'accepte et je souhaite poursuivre
                                    </button>
                                    <button type="submit" name="choix" value="refuse" class="btn btn-danger">
                                        ✗ Je refuse
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
