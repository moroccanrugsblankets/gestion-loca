<?php
/**
 * Page d'acceptation de l'intervention par le locataire (intervention facturable)
 *
 * URL: /signalement/accepter-intervention.php?sig=xxx&token=xxx
 *
 * Utilisée lorsque la responsabilité du signalement est à la charge du locataire :
 * le locataire accepte que l'intervention soit réalisée par la société et sera facturée.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$sigId = (int)($_GET['sig'] ?? 0);
$token = trim($_GET['token'] ?? '');

if ($sigId <= 0 || empty($token)) {
    http_response_code(400);
    die('Lien invalide.');
}

// Charger le signalement et valider le token locataire
$stmt = $pdo->prepare("
    SELECT sig.id, sig.reference, sig.titre, sig.statut, sig.responsabilite,
           l.adresse,
           loc.token_signalement,
           CONCAT(loc.prenom, ' ', loc.nom) AS locataire_nom
    FROM signalements sig
    INNER JOIN logements l ON sig.logement_id = l.id
    LEFT JOIN locataires loc ON sig.locataire_id = loc.id
    WHERE sig.id = ? AND loc.token_signalement = ?
    LIMIT 1
");
$stmt->execute([$sigId, $token]);
$sig = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sig) {
    http_response_code(404);
    die('Lien invalide ou expiré.');
}

$alreadyAccepted = ($sig['statut'] === 'clos');
$accepted        = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyAccepted) {
    // Le locataire accepte l'intervention facturable — clore le signalement
    $pdo->prepare("UPDATE signalements SET statut = 'clos', date_cloture = COALESCE(date_cloture, NOW()), updated_at = NOW() WHERE id = ?")
        ->execute([$sigId]);
    $pdo->prepare("
        INSERT INTO signalements_actions (signalement_id, type_action, description, acteur, ip_address)
        VALUES (?, 'cloture', 'Intervention acceptée par le locataire (intervention facturable) — dossier clos', ?, ?)
    ")->execute([$sigId, $sig['locataire_nom'], $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    $accepted = true;
}

$companyName = $config['COMPANY_NAME'] ?? 'My Invest Immobilier';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceptation de l'intervention — <?php echo htmlspecialchars($companyName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f4f6f9; }
        .accept-card {
            max-width: 580px;
            margin: 60px auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
            overflow: hidden;
        }
        .accept-header {
            padding: 30px;
            text-align: center;
            color: #fff;
            background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
        }
        .accept-body { padding: 30px; }
        .bareme-table td { padding: 8px 12px; }
        .bareme-table tr:not(:last-child) td { border-bottom: 1px solid #dee2e6; }
    </style>
</head>
<body>
    <div class="accept-card">
        <div class="accept-header">
            <h1 class="h3 mb-1">🔧 Intervention à votre charge</h1>
            <p class="mb-0 opacity-75"><?php echo htmlspecialchars($companyName); ?></p>
        </div>
        <div class="accept-body">
            <div class="mb-4">
                <small class="text-muted d-block">Référence</small>
                <strong class="font-monospace"><?php echo htmlspecialchars($sig['reference']); ?></strong>
                <br>
                <small class="text-muted d-block mt-2">Titre</small>
                <strong><?php echo htmlspecialchars($sig['titre']); ?></strong>
                <br>
                <small class="text-muted d-block mt-2">Logement</small>
                <?php echo htmlspecialchars($sig['adresse']); ?>
            </div>

            <?php if ($accepted || $alreadyAccepted): ?>
                <div class="alert alert-success text-center py-4">
                    <i class="bi bi-check-circle-fill" style="font-size:2.5rem;display:block;margin-bottom:10px;"></i>
                    <strong>Merci !</strong><br>
                    Votre accord a bien été enregistré. Nous allons prendre contact avec vous pour planifier l'intervention.
                </div>
            <?php else: ?>
                <p>Suite à l'analyse de votre signalement, la responsabilité de ce problème a été déterminée comme étant <strong>à votre charge</strong>.</p>
                <p>Notre équipe peut prendre en charge cette intervention. Les tarifs applicables sont les suivants :</p>

                <div class="card border-warning mb-4">
                    <div class="card-header bg-warning bg-opacity-10 fw-bold">
                        <i class="bi bi-currency-euro me-1"></i> Barème applicable
                    </div>
                    <div class="card-body p-0">
                        <table class="table mb-0 bareme-table">
                            <tbody>
                                <tr>
                                    <td><i class="bi bi-geo-alt-fill text-warning me-1"></i> Forfait déplacement + diagnostic<br><small class="text-muted">(incluant jusqu'à 1 heure sur place)</small></td>
                                    <td class="fw-bold text-end text-nowrap">80 € TTC</td>
                                </tr>
                                <tr>
                                    <td><i class="bi bi-clock-fill text-warning me-1"></i> Heure supplémentaire entamée</td>
                                    <td class="fw-bold text-end text-nowrap">60 € TTC</td>
                                </tr>
                                <tr>
                                    <td><i class="bi bi-tools text-warning me-1"></i> Fournitures et pièces</td>
                                    <td class="fw-bold text-end text-nowrap">Coût réel</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <p class="text-muted small">En cliquant sur le bouton ci-dessous, vous acceptez que cette intervention soit réalisée par notre équipe et facturée selon le barème ci-dessus.</p>
                <form method="POST" class="d-grid">
                    <button type="submit" class="btn btn-warning btn-lg fw-bold">
                        <i class="bi bi-check-circle me-2"></i>J'accepte — Planifier l'intervention
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
