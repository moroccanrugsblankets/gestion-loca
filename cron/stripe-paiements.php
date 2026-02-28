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
 *   - stripe_rappel_mois_arrieres_max : Nombre max de mois passés non payés à rappeler (défaut: 3)
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
logMsg("--- REQUÊTE 1 : Récupération des contrats actifs (statut=valide, date_prise_effet <= CURDATE()) ---");
$contrats = $pdo->query("
    SELECT c.id as contrat_id, c.reference_unique, c.date_prise_effet,
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

logMsg("--- RÉSULTAT 1 : " . count($contrats) . " contrat(s) actif(s) trouvé(s) ---");
foreach ($contrats as $c) {
    logMsg("  Contrat id={$c['contrat_id']} | logement_id={$c['logement_id']} | adresse={$c['adresse']} | date_prise_effet={$c['date_prise_effet']} | loyer={$c['loyer']} | charges={$c['charges']}");
}

logMsg(count($contrats) . ' contrat(s) actif(s) trouvé(s).');

$nomsMois = [
    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
];

$liensExpirationHeures = (int)getParameter('stripe_lien_expiration_heures', 168);
$maxMoisArrieres = (int)getParameter('stripe_rappel_mois_arrieres_max', 3);
$siteUrl = rtrim($config['SITE_URL'], '/');

// ─── Traitement de chaque contrat ───────────────────────────────────────────
foreach ($contrats as $contrat) {
    $contratId  = $contrat['contrat_id'];
    $logementId = $contrat['logement_id'];
    $montant    = (float)$contrat['loyer'] + (float)$contrat['charges'];

    // Récupérer les locataires du contrat (commun à tous les mois)
    logMsg("--- REQUÊTE 2 : Récupération des locataires pour contrat_id=$contratId (logement_id=$logementId) ---");
    $locatairesStmt = $pdo->prepare("SELECT * FROM locataires WHERE contrat_id = ? ORDER BY ordre");
    $locatairesStmt->execute([$contratId]);
    $locataires = $locatairesStmt->fetchAll(PDO::FETCH_ASSOC);
    logMsg("--- RÉSULTAT 2 : " . count($locataires) . " locataire(s) pour contrat_id=$contratId ---");
    foreach ($locataires as $loc) {
        logMsg("  Locataire id={$loc['id']} | nom={$loc['nom']} {$loc['prenom']} | email={$loc['email']}");
    }

    if (empty($locataires)) {
        logMsg("Contrat $contratId : aucun locataire trouvé - ignoré.", true);
        continue;
    }

    $montantLoyer   = number_format((float)$contrat['loyer'], 2, ',', ' ');
    $montantCharges = number_format((float)$contrat['charges'], 2, ',', ' ');
    $montantTotal   = number_format($montant, 2, ',', ' ');
    $signature      = getParameter('email_signature', '');

    // ── Construire la liste des mois à traiter : mois antérieurs non payés + mois courant ──
    $monthsToProcess = [];

    // Récupérer tous les statuts de paiement des mois passés en une seule requête
    // Inclut aussi les entrées soft-deleted pour ne pas relancer des rappels sur des mois déjà payés
    logMsg("--- REQUÊTE 3 : Récupération des mois passés (loyers_tracking) pour logement_id=$logementId avant $anneeActuelle-$moisActuel ---");
    $pastTrackingStmt = $pdo->prepare("
        SELECT mois, annee, statut_paiement
        FROM loyers_tracking
        WHERE logement_id = ?
          AND (annee < ? OR (annee = ? AND mois < ?))
    ");
    $pastTrackingStmt->execute([$logementId, $anneeActuelle, $anneeActuelle, $moisActuel]);
    $paidMonths = [];
    foreach ($pastTrackingStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        logMsg("  loyers_tracking : mois={$row['mois']}/{$row['annee']} statut={$row['statut_paiement']}");
        if ($row['statut_paiement'] === 'paye') {
            $paidMonths[$row['annee'] . '-' . $row['mois']] = true;
        }
    }
    logMsg("--- RÉSULTAT 3 : mois déjà payés = [" . implode(', ', array_keys($paidMonths)) . "] ---");

    // Mois antérieurs non payés : itérer depuis la date de prise d'effet du contrat
    // afin d'inclure les mois sans entrée dans loyers_tracking (ex. module Stripe récemment activé)
    if (!empty($contrat['date_prise_effet'])) {
        logMsg("--- Contrat $contratId : itération des mois depuis date_prise_effet={$contrat['date_prise_effet']} jusqu'à $anneeActuelle-$moisActuel ---");
        try {
            $iterDate  = new DateTime($contrat['date_prise_effet']);
            $iterDate->modify('first day of this month');
            $limitDate = new DateTime(sprintf('%04d-%02d-01', $anneeActuelle, $moisActuel));
        } catch (Exception $e) {
            logMsg("Contrat $contratId : date_prise_effet invalide ({$contrat['date_prise_effet']}) - " . $e->getMessage(), true);
            $iterDate = null;
        }
        if ($iterDate !== null) {
            while ($iterDate < $limitDate) {
                $iterMois  = (int)$iterDate->format('n');
                $iterAnnee = (int)$iterDate->format('Y');
                if (!isset($paidMonths[$iterAnnee . '-' . $iterMois])) {
                    $monthsToProcess[] = ['mois' => $iterMois, 'annee' => $iterAnnee, 'is_past' => true];
                }
                $iterDate->modify('+1 month');
            }
        }
    } else {
        // Fallback si pas de date_prise_effet : utiliser les entrées existantes
        logMsg("--- REQUÊTE 3b (fallback) : Récupération des mois non payés depuis loyers_tracking pour contrat_id=$contratId ---");
        $pastFallbackStmt = $pdo->prepare("
            SELECT mois, annee
            FROM loyers_tracking
            WHERE contrat_id = ? AND statut_paiement != 'paye' AND deleted_at IS NULL
              AND (annee < ? OR (annee = ? AND mois < ?))
            ORDER BY annee ASC, mois ASC
        ");
        $pastFallbackStmt->execute([$contratId, $anneeActuelle, $anneeActuelle, $moisActuel]);
        foreach ($pastFallbackStmt->fetchAll(PDO::FETCH_ASSOC) as $pm) {
            logMsg("  Fallback mois non payé : {$pm['mois']}/{$pm['annee']}");
            $monthsToProcess[] = ['mois' => (int)$pm['mois'], 'annee' => (int)$pm['annee'], 'is_past' => true];
        }
    }

    // Limiter le nombre de mois passés non payés à traiter (pour éviter les envois massifs)
    if ($maxMoisArrieres > 0 && count($monthsToProcess) > $maxMoisArrieres) {
        logMsg("--- Limitation des mois passés : " . count($monthsToProcess) . " → $maxMoisArrieres (stripe_rappel_mois_arrieres_max=$maxMoisArrieres) ---");
        $monthsToProcess = array_slice($monthsToProcess, -$maxMoisArrieres);
    }

    // Mois courant
    $monthsToProcess[] = ['mois' => $moisActuel, 'annee' => $anneeActuelle, 'is_past' => false];

    logMsg("--- Contrat $contratId : mois à traiter = " . count($monthsToProcess) . " ---");
    foreach ($monthsToProcess as $me) {
        logMsg("  → mois={$me['mois']}/{$me['annee']} | is_past=" . ($me['is_past'] ? 'oui' : 'non (mois courant)'));
    }

    foreach ($monthsToProcess as $monthEntry) {
        $mois   = $monthEntry['mois'];
        $annee  = $monthEntry['annee'];
        $isPast = $monthEntry['is_past'];
        $periode = $nomsMois[$mois] . ' ' . $annee;

        logMsg("=== Contrat $contratId | Traitement de la période : $periode (is_past=" . ($isPast ? 'oui' : 'non') . ") ===");

        // Vérifier si le loyer du mois est déjà payé
        logMsg("--- REQUÊTE 4 : Vérification loyers_tracking pour contrat_id=$contratId mois=$mois annee=$annee (deleted_at IS NULL) ---");
        $ltStmt = $pdo->prepare("
            SELECT id, statut_paiement
            FROM loyers_tracking
            WHERE contrat_id = ? AND mois = ? AND annee = ? AND deleted_at IS NULL
            LIMIT 1
        ");
        $ltStmt->execute([$contratId, $mois, $annee]);
        $lt = $ltStmt->fetch(PDO::FETCH_ASSOC);
        logMsg("--- RÉSULTAT 4 : loyers_tracking " . ($lt ? "id={$lt['id']} statut={$lt['statut_paiement']}" : "AUCUNE ENTRÉE") . " ---");

        if ($lt && $lt['statut_paiement'] === 'paye') {
            logMsg("Contrat $contratId ($contrat[adresse]) : loyer $periode déjà payé - ignoré.");
            continue;
        }

        // Créer une entrée de tracking si elle n'existe pas
        if (!$lt) {
            logMsg("--- REQUÊTE 5 : INSERT loyers_tracking pour contrat_id=$contratId mois=$mois annee=$annee montant=$montant ---");
            $pdo->prepare("
                INSERT INTO loyers_tracking (logement_id, contrat_id, mois, annee, montant_attendu, statut_paiement)
                VALUES (?, ?, ?, ?, ?, 'attente')
                ON DUPLICATE KEY UPDATE
                    statut_paiement = IF(contrat_id != VALUES(contrat_id), 'attente', statut_paiement),
                    contrat_id = VALUES(contrat_id),
                    montant_attendu = VALUES(montant_attendu),
                    deleted_at = NULL
            ")->execute([$logementId, $contratId, $mois, $annee, $montant]);

            $ltStmt->execute([$contratId, $mois, $annee]);
            $lt = $ltStmt->fetch(PDO::FETCH_ASSOC);
            logMsg("--- RÉSULTAT 5 : loyers_tracking créé/mis à jour → " . ($lt ? "id={$lt['id']} statut={$lt['statut_paiement']}" : "ÉCHEC RÉCUPÉRATION") . " ---");
        }

        if (!$lt || empty($lt['id'])) {
            logMsg("Contrat $contratId : impossible de récupérer l'entrée de tracking pour $periode - ignoré.", true);
            continue;
        }

        $ltId = $lt['id'];

        // Récupérer ou créer la session de paiement Stripe
        logMsg("--- REQUÊTE 6 : Recherche session Stripe pour contrat_id=$contratId mois=$mois annee=$annee (statut NOT IN paye/annule) ---");
        $sessionStmt = $pdo->prepare("
            SELECT * FROM stripe_payment_sessions
            WHERE contrat_id = ? AND mois = ? AND annee = ?
              AND statut NOT IN ('paye', 'annule')
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $sessionStmt->execute([$contratId, $mois, $annee]);
        $paySession = $sessionStmt->fetch(PDO::FETCH_ASSOC);
        if ($paySession) {
            logMsg("--- RÉSULTAT 6 : Session Stripe id={$paySession['id']} statut={$paySession['statut']} email_invitation_envoye={$paySession['email_invitation_envoye']} token_expiration={$paySession['token_expiration']} ---");
        } else {
            logMsg("--- RÉSULTAT 6 : Aucune session Stripe active trouvée ---");
        }

        // Créer une nouvelle session si nécessaire ou si le lien est expiré
        if (!$paySession || strtotime($paySession['token_expiration']) < time()) {
            $raison = !$paySession ? 'aucune session existante' : 'session expirée (expiration=' . $paySession['token_expiration'] . ')';
            logMsg("--- REQUÊTE 7 : INSERT stripe_payment_sessions pour contrat_id=$contratId mois=$mois annee=$annee ($raison) ---");
            $token = bin2hex(random_bytes(32));
            $expiration = date('Y-m-d H:i:s', time() + $liensExpirationHeures * 3600);

            $pdo->prepare("
                INSERT INTO stripe_payment_sessions
                    (loyer_tracking_id, contrat_id, logement_id, mois, annee, montant, token_acces, token_expiration, statut)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'en_attente')
            ")->execute([$ltId, $contratId, $logementId, $mois, $annee, $montant, $token, $expiration]);

            $sessionStmt->execute([$contratId, $mois, $annee]);
            $paySession = $sessionStmt->fetch(PDO::FETCH_ASSOC);

            if (!$paySession) {
                logMsg("Erreur création session de paiement pour contrat $contratId ($periode)", true);
                continue;
            }
            logMsg("--- RÉSULTAT 7 : Nouvelle session créée id={$paySession['id']} expiration=$expiration ---");
        }

        $lienPaiement   = $siteUrl . '/payment/pay.php?token=' . urlencode($paySession['token_acces']);
        $dateExpiration = date('d/m/Y à H:i', strtotime($paySession['token_expiration']));

        // Choisir le template selon le contexte
        // Les mois passés sont toujours des rappels ; le mois courant suit la logique invitation/rappel
        if ($isPast) {
            // Éviter les doublons : ne pas renvoyer si un email a été envoyé il y a moins de 6 jours
            if ($paySession['email_invitation_envoye'] && !empty($paySession['date_email_invitation'])) {
                $joursSinceEmail = (int)floor((time() - strtotime($paySession['date_email_invitation'])) / 86400);
                if ($joursSinceEmail < 6) {
                    logMsg("Contrat $contratId ({$contrat['adresse']}) : rappel $periode déjà envoyé il y a {$joursSinceEmail}j - ignoré.");
                    continue;
                }
            }
            $templateId = 'stripe_rappel_paiement';
            logMsg("--- Choix template : $templateId (mois passé) ---");
        } elseif ($doInvitation && !$paySession['email_invitation_envoye']) {
            $templateId = 'stripe_invitation_paiement';
            logMsg("--- Choix template : $templateId (doInvitation=oui, email_invitation_envoye=non) ---");
        } else {
            // Sur un jour de rappel uniquement, ignorer si l'invitation n'a pas encore été envoyée
            if ($doRappel && !$doInvitation && !$paySession['email_invitation_envoye']) {
                logMsg("Contrat $contratId ({$contrat['adresse']}) : rappel $periode ignoré (invitation non encore envoyée).");
                continue;
            }
            $templateId = 'stripe_rappel_paiement';
            logMsg("--- Choix template : $templateId (doInvitation=" . ($doInvitation ? 'oui' : 'non') . ", doRappel=" . ($doRappel ? 'oui' : 'non') . ", email_invitation_envoye={$paySession['email_invitation_envoye']}) ---");
        }

        // Si invitation déjà envoyée ce mois et aujourd'hui = jour invitation sans rappel, ignorer
        if (!$isPast && $doInvitation && $paySession['email_invitation_envoye'] && !$doRappel) {
            logMsg("Contrat $contratId : invitation déjà envoyée ce mois - ignoré.");
            continue;
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

        // Mettre à jour le flag d'invitation si c'est l'invitation initiale du mois courant,
        // ou mettre à jour la date du dernier email pour les mois passés (throttle anti-doublons)
        if ($isPast || (!$isPast && $doInvitation && !$paySession['email_invitation_envoye'])) {
            logMsg("--- REQUÊTE 8 : UPDATE stripe_payment_sessions SET email_invitation_envoye=1 WHERE id={$paySession['id']} ---");
            $pdo->prepare("
                UPDATE stripe_payment_sessions
                SET email_invitation_envoye = 1, date_email_invitation = NOW()
                WHERE id = ?
            ")->execute([$paySession['id']]);
            logMsg("--- RÉSULTAT 8 : flag email_invitation_envoye mis à 1 pour session id={$paySession['id']} ---");
        }
    } // end foreach monthsToProcess
}

// ─── Enregistrer le résultat dans cron_jobs ─────────────────────────────────
try {
    logMsg("--- REQUÊTE 9 : UPDATE cron_jobs SET last_run=NOW() WHERE fichier='cron/stripe-paiements.php' ---");
    $cronLogsStr = implode('', $cronLogs);
    $pdo->prepare("
        UPDATE cron_jobs
        SET last_run = NOW(),
            last_result = ?
        WHERE fichier = 'cron/stripe-paiements.php'
    ")->execute([mb_substr($cronLogsStr, 0, 65000)]);
    logMsg("--- RÉSULTAT 9 : cron_jobs mis à jour ---");
} catch (Exception $e) {
    error_log('stripe-paiements cron: erreur mise à jour last_run - ' . $e->getMessage());
}

logMsg('Cron stripe-paiements terminé.');
exit(0);
