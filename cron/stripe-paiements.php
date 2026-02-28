#!/usr/bin/env php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mail-templates.php';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    exit("Stripe SDK manquant\n");
}
require_once $autoload;

function logMsg(string $msg, bool $isError = false): void {
    $level = $isError ? '[ERROR]' : '[INFO]';
    echo "<pre>[".date('Y-m-d H:i:s')."] $level $msg</pre>";
}
function logSection(string $title): void { logMsg("========== $title =========="); }
function logStep(string $msg): void { logMsg("---- $msg ----"); }

$stripeActif = getParameter('stripe_actif', false);
if (!$stripeActif) { logMsg('Module Stripe inactif.'); exit; }

$stripeMode = getParameter('stripe_mode', 'test');
$stripeSecretKey = ($stripeMode === 'live')
    ? getParameter('stripe_secret_key_live', '')
    : getParameter('stripe_secret_key_test', '');
if (empty($stripeSecretKey)) { logMsg('Clé Stripe manquante', true); exit; }
\Stripe\Stripe::setApiKey($stripeSecretKey);

$aujourdHui = (int)date('j');
$moisActuel = (int)date('n');
$anneeActuelle = (int)date('Y');

$jourInvitation = (int)getParameter('stripe_paiement_invitation_jour', 1);
$joursRappel    = getParameter('stripe_paiement_rappel_jours', [7, 14]);
if (!is_array($joursRappel)) $joursRappel = [7, 14];

$doInvitation = ($aujourdHui === $jourInvitation);
$doRappel     = in_array($aujourdHui, $joursRappel, true);

if (!$doInvitation && !$doRappel) { logMsg("Aucune action prévue aujourd'hui."); exit; }

logSection("Démarrage du cron - mode=$stripeMode, jour=$aujourdHui");

$sqlContrats = "
    SELECT c.id as contrat_id, c.date_prise_effet,
           l.id as logement_id, l.adresse, l.loyer, l.charges
    FROM contrats c
    INNER JOIN logements l ON c.logement_id = l.id
    INNER JOIN (
        SELECT logement_id, MAX(id) AS max_id
        FROM contrats
        WHERE statut = 'valide'
          AND date_prise_effet <= CURDATE()
        GROUP BY logement_id
    ) actifs ON c.id = actifs.max_id
";
$contrats = $pdo->query($sqlContrats)->fetchAll(PDO::FETCH_ASSOC);

$nomsMois = [1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'];
$siteUrl = rtrim($config['SITE_URL'], '/');

foreach ($contrats as $contrat) {
    $contratId  = $contrat['contrat_id'];
    logSection("Contrat $contratId");

    $sqlLocataires = "SELECT * FROM locataires WHERE contrat_id = ?";
    $locatairesStmt = $pdo->prepare($sqlLocataires);
    $locatairesStmt->execute([$contratId]);
    $locataires = $locatairesStmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($locataires)) { logStep("Pas de locataires"); continue; }

    $sqlImpayes = "
        SELECT DISTINCT mois, annee
        FROM loyers_tracking
        WHERE contrat_id = ? 
          AND statut_paiement != 'paye'
          AND deleted_at IS NULL
        ORDER BY annee, mois
    ";
    $impayesStmt = $pdo->prepare($sqlImpayes);
    $impayesStmt->execute([$contratId]);
    $monthsToProcess = $impayesStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($monthsToProcess as $monthEntry) {
        $mois   = (int)$monthEntry['mois'];
        $annee  = (int)$monthEntry['annee'];
        $isPast = ($annee < $anneeActuelle) || ($annee == $anneeActuelle && $mois < $moisActuel);
        $periode = $nomsMois[$mois] . ' ' . $annee;

        logStep("Traitement période $periode");

        if ($doInvitation && !$isPast) {
            $templateId = 'stripe_invitation_paiement';
            logStep("Template choisi = INVITATION");
        } else {
            $templateId = 'stripe_rappel_paiement';
            logStep("Template choisi = RAPPEL");
        }

        foreach ($locataires as $locataire) {
            logStep("Préparation envoi pour {$locataire['email']} (contrat=$contratId, période=$periode)");

            $sent = sendTemplatedEmail(
                $templateId,
                $locataire['email'],
                [
                    'locataire_nom'     => $locataire['nom'],
                    'locataire_prenom'  => $locataire['prenom'],
                    'adresse'           => $contrat['adresse'],
                    'periode'           => $periode,
                    'montant_loyer'     => number_format((float)$contrat['loyer'], 2, ',', ' '),
                    'montant_charges'   => number_format((float)$contrat['charges'], 2, ',', ' '),
                    'montant_total'     => number_format((float)$contrat['loyer'] + (float)$contrat['charges'], 2, ',', ' '),
                    'lien_paiement'     => $siteUrl.'/payment/pay.php?token=xxx',
                    'date_expiration'   => date('d/m/Y à H:i', time()+168*3600),
                    'signature'         => getParameter('email_signature', ''),
                ],
                null,       // attachmentPath
                false,      // isAdminEmail
                true,       // addAdminBcc
                ['debug' => "contrat=$contratId;periode=$periode;locataire={$locataire['email']}"]
            );

            if ($sent) {
                logStep("✅ Email $templateId envoyé à {$locataire['email']} pour $periode (admins en BCC)");
            } else {
                logStep("❌ Échec envoi email $templateId à {$locataire['email']} pour $periode");
            }
        }
    }
}

logSection('Cron stripe-paiements terminé');
