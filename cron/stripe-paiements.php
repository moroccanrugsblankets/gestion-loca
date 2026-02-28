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

$contrats = $pdo->query("
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
")->fetchAll(PDO::FETCH_ASSOC);

$nomsMois = [1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'];

foreach ($contrats as $contrat) {
    $contratId  = $contrat['contrat_id'];
    logSection("Contrat $contratId");

    $locatairesStmt = $pdo->prepare("SELECT * FROM locataires WHERE contrat_id = ?");
    $locatairesStmt->execute([$contratId]);
    $locataires = $locatairesStmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($locataires)) { logStep("Pas de locataires"); continue; }

    // Construire la liste des mois depuis la prise d'effet
    $dateDebut = new DateTime($contrat['date_prise_effet']);
    $dateCourante = new DateTime();
    $ltStmt = $pdo->prepare("
        SELECT statut_paiement
        FROM loyers_tracking
        WHERE contrat_id = ? AND mois = ? AND annee = ? AND deleted_at IS NULL
        LIMIT 1
    ");

    while ($dateDebut <= $dateCourante) {
        $mois = (int)$dateDebut->format('n');
        $annee = (int)$dateDebut->format('Y');
        $isPast = ($annee < $anneeActuelle) || ($annee == $anneeActuelle && $mois < $moisActuel);
        $periode = $nomsMois[$mois] . ' ' . $annee;

        $ltStmt->execute([$contratId, $mois, $annee]);
        $lt = $ltStmt->fetch(PDO::FETCH_ASSOC);

        // Filtrer uniquement les mois impayés
        if ($lt && $lt['statut_paiement'] === 'paye') {
            logStep("$periode déjà payé → SKIP");
            $dateDebut->modify('+1 month');
            continue;
        }

        // Choix du template
        if ($doInvitation && !$isPast) {
            $templateId = 'stripe_invitation_paiement';
        } else {
            $templateId = 'stripe_rappel_paiement';
        }

        foreach ($locataires as $locataire) {
            echo "<p>[DEBUG] Objet du mail : <b>$templateId</b> → Destinataire : {$locataire['email']} → Période : $periode</p>";
            logStep("Simulation envoi : $templateId à {$locataire['email']} pour $periode");
        }

        $dateDebut->modify('+1 month');
    }
}

logSection('Cron stripe-paiements terminé');
