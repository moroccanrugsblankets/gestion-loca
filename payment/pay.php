<?php
/**
 * Page de paiement du loyer via Stripe
 *
 * Page publique (sans authentification) accessible via un lien sécurisé unique
 * envoyé par email au locataire.
 *
 * URL: /payment/pay.php?token=XXXXX
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Charger l'autoloader Composer (Stripe SDK)
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(503);
    die('Service temporairement indisponible. Veuillez contacter le propriétaire.');
}
require_once $autoload;

$token = isset($_GET['token']) ? trim($_GET['token']) : '';

// Validation basique du token
if (empty($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(404);
    die('Lien de paiement invalide ou expiré.');
}

// Récupérer la session de paiement associée au token
try {
    $stmt = $pdo->prepare("
        SELECT sps.*,
               c.reference_unique as contrat_ref,
               l.adresse,
               l.reference as logement_ref,
               (SELECT GROUP_CONCAT(CONCAT(loc.prenom, ' ', loc.nom) SEPARATOR ', ')
                FROM locataires loc WHERE loc.contrat_id = sps.contrat_id) as locataires_noms
        FROM stripe_payment_sessions sps
        INNER JOIN contrats c ON sps.contrat_id = c.id
        INNER JOIN logements l ON sps.logement_id = l.id
        WHERE sps.token_acces = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Stripe pay.php DB error: ' . $e->getMessage());
    http_response_code(500);
    die('Erreur interne. Veuillez réessayer plus tard.');
}

if (!$session) {
    http_response_code(404);
    die('Lien de paiement introuvable.');
}

// Vérifier la validité du lien
if ($session['statut'] === 'paye') {
    $alreadyPaid = true;
} elseif ($session['statut'] === 'annule') {
    http_response_code(410);
    die('Ce lien de paiement a été annulé.');
} elseif (strtotime($session['token_expiration']) < time()) {
    http_response_code(410);
    die('Ce lien de paiement a expiré. Veuillez contacter votre propriétaire pour obtenir un nouveau lien.');
} else {
    $alreadyPaid = false;
}

$nomsMois = [
    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
];
$periode = ($nomsMois[$session['mois']] ?? $session['mois']) . ' ' . $session['annee'];

// Si déjà payé, afficher la confirmation
if ($alreadyPaid) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Paiement confirmé - <?php echo htmlspecialchars($config['COMPANY_NAME']); ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card shadow text-center">
                        <div class="card-body p-5">
                            <div class="text-success mb-4" style="font-size: 64px;">✅</div>
                            <h2 class="text-success">Paiement déjà effectué</h2>
                            <p class="text-muted">Votre loyer de <strong><?php echo htmlspecialchars($periode); ?></strong> a été réglé.</p>
                            <p>Montant : <strong><?php echo number_format($session['montant'], 2, ',', ' '); ?> €</strong></p>
                            <p class="text-muted small">Votre quittance vous a été envoyée par email.</p>
                        </div>
                    </div>
                    <p class="text-center mt-3 text-muted small"><?php echo htmlspecialchars($config['COMPANY_NAME']); ?></p>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Vérifier que Stripe est configuré
$stripeActif = getParameter('stripe_actif', false);
if (!$stripeActif) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Paiement - <?php echo htmlspecialchars($config['COMPANY_NAME']); ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card shadow">
                        <div class="card-body p-5 text-center">
                            <div style="font-size: 48px;" class="mb-3">🏦</div>
                            <h3>Paiement en ligne temporairement indisponible</h3>
                            <p class="text-muted">Le paiement en ligne n'est pas encore activé. Merci de contacter votre propriétaire pour les modalités de paiement.</p>
                            <hr>
                            <p><strong>Loyer dû :</strong> <?php echo number_format($session['montant'], 2, ',', ' '); ?> € pour <?php echo htmlspecialchars($periode); ?></p>
                            <p class="text-muted small">IBAN : <?php echo htmlspecialchars($config['IBAN']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Récupérer les clés Stripe selon le mode
$stripeMode = getParameter('stripe_mode', 'test');
$stripeSecretKey = ($stripeMode === 'live')
    ? getParameter('stripe_secret_key_live', '')
    : getParameter('stripe_secret_key_test', '');
$stripePublicKey = ($stripeMode === 'live')
    ? getParameter('stripe_public_key_live', '')
    : getParameter('stripe_public_key_test', '');

if (empty($stripeSecretKey)) {
    error_log('Stripe: clé secrète non configurée');
    die('Configuration de paiement incomplète. Contactez votre propriétaire.');
}

\Stripe\Stripe::setApiKey($stripeSecretKey);

// Créer ou récupérer la Checkout Session Stripe
$checkoutUrl = null;
if (!empty($session['stripe_session_id'])) {
    // Essayer de récupérer la session existante
    try {
        $existingSession = \Stripe\Checkout\Session::retrieve($session['stripe_session_id']);
        if ($existingSession->status === 'open') {
            $checkoutUrl = $existingSession->url;
        }
    } catch (Exception $e) {
        // Session expirée ou invalide, on en crée une nouvelle
        $checkoutUrl = null;
    }
}

if (!$checkoutUrl) {
    // Créer une nouvelle Checkout Session
    try {
        $methodesAcceptees = getParameter('stripe_methodes_paiement', ['card', 'sepa_debit']);
        if (!is_array($methodesAcceptees)) {
            $methodesAcceptees = ['card'];
        }

        $successUrl = rtrim($config['SITE_URL'], '/') . '/payment/pay.php?token=' . urlencode($token) . '&stripe_success=1&session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl  = rtrim($config['SITE_URL'], '/') . '/payment/pay.php?token=' . urlencode($token) . '&stripe_cancel=1';

        // Récupérer les infos du premier locataire pour préremplir Stripe
        $locataireStmt = $pdo->prepare("SELECT prenom, nom, email FROM locataires WHERE contrat_id = ? ORDER BY ordre LIMIT 1");
        $locataireStmt->execute([$session['contrat_id']]);
        $locataire = $locataireStmt->fetch(PDO::FETCH_ASSOC);

        $checkoutParams = [
            'payment_method_types' => $methodesAcceptees,
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => 'Loyer ' . $periode . ' - ' . $session['adresse'],
                        'description' => 'Loyer et charges - ' . $session['adresse'],
                    ],
                    'unit_amount' => (int)round($session['montant'] * 100), // montant en centimes
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url'  => $cancelUrl,
            'metadata' => [
                'payment_session_token' => $token,
                'contrat_id'  => $session['contrat_id'],
                'logement_id' => $session['logement_id'],
                'mois'        => $session['mois'],
                'annee'       => $session['annee'],
            ],
            'locale' => 'fr',
            'expires_at' => min(
                strtotime($session['token_expiration']),
                time() + 86400 // max 24h pour une session Checkout Stripe
            ),
        ];

        // Préremplir l'email si disponible
        if ($locataire && !empty($locataire['email'])) {
            $checkoutParams['customer_email'] = $locataire['email'];
        }

        $stripeCheckout = \Stripe\Checkout\Session::create($checkoutParams);
        $checkoutUrl = $stripeCheckout->url;

        // Enregistrer l'ID de session Stripe
        $updateStmt = $pdo->prepare("UPDATE stripe_payment_sessions SET stripe_session_id = ? WHERE token_acces = ?");
        $updateStmt->execute([$stripeCheckout->id, $token]);

    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('Stripe Checkout Session error: ' . $e->getMessage());
        die('Erreur lors de la création du lien de paiement. Veuillez réessayer ou contacter votre propriétaire.');
    }
}

// Rediriger vers Stripe Checkout
if ($checkoutUrl) {
    header('Location: ' . $checkoutUrl);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement - <?php echo htmlspecialchars($config['COMPANY_NAME']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body p-5 text-center">
                        <div class="spinner-border text-primary mb-3" role="status"></div>
                        <h4>Redirection vers le paiement sécurisé...</h4>
                        <p class="text-muted">Vous allez être redirigé vers Stripe pour régler votre loyer.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
