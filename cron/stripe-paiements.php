#!/usr/bin/env php
<?php
/**
 * CRON JOB: Envoi automatique des invitations et rappels de paiement Stripe
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
function logSection(string $title): void {
    logMsg("========== $title ==========");
}
function logStep(string $msg): void {
    logMsg("---- $msg ----");
}

// ─── Vérifications préalables ───────────────────────────────────────────────
$stripeActif = getParameter('stripe_actif', false);
if (!$stripeActif) {
    logMsg('Module Stripe inactif. Arrêt du cron.');
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
$aujourdHui = (int)date('j');
$moisActuel = (int)date('n');
$anneeActuelle = (int)date('Y');

$jourInvitation = (int)getParameter('stripe_paiement_invitation_jour', 1);
$joursRappel    = getParameter('stripe_paiement_rappel_jours', [7, 14]);
if (!is_array($joursRappel)) $joursRappel = [7, 14];

$doInvitation = ($aujourdHui === $jourInvitation);
$doRappel     = in_array($aujourdHui, $joursRappel, true);

if (!$doInvitation && !$doRappel) {
    logMsg("Aucune action prévue pour le jour $aujourdHui.");
    exit(0);
}

logSection("Démarrage du cron - mode=$stripeMode, jour=$aujourdHui");

// ─── Récupérer les contrats actifs ─────────────────────────────────────────
$contrats = $pdo->query("
    SELECT c.id as contrat_id, c.date_prise_effet,
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
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($contrats)) {
    logMsg('Aucun contrat actif trouvé.');
    exit(0);
}

$nomsMois = [1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'];
$liensExpirationHeures = (int)getParameter('stripe_lien_expiration_heures', 168);
$siteUrl = rtrim($config['SITE_URL'], '/');

// ─── Traitement de chaque contrat ───────────────────────────────────────────
foreach ($contrats as $contrat) {
    $contratId  = $contrat['contrat_id'];
    $logementId = $contrat['logement_id'];
    $montant    = (float)$contrat['loyer'] + (float)$contrat['charges'];

    logSection("Traitement contrat $contratId - logement $logementId");

    // Locataires
    $locatairesStmt = $pdo->prepare("SELECT * FROM locataires WHERE contrat_id = ? ORDER BY ordre");
    $locatairesStmt->execute([$contratId]);
    $locataires = $locatairesStmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($locataires)) {
        logStep("Aucun locataire trouvé → SKIP");
        continue;
    }

    $montantLoyer   = number_format((float)$contrat['loyer'], 2, ',', ' ');
    $montantCharges = number_format((float)$contrat['charges'], 2, ',', ' ');
    $montantTotal   = number_format($montant, 2, ',', ' ');
    $signature      = getParameter('email_signature', '');

    // Mois à traiter (ici simplifié : uniquement mois courant + mois passés déjà gérés ailleurs)
    $monthsToProcess = [['mois'=>$moisActuel,'annee'=>$anneeActuelle,'is_past'=>false]];

    // Préparer requête loyers_tracking
    $ltStmt = $pdo->prepare("
        SELECT id, statut_paiement, date_email_invitation
        FROM loyers_tracking
        WHERE contrat_id = ? AND mois = ? AND annee = ? AND deleted_at IS NULL
        LIMIT 1
    ");

    foreach ($monthsToProcess as $monthEntry) {
        $mois   = $monthEntry['mois'];
        $annee  = $monthEntry['annee'];
        $isPast = $monthEntry['is_past'];
        $periode = $nomsMois[$mois] . ' ' . $annee;

        logStep("Mois $mois/$annee → période $periode");

        $ltStmt->execute([$contratId, $mois, $annee]);
        $lt = $ltStmt->fetch(PDO::FETCH_ASSOC);
        if ($lt && $lt['statut_paiement'] === 'paye') {
            logStep("Déjà payé → SKIP");
            continue;
        }

        // Anti-doublon : vérifier si déjà envoyé aujourd'hui
        $todayDate = date('Y-m-d');
        $derniereDate = (!empty($lt['date_email_invitation']))
            ? date('Y-m-d', strtotime($lt['date_email_invitation']))
            : null;
        if ($derniereDate === $todayDate) {
            logStep("Déjà envoyé aujourd'hui → SKIP");
            continue;
        }

        // Choix du template
        if ($doInvitation && !$isPast) {
            $templateId = 'stripe_invitation_paiement';
            logStep("Template choisi = INVITATION (jour d'invitation)");
        } else {
            $templateId = 'stripe_rappel_paiement';
            logStep("Template choisi = RAPPEL");
        }

        // Envoi du mail
        foreach ($locataires as $locataire) {
            $sent = sendTemplatedEmail($templateId, $locataire['email'], [
                'locataire_nom'     => $locataire['nom'],
                'locataire_prenom'  => $locataire['prenom'],
                'adresse'           => $contrat['adresse'],
                'periode'           => $periode,
                'montant_loyer'     => $montantLoyer,
                'montant_charges'   => $montantCharges,
                'montant_total'     => $montantTotal,
                'lien_paiement'     => $siteUrl.'/payment/pay.php?token=xxx',
                'date_expiration'   => date('d/m/Y à H:i', time()+$liensExpirationHeures*3600),
                'signature'         => $signature,
            ]);
            logStep($sent ? "Email $templateId envoyé à {$locataire['email']}" : "Échec envoi email $templateId à {$locataire['email']}");
        }
    }
}

// ─── Enregistrer le résultat ───────────────────────────────────────────────
try {
    $cronLogsStr = implode('', $cronLogs);
    $pdo->prepare("
        UPDATE cron_jobs
        SET last_run = NOW(), last_result = ?
        WHERE fichier = 'cron/stripe-paiements.php'
    ")->execute([mb_substr($cronLogsStr, 0, 65000)]);
} catch (Exception $e) {
    error_log('Erreur mise à jour cron
