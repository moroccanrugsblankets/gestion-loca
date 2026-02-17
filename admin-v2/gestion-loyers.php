<?php
/**
 * INTERFACE DE GESTION DES LOYERS
 * 
 * Affiche un tableau coloré de l'état des paiements de loyers
 * pour tous les biens en location, mois par mois.
 * 
 * Fonctionnalités:
 * - Vue synthétique avec code couleur (vert=payé, rouge=impayé, orange=attente)
 * - Affichage côte à côte des biens
 * - Modification manuelle du statut de paiement
 * - Envoi de rappels manuels aux locataires
 */

require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';
require_once '../includes/mail-templates.php';

// Filtre SQL pour les contrats actifs (utilisé dans plusieurs requêtes)
// Un contrat est considéré actif si :
// - Son statut est 'actif', 'signe' ou 'valide' (pas annulé, expiré ou terminé)
// - Sa date de prise d'effet est NULL (pas encore définie) OU dans le passé/aujourd'hui
define('CONTRAT_ACTIF_FILTER', "c.statut IN ('actif', 'signe', 'valide') AND (c.date_prise_effet IS NULL OR c.date_prise_effet <= CURDATE())");

// Déterminer la période à afficher (12 derniers mois par défaut)
$anneeActuelle = (int)date('Y');
$moisActuel = (int)date('n');

// Récupérer tous les logements en location
$stmtLogements = $pdo->query("
    SELECT DISTINCT l.*, c.id as contrat_id,
           (SELECT GROUP_CONCAT(CONCAT(prenom, ' ', nom) SEPARATOR ', ')
            FROM locataires 
            WHERE contrat_id = c.id) as locataires
    FROM logements l
    INNER JOIN contrats c ON c.logement_id = l.id
    WHERE l.statut = 'en_location'
    AND " . CONTRAT_ACTIF_FILTER . "
    ORDER BY l.reference
");
$logements = $stmtLogements->fetchAll(PDO::FETCH_ASSOC);

// Nom des mois en français
$nomsMois = [
    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
];

// Générer la liste des 12 derniers mois
$mois = [];
for ($i = 11; $i >= 0; $i--) {
    $date = new DateTime();
    $date->modify("-$i months");
    $moisNum = (int)$date->format('n');
    $mois[] = [
        'num' => $moisNum,
        'annee' => (int)$date->format('Y'),
        'nom' => $nomsMois[$moisNum],
        'nom_court' => substr($nomsMois[$moisNum], 0, 3)
    ];
}

// Récupérer les statuts de paiement pour tous les logements et mois
$statutsPaiement = [];
if (!empty($logements)) {
    $logementIds = array_column($logements, 'id');
    $placeholders = implode(',', array_fill(0, count($logementIds), '?'));
    
    $stmtStatuts = $pdo->prepare("
        SELECT logement_id, mois, annee, statut_paiement, montant_attendu, date_paiement, notes
        FROM loyers_tracking
        WHERE logement_id IN ($placeholders)
    ");
    $stmtStatuts->execute($logementIds);
    
    while ($row = $stmtStatuts->fetch(PDO::FETCH_ASSOC)) {
        $key = $row['logement_id'] . '_' . $row['mois'] . '_' . $row['annee'];
        $statutsPaiement[$key] = $row;
    }
}

/**
 * Récupère le statut de paiement pour un logement et un mois donnés
 */
function getStatutPaiement($logementId, $mois, $annee) {
    global $statutsPaiement;
    $key = $logementId . '_' . $mois . '_' . $annee;
    return $statutsPaiement[$key] ?? null;
}

/**
 * Créer automatiquement une entrée de tracking pour un logement/mois
 */
function creerEntryTracking($pdo, $logementId, $contratId, $mois, $annee, $montantAttendu) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO loyers_tracking 
            (logement_id, contrat_id, mois, annee, montant_attendu, statut_paiement)
            VALUES (?, ?, ?, ?, ?, 'attente')
            ON DUPLICATE KEY UPDATE logement_id = logement_id
        ");
        return $stmt->execute([$logementId, $contratId, $mois, $annee, $montantAttendu]);
    } catch (Exception $e) {
        return false;
    }
}

