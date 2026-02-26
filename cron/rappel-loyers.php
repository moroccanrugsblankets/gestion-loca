#!/usr/bin/env php
<?php
/**
 * CRON JOB: Rappel automatique des loyers
 * 
 * Ce script envoie automatiquement des rappels aux administrateurs
 * concernant l'état des paiements de loyers.
 * 
 * Fonctionnement:
 * 1. Vérifie si aujourd'hui est un jour de rappel configuré
 * 2. Récupère tous les logements en location avec leur statut de paiement du mois
 * 3. Détermine si tous les loyers sont payés ou s'il y a des impayés
 * 4. Envoie l'email approprié aux administrateurs configurés
 * 
 * Configuration:
 * - Jours d'envoi: Paramètre 'rappel_loyers_dates_envoi' (défaut: [7, 9, 15])
 * - Destinataires: Paramètre 'rappel_loyers_destinataires'
 * - Actif/Inactif: Paramètre 'rappel_loyers_actif'
 * 
 * Usage:
 *   php cron/rappel-loyers.php
 *   
 * Cron expression recommandée: 0 9 * * * (tous les jours à 9h)
 */

// Configuration
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mail-templates.php';

// Log file
$logFile = __DIR__ . '/rappel-loyers-log.txt';

// Collector for DB log storage
$cronLogs = [];

/**
 * Log un message avec timestamp
 */
function logMessage($message, $isError = false) {
    global $logFile, $cronLogs;
    $timestamp = date('Y-m-d H:i:s');
    $prefix = $isError ? '[ERROR]' : '[INFO]';
    $logEntry = "[$timestamp] $prefix $message\n";
    
    echo $logEntry;
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    $cronLogs[] = $logEntry;
}

/**
 * Vérifie si un bien a un loyer payé pour le mois donné
 */
