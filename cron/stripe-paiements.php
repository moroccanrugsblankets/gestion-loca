#!/usr/bin/env php
<?php
/**
 * CRON JOB: Envoi automatique des invitations et rappels de paiement Stripe
 *
 * Ce script s'exécute chaque jour et :
 *   1. En début de mois (jour configuré) : envoie les liens de paiement Stripe à tous les locataires
 *   2. Aux jours de rappel configurés : renvoie un rappel aux locataires qui n'ont pas encore payé
 *
 * Configuration dans la table parametres :
 *   - stripe_actif                 : Active/désactive le module (boolean)
 *   - stripe_paiement_invitation_jour : Jour du mois pour l'envoi initial (integer, défaut: 1)
 *   - stripe_paiement_rappel_jours : Jours de rappel (json array, défaut: [7, 14])
 *   - stripe_lien_expiration_heures: Durée de validité du lien en heures (défaut: 168 = 7j)
 *
 * Usage:
 *   php cron/stripe-paiements.php
 *
 * Cron expression recommandée : 0 8 * * * (tous les jours à 8h)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mail-templates.php';

// Charger Stripe SDK
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    error_log('[stripe-paiements] vendor/autoload.php introuvable - exécutez composer install');
    exit(1);
}
require_once $autoload;

// ─── Logging ────────────────────────────────────────────────────────────────
$logFile = __DIR__ . '/stripe-paiements-log.txt';
$cronLogs = [];

function logMsg(string $msg, bool $isError = false): void {
    global $logFile, $cronLogs;
    $level = $isError ? '[ERROR]' : '[INFO]';
    $line = '[' . date('Y-m-d H:i:s') . "] $level $msg\n";
    echo $line;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    $cronLogs[] = $line;
}

// ─── Vérifications préalables ───────────────────────────────────────────────
$stripeActif = getParameter('stripe_actif', false);
if (!$stripeActif) {
    logMsg('Module Stripe inactif (stripe_actif = 0). Arrêt du cron.');
    exit(0);
}

$stripeMode = getParameter('stripe_mode', 'test');
$stripeSecretKey = ($stripeMode === 'live')
    ? getParameter('stripe_secret_key_live', '')
    : getParameter('stripe_secret_key_test', '');

if (empty($stripeSecretKey)) {
    logMsg('Clé secrète Stripe non configurée. Arrêt du cron.', true);
    exit(1);
}

\Stripe\Stripe::setApiKey($stripeSecretKey);

// ─── Déterminer l'action à effectuer aujourd'hui ────────────────────────────
$aujourdHui = (int)date('j'); // Jour du mois (1-31)
$moisActuel = (int)date('n');
$anneeActuelle = (int)date('Y');

$jourInvitation = (int)getParameter('stripe_paiement_invitation_jour', 1);
$joursRappel    = getParameter('stripe_paiement_rappel_jours', [7, 14]);
if (!is_array($joursRappel)) {
    $joursRappel = [7, 14];
}

$doInvitation = ($aujourdHui === $jourInvitation);
$doRappel     = in_array($aujourdHui, $joursRappel, true);

if (!$doInvitation && !$doRappel) {
    logMsg("Aucune action prévue pour le jour $aujourdHui du mois. (invitation=$jourInvitation, rappels=" . implode(',', $joursRappel) . ')');
    exit(0);
}

logMsg("Démarrage - mode=$stripeMode, jour=$aujourdHui, invitation=" . ($doInvitation ? 'oui' : 'non') . ", rappel=" . ($doRappel ? 'oui' : 'non'));

// ─── Récupérer les contrats actifs et leurs locataires ──────────────────────
$contrats = $pdo->query("
    SELECT c.id as contrat_id, c.reference_unique,
           l.id as logement_id, l.adresse, l.loyer, l.charges
    FROM contrats c
    INNER JOIN logements l ON c.logement_id = l.id
    INNER JOIN (
        SELECT logement_id, MAX(id) AS max_id
        FROM contrats
        WHERE statut = 'valide'
          AND date_prise_effet IS NOT NULL
          AND date_prise_effet <= CURDATE()
        GROUP BY logement_id
    ) actifs ON c.id = actifs.max_id
    ORDER BY l.reference
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($contrats)) {
    logMsg('Aucun contrat actif trouvé.');
    exit(0);
}

logMsg(count($contrats) . ' contrat(s) actif(s) trouvé(s).');

$nomsMois = [
    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
];

$liensExpirationHeures = (int)getParameter('stripe_lien_expiration_heures', 168);
$siteUrl = rtrim($config['SITE_URL'], '/');

// ─── Traitement de chaque contrat ───────────────────────────────────────────
foreach ($contrats as $contrat) {
    $contratId  = $contrat['contrat_id'];
    $logementId = $contrat['logement_id'];
    $montant    = (float)$contrat['loyer'] + (float)$contrat['charges'];
    $periode    = $nomsMois[$moisActuel] . ' ' . $anneeActuelle;

    // Vérifier si le loyer du mois est déjà payé
    $ltStmt = $pdo->prepare("
        SELECT id, statut_paiement
        FROM loyers_tracking
        WHERE contrat_id = ? AND mois = ? AND annee = ? AND deleted_at IS NULL
        LIMIT 1
    ");
    $ltStmt->execute([$contratId, $moisActuel, $anneeActuelle]);
    $lt = $ltStmt->fetch(PDO::FETCH_ASSOC);

    if ($lt && $lt['statut_paiement'] === 'paye') {
        logMsg("Contrat $contratId ($contrat[adresse]) : loyer $periode déjà payé - ignoré.");
        continue;
    }

    // Créer une entrée de tracking si elle n'existe pas
    if (!$lt) {
        $pdo->prepare("
            INSERT INTO loyers_tracking (logement_id, contrat_id, mois, annee, montant_attendu, statut_paiement)
            VALUES (?, ?, ?, ?, ?, 'attente')
            ON DUPLICATE KEY UPDATE montant_attendu = VALUES(montant_attendu)
        ")->execute([$logementId, $contratId, $moisActuel, $anneeActuelle, $montant]);

        $ltStmt->execute([$contratId, $moisActuel, $anneeActuelle]);
        $lt = $ltStmt->fetch(PDO::FETCH_ASSOC);
    }

    $ltId = $lt['id'];

    // Récupérer ou créer la session de paiement Stripe
    $sessionStmt = $pdo->prepare("
        SELECT * FROM stripe_payment_sessions
        WHERE contrat_id = ? AND mois = ? AND annee = ?
          AND statut NOT IN ('paye', 'annule')
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $sessionStmt->execute([$contratId, $moisActuel, $anneeActuelle]);
    $paySession = $sessionStmt->fetch(PDO::FETCH_ASSOC);

    // Créer une nouvelle session si nécessaire ou si le lien est expiré
    if (!$paySession || strtotime($paySession['token_expiration']) < time()) {
        $token = bin2hex(random_bytes(32));
        $expiration = date('Y-m-d H:i:s', time() + $liensExpirationHeures * 3600);

        $pdo->prepare("
            INSERT INTO stripe_payment_sessions
                (loyer_tracking_id, contrat_id, logement_id, mois, annee, montant, token_acces, token_expiration, statut)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'en_attente')
        ")->execute([$ltId, $contratId, $logementId, $moisActuel, $anneeActuelle, $montant, $token, $expiration]);

        $sessionStmt->execute([$contratId, $moisActuel, $anneeActuelle]);
        $paySession = $sessionStmt->fetch(PDO::FETCH_ASSOC);

        if (!$paySession) {
            logMsg("Erreur création session de paiement pour contrat $contratId", true);
            continue;
        }
    }

    $lienPaiement  = $siteUrl . '/payment/pay.php?token=' . urlencode($paySession['token_acces']);
    $dateExpiration = date('d/m/Y à H:i', strtotime($paySession['token_expiration']));

    // Récupérer les locataires du contrat
    $locatairesStmt = $pdo->prepare("SELECT * FROM locataires WHERE contrat_id = ? ORDER BY ordre");
    $locatairesStmt->execute([$contratId]);
    $locataires = $locatairesStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($locataires)) {
        logMsg("Contrat $contratId : aucun locataire trouvé - ignoré.", true);
        continue;
    }

    $montantLoyer   = number_format((float)$contrat['loyer'], 2, ',', ' ');
    $montantCharges = number_format((float)$contrat['charges'], 2, ',', ' ');
    $montantTotal   = number_format($montant, 2, ',', ' ');
    $signature      = getParameter('email_signature', '');

    // Choisir le template selon le contexte
    $templateId = ($doInvitation && !$paySession['email_invitation_envoye'])
        ? 'stripe_invitation_paiement'
        : 'stripe_rappel_paiement';

    // Si invitation déjà envoyée et aujourd'hui = jour invitation, on ne renvoie pas
    if ($doInvitation && $paySession['email_invitation_envoye'] && !$doRappel) {
        logMsg("Contrat $contratId : invitation déjà envoyée ce mois - ignoré.");
        continue;
    }

    // Si c'est un jour de rappel seulement (pas d'invitation), utiliser le template rappel
    if (!$doInvitation && $doRappel) {
        $templateId = 'stripe_rappel_paiement';
    }

    foreach ($locataires as $locataire) {
        $sent = sendTemplatedEmail($templateId, $locataire['email'], [
            'locataire_nom'     => $locataire['nom'],
            'locataire_prenom'  => $locataire['prenom'],
            'adresse'           => $contrat['adresse'],
            'periode'           => $periode,
            'montant_loyer'     => $montantLoyer,
            'montant_charges'   => $montantCharges,
            'montant_total'     => $montantTotal,
            'lien_paiement'     => $lienPaiement,
            'date_expiration'   => $dateExpiration,
            'signature'         => $signature,
        ], null, false, true, ['contexte' => 'stripe_' . $templateId]);

        if ($sent) {
            logMsg("Email $templateId envoyé à {$locataire['email']} pour contrat $contratId ($periode)");
        } else {
            logMsg("Échec envoi email $templateId à {$locataire['email']}", true);
        }
    }

    // Mettre à jour le flag d'invitation si c'est l'invitation initiale
    if ($doInvitation && !$paySession['email_invitation_envoye']) {
        $pdo->prepare("
            UPDATE stripe_payment_sessions
            SET email_invitation_envoye = 1, date_email_invitation = NOW()
            WHERE id = ?
        ")->execute([$paySession['id']]);
    }
}

// ─── Enregistrer le résultat dans cron_jobs ─────────────────────────────────
try {
    $cronLogsStr = implode('', $cronLogs);
    $pdo->prepare("
        UPDATE cron_jobs
        SET last_run = NOW(),
            last_result = ?
        WHERE fichier = 'cron/stripe-paiements.php'
    ")->execute([mb_substr($cronLogsStr, 0, 65000)]);
} catch (Exception $e) {
    error_log('stripe-paiements cron: erreur mise à jour last_run - ' . $e->getMessage());
}

logMsg('Cron stripe-paiements terminé.');
exit(0);
