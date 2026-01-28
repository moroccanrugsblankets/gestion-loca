<?php
/**
 * Admin - Génération de lien de signature
 * My Invest Immobilier
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mail-templates.php';

$error = '';
$success = '';
$generatedLink = '';
$emailTemplate = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Token CSRF invalide.';
    } else {
        $reference = cleanInput($_POST['reference'] ?? '');
        $nbLocataires = (int)($_POST['nb_locataires'] ?? 1);
        
        if (empty($reference)) {
            $error = 'Veuillez saisir une référence de logement.';
        } elseif ($nbLocataires < 1 || $nbLocataires > 2) {
            $error = 'Le nombre de locataires doit être 1 ou 2.';
        } else {
            // Vérifier que le logement existe
            $logement = getLogementByReference($reference);
            
            if (!$logement) {
                $error = "Aucun logement trouvé avec la référence : $reference";
            } else {
                // Créer le contrat
                $contrat = createContract($logement['id'], $nbLocataires);
                
                if ($contrat) {
                    $signatureLink = $config['SITE_URL'] . '/signature/index.php?token=' . $contrat['token'];
                    $generatedLink = $signatureLink;
                    
                    // Générer le template d'email
                    $emailData = getInvitationEmailTemplate($signatureLink, $logement);
                    $emailTemplate = $emailData['body'];
                    
                    $success = 'Lien de signature généré avec succès !';
                    logAction($contrat['id'], 'generation_lien', "Lien généré pour logement $reference");
                } else {
                    $error = 'Erreur lors de la création du contrat.';
                }
            }
        }
    }
}

$csrfToken = generateCsrfToken();
$logements = getAllLogements();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Générer un lien de signature - MY Invest Immobilier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="text-center mb-4">
            <img src="../assets/images/logo.png" alt="MY Invest Immobilier" class="logo mb-3" 
                 onerror="this.style.display='none'">
            <h1 class="h2">Générer un lien de signature</h1>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>

                        <?php if (!$generatedLink): ?>
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                
                                <div class="mb-3">
                                    <label for="reference" class="form-label">Référence du logement *</label>
                                    <select class="form-select" id="reference" name="reference" required>
                                        <option value="">-- Sélectionner un logement --</option>
                                        <?php foreach ($logements as $log): ?>
                                            <option value="<?= htmlspecialchars($log['reference']) ?>">
                                                <?= htmlspecialchars($log['reference']) ?> - 
                                                <?= htmlspecialchars($log['adresse']) ?> 
                                                (<?= htmlspecialchars($log['type']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted">Ex: RP-01</small>
                                </div>

                                <div class="mb-3">
                                    <label for="nb_locataires" class="form-label">Nombre de locataires *</label>
                                    <select class="form-select" id="nb_locataires" name="nb_locataires" required>
                                        <option value="1">1 locataire</option>
                                        <option value="2">2 locataires</option>
                                    </select>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">Générer le lien</button>
                                    <a href="dashboard.php" class="btn btn-outline-secondary">Voir le tableau de bord</a>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="mb-4">
                                <h5>Lien de signature généré :</h5>
                                <div class="input-group mb-3">
                                    <input type="text" class="form-control" id="signatureLink" 
                                           value="<?= htmlspecialchars($generatedLink) ?>" readonly>
                                    <button class="btn btn-outline-secondary" type="button" onclick="copyLink()">
                                        Copier
                                    </button>
                                </div>
                                <small class="text-danger">⚠️ Ce lien expire dans 24 heures</small>
                            </div>

                            <div class="mb-4">
                                <h5>Email pré-formaté :</h5>
                                <textarea class="form-control" id="emailTemplate" rows="15" readonly><?= htmlspecialchars($emailTemplate) ?></textarea>
                                <button class="btn btn-success mt-2" onclick="copyEmail()">
                                    Copier l'email complet
                                </button>
                            </div>

                            <div class="d-grid gap-2">
                                <a href="generate-link.php" class="btn btn-primary">Générer un autre lien</a>
                                <a href="dashboard.php" class="btn btn-outline-secondary">Voir le tableau de bord</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function copyLink() {
            const linkInput = document.getElementById('signatureLink');
            linkInput.select();
            document.execCommand('copy');
            alert('Lien copié dans le presse-papier !');
        }

        function copyEmail() {
            const emailTextarea = document.getElementById('emailTemplate');
            emailTextarea.select();
            document.execCommand('copy');
            alert('Email copié dans le presse-papier !');
        }
    </script>
</body>
</html>