// Gestion des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        // Changement de statut de paiement
        if (isset($_POST['action']) && $_POST['action'] === 'update_statut') {
            $logementId = (int)$_POST['logement_id'];
            $mois = (int)$_POST['mois'];
            $annee = (int)$_POST['annee'];
            $nouveauStatut = $_POST['statut'];
            
            // Valider le statut
            if (!in_array($nouveauStatut, ['paye', 'impaye', 'attente'])) {
                throw new Exception('Statut invalide');
            }
            
            // Vérifier si l'entrée existe
            $check = $pdo->prepare("
                SELECT id FROM loyers_tracking 
                WHERE logement_id = ? AND mois = ? AND annee = ?
            ");
            $check->execute([$logementId, $mois, $annee]);
            
            if ($check->fetch()) {
                // Mettre à jour
                $update = $pdo->prepare("
                    UPDATE loyers_tracking 
                    SET statut_paiement = ?,
                        date_paiement = IF(? = 'paye', CURDATE(), NULL),
                        updated_at = NOW()
                    WHERE logement_id = ? AND mois = ? AND annee = ?
                ");
                $update->execute([$nouveauStatut, $nouveauStatut, $logementId, $mois, $annee]);
            } else {
                // Créer l'entrée
                $logement = $pdo->prepare("SELECT loyer, charges FROM logements WHERE id = ?");
                $logement->execute([$logementId]);
                $logInfo = $logement->fetch(PDO::FETCH_ASSOC);
                
                // Récupérer le contrat actif pour ce logement (utilise les mêmes critères que la requête principale)
                $contrat = $pdo->prepare("SELECT id FROM contrats c WHERE logement_id = ? AND " . CONTRAT_ACTIF_FILTER . " LIMIT 1");
                $contrat->execute([$logementId]);
                $contratInfo = $contrat->fetch(PDO::FETCH_ASSOC);
                
                $montantTotal = $logInfo['loyer'] + $logInfo['charges'];
                
                $insert = $pdo->prepare("
                    INSERT INTO loyers_tracking 
                    (logement_id, contrat_id, mois, annee, montant_attendu, statut_paiement, date_paiement)
                    VALUES (?, ?, ?, ?, ?, ?, IF(? = 'paye', CURDATE(), NULL))
                ");
                $insert->execute([
                    $logementId,
                    $contratInfo['id'] ?? null,
                    $mois,
                    $annee,
                    $montantTotal,
                    $nouveauStatut,
                    $nouveauStatut
                ]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Statut mis à jour']);
            exit;
        }
        
        // Envoi de rappel manuel au locataire
        if (isset($_POST['action']) && $_POST['action'] === 'envoyer_rappel_locataire') {
            $logementId = (int)$_POST['logement_id'];
            $mois = (int)$_POST['mois'];
            $annee = (int)$_POST['annee'];
            
            // Récupérer les informations du logement et du locataire
            $stmt = $pdo->prepare("
                SELECT l.*, c.id as contrat_id,
                       (SELECT email FROM locataires WHERE contrat_id = c.id LIMIT 1) as email_locataire,
                       (SELECT CONCAT(prenom, ' ', nom) FROM locataires WHERE contrat_id = c.id LIMIT 1) as nom_locataire
                FROM logements l
                INNER JOIN contrats c ON c.logement_id = l.id
                WHERE l.id = ? AND " . CONTRAT_ACTIF_FILTER . "
            ");
            $stmt->execute([$logementId]);
            $info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$info || !$info['email_locataire']) {
                throw new Exception('Locataire introuvable ou email manquant');
            }
            
            // Préparer l'email de rappel
            $moisNom = $nomsMois[$mois];
            $montantTotal = $info['loyer'] + $info['charges'];
            
            $sujet = "Rappel de paiement - Loyer de $moisNom $annee";
            $corps = "
            <h2>Rappel de paiement</h2>
            <p>Bonjour " . htmlspecialchars($info['nom_locataire']) . ",</p>
            <p>Nous vous rappelons que le loyer du mois de <strong>$moisNom $annee</strong> n'a pas encore été enregistré pour le bien situé au:</p>
            <p><strong>" . htmlspecialchars($info['adresse']) . "</strong><br>
            Référence: " . htmlspecialchars($info['reference']) . "</p>
            <p><strong>Montant dû: " . number_format($montantTotal, 2, ',', ' ') . " €</strong><br>
            (Loyer: " . number_format($info['loyer'], 2, ',', ' ') . " € + Charges: " . number_format($info['charges'], 2, ',', ' ') . " €)</p>
            <p>Merci de régulariser votre situation dans les meilleurs délais.</p>
            <p>Cordialement,<br>
            <strong>My Invest Immobilier</strong></p>
            ";
            
            // Envoyer l'email
            $result = sendEmail(
                $info['email_locataire'],
                $sujet,
                $corps,
                $config['MAIL_FROM'],
                $config['MAIL_FROM_NAME']
            );
            
            if ($result) {
                // Enregistrer l'envoi dans le tracking
                $pdo->prepare("
                    UPDATE loyers_tracking 
                    SET rappel_envoye = TRUE, date_rappel = NOW(), nb_rappels = nb_rappels + 1
                    WHERE logement_id = ? AND mois = ? AND annee = ?
                ")->execute([$logementId, $mois, $annee]);
                
                echo json_encode(['success' => true, 'message' => 'Rappel envoyé au locataire']);
            } else {
                throw new Exception('Échec de l\'envoi de l\'email');
            }
            exit;
        }
        
        // Envoi de rappel manuel aux administrateurs
        if (isset($_POST['action']) && $_POST['action'] === 'envoyer_rappel_administrateurs') {
            // Inclure le script de rappel pour exécuter la logique
            require_once __DIR__ . '/../cron/rappel-loyers.php';
            exit;
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Loyers - Administration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <?php require_once __DIR__ . '/includes/sidebar-styles.php'; ?>
    <style>
        .payment-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            margin-top: 20px;
        }
        
        .payment-table th,
        .payment-table td {
            border: 1px solid #dee2e6;
            padding: 12px 8px;
            text-align: center;
        }
        
        .payment-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .payment-table .property-cell {
            text-align: left;
            font-weight: 600;
            background-color: #f8f9fa;
            position: sticky;
            left: 0;
            z-index: 5;
            min-width: 200px;
        }
        
        .payment-cell {
            cursor: pointer;
            transition: all 0.2s;
            min-width: 80px;
            position: relative;
        }
        
        .payment-cell:hover {
            opacity: 0.8;
            transform: scale(1.05);
        }
        
        .payment-cell.paye {
            background-color: #28a745;
            color: white;
        }
        
        .payment-cell.impaye {
            background-color: #dc3545;
            color: white;
        }
        
        .payment-cell.attente {
            background-color: #ffc107;
            color: #333;
        }
        
        .payment-cell .status-icon {
            font-size: 20px;
            display: block;
        }
        
        .payment-cell .amount {
            font-size: 11px;
            margin-top: 4px;
            opacity: 0.9;
        }
        
        .legend {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .legend-box {
            width: 30px;
            height: 30px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            padding: 15px;
            border-radius: 8px;
            color: white;
            text-align: center;
        }
        
        .stat-card.total { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-card.paye { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
        .stat-card.impaye { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); }
        .stat-card.attente { background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 5px;
        }
        
        .table-container {
            overflow-x: auto;
            max-height: 600px;
            overflow-y: auto;
        }
        
        .current-month {
            background-color: #e3f2fd !important;
        }
        
        .action-buttons {
            margin-top: 5px;
        }
        
        .action-buttons button {
            font-size: 11px;
            padding: 2px 6px;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/menu.php'; ?>
    
    <div class="main-content">
    <div class="container-fluid mt-4">
        <div class="header-actions">
            <div>
                <h1><i class="bi bi-cash-stack"></i> Gestion des Loyers</h1>
                <p class="text-muted">Vue synthétique de l'état des paiements mensuels</p>
            </div>
            <div>
                <a href="configuration-rappels-loyers.php" class="btn btn-primary">
                    <i class="bi bi-gear"></i> Configuration
                </a>
                <button class="btn btn-success" onclick="envoyerRappelManuel()">
                    <i class="bi bi-envelope"></i> Envoyer rappel maintenant
                </button>
            </div>
        </div>
        
        <?php
        // Calculer les statistiques pour le mois en cours
        $totalBiens = count($logements);
        $nbPaye = 0;
        $nbImpaye = 0;
        $nbAttente = 0;
        
        foreach ($logements as $logement) {
            $statut = getStatutPaiement($logement['id'], $moisActuel, $anneeActuelle);
            if ($statut) {
                switch ($statut['statut_paiement']) {
                    case 'paye': $nbPaye++; break;
                    case 'impaye': $nbImpaye++; break;
                    default: $nbAttente++; break;
                }
            } else {
                $nbAttente++;
            }
        }
        ?>
        
        <div class="stats-summary">
            <div class="stat-card total">
                <div class="stat-value"><?= $totalBiens ?></div>
                <div class="stat-label">Biens en location</div>
            </div>
            <div class="stat-card paye">
                <div class="stat-value"><?= $nbPaye ?></div>
                <div class="stat-label">Loyers payés ce mois</div>
            </div>
            <div class="stat-card impaye">
                <div class="stat-value"><?= $nbImpaye ?></div>
                <div class="stat-label">Loyers impayés</div>
            </div>
            <div class="stat-card attente">
                <div class="stat-value"><?= $nbAttente ?></div>
                <div class="stat-label">En attente</div>
            </div>
        </div>
        
        <div class="legend">
            <div class="legend-item">
                <div class="legend-box" style="background-color: #28a745;"></div>
                <span><strong>Payé</strong> - Loyer reçu</span>
            </div>
            <div class="legend-item">
                <div class="legend-box" style="background-color: #dc3545;"></div>
                <span><strong>Impayé</strong> - Loyer non reçu</span>
            </div>
            <div class="legend-item">
                <div class="legend-box" style="background-color: #ffc107;"></div>
                <span><strong>En attente</strong> - Statut non défini</span>
            </div>
            <div class="ms-auto">
                <small class="text-muted"><i class="bi bi-info-circle"></i> Cliquez sur une case pour changer le statut</small>
            </div>
        </div>
        
        <?php if (empty($logements)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Aucun bien en location actuellement.
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="payment-table">
                    <thead>
                        <tr>
                            <th class="property-cell">Bien / Locataire</th>
                            <?php foreach ($mois as $m): ?>
                                <th class="<?= ($m['num'] == $moisActuel && $m['annee'] == $anneeActuelle) ? 'current-month' : '' ?>">
                                    <?= htmlspecialchars($nomsMois[$m['num']]) ?><br>
                                    <small><?= $m['annee'] ?></small>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logements as $logement): ?>
                            <tr>
                                <td class="property-cell">
                                    <strong><?= htmlspecialchars($logement['reference']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($logement['locataires'] ?: 'Non assigné') ?></small><br>
                                    <small><?= htmlspecialchars(substr($logement['adresse'], 0, 40)) ?></small>
                                </td>
                                <?php 
                                $montantTotal = $logement['loyer'] + $logement['charges'];
                                foreach ($mois as $m): 
                                    $statut = getStatutPaiement($logement['id'], $m['num'], $m['annee']);
                                    $statutClass = $statut ? $statut['statut_paiement'] : 'attente';
                                    $icon = [
                                        'paye' => '✓',
                                        'impaye' => '✗',
                                        'attente' => '⏳'
                                    ][$statutClass];
                                    
                                    // Ne pas créer automatiquement - attendre l'interaction utilisateur
                                ?>
                                    <td class="payment-cell <?= $statutClass ?>" 
                                        onclick="changerStatut(<?= $logement['id'] ?>, <?= $m['num'] ?>, <?= $m['annee'] ?>, '<?= $statutClass ?>')">
                                        <span class="status-icon"><?= $icon ?></span>
                                        <div class="amount"><?= number_format($montantTotal, 0, ',', ' ') ?>€</div>
                                        <?php if ($statutClass === 'impaye'): ?>
                                            <div class="action-buttons">
                                                <button class="btn btn-sm btn-outline-light" 
                                                        onclick="event.stopPropagation(); envoyerRappelLocataire(<?= $logement['id'] ?>, <?= $m['num'] ?>, <?= $m['annee'] ?>)">
                                                    <i class="bi bi-envelope"></i>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function changerStatut(logementId, mois, annee, statutActuel) {
            // Cycle entre les statuts: attente -> paye -> impaye -> attente
            const cycle = {
                'attente': 'paye',
                'paye': 'impaye',
                'impaye': 'attente'
            };
            
            const nouveauStatut = cycle[statutActuel] || 'attente';
            
            // Envoyer la requête AJAX
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'update_statut',
                    logement_id: logementId,
                    mois: mois,
                    annee: annee,
                    statut: nouveauStatut
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Recharger la page pour afficher les changements
                    location.reload();
                } else {
                    alert('Erreur: ' + (data.error || 'Échec de la mise à jour'));
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur de communication avec le serveur');
            });
        }
        
        function envoyerRappelLocataire(logementId, mois, annee) {
            if (!confirm('Envoyer un rappel de paiement au locataire pour ce mois ?')) {
                return;
            }
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'envoyer_rappel_locataire',
                    logement_id: logementId,
                    mois: mois,
                    annee: annee
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    location.reload();
                } else {
                    alert('❌ Erreur: ' + (data.error || 'Échec de l\'envoi'));
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur de communication avec le serveur');
            });
        }
        
        function envoyerRappelManuel() {
            if (!confirm('Envoyer un rappel immédiat aux administrateurs concernant l\'état des loyers ?')) {
                return;
            }
            
            // Envoyer la requête AJAX
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'envoyer_rappel_administrateurs'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    location.reload();
                } else {
                    alert('❌ Erreur: ' + (data.error || 'Échec de l\'envoi'));
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur de communication avec le serveur');
            });
        }
    </script>
    </div><!-- end main-content -->
</body>
</html>
