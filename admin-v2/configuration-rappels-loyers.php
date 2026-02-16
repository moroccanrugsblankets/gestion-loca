<?php
/**
 * CONFIGURATION DES RAPPELS DE LOYERS
 * 
 * Interface pour configurer:
 * - Les dates d'envoi automatique des rappels (jours du mois)
 * - Les administrateurs destinataires des rappels
 * - Activation/désactivation du module
 * - Options d'email (bouton vers l'interface, etc.)
 */

require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

/**
 * Récupère un paramètre de configuration
 */
function getParameter($pdo, $key, $default = null) {
    try {
        $stmt = $pdo->prepare("SELECT valeur, type FROM parametres WHERE cle = ?");
        $stmt->execute([$key]);
        $param = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$param) {
            return $default;
        }
        
        $value = $param['valeur'];
        
        switch ($param['type']) {
            case 'json':
                return json_decode($value, true);
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'integer':
                return (int)$value;
            case 'float':
                return (float)$value;
            default:
                return $value;
        }
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Enregistre un paramètre de configuration
 */
function setParameter($pdo, $key, $value, $type = 'string') {
    try {
        // Encoder en JSON si c'est un array
        if (is_array($value)) {
            $value = json_encode($value);
            $type = 'json';
        } elseif (is_bool($value)) {
            $value = $value ? '1' : '0';
            $type = 'boolean';
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO parametres (cle, valeur, type)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE valeur = VALUES(valeur), type = VALUES(type)
        ");
        return $stmt->execute([$key, $value, $type]);
    } catch (Exception $e) {
        return false;
    }
}

// Récupérer tous les administrateurs pour la sélection
$stmtAdmins = $pdo->query("
    SELECT id, email, CONCAT(prenom, ' ', nom) as nom_complet
    FROM administrateurs
    ORDER BY nom, prenom
");
$administrateurs = $stmtAdmins->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la configuration actuelle
$datesEnvoi = getParameter($pdo, 'rappel_loyers_dates_envoi', [7, 9, 15]);
$destinataires = getParameter($pdo, 'rappel_loyers_destinataires', []);
$moduleActif = getParameter($pdo, 'rappel_loyers_actif', true);
$inclureBouton = getParameter($pdo, 'rappel_loyers_inclure_bouton', true);

// Messages
$message = '';
$messageType = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Récupérer les données du formulaire
        $newDatesEnvoi = [];
        if (isset($_POST['dates_envoi']) && is_array($_POST['dates_envoi'])) {
            foreach ($_POST['dates_envoi'] as $jour) {
                $jour = (int)$jour;
                if ($jour >= 1 && $jour <= 31) {
                    $newDatesEnvoi[] = $jour;
                }
            }
        }
        
        // Si aucune date sélectionnée, utiliser les défauts
        if (empty($newDatesEnvoi)) {
            $newDatesEnvoi = [7, 9, 15];
        }
        
        $newDestinataires = $_POST['destinataires'] ?? [];
        $newModuleActif = isset($_POST['module_actif']);
        $newInclureBouton = isset($_POST['inclure_bouton']);
        
        // Sauvegarder les paramètres
        setParameter($pdo, 'rappel_loyers_dates_envoi', $newDatesEnvoi, 'json');
        setParameter($pdo, 'rappel_loyers_destinataires', $newDestinataires, 'json');
        setParameter($pdo, 'rappel_loyers_actif', $newModuleActif, 'boolean');
        setParameter($pdo, 'rappel_loyers_inclure_bouton', $newInclureBouton, 'boolean');
        
        $message = 'Configuration enregistrée avec succès !';
        $messageType = 'success';
        
        // Recharger les valeurs
        $datesEnvoi = $newDatesEnvoi;
        $destinataires = $newDestinataires;
        $moduleActif = $newModuleActif;
        $inclureBouton = $newInclureBouton;
        
    } catch (Exception $e) {
        $message = 'Erreur lors de l\'enregistrement: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Récupérer des informations sur le dernier envoi
$stmtDernierEnvoi = $pdo->query("
    SELECT MAX(date_rappel) as derniere_date
    FROM loyers_tracking
    WHERE rappel_envoye = TRUE
");
$dernierEnvoiInfo = $stmtDernierEnvoi->fetch(PDO::FETCH_ASSOC);
$dernierEnvoi = $dernierEnvoiInfo['derniere_date'] ?? null;

// Vérifier le statut du cron job
$stmtCronJob = $pdo->query("
    SELECT * FROM cron_jobs
    WHERE fichier = 'cron/rappel-loyers.php'
");
$cronJob = $stmtCronJob->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration des Rappels de Loyers - Administration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <?php require_once __DIR__ . '/includes/sidebar-styles.php'; ?>
    <style>
        .config-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .config-section h3 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #007bff;
        }
        
        .day-selector {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-top: 15px;
        }
        
        .day-checkbox {
            text-align: center;
        }
        
        .day-checkbox input[type="checkbox"] {
            display: none;
        }
        
        .day-checkbox label {
            display: block;
            padding: 12px 8px;
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 600;
        }
        
        .day-checkbox input[type="checkbox"]:checked + label {
            background: #007bff;
            color: white;
            border-color: #0056b3;
        }
        
        .day-checkbox label:hover {
            border-color: #007bff;
        }
        
        .admin-checkbox {
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
            border: 1px solid #dee2e6;
            transition: all 0.2s;
        }
        
        .admin-checkbox:hover {
            background: #e9ecef;
        }
        
        .admin-checkbox input[type="checkbox"]:checked ~ label {
            font-weight: 600;
            color: #007bff;
        }
        
        .info-card {
            padding: 15px;
            border-left: 4px solid #17a2b8;
            background: #d1ecf1;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        .warning-card {
            padding: 15px;
            border-left: 4px solid #ffc107;
            background: #fff3cd;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-badge.active {
            background: #28a745;
            color: white;
        }
        
        .status-badge.inactive {
            background: #dc3545;
            color: white;
        }
        
        .cron-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/menu.php'; ?>
    
    <div class="main-content">
    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1><i class="bi bi-gear"></i> Configuration des Rappels de Loyers</h1>
                <p class="text-muted">Configurez les envois automatiques de rappels aux administrateurs</p>
            </div>
            <a href="gestion-loyers.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Retour à la gestion
            </a>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="row">
                <div class="col-lg-8">
                    <!-- SECTION 1: Activation du module -->
                    <div class="config-section">
                        <h3><i class="bi bi-toggle-on"></i> Activation du Module</h3>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="module_actif" name="module_actif" 
                                   <?= $moduleActif ? 'checked' : '' ?>>
                            <label class="form-check-label" for="module_actif">
                                <strong>Activer les rappels automatiques de loyers</strong>
                            </label>
                        </div>
                        
                        <div class="info-card">
                            <i class="bi bi-info-circle"></i>
                            <strong>Statut actuel:</strong> 
                            <span class="status-badge <?= $moduleActif ? 'active' : 'inactive' ?>">
                                <?= $moduleActif ? 'Activé' : 'Désactivé' ?>
                            </span>
                            <?php if ($dernierEnvoi): ?>
                                <br><small class="text-muted">Dernier rappel envoyé: <?= date('d/m/Y à H:i', strtotime($dernierEnvoi)) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- SECTION 2: Dates d'envoi -->
                    <div class="config-section">
                        <h3><i class="bi bi-calendar-check"></i> Dates d'Envoi Automatique</h3>
                        
                        <p>Sélectionnez les jours du mois où les rappels doivent être envoyés automatiquement:</p>
                        
                        <div class="day-selector">
                            <?php for ($jour = 1; $jour <= 31; $jour++): ?>
                                <div class="day-checkbox">
                                    <input type="checkbox" id="jour_<?= $jour ?>" name="dates_envoi[]" 
                                           value="<?= $jour ?>" <?= in_array($jour, $datesEnvoi) ? 'checked' : '' ?>>
                                    <label for="jour_<?= $jour ?>"><?= $jour ?></label>
                                </div>
                            <?php endfor; ?>
                        </div>
                        
                        <div class="info-card mt-3">
                            <i class="bi bi-info-circle"></i>
                            <strong>Dates actuellement configurées:</strong> 
                            <?php if (empty($datesEnvoi)): ?>
                                <em>Aucune date sélectionnée (utilisation des défauts: 7, 9, 15)</em>
                            <?php else: ?>
                                <?= implode(', ', $datesEnvoi) ?> de chaque mois
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- SECTION 3: Destinataires -->
                    <div class="config-section">
                        <h3><i class="bi bi-people"></i> Administrateurs Destinataires</h3>
                        
                        <p>Sélectionnez les administrateurs qui doivent recevoir les rappels:</p>
                        
                        <?php if (empty($administrateurs)): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                Aucun administrateur trouvé dans le système.
                            </div>
                        <?php else: ?>
                            <?php foreach ($administrateurs as $admin): ?>
                                <div class="admin-checkbox">
                                    <input type="checkbox" class="form-check-input" 
                                           id="admin_<?= $admin['id'] ?>" 
                                           name="destinataires[]" 
                                           value="<?= htmlspecialchars($admin['email']) ?>"
                                           <?= in_array($admin['email'], $destinataires) ? 'checked' : '' ?>>
                                    <label class="form-check-label ms-2" for="admin_<?= $admin['id'] ?>">
                                        <i class="bi bi-person"></i>
                                        <strong><?= htmlspecialchars($admin['nom_complet']) ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($admin['email']) ?></small>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <?php if (empty($destinataires) && !empty($config['ADMIN_EMAIL'])): ?>
                            <div class="warning-card mt-3">
                                <i class="bi bi-exclamation-triangle"></i>
                                <strong>Aucun destinataire sélectionné.</strong><br>
                                Par défaut, les rappels seront envoyés à: <strong><?= htmlspecialchars($config['ADMIN_EMAIL']) ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- SECTION 4: Options d'email -->
                    <div class="config-section">
                        <h3><i class="bi bi-envelope-check"></i> Options d'Email</h3>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="inclure_bouton" name="inclure_bouton" 
                                   <?= $inclureBouton ? 'checked' : '' ?>>
                            <label class="form-check-label" for="inclure_bouton">
                                <strong>Inclure un bouton vers l'interface de gestion dans les emails</strong>
                            </label>
                            <div class="form-text">
                                Permet aux administrateurs d'accéder directement à la page de gestion des loyers depuis l'email.
                            </div>
                        </div>
                    </div>
                    
                    <!-- Boutons d'action -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-save"></i> Enregistrer la Configuration
                        </button>
                        <a href="gestion-loyers.php" class="btn btn-outline-secondary btn-lg">
                            <i class="bi bi-x"></i> Annuler
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <!-- Informations sur le Cron Job -->
                    <div class="config-section">
                        <h3><i class="bi bi-clock-history"></i> Planification</h3>
                        
                        <?php if ($cronJob): ?>
                            <div class="mb-3">
                                <strong>Nom du job:</strong><br>
                                <?= htmlspecialchars($cronJob['nom']) ?>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Expression cron:</strong>
                                <div class="cron-info"><?= htmlspecialchars($cronJob['cron_expression']) ?></div>
                                <small class="text-muted">Tous les jours à 9h00</small>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Statut:</strong><br>
                                <span class="status-badge <?= $cronJob['actif'] ? 'active' : 'inactive' ?>">
                                    <?= $cronJob['actif'] ? 'Actif' : 'Inactif' ?>
                                </span>
                            </div>
                            
                            <?php if ($cronJob['derniere_execution']): ?>
                                <div class="mb-3">
                                    <strong>Dernière exécution:</strong><br>
                                    <small><?= date('d/m/Y à H:i:s', strtotime($cronJob['derniere_execution'])) ?></small>
                                    <br>
                                    <span class="badge bg-<?= $cronJob['statut_derniere_execution'] === 'success' ? 'success' : 'danger' ?>">
                                        <?= htmlspecialchars($cronJob['statut_derniere_execution']) ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($cronJob['prochaine_execution']): ?>
                                <div class="mb-3">
                                    <strong>Prochaine exécution:</strong><br>
                                    <small><?= date('d/m/Y à H:i:s', strtotime($cronJob['prochaine_execution'])) ?></small>
                                </div>
                            <?php endif; ?>
                            
                            <a href="cron-jobs.php" class="btn btn-sm btn-outline-primary w-100">
                                <i class="bi bi-gear"></i> Gérer les Cron Jobs
                            </a>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                Le cron job n'est pas encore configuré dans le système.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Aide et Documentation -->
                    <div class="config-section">
                        <h3><i class="bi bi-question-circle"></i> Aide</h3>
                        
                        <div class="mb-3">
                            <strong>Fonctionnement:</strong>
                            <ol class="small">
                                <li>Le système vérifie quotidiennement si c'est un jour de rappel configuré</li>
                                <li>Il analyse l'état des paiements de loyers du mois en cours</li>
                                <li>Il envoie un email aux administrateurs:
                                    <ul>
                                        <li>Email de confirmation si tous les loyers sont payés</li>
                                        <li>Email de rappel s'il y a des impayés</li>
                                    </ul>
                                </li>
                            </ol>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Configuration recommandée:</strong>
                            <ul class="small">
                                <li>Dates: 7, 9, et 15 du mois</li>
                                <li>Au moins 1 administrateur destinataire</li>
                                <li>Bouton vers l'interface activé</li>
                            </ul>
                        </div>
                        
                        <div class="info-card">
                            <i class="bi bi-lightbulb"></i>
                            <strong>Astuce:</strong> Vous pouvez aussi envoyer des rappels manuellement depuis la page de gestion des loyers.
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    </div><!-- end main-content -->
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