function estLoyerPaye($pdo, $logementId, $mois, $annee) {
    try {
        $stmt = $pdo->prepare("
            SELECT statut_paiement 
            FROM loyers_tracking 
            WHERE logement_id = ? AND mois = ? AND annee = ?
        ");
        $stmt->execute([$logementId, $mois, $annee]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result && $result['statut_paiement'] === 'paye';
    } catch (Exception $e) {
        logMessage("Erreur vérification paiement logement $logementId: " . $e->getMessage(), true);
        return false;
    }
}

/**
 * Crée automatiquement les entrées de tracking pour le mois si nécessaire
 */
function creerEntriesTrackingMoisCourant($pdo, $mois, $annee) {
    try {
        // Récupérer tous les logements avec leur dernier contrat actif (valide et en cours)
        $stmt = $pdo->query("
            SELECT l.id, l.loyer, l.charges, c.id as contrat_id
            FROM logements l
            INNER JOIN contrats c ON c.logement_id = l.id
            INNER JOIN (
                SELECT logement_id, MAX(id) AS max_contrat_id
                FROM contrats
                WHERE statut = 'valide'
                AND date_prise_effet IS NOT NULL
                AND date_prise_effet <= CURDATE()
                GROUP BY logement_id
            ) lc ON c.logement_id = lc.logement_id AND c.id = lc.max_contrat_id
        ");
        $logements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $created = 0;
        foreach ($logements as $logement) {
            // Vérifier si l'entrée existe déjà
            $check = $pdo->prepare("
                SELECT id FROM loyers_tracking 
                WHERE logement_id = ? AND mois = ? AND annee = ?
            ");
            $check->execute([$logement['id'], $mois, $annee]);
            
            if (!$check->fetch()) {
                // Créer l'entrée
                $insert = $pdo->prepare("
                    INSERT INTO loyers_tracking 
                    (logement_id, contrat_id, mois, annee, montant_attendu, statut_paiement)
                    VALUES (?, ?, ?, ?, ?, 'attente')
                ");
                $montantTotal = $logement['loyer'] + $logement['charges'];
                $insert->execute([
                    $logement['id'],
                    $logement['contrat_id'],
                    $mois,
                    $annee,
                    $montantTotal
                ]);
                $created++;
            }
        }
        
        if ($created > 0) {
            logMessage("Créées $created nouvelles entrées de tracking pour $mois/$annee");
        }
        
        return $created;
    } catch (Exception $e) {
        logMessage("Erreur création entries tracking: " . $e->getMessage(), true);
        return 0;
    }
}

/**
 * Génère le message de statut pour l'email
 * 
 * Le tableau est général sur TOUS les mois (pas seulement le mois courant).
 * - Le statut d'un logement est "impayé" si au moins un loyer est impayé sur n'importe quel mois.
 * - La somme des montants impayés est calculée sur tous les mois.
 */
function genererMessageStatut($pdo, $mois, $annee) {
    global $config;
    
    try {
        // Récupérer tous les biens avec contrat actif et leur statut agrégé sur TOUS les mois
        $stmt = $pdo->query("
            SELECT 
                l.reference,
                l.adresse,
                l.loyer,
                l.charges,
                (SELECT GROUP_CONCAT(CONCAT(loc.prenom, ' ', loc.nom) SEPARATOR ', ')
                 FROM locataires loc
                 INNER JOIN contrats c2 ON loc.contrat_id = c2.id
                 WHERE c2.logement_id = l.id AND c2.statut = 'valide'
                 AND c2.date_prise_effet IS NOT NULL AND c2.date_prise_effet <= CURDATE()
                 AND c2.id = (
                     SELECT id FROM contrats c3
                     WHERE c3.logement_id = l.id AND c3.statut = 'valide'
                     AND c3.date_prise_effet IS NOT NULL AND c3.date_prise_effet <= CURDATE()
                     ORDER BY c3.date_prise_effet DESC, c3.id DESC LIMIT 1
                 )) as locataires,
                COALESCE(SUM(CASE WHEN lt.statut_paiement = 'impaye' THEN lt.montant_attendu ELSE 0 END), 0) as montant_total_impaye,
                COUNT(CASE WHEN lt.statut_paiement = 'impaye' THEN 1 END) as nb_mois_impayes,
                COUNT(CASE WHEN lt.statut_paiement = 'attente' THEN 1 END) as nb_mois_attente,
                COUNT(CASE WHEN lt.statut_paiement = 'paye' THEN 1 END) as nb_mois_payes,
                CASE
                    WHEN COUNT(CASE WHEN lt.statut_paiement = 'impaye' THEN 1 END) > 0 THEN 'impaye'
                    WHEN COUNT(CASE WHEN lt.statut_paiement = 'attente' THEN 1 END) > 0 THEN 'attente'
                    ELSE 'paye'
                END as statut_global
            FROM logements l
            INNER JOIN contrats c ON c.logement_id = l.id
            INNER JOIN (
                SELECT logement_id, MAX(id) AS max_contrat_id
                FROM contrats
                WHERE statut = 'valide'
                AND date_prise_effet IS NOT NULL AND date_prise_effet <= CURDATE()
                GROUP BY logement_id
            ) dc ON c.id = dc.max_contrat_id
            LEFT JOIN loyers_tracking lt ON lt.logement_id = l.id AND lt.contrat_id = c.id AND lt.deleted_at IS NULL
            GROUP BY l.id, l.reference, l.adresse, l.loyer, l.charges
            ORDER BY l.reference
        ");
        $biens = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($biens)) {
            return [
                'tous_payes' => true,
                'message' => '<p><strong>Aucun bien en location actuellement.</strong></p>'
            ];
        }
        
        $nbTotal = count($biens);
        $nbPayes = 0;
        $nbImpayes = 0;
        $nbAttente = 0;
        $montantTotalImpaye = 0;
        
        $listeBiens = [];
        
        foreach ($biens as $bien) {
            $statusIcon = '⏳';
            $statusText = 'En attente';
            $statusColor = '#ffc107';
            
            if ($bien['statut_global'] === 'paye') {
                $nbPayes++;
                $statusIcon = '✅';
                $statusText = 'Payé';
                $statusColor = '#28a745';
            } elseif ($bien['statut_global'] === 'impaye') {
                $nbImpayes++;
                $statusIcon = '❌';
                $statusText = 'Impayé';
                $statusColor = '#dc3545';
                $montantTotalImpaye += $bien['montant_total_impaye'];
            } else {
                $nbAttente++;
            }
            
            $locataires = $bien['locataires'] ?: 'Non assigné';
            $montantImpaye = $bien['montant_total_impaye'];
            
            $detailImpaye = '';
            if ($bien['nb_mois_impayes'] > 0) {
                $detailImpaye = ' (' . number_format($montantImpaye, 2, ',', ' ') . ' €)';
            }
            
            $listeBiens[] = sprintf(
                '<tr>
                    <td style="padding: 10px; border: 1px solid #dee2e6;"><strong>%s</strong></td>
                    <td style="padding: 10px; border: 1px solid #dee2e6;">%s</td>
                    <td style="padding: 10px; border: 1px solid #dee2e6; text-align: center; background-color: %s; color: white; font-weight: bold;">%s %s%s</td>
                </tr>',
                htmlspecialchars($bien['reference']),
                htmlspecialchars($locataires),
                $statusColor,
                $statusIcon,
                $statusText,
                $detailImpaye
            );
        }
        
        $nomsMois = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
        ];
        
        $moisNom = $nomsMois[$mois] ?? $mois;
        
        $tousPayes = ($nbImpayes === 0 && $nbAttente === 0);
        
        $resume = sprintf(
            '<p><strong>Récapitulatif général (tous les mois) :</strong></p>
            <ul>
                <li>Total de biens en location: <strong>%d</strong></li>
                <li style="color: #28a745;">✅ Biens à jour: <strong>%d</strong></li>
                <li style="color: #dc3545;">❌ Biens avec loyers impayés: <strong>%d</strong></li>
                <li style="color: #ffc107;">⏳ Biens en attente: <strong>%d</strong></li>
                %s
            </ul>',
            $nbTotal,
            $nbPayes,
            $nbImpayes,
            $nbAttente,
            $montantTotalImpaye > 0 ? '<li style="color: #dc3545;"><strong>Total impayés: ' . number_format($montantTotalImpaye, 2, ',', ' ') . ' €</strong></li>' : ''
        );
        
        if ($tousPayes) {
            $message = $resume . '<p style="color: #28a745; font-size: 16px; font-weight: bold;">🎉 Excellente nouvelle ! Tous les loyers sont à jour.</p>';
        } else {
            $message = $resume . '<p style="color: #dc3545; font-size: 16px; font-weight: bold;">⚠️ Attention ! Il reste des loyers impayés ou en attente de confirmation.</p>';
        }
        
        $message .= '<table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
            <thead>
                <tr style="background-color: #f8f9fa;">
                    <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">Référence</th>
                    <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">Locataire(s)</th>
                    <th style="padding: 10px; border: 1px solid #dee2e6; text-align: center;">Statut global</th>
                </tr>
            </thead>
            <tbody>' . implode('', $listeBiens) . '</tbody>
        </table>';
        
        return [
            'tous_payes' => $tousPayes,
            'message' => $message,
            'nb_total' => $nbTotal,
            'nb_payes' => $nbPayes,
            'nb_impayes' => $nbImpayes
        ];
        
    } catch (Exception $e) {
        logMessage("Erreur génération message statut: " . $e->getMessage(), true);
        return [
            'tous_payes' => false,
            'message' => '<p>Erreur lors de la récupération des données de paiement.</p>'
        ];
    }
}

/**
 * Envoie le rappel aux administrateurs
 */
function envoyerRappel($pdo, $destinataires, $statusInfo, $mois, $annee) {
    global $config;
    
    try {
        // Déterminer le template à utiliser
        $templateId = $statusInfo['tous_payes'] ? 'confirmation_loyers_payes' : 'rappel_loyers_impaye';
        
        // Récupérer le template
        $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE identifiant = ?");
        $stmt->execute([$templateId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$template) {
            logMessage("Template email '$templateId' introuvable", true);
            return false;
        }
        
        // Vérifier si on doit inclure le bouton
        $inclureBouton = getParameter('rappel_loyers_inclure_bouton', true);
        
        $boutonHtml = '';
        if ($inclureBouton) {
            $urlInterface = rtrim($config['SITE_URL'], '/') . '/admin-v2/gestion-loyers.php';
            $boutonHtml = '<div style="text-align: center;">
                <a href="' . htmlspecialchars($urlInterface) . '" class="btn" style="display: inline-block; padding: 12px 30px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0;">
                    📊 Accéder à l\'interface de gestion
                </a>
            </div>';
        }
        
        // Récupérer la signature email
        $signature = getParameter('email_signature', '');
        
        // Remplacer les variables
        $corps = $template['corps_html'];
        $corps = str_replace('{{status_paiements}}', $statusInfo['message'], $corps);
        $corps = str_replace('{{bouton_interface}}', $boutonHtml, $corps);
        $corps = str_replace('{{signature}}', $signature, $corps);
        
        $sujet = $template['sujet'];
        
        // Envoyer à chaque destinataire
        $envoyesOk = 0;
        $envoyesErreur = 0;
        
        foreach ($destinataires as $destinataire) {
            if (!filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
                logMessage("Email invalide ignoré: $destinataire", true);
                $envoyesErreur++;
                continue;
            }
            
            try {
                $result = sendEmail(
                    $destinataire,
                    $sujet,
                    $corps
                );
                
                if ($result) {
                    logMessage("Email envoyé avec succès à: $destinataire");
                    $envoyesOk++;
                } else {
                    logMessage("Échec envoi email à: $destinataire", true);
                    $envoyesErreur++;
                }
            } catch (Exception $e) {
                logMessage("Erreur envoi à $destinataire: " . $e->getMessage(), true);
                $envoyesErreur++;
            }
        }
        
        logMessage("Rappels envoyés: $envoyesOk réussi(s), $envoyesErreur échec(s)");
        
        return $envoyesOk > 0;
        
    } catch (Exception $e) {
        logMessage("Erreur envoi rappels: " . $e->getMessage(), true);
        return false;
    }
}

/**
 * Envoie le rappel aux locataires pour loyers impayés
 */
function envoyerRappelLocataires($pdo, $mois, $annee) {
    global $config;
    
    try {
        // Récupérer le template pour locataires
        $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE identifiant = 'rappel_loyer_impaye_locataire'");
        $stmt->execute();
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$template) {
            logMessage("Template email 'rappel_loyer_impaye_locataire' introuvable", true);
            return false;
        }
        
        // Récupérer les logements avec loyer impayé ou en attente (dernier contrat actif seulement)
        $stmt = $pdo->prepare("
            SELECT 
                l.id as logement_id,
                l.reference,
                l.adresse,
                l.loyer,
                l.charges,
                lt.statut_paiement,
                c.id as contrat_id
            FROM logements l
            INNER JOIN contrats c ON c.logement_id = l.id
            INNER JOIN (
                SELECT logement_id, MAX(id) AS max_contrat_id
                FROM contrats
                WHERE statut = 'valide'
                AND date_prise_effet IS NOT NULL
                AND date_prise_effet <= CURDATE()
                GROUP BY logement_id
            ) lc ON c.logement_id = lc.logement_id AND c.id = lc.max_contrat_id
            LEFT JOIN loyers_tracking lt ON lt.logement_id = l.id AND lt.contrat_id = c.id AND lt.mois = ? AND lt.annee = ?
            WHERE (lt.statut_paiement IN ('impaye', 'attente') OR lt.statut_paiement IS NULL)
        ");
        $stmt->execute([$mois, $annee]);
        $logements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($logements)) {
            logMessage("Aucun logement avec loyer impayé trouvé");
            return true;
        }
        
        logMessage("Trouvé " . count($logements) . " logement(s) avec loyer impayé");
        
        $nomsMois = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
        ];
        
        $periode = $nomsMois[$mois] . ' ' . $annee;
        $signature = getParameter('email_signature', '');
        
        $envoyesOk = 0;
        $envoyesErreur = 0;
        
        // Pour chaque logement, envoyer l'email à chaque locataire
        foreach ($logements as $logement) {
            // Récupérer les locataires du contrat
            $stmtLocataires = $pdo->prepare("
                SELECT email, nom, prenom
                FROM locataires
                WHERE contrat_id = ?
                AND email IS NOT NULL AND email != ''
            ");
            $stmtLocataires->execute([$logement['contrat_id']]);
            $locataires = $stmtLocataires->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($locataires)) {
                logMessage("Aucun locataire avec email trouvé pour logement " . $logement['reference']);
                continue;
            }
            
            $montantTotal = number_format($logement['loyer'] + $logement['charges'], 2, ',', ' ');
            
            // Envoyer à chaque locataire
            foreach ($locataires as $locataire) {
                if (!filter_var($locataire['email'], FILTER_VALIDATE_EMAIL)) {
                    logMessage("Email invalide pour locataire: " . $locataire['email'], true);
                    $envoyesErreur++;
                    continue;
                }
                
                try {
                    // Préparer les variables
                    $variables = [
                        'locataire_nom' => $locataire['nom'],
                        'locataire_prenom' => $locataire['prenom'],
                        'periode' => $periode,
                        'adresse' => $logement['adresse'],
                        'montant_total' => $montantTotal,
                        'signature' => $signature
                    ];
                    
                    // Remplacer les variables dans le template
                    $corps = $template['corps_html'];
                    $sujet = $template['sujet'];
                    
                    foreach ($variables as $key => $value) {
                        $corps = str_replace('{{' . $key . '}}', $value, $corps);
                        $sujet = str_replace('{{' . $key . '}}', $value, $sujet);
                    }
                    
                    // Envoyer l'email
                    $result = sendEmail(
                        $locataire['email'],
                        $sujet,
                        $corps
                    );
                    
                    if ($result) {
                        logMessage("Rappel envoyé à locataire: " . $locataire['prenom'] . " " . $locataire['nom'] . " (" . $locataire['email'] . ")");
                        $envoyesOk++;
                    } else {
                        logMessage("Échec envoi rappel à locataire: " . $locataire['email'], true);
                        $envoyesErreur++;
                    }
                } catch (Exception $e) {
                    logMessage("Erreur envoi à locataire " . $locataire['email'] . ": " . $e->getMessage(), true);
                    $envoyesErreur++;
                }
            }
        }
        
        logMessage("Rappels locataires: $envoyesOk réussi(s), $envoyesErreur échec(s)");
        
        return $envoyesOk > 0;
        
    } catch (Exception $e) {
        logMessage("Erreur envoi rappels locataires: " . $e->getMessage(), true);
        return false;
    }
}

/**
 * Met à jour le statut du cron job dans la table cron_jobs
 */
function mettreAJourCronJob($pdo, $statut, $log = '') {
    try {
        if ($statut === 'running') {
            $stmt = $pdo->prepare("
                UPDATE cron_jobs 
                SET statut_derniere_execution = 'running', derniere_execution = NOW()
                WHERE fichier = 'cron/rappel-loyers.php'
            ");
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("
                UPDATE cron_jobs 
                SET statut_derniere_execution = ?,
                    log_derniere_execution = ?
                WHERE fichier = 'cron/rappel-loyers.php'
            ");
            $stmt->execute([$statut, substr($log, 0, 5000)]);
        }
    } catch (Exception $e) {
        error_log("Erreur mise à jour cron_jobs: " . $e->getMessage());
    }
}

// =====================================================
// SCRIPT PRINCIPAL
// =====================================================

try {
    logMessage("===== DÉMARRAGE DU SCRIPT DE RAPPEL LOYERS =====");

    // Marquer le cron comme en cours d'exécution
    mettreAJourCronJob($pdo, 'running');

    // 1. Vérifier si le module est actif
    $moduleActif = getParameter('rappel_loyers_actif', false);
    
    if (!$moduleActif) {
        logMessage("Module de rappel désactivé dans la configuration");
        mettreAJourCronJob($pdo, 'success', implode('', $cronLogs));
        exit(0);
    }
    
    // 2. Vérifier si c'est un jour de rappel
    $joursRappel = getParameter('rappel_loyers_dates_envoi', [7, 9, 15]);
    $jourActuel = (int)date('j');
    
    if (!in_array($jourActuel, $joursRappel)) {
        logMessage("Pas un jour de rappel configuré (jour actuel: $jourActuel, jours configurés: " . implode(', ', $joursRappel) . ")");
        mettreAJourCronJob($pdo, 'success', implode('', $cronLogs));
        exit(0);
    }
    
    logMessage("Jour de rappel détecté: $jourActuel");
    
    // 3. Récupérer les destinataires (strictement les administrateurs configurés)
    $destinataires = getParameter('rappel_loyers_destinataires', []);
    
    // Fallback sur ADMIN_EMAIL si aucun destinataire configuré
    if (empty($destinataires)) {
        if (!empty($config['ADMIN_EMAIL'])) {
            $destinataires = [$config['ADMIN_EMAIL']];
            logMessage("Aucun destinataire configuré, utilisation de ADMIN_EMAIL: " . $config['ADMIN_EMAIL']);
        } else {
            logMessage("Aucun destinataire configuré et ADMIN_EMAIL vide", true);
            mettreAJourCronJob($pdo, 'error', implode('', $cronLogs));
            exit(1);
        }
    }
    
    logMessage("Destinataires (administrateurs): " . implode(', ', $destinataires));
    
    // 4. Déterminer le mois et l'année à vérifier (mois en cours)
    $mois = (int)date('n');
    $annee = (int)date('Y');
    
    logMessage("Vérification des paiements pour: $mois/$annee");
    
    // 5. Créer les entrées de tracking si nécessaire
    creerEntriesTrackingMoisCourant($pdo, $mois, $annee);
    
    // 6. Générer le message de statut
    $statusInfo = genererMessageStatut($pdo, $mois, $annee);
    
    logMessage("Statut: " . ($statusInfo['tous_payes'] ? 'Tous payés' : 'Impayés détectés'));
    if (isset($statusInfo['nb_total'])) {
        logMessage("  - Total: {$statusInfo['nb_total']} biens");
        logMessage("  - Payés: {$statusInfo['nb_payes']}");
        logMessage("  - Impayés: {$statusInfo['nb_impayes']}");
    }
    
    // 7. Envoyer le rappel aux administrateurs uniquement
    $resultat = envoyerRappel($pdo, $destinataires, $statusInfo, $mois, $annee);
    
    if ($resultat) {
        logMessage("✅ Rappel envoyé avec succès aux administrateurs");
    } else {
        logMessage("❌ Échec de l'envoi du rappel aux administrateurs", true);
    }
    
    // Note: Les rappels aux locataires sont envoyés uniquement via le bouton manuel
    // dans l'interface de gestion des loyers (admin-v2/gestion-loyers.php)
    
    if ($resultat) {
        // 9. Mettre à jour le statut des rappels dans la base
        try {
            $stmt = $pdo->prepare("
                UPDATE loyers_tracking 
                SET rappel_envoye = TRUE, 
                    date_rappel = NOW(),
                    nb_rappels = nb_rappels + 1
                WHERE mois = ? AND annee = ?
            ");
            $stmt->execute([$mois, $annee]);
            logMessage("Statut des rappels mis à jour dans la base");
        } catch (Exception $e) {
            logMessage("Erreur mise à jour statut rappels: " . $e->getMessage(), true);
        }
        
        mettreAJourCronJob($pdo, 'success', implode('', $cronLogs));
        exit(0);
    } else {
        logMessage("❌ Échec de l'envoi du rappel", true);
        mettreAJourCronJob($pdo, 'error', implode('', $cronLogs));
        exit(1);
    }
    
} catch (Exception $e) {
    logMessage("ERREUR FATALE: " . $e->getMessage(), true);
    logMessage("Stack trace: " . $e->getTraceAsString(), true);
    try {
        mettreAJourCronJob($pdo, 'error', implode('', $cronLogs));
    } catch (Exception $ignored) {}
    exit(1);
}

logMessage("===== FIN DU SCRIPT DE RAPPEL LOYERS =====");
