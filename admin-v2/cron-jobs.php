<?php
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

// Handle immediate execution request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'execute' && isset($_POST['job_id'])) {
        $job_id = (int)$_POST['job_id'];
        
        // Get job details
        $stmt = $pdo->prepare("SELECT * FROM cron_jobs WHERE id = ?");
        $stmt->execute([$job_id]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($job && $job['actif']) {
            // Update status to running
            $stmt = $pdo->prepare("UPDATE cron_jobs SET statut_derniere_execution = 'running', derniere_execution = NOW() WHERE id = ?");
            $stmt->execute([$job_id]);
            
            $file_path = __DIR__ . '/../' . $job['fichier'];
            
            if (file_exists($file_path)) {
                // Execute the cron job
                ob_start();
                $start_time = microtime(true);
                
                try {
                    include $file_path;
                    $output = ob_get_clean();
                    $execution_time = round(microtime(true) - $start_time, 2);
                    
                    // Update job with success
                    $stmt = $pdo->prepare("
                        UPDATE cron_jobs 
                        SET statut_derniere_execution = 'success',
                            log_derniere_execution = ?
                        WHERE id = ?
                    ");
                    $log = "Exécution manuelle réussie (durée: {$execution_time}s)\n\n" . $output;
                    $stmt->execute([substr($log, 0, 5000), $job_id]);
                    
                    $_SESSION['success'] = "Tâche exécutée avec succès en {$execution_time}s";
                } catch (Exception $e) {
                    $output = ob_get_clean();
                    
                    // Update job with error
                    $stmt = $pdo->prepare("
                        UPDATE cron_jobs 
                        SET statut_derniere_execution = 'error',
                            log_derniere_execution = ?
                        WHERE id = ?
                    ");
                    $log = "Erreur lors de l'exécution manuelle:\n" . $e->getMessage() . "\n\n" . $output;
                    $stmt->execute([substr($log, 0, 5000), $job_id]);
                    
                    $_SESSION['error'] = "Erreur lors de l'exécution: " . $e->getMessage();
                }
            } else {
                $_SESSION['error'] = "Fichier de tâche introuvable: " . $job['fichier'];
            }
        } else {
            $_SESSION['error'] = "Tâche introuvable ou désactivée";
        }
        
        header('Location: cron-jobs.php');
        exit;
    }
    
    if ($_POST['action'] === 'toggle' && isset($_POST['job_id'])) {
        $job_id = (int)$_POST['job_id'];
        
        $stmt = $pdo->prepare("UPDATE cron_jobs SET actif = NOT actif WHERE id = ?");
        $stmt->execute([$job_id]);
        
        $_SESSION['success'] = "Statut de la tâche mis à jour";
        header('Location: cron-jobs.php');
        exit;
    }
}

// Get all cron jobs
$stmt = $pdo->query("SELECT * FROM cron_jobs ORDER BY id");
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tâches Automatisées - My Invest Immobilier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <?php require_once __DIR__ . '/includes/sidebar-styles.php'; ?>
    <style>
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .job-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .job-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        .job-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
        }
        .job-description {
            color: #7f8c8d;
            margin-bottom: 15px;
        }
        .job-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        .job-info-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .job-info-label {
            font-size: 0.85rem;
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        .job-info-value {
            font-weight: 600;
            color: #2c3e50;
        }
        .log-output {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            max-height: 200px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 0.85rem;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/menu.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4><i class="bi bi-clock-history"></i> Tâches Automatisées (Cron Jobs)</h4>
                    <p class="text-muted mb-0">Gérer et surveiller les tâches planifiées</p>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($jobs)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Aucune tâche automatisée configurée.
            </div>
        <?php else: ?>
            <?php foreach ($jobs as $job): ?>
                <div class="job-card">
                    <div class="job-header">
                        <div>
                            <div class="job-title">
                                <i class="bi bi-gear"></i> <?php echo htmlspecialchars($job['nom']); ?>
                                <?php if ($job['actif']): ?>
                                    <span class="badge bg-success ms-2">Actif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary ms-2">Désactivé</span>
                                <?php endif; ?>
                                <?php if ($job['statut_derniere_execution'] === 'running'): ?>
                                    <span class="badge bg-warning ms-2">En cours...</span>
                                <?php elseif ($job['statut_derniere_execution'] === 'success'): ?>
                                    <span class="badge bg-success ms-2">Succès</span>
                                <?php elseif ($job['statut_derniere_execution'] === 'error'): ?>
                                    <span class="badge bg-danger ms-2">Erreur</span>
                                <?php endif; ?>
                            </div>
                            <div class="job-description">
                                <?php echo htmlspecialchars($job['description']); ?>
                            </div>
                        </div>
                        <div>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                <button type="submit" class="btn btn-sm <?php echo $job['actif'] ? 'btn-warning' : 'btn-success'; ?>" 
                                        onclick="return confirm('Confirmer le changement de statut ?')">
                                    <i class="bi bi-<?php echo $job['actif'] ? 'pause' : 'play'; ?>-fill"></i>
                                    <?php echo $job['actif'] ? 'Désactiver' : 'Activer'; ?>
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="job-info">
                        <div class="job-info-item">
                            <div class="job-info-label">Fichier</div>
                            <div class="job-info-value">
                                <code><?php echo htmlspecialchars($job['fichier']); ?></code>
                            </div>
                        </div>
                        <div class="job-info-item">
                            <div class="job-info-label">Fréquence</div>
                            <div class="job-info-value">
                                <?php 
                                $freq_labels = [
                                    'hourly' => 'Toutes les heures',
                                    'daily' => 'Quotidien',
                                    'weekly' => 'Hebdomadaire'
                                ];
                                echo $freq_labels[$job['frequence']] ?? $job['frequence'];
                                ?>
                                <?php if ($job['cron_expression']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($job['cron_expression']); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="job-info-item">
                            <div class="job-info-label">Dernière exécution</div>
                            <div class="job-info-value">
                                <?php 
                                if ($job['derniere_execution']) {
                                    echo date('d/m/Y H:i:s', strtotime($job['derniere_execution']));
                                } else {
                                    echo '<span class="text-muted">Jamais exécuté</span>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($job['log_derniere_execution']): ?>
                        <div class="mt-3">
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#log-<?php echo $job['id']; ?>">
                                <i class="bi bi-file-text"></i> Voir les logs
                            </button>
                            <div class="collapse mt-2" id="log-<?php echo $job['id']; ?>">
                                <div class="log-output">
                                    <?php echo htmlspecialchars($job['log_derniere_execution']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="mt-3">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="execute">
                            <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                            <button type="submit" class="btn btn-primary" 
                                    <?php echo !$job['actif'] ? 'disabled' : ''; ?>
                                    onclick="return confirm('Confirmer l\'exécution immédiate de cette tâche ?')">
                                <i class="bi bi-play-circle"></i> Exécuter maintenant
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="alert alert-info mt-4">
            <i class="bi bi-info-circle"></i> 
            <strong>Configuration serveur requise:</strong> Pour que les tâches s'exécutent automatiquement, 
            vous devez configurer le cron sur votre serveur. Voir <a href="#" data-bs-toggle="modal" data-bs-target="#cronHelpModal">comment configurer</a>.
        </div>
    </div>

    <!-- Cron Help Modal -->
    <div class="modal fade" id="cronHelpModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Configuration du Cron</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>Étapes de configuration:</h6>
                    <ol>
                        <li>Connectez-vous à votre serveur via SSH</li>
                        <li>Tapez: <code>crontab -e</code></li>
                        <li>Ajoutez les lignes suivantes:</li>
                    </ol>
                    <pre class="bg-light p-3 border rounded"><?php
                    foreach ($jobs as $job) {
                        if ($job['actif'] && $job['cron_expression']) {
                            $full_path = realpath(__DIR__ . '/../' . $job['fichier']);
                            echo htmlspecialchars($job['cron_expression']) . ' /usr/bin/php ' . htmlspecialchars($full_path) . "\n";
                        }
                    }
                    ?></pre>
                    <p class="mb-0">
                        <strong>Note:</strong> Ajustez le chemin de PHP (<code>/usr/bin/php</code>) selon votre configuration serveur.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
