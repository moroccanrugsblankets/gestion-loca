<?php
/**
 * Page de confirmation après soumission de candidature
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// Vérifier qu'un ID de candidature est fourni
if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$candidature_id = (int)$_GET['id'];

// Récupérer les informations de la candidature
try {
    $stmt = $pdo->prepare("
        SELECT c.*, l.reference, l.type, l.adresse, l.loyer
        FROM candidatures c
        JOIN logements l ON c.logement_id = l.id
        WHERE c.id = ?
    ");
    $stmt->execute([$candidature_id]);
    $candidature = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$candidature) {
        header('Location: index.php');
        exit;
    }
    
    // Compter les documents
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM candidature_documents WHERE candidature_id = ?");
    $stmt->execute([$candidature_id]);
    $nb_documents = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log('Erreur récupération candidature: ' . $e->getMessage());
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidature Envoyée - MY Invest Immobilier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Header -->
    <nav class="navbar navbar-light bg-light shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="/">
                <i class="bi bi-house-door-fill me-2"></i>
                <strong>MY Invest Immobilier</strong>
            </a>
        </div>
    </nav>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Message de confirmation -->
                <div class="text-center mb-4">
                    <div class="mb-4">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
                    </div>
                    <h1 class="h2 mb-3">Candidature Envoyée !</h1>
                    <p class="lead text-muted">Merci pour votre confiance</p>
                </div>

                <!-- Informations de suivi -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-info-circle-fill me-2"></i>Informations de Suivi</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Numéro de candidature :</strong></p>
                                <p class="text-primary fs-4 mb-0">#<?php echo str_pad($candidature_id, 6, '0', STR_PAD_LEFT); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Date de soumission :</strong></p>
                                <p class="mb-0"><?php echo date('d/m/Y à H:i', strtotime($candidature['date_soumission'])); ?></p>
                            </div>
                        </div>

                        <hr>

                        <p class="mb-1"><strong>Logement demandé :</strong></p>
                        <p class="mb-3">
                            <?php echo htmlspecialchars($candidature['reference']); ?> - 
                            <?php echo htmlspecialchars($candidature['type']); ?> - 
                            <?php echo htmlspecialchars($candidature['adresse']); ?><br>
                            <span class="text-muted"><?php echo number_format($candidature['loyer'], 0, ',', ' '); ?> €/mois</span>
                        </p>

                        <p class="mb-1"><strong>Vos informations :</strong></p>
                        <p class="mb-3">
                            <?php echo htmlspecialchars($candidature['nom']); ?> 
                            <?php echo htmlspecialchars($candidature['prenom']); ?><br>
                            <span class="text-muted"><?php echo htmlspecialchars($candidature['email']); ?></span>
                        </p>

                        <p class="mb-1"><strong>Documents joints :</strong></p>
                        <p class="mb-0"><?php echo $nb_documents; ?> document(s)</p>
                    </div>
                </div>

                <!-- Prochaines étapes -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Prochaines Étapes</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h6><i class="bi bi-calendar-event me-2"></i>Délai de traitement</h6>
                            <p class="mb-0">
                                Votre candidature sera étudiée et vous recevrez une réponse par email dans un délai 
                                <strong>entre 1 et 4 jours ouvrés</strong> (du lundi au vendredi, hors jours fériés).
                            </p>
                        </div>

                        <h6 class="mt-4">Ce que nous allons faire :</h6>
                        <ul class="mb-0">
                            <li>Examen attentif de votre dossier de candidature</li>
                            <li>Vérification de vos pièces justificatives</li>
                            <li>Évaluation de votre solvabilité</li>
                            <li>Réponse par email (acceptation ou refus)</li>
                        </ul>

                        <h6 class="mt-4">Si votre candidature est acceptée :</h6>
                        <ul class="mb-0">
                            <li>Vous recevrez un email avec un bouton pour confirmer votre intérêt</li>
                            <li>Nous vous contacterons via WhatsApp pour organiser une visite</li>
                            <li>Après visite, possibilité d'envoi du contrat de bail</li>
                        </ul>
                    </div>
                </div>

                <!-- Email de confirmation -->
                <div class="alert alert-success">
                    <h6><i class="bi bi-envelope-check-fill me-2"></i>Email de Confirmation</h6>
                    <p class="mb-0">
                        Un email de confirmation a été envoyé à <strong><?php echo htmlspecialchars($candidature['email']); ?></strong>.
                        <br>
                        Si vous ne le recevez pas dans les prochaines minutes, pensez à vérifier vos spam/courrier indésirable.
                    </p>
                </div>

                <!-- Coordonnées de contact -->
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="bi bi-headset me-2"></i>Besoin d'Aide ?</h5>
                    </div>
                    <div class="card-body">
                        <p>Nous restons à votre disposition pour toute question :</p>
                        <p class="mb-0">
                            <i class="bi bi-envelope-fill me-2 text-primary"></i>
                            <a href="mailto:contact@myinvest-immobilier.com">contact@myinvest-immobilier.com</a>
                        </p>
                    </div>
                </div>

                <!-- Bouton retour -->
                <div class="text-center mt-4">
                    <a href="https://www.myinvest-immobilier.com" class="btn btn-outline-primary">
                        <i class="bi bi-house-door-fill me-2"></i>
                        Retour au site web
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
