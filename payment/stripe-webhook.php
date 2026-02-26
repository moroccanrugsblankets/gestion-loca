<?php
/**
 * Webhook Stripe - Réception des événements de paiement
 *
 * Ce fichier reçoit les notifications de Stripe (checkout.session.completed, etc.)
 * et effectue automatiquement :
 *   1. Marque le loyer comme payé dans loyers_tracking
 *   2. Génère la quittance PDF et l'envoie par email au locataire
 *
 * URL à configurer dans le tableau de bord Stripe :
 *   https://votre-domaine.com/payment/stripe-webhook.php
 *
 * Événements à activer dans Stripe :
 *   - checkout.session.completed
 *   - checkout.session.expired
 */

// Ne pas démarrer de session (endpoint HTTP brut)
// Lire le corps de la requête avant tout output
$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Initialisation
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../pdf/generate-quittance.php';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(503);
    error_log('Stripe webhook: vendor/autoload.php introuvable');
    exit;
}
require_once $autoload;

/**
 * Log un message de webhook
 */
function webhookLog(string $message, bool $isError = false): void {
    $logFile = __DIR__ . '/../cron/stripe-webhook-log.txt';
    $level = $isError ? '[ERROR]' : '[INFO]';
    $line = '[' . date('Y-m-d H:i:s') . "] $level $message\n";
    error_log(rtrim($message));
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

// Vérifier que Stripe est configuré
$stripeMode = getParameter('stripe_mode', 'test');
$webhookSecret = getParameter('stripe_webhook_secret', '');
$stripeSecretKey = ($stripeMode === 'live')
    ? getParameter('stripe_secret_key_live', '')
    : getParameter('stripe_secret_key_test', '');

if (empty($stripeSecretKey)) {
    http_response_code(503);
    webhookLog('Clé secrète Stripe non configurée', true);
    exit;
}

\Stripe\Stripe::setApiKey($stripeSecretKey);

// Construire et valider l'événement Stripe
try {
    if (!empty($webhookSecret)) {
        $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
    } else {
        // Sans secret configuré, rejeter les requêtes en mode live (sécurité critique)
        if ($stripeMode === 'live') {
            http_response_code(403);
            webhookLog('ERREUR SÉCURITÉ: webhook_secret non configuré en mode production. Requête rejetée.', true);
            exit;
        }
        // En mode test uniquement : accepter sans vérification avec avertissement
        $eventData = json_decode($payload, true);
        if (!$eventData) {
            throw new \UnexpectedValueException('Payload JSON invalide');
        }
        $event = \Stripe\Event::constructFrom($eventData);
        webhookLog('AVERTISSEMENT: webhook_secret non configuré (mode test). Ne pas utiliser en production.', true);
    }
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    webhookLog('Signature webhook invalide: ' . $e->getMessage(), true);
    exit;
} catch (\UnexpectedValueException $e) {
    http_response_code(400);
    webhookLog('Payload invalide: ' . $e->getMessage(), true);
    exit;
}

webhookLog("Événement reçu: {$event->type} (ID: {$event->id})");

// Traitement selon le type d'événement
switch ($event->type) {
    case 'checkout.session.completed':
        handleCheckoutCompleted($event->data->object, $event->id);
        break;

    case 'checkout.session.expired':
        handleCheckoutExpired($event->data->object);
        break;

    default:
        webhookLog("Événement ignoré: {$event->type}");
        break;
}

http_response_code(200);
echo json_encode(['received' => true]);
exit;

// ─── Handlers ───────────────────────────────────────────────────────────────

/**
 * Traiter un paiement Stripe Checkout complété avec succès
 */
function handleCheckoutCompleted(\Stripe\Checkout\Session $stripeSession, string $eventId): void {
    global $pdo;

    $stripeSessionId = $stripeSession->id;
    webhookLog("Paiement complété pour session Stripe: $stripeSessionId");

    // Idempotence : ignorer si cet événement a déjà été traité
    $stmt = $pdo->prepare("SELECT id, statut FROM stripe_payment_sessions WHERE stripe_session_id = ? LIMIT 1");
    $stmt->execute([$stripeSessionId]);
    $paySession = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$paySession) {
        // Essayer de retrouver via les métadonnées
        $token = $stripeSession->metadata->payment_session_token ?? null;
        if ($token) {
            $stmt2 = $pdo->prepare("SELECT id, statut FROM stripe_payment_sessions WHERE token_acces = ? LIMIT 1");
            $stmt2->execute([$token]);
            $paySession = $stmt2->fetch(PDO::FETCH_ASSOC);
            if ($paySession) {
                // Enregistrer l'ID de session Stripe maintenant qu'on l'a
                $pdo->prepare("UPDATE stripe_payment_sessions SET stripe_session_id = ? WHERE id = ?")
                    ->execute([$stripeSessionId, $paySession['id']]);
            }
        }
    }

    if (!$paySession) {
        webhookLog("Session de paiement introuvable pour stripe_session_id=$stripeSessionId", true);
        return;
    }

    if ($paySession['statut'] === 'paye') {
        webhookLog("Paiement déjà traité pour session ID {$paySession['id']} (idempotence)");
        return;
    }

    // Récupérer les détails complets
    $stmt = $pdo->prepare("
        SELECT sps.*,
               l.adresse, l.loyer, l.charges, l.reference as logement_ref
        FROM stripe_payment_sessions sps
        INNER JOIN logements l ON sps.logement_id = l.id
        WHERE sps.id = ?
    ");
    $stmt->execute([$paySession['id']]);
    $fullSession = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fullSession) {
        webhookLog("Impossible de récupérer les détails de la session {$paySession['id']}", true);
        return;
    }

    $paymentIntentId = $stripeSession->payment_intent ?? null;

    // 1. Mettre à jour la stripe_payment_sessions
    $updateStmt = $pdo->prepare("
        UPDATE stripe_payment_sessions
        SET statut = 'paye',
            stripe_payment_intent_id = ?,
            stripe_event_id = ?,
            date_paiement = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([$paymentIntentId, $eventId, $paySession['id']]);

    // 2. Mettre à jour loyers_tracking
    $ltStmt = $pdo->prepare("
        UPDATE loyers_tracking
        SET statut_paiement = 'paye',
            mode_paiement = 'stripe',
            stripe_session_id = ?,
            date_paiement = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    $ltStmt->execute([$stripeSessionId, $fullSession['loyer_tracking_id']]);

    webhookLog("Loyer marqué payé (loyers_tracking ID: {$fullSession['loyer_tracking_id']})");

    // 3. Générer la quittance PDF et l'envoyer par email
    try {
        $result = generateQuittancePDF($fullSession['contrat_id'], $fullSession['mois'], $fullSession['annee']);

        if ($result) {
            webhookLog("Quittance générée: quittance_id={$result['quittance_id']}");

            // Marquer la quittance comme générée dans stripe_payment_sessions
            $pdo->prepare("
                UPDATE stripe_payment_sessions
                SET quittance_generee = 1, quittance_id = ?
                WHERE id = ?
            ")->execute([$result['quittance_id'], $paySession['id']]);

            // Envoyer l'email au(x) locataire(s)
            $locatairesStmt = $pdo->prepare("SELECT * FROM locataires WHERE contrat_id = ? ORDER BY ordre");
            $locatairesStmt->execute([$fullSession['contrat_id']]);
            $locataires = $locatairesStmt->fetchAll(PDO::FETCH_ASSOC);

            $nomsMois = [
                1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
                5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
                9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
            ];
            $periode = ($nomsMois[$fullSession['mois']] ?? $fullSession['mois']) . ' ' . $fullSession['annee'];
            $montantLoyer   = number_format((float)$fullSession['loyer'], 2, ',', ' ');
            $montantCharges = number_format((float)$fullSession['charges'], 2, ',', ' ');
            $montantTotal   = number_format((float)$fullSession['loyer'] + (float)$fullSession['charges'], 2, ',', ' ');

            foreach ($locataires as $locataire) {
                $sent = sendTemplatedEmail('quittance_envoyee', $locataire['email'], [
                    'locataire_nom'     => $locataire['nom'],
                    'locataire_prenom'  => $locataire['prenom'],
                    'adresse'           => $fullSession['adresse'],
                    'periode'           => $periode,
                    'montant_loyer'     => $montantLoyer,
                    'montant_charges'   => $montantCharges,
                    'montant_total'     => $montantTotal,
                    'signature'         => getParameter('email_signature', ''),
                ], $result['filepath'], false, true, ['contexte' => 'stripe_quittance']);

                if ($sent) {
                    webhookLog("Quittance envoyée par email à {$locataire['email']}");
                } else {
                    webhookLog("Échec envoi quittance à {$locataire['email']}", true);
                }
            }

            // Mettre à jour le flag email_envoye sur la quittance
            $pdo->prepare("UPDATE quittances SET email_envoye = 1, date_envoi_email = NOW() WHERE id = ?")
                ->execute([$result['quittance_id']]);

        } else {
            webhookLog("Échec génération quittance pour contrat {$fullSession['contrat_id']}", true);
        }
    } catch (Exception $e) {
        webhookLog("Erreur génération quittance: " . $e->getMessage(), true);
    }
}

/**
 * Traiter une session Stripe Checkout expirée
 */
function handleCheckoutExpired(\Stripe\Checkout\Session $stripeSession): void {
    global $pdo;

    $stripeSessionId = $stripeSession->id;
    $stmt = $pdo->prepare("
        UPDATE stripe_payment_sessions
        SET statut = 'expire', updated_at = NOW()
        WHERE stripe_session_id = ? AND statut = 'en_attente'
    ");
    $stmt->execute([$stripeSessionId]);
    webhookLog("Session expirée: $stripeSessionId");
}
