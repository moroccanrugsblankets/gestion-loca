<?php
/**
 * Signature - Étape 1 : Informations du locataire
 * My Invest Immobilier
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Vérifier la session
if (!isset($_SESSION['signature_token']) || !isset($_SESSION['contrat_id'])) {
    die('Session invalide. Veuillez utiliser le lien fourni dans votre email.');
}

$contratId = $_SESSION['contrat_id'];
// Important: Select c.* first, then explicitly name logements columns to avoid column name collision
// Both tables have 'statut' column, and we need contrats.statut, not logements.statut
$contrat = fetchOne("
    SELECT c.*, 
           l.reference,
           l.adresse,
           
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

// Déterminer le numéro du locataire actuel
$locatairesExistants = getTenantsByContract($contratId);
$numeroLocataire = count($locatairesExistants) + 1;

if ($numeroLocataire > $contrat['nb_locataires']) {
    // Tous les locataires ont déjà saisi leurs infos, rediriger vers step2
    header('Location: step2-signature.php');
    exit;
}

$error = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Token CSRF invalide.';
    } else {
        $nom = cleanInput($_POST['nom'] ?? '');
        $prenom = cleanInput($_POST['prenom'] ?? '');
        $dateNaissance = cleanInput($_POST['date_naissance'] ?? '');
        $email = cleanInput($_POST['email'] ?? '');
        
        // Validation
        if (empty($nom) || empty($prenom) || empty($dateNaissance) || empty($email)) {
            $error = 'Tous les champs sont obligatoires.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Adresse email invalide.';
        } else {
            // Créer le locataire
            $locataireId = createTenant($contratId, $numeroLocataire, [
                'nom' => $nom,
                'prenom' => $prenom,
                'date_naissance' => $dateNaissance,
                'email' => $email
            ]);
            
            if ($locataireId) {
                // Stocker l'ID du locataire actuel en session
                $_SESSION['current_locataire_id'] = $locataireId;
                $_SESSION['current_locataire_numero'] = $numeroLocataire;
                
                logAction($contratId, 'saisie_infos_locataire', "Locataire $numeroLocataire: $nom $prenom");
                
                // Rediriger vers l'étape 2
                header('Location: step2-signature.php');
                exit;
            } else {
                $error = 'Erreur lors de l\'enregistrement des informations.';
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
    <title>Informations personnelles - MY Invest Immobilier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="text-center mb-4">
            <img src="../assets/images/logo.png" alt="MY Invest Immobilier" class="logo mb-3" 
                 onerror="this.style.display='none'">
            <h1 class="h2">Informations du locataire</h1>
        </div>

        <!-- Barre de progression -->
        <div class="mb-4">
            <div class="progress" style="height: 30px;">
                <div class="progress-bar bg-success" role="progressbar" style="width: 25%;">
                    Étape 1/4 - Informations
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-body">
                        <h4 class="card-title mb-4">
                            <?php if ($numeroLocataire === 1): ?>
                                Vos informations personnelles
                            <?php else: ?>
                                Informations du locataire <?= $numeroLocataire ?>
                            <?php endif; ?>
                        </h4>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            
                            <div class="mb-3">
                                <label for="nom" class="form-label">Nom *</label>
                                <input type="text" class="form-control" id="nom" name="nom" 
                                       value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="prenom" class="form-label">Prénom *</label>
                                <input type="text" class="form-control" id="prenom" name="prenom" 
                                       value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="date_naissance" class="form-label">Date de naissance *</label>
                                <input type="date" class="form-control" id="date_naissance" name="date_naissance" 
                                       value="<?= htmlspecialchars($_POST['date_naissance'] ?? '') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    Suivant →
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
