<?php
/**
 * SAUVEGARDES - Backup & Restauration
 *
 * Interface pour :
 * - Créer des sauvegardes manuelles (BDD, fichiers, ou complet)
 * - Restaurer une sauvegarde existante
 * - Configurer les sauvegardes automatiques
 * - Télécharger les sauvegardes
 */

require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

$backupDir = realpath(__DIR__ . '/../backups');
if (!$backupDir) {
    $backupDirPath = __DIR__ . '/../backups';
    if (!mkdir($backupDirPath, 0755, true)) {
        $backupDir = false;
    } else {
        $backupDir = realpath($backupDirPath);
    }
}

$errors   = [];
$successMsg = '';

// ─── Actions ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF invalide. Veuillez recharger la page.';
    } else {
        $action = $_POST['action'] ?? '';

        // ── Sauvegarde manuelle ──────────────────────────────────────────────
        if ($action === 'backup_now') {
            $type = $_POST['backup_type_manuel'] ?? 'complet';
            if (!in_array($type, ['bdd', 'fichiers', 'complet'])) $type = 'complet';

            $timestamp = date('Y-m-d_H-i-s');
            $created   = [];

            if ($type === 'bdd' || $type === 'complet') {
                $dbFile = $backupDir . '/bdd_' . $timestamp . '.sql.gz';
                $host     = $config['DB_HOST'] ?? 'localhost';
                $dbname   = $config['DB_NAME'] ?? '';
                $user     = $config['DB_USER'] ?? '';
                $password = $config['DB_PASS'] ?? '';

                $passFile = tempnam(sys_get_temp_dir(), bin2hex(random_bytes(4)));
                file_put_contents($passFile, "[client]\npassword=" . $password . "\n");
                chmod($passFile, 0600);

                $cmd = "mysqldump --defaults-extra-file=" . escapeshellarg($passFile)
                     . " -h " . escapeshellarg($host)
                     . " -u " . escapeshellarg($user)
                     . " " . escapeshellarg($dbname)
                     . " | gzip > " . escapeshellarg($dbFile)
                     . " 2>&1";
                exec($cmd, $out, $ret);
                if (file_exists($passFile)) {
                    unlink($passFile);
                }

                if ($ret === 0 && file_exists($dbFile)) {
                    $size = filesize($dbFile);
                    $pdo->prepare("INSERT INTO sauvegardes (nom, type, fichier, taille, statut, created_by) VALUES (?,?,?,?,'termine','manuel')")
                        ->execute(['bdd_' . $timestamp . '.sql.gz', 'bdd', 'backups/bdd_' . $timestamp . '.sql.gz', $size]);
                    $created[] = 'BDD (bdd_' . $timestamp . '.sql.gz)';
                } else {
                    $errors[] = 'Échec de la sauvegarde BDD. Vérifiez que mysqldump est disponible.';
                }
            }

            if ($type === 'fichiers' || $type === 'complet') {
                $filesArchive = $backupDir . '/fichiers_' . $timestamp . '.tar.gz';
                $root = realpath(__DIR__ . '/..');
                $dirsToBackup = ['uploads', 'assets/img'];
                $existingDirs = array_filter($dirsToBackup, fn($d) => is_dir($root . '/' . $d));

                if (!empty($existingDirs)) {
                    $dirArgs = implode(' ', array_map('escapeshellarg', $existingDirs));
                    $cmd = "cd " . escapeshellarg($root) . " && tar -czf " . escapeshellarg($filesArchive) . " $dirArgs 2>&1";
                    exec($cmd, $out, $ret);

                    if ($ret === 0 && file_exists($filesArchive)) {
                        $size = filesize($filesArchive);
                        $pdo->prepare("INSERT INTO sauvegardes (nom, type, fichier, taille, statut, created_by) VALUES (?,?,?,?,'termine','manuel')")
                            ->execute(['fichiers_' . $timestamp . '.tar.gz', 'fichiers', 'backups/fichiers_' . $timestamp . '.tar.gz', $size]);
                        $created[] = 'Fichiers (fichiers_' . $timestamp . '.tar.gz)';
                    } else {
                        $errors[] = 'Échec de la sauvegarde des fichiers.';
                    }
                } else {
                    $errors[] = 'Aucun répertoire de fichiers trouvé à sauvegarder.';
                }
            }

            if (!empty($created) && empty($errors)) {
                $successMsg = 'Sauvegarde créée avec succès : ' . implode(', ', $created);
            } elseif (!empty($created)) {
                $successMsg = 'Sauvegarde partielle créée : ' . implode(', ', $created);
            }
        }

        // ── Restauration BDD ─────────────────────────────────────────────────
        if ($action === 'restore_bdd') {
            $sauvegardeId = (int)($_POST['sauvegarde_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM sauvegardes WHERE id = ?");
            $stmt->execute([$sauvegardeId]);
            $sauvegarde = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sauvegarde || $sauvegarde['type'] !== 'bdd') {
                $errors[] = 'Sauvegarde invalide ou introuvable.';
            } else {
                $filePath = realpath(__DIR__ . '/../' . $sauvegarde['fichier']);
                if (!$filePath || !file_exists($filePath) || strpos($filePath, $backupDir) !== 0) {
                    $errors[] = 'Fichier de sauvegarde introuvable ou accès refusé.';
                } else {
                    $host     = $config['DB_HOST'] ?? 'localhost';
                    $dbname   = $config['DB_NAME'] ?? '';
                    $user     = $config['DB_USER'] ?? '';
                    $password = $config['DB_PASS'] ?? '';

                    $passFile = tempnam(sys_get_temp_dir(), bin2hex(random_bytes(4)));
                    file_put_contents($passFile, "[client]\npassword=" . $password . "\n");
                    chmod($passFile, 0600);

                    $cmd = "zcat " . escapeshellarg($filePath)
                         . " | mysql --defaults-extra-file=" . escapeshellarg($passFile)
                         . " -h " . escapeshellarg($host)
                         . " -u " . escapeshellarg($user)
                         . " " . escapeshellarg($dbname)
                         . " 2>&1";
                    exec($cmd, $out, $ret);
                    if (file_exists($passFile)) {
                        unlink($passFile);
                    }

                    if ($ret === 0) {
                        $successMsg = '✅ Base de données restaurée avec succès depuis : ' . htmlspecialchars($sauvegarde['nom']);
                    } else {
                        $errors[] = 'Échec de la restauration BDD (code: ' . $ret . '). ' . implode(' ', $out);
                    }
                }
            }
        }

        // ── Restauration Fichiers ─────────────────────────────────────────────
        if ($action === 'restore_fichiers') {
            $sauvegardeId = (int)($_POST['sauvegarde_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM sauvegardes WHERE id = ?");
            $stmt->execute([$sauvegardeId]);
            $sauvegarde = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sauvegarde || $sauvegarde['type'] !== 'fichiers') {
                $errors[] = 'Sauvegarde de fichiers invalide ou introuvable.';
            } else {
                $filePath = realpath(__DIR__ . '/../' . $sauvegarde['fichier']);
                if (!$filePath || !file_exists($filePath) || strpos($filePath, $backupDir) !== 0) {
                    $errors[] = 'Fichier de sauvegarde introuvable ou accès refusé.';
                } else {
                    $root = realpath(__DIR__ . '/..');
                    $cmd = "cd " . escapeshellarg($root) . " && tar -xzf " . escapeshellarg($filePath) . " 2>&1";
                    exec($cmd, $out, $ret);

                    if ($ret === 0) {
                        $successMsg = '✅ Fichiers restaurés avec succès depuis : ' . htmlspecialchars($sauvegarde['nom']);
                    } else {
                        $errors[] = 'Échec de la restauration des fichiers (code: ' . $ret . '). ' . implode(' ', $out);
                    }
                }
            }
        }

        // ── Upload & Restauration ─────────────────────────────────────────────
        if ($action === 'upload_restore') {
            if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Erreur lors du téléversement du fichier.';
            } else {
                $uploadedFile = $_FILES['backup_file'];
                $ext = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
                $allowedExts = ['gz'];
                $allowedTypes = ['application/gzip', 'application/x-gzip', 'application/octet-stream'];

                if (!in_array($ext, $allowedExts)) {
                    $errors[] = 'Type de fichier non supporté. Seuls les fichiers .gz sont acceptés.';
                } elseif ($uploadedFile['size'] > 500 * 1024 * 1024) {
                    $errors[] = 'Fichier trop volumineux (max 500 Mo).';
                } else {
                    $safeName = basename(preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $uploadedFile['name']));
                    // Reject names starting with a dot or containing multiple path separators
                    if ($safeName === '' || $safeName[0] === '.' || strpos($safeName, '/') !== false) {
                        $errors[] = 'Nom de fichier invalide.';
                    } else {
                        $destPath = $backupDir . '/' . $safeName;
                        if (move_uploaded_file($uploadedFile['tmp_name'], $destPath)) {
                            // Déterminer le type
                            $typeDetected = 'bdd';
                            if (strpos($safeName, 'fichiers_') === 0) $typeDetected = 'fichiers';
                            elseif (strpos($safeName, 'complet_') === 0) $typeDetected = 'complet';

                            $pdo->prepare("INSERT INTO sauvegardes (nom, type, fichier, taille, statut, created_by) VALUES (?,?,?,?,'termine','manuel')")
                                ->execute([$safeName, $typeDetected, 'backups/' . $safeName, filesize($destPath)]);
                            $successMsg = '✅ Fichier téléversé avec succès. Vous pouvez maintenant restaurer cette sauvegarde.';
                        } else {
                            $errors[] = 'Impossible de déplacer le fichier téléversé.';
                        }
                    }
                }
            }
        }

        // ── Supprimer une sauvegarde ──────────────────────────────────────────
        if ($action === 'supprimer') {
            $sauvegardeId = (int)($_POST['sauvegarde_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM sauvegardes WHERE id = ?");
            $stmt->execute([$sauvegardeId]);
            $sauvegarde = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($sauvegarde) {
                $filePath = __DIR__ . '/../' . $sauvegarde['fichier'];
                $realPath = realpath($filePath);
                if ($realPath && strpos($realPath, $backupDir) === 0 && file_exists($realPath)) {
                    unlink($realPath);
                }
                $pdo->prepare("DELETE FROM sauvegardes WHERE id = ?")->execute([$sauvegardeId]);
                $successMsg = 'Sauvegarde supprimée.';
            } else {
                $errors[] = 'Sauvegarde introuvable.';
            }
        }

        // ── Enregistrer la configuration ──────────────────────────────────────
        if ($action === 'save_config') {
            $backupActif      = isset($_POST['backup_actif']) ? '1' : '0';
            $backupFrequence  = in_array($_POST['backup_frequence'] ?? 'daily', ['daily', 'weekly', 'monthly']) ? $_POST['backup_frequence'] : 'daily';
            $backupHeure      = max(0, min(23, (int)($_POST['backup_heure'] ?? 2)));
            $backupType       = in_array($_POST['backup_type_auto'] ?? 'complet', ['bdd', 'fichiers', 'complet']) ? $_POST['backup_type_auto'] : 'complet';
            $retentionJours   = max(0, min(365, (int)($_POST['backup_retention_jours'] ?? 30)));
            $maxFichiers      = max(0, min(100, (int)($_POST['backup_max_fichiers'] ?? 10)));

            $params = [
                'backup_actif'           => $backupActif,
                'backup_frequence'       => $backupFrequence,
                'backup_heure'           => (string)$backupHeure,
                'backup_type'            => $backupType,
                'backup_retention_jours' => (string)$retentionJours,
                'backup_max_fichiers'    => (string)$maxFichiers,
            ];

            foreach ($params as $cle => $valeur) {
                $check = $pdo->prepare("SELECT id FROM parametres WHERE cle = ?");
                $check->execute([$cle]);
                if ($check->fetch()) {
                    $pdo->prepare("UPDATE parametres SET valeur = ?, updated_at = NOW() WHERE cle = ?")
                        ->execute([$valeur, $cle]);
                } else {
                    $pdo->prepare("INSERT INTO parametres (cle, valeur, type, description, groupe) VALUES (?, ?, 'string', ?, 'backup')")
                        ->execute([$cle, $valeur, $cle]);
                }
            }
            $successMsg = 'Configuration des sauvegardes enregistrée.';
        }
    }
}

// ─── Téléchargement ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'download') {
    $sauvegardeId = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM sauvegardes WHERE id = ?");
    $stmt->execute([$sauvegardeId]);
    $sauvegarde = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($sauvegarde) {
        $filePath = realpath(__DIR__ . '/../' . $sauvegarde['fichier']);
        if ($filePath && file_exists($filePath) && $backupDir && strpos($filePath, $backupDir) === 0) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        }
    }
    http_response_code(404);
    die('Fichier introuvable.');
}

// ─── Charger la configuration ─────────────────────────────────────────────────
$backupActif     = (bool)getParameter('backup_actif', false);
$backupFrequence = getParameter('backup_frequence', 'daily');
$backupHeure     = (int)getParameter('backup_heure', 2);
$backupType      = getParameter('backup_type', 'complet');
$retentionJours  = (int)getParameter('backup_retention_jours', 30);
$maxFichiers     = (int)getParameter('backup_max_fichiers', 10);

// ─── Charger la liste des sauvegardes ────────────────────────────────────────
try {
    $sauvegardes = $pdo->query("SELECT * FROM sauvegardes ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $sauvegardes = [];
    $errors[] = 'Table sauvegardes non trouvée. Exécutez la migration 080.';
}

// Espace disque
$diskFree  = disk_free_space(__DIR__ . '/..');
$diskTotal = disk_total_space(__DIR__ . '/..');
$diskUsedPct = $diskTotal > 0 ? round(($diskTotal - $diskFree) / $diskTotal * 100, 1) : 0;

$csrfToken = generateCsrfToken();

// Labels
$freqLabels = ['daily' => 'Quotidien', 'weekly' => 'Hebdomadaire', 'monthly' => 'Mensuel'];
$typeLabels = ['bdd' => 'Base de données', 'fichiers' => 'Fichiers', 'complet' => 'Complet (BDD + fichiers)'];
$cronExprs  = [
    'daily'   => "0 {$backupHeure} * * *",
    'weekly'  => "0 {$backupHeure} * * 1",
    'monthly' => "0 {$backupHeure} 1 * *",
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sauvegardes - Administration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <?php require_once __DIR__ . '/includes/sidebar-styles.php'; ?>
    <style>
        .section-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        .section-card h5 {
            color: #2c3e50;
            padding-bottom: 12px;
            border-bottom: 2px solid #198754;
            margin-bottom: 20px;
        }
        .backup-row:hover { background: #f8f9fa; }
        .backup-type-bdd       { background: #cce5ff; color: #004085; }
        .backup-type-fichiers  { background: #d4edda; color: #155724; }
        .backup-type-complet   { background: #e2d9f3; color: #4a235a; }
        .disk-bar { height: 12px; border-radius: 6px; overflow: hidden; background: #e9ecef; }
        .disk-bar-fill { height: 100%; background: linear-gradient(90deg, #198754, #157347); transition: width 0.5s; }
        .disk-bar-fill.danger { background: linear-gradient(90deg, #dc3545, #b02a37); }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/menu.php'; ?>

    <div class="main-content">
        <div class="container-fluid mt-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1><i class="bi bi-archive"></i> Sauvegardes</h1>
                <p class="text-muted mb-0">Sauvegarde et restauration de la base de données et des fichiers</p>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php foreach ($errors as $e): ?><div><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($successMsg): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo htmlspecialchars($successMsg); ?>
            </div>
        <?php endif; ?>

        <!-- Espace disque -->
        <div class="section-card">
            <h5><i class="bi bi-hdd me-2"></i>Espace disque</h5>
            <div class="row g-3 mb-2">
                <div class="col-md-4">
                    <small class="text-muted">Libre</small>
                    <div class="fw-bold"><?php echo round($diskFree / 1024 / 1024 / 1024, 2); ?> Go</div>
                </div>
                <div class="col-md-4">
                    <small class="text-muted">Total</small>
                    <div class="fw-bold"><?php echo round($diskTotal / 1024 / 1024 / 1024, 2); ?> Go</div>
                </div>
                <div class="col-md-4">
                    <small class="text-muted">Utilisé</small>
                    <div class="fw-bold"><?php echo $diskUsedPct; ?>%</div>
                </div>
            </div>
            <div class="disk-bar">
                <div class="disk-bar-fill <?php echo $diskUsedPct > 85 ? 'danger' : ''; ?>"
                     style="width: <?php echo min(100, $diskUsedPct); ?>%"></div>
            </div>
        </div>

        <!-- Sauvegarde manuelle -->
        <div class="section-card">
            <h5><i class="bi bi-cloud-arrow-down me-2"></i>Créer une sauvegarde</h5>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="backup_now">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Type de sauvegarde</label>
                        <select class="form-select" name="backup_type_manuel">
                            <option value="complet">Complet (BDD + fichiers)</option>
                            <option value="bdd">Base de données uniquement</option>
                            <option value="fichiers">Fichiers uniquement</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="bi bi-cloud-arrow-down me-2"></i>Sauvegarder maintenant
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Téléverser une sauvegarde -->
        <div class="section-card">
            <h5><i class="bi bi-upload me-2"></i>Téléverser une sauvegarde existante</h5>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="upload_restore">
                <div class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Fichier de sauvegarde (.sql.gz ou .tar.gz)</label>
                        <input type="file" class="form-control" name="backup_file" accept=".gz">
                        <div class="form-text">Max 500 Mo. Formats acceptés : .sql.gz (BDD), .tar.gz (fichiers)</div>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload me-2"></i>Téléverser
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Liste des sauvegardes -->
        <div class="section-card">
            <h5><i class="bi bi-list-ul me-2"></i>Sauvegardes disponibles</h5>
            <?php if (empty($sauvegardes)): ?>
                <div class="alert alert-info mb-0">
                    <i class="bi bi-info-circle me-2"></i>Aucune sauvegarde disponible. Créez votre première sauvegarde ci-dessus.
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Nom</th>
                            <th>Type</th>
                            <th>Taille</th>
                            <th>Créé par</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sauvegardes as $s): ?>
                        <?php
                        $filePath = realpath(__DIR__ . '/../' . $s['fichier']);
                        $exists   = $filePath && file_exists($filePath) && $backupDir && strpos($filePath, $backupDir) === 0;
                        ?>
                        <tr class="backup-row">
                            <td>
                                <i class="bi bi-file-earmark-zip me-1 text-secondary"></i>
                                <?php echo htmlspecialchars($s['nom']); ?>
                                <?php if (!$exists): ?><span class="badge bg-danger ms-1">Fichier manquant</span><?php endif; ?>
                            </td>
                            <td>
                                <span class="badge backup-type-<?php echo $s['type']; ?>">
                                    <?php echo htmlspecialchars($typeLabels[$s['type']] ?? $s['type']); ?>
                                </span>
                            </td>
                            <td><?php echo $s['taille'] > 0 ? round($s['taille'] / 1024, 1) . ' Ko' : '—'; ?></td>
                            <td>
                                <span class="badge bg-<?php echo $s['created_by'] === 'cron' ? 'secondary' : 'info text-dark'; ?>">
                                    <?php echo $s['created_by'] === 'cron' ? 'Auto' : 'Manuel'; ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($s['created_at'])); ?></td>
                            <td>
                                <?php if ($exists): ?>
                                    <a href="sauvegardes.php?action=download&id=<?php echo $s['id']; ?>"
                                       class="btn btn-sm btn-outline-primary me-1" title="Télécharger">
                                        <i class="bi bi-download"></i>
                                    </a>
                                    <?php if ($s['type'] === 'bdd'): ?>
                                    <button type="button" class="btn btn-sm btn-outline-warning me-1"
                                            onclick="confirmRestore(<?php echo $s['id']; ?>, 'restore_bdd', '<?php echo htmlspecialchars($s['nom'], ENT_QUOTES); ?>')"
                                            title="Restaurer la BDD">
                                        <i class="bi bi-arrow-counterclockwise"></i> Restaurer
                                    </button>
                                    <?php elseif ($s['type'] === 'fichiers'): ?>
                                    <button type="button" class="btn btn-sm btn-outline-warning me-1"
                                            onclick="confirmRestore(<?php echo $s['id']; ?>, 'restore_fichiers', '<?php echo htmlspecialchars($s['nom'], ENT_QUOTES); ?>')"
                                            title="Restaurer les fichiers">
                                        <i class="bi bi-arrow-counterclockwise"></i> Restaurer
                                    </button>
                                    <?php else: ?>
                                    <span class="text-muted small">Restauration manuelle requise</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <form method="POST" action="" class="d-inline"
                                      onsubmit="return confirm('Supprimer cette sauvegarde ?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                    <input type="hidden" name="action" value="supprimer">
                                    <input type="hidden" name="sauvegarde_id" value="<?php echo $s['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Configuration des sauvegardes automatiques -->
        <div class="section-card">
            <h5><i class="bi bi-calendar-event me-2"></i>Sauvegardes automatiques</h5>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="save_config">

                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" role="switch" id="backup_actif"
                           name="backup_actif" <?php echo $backupActif ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="backup_actif">
                        <strong>Activer les sauvegardes automatiques</strong>
                    </label>
                </div>

                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Fréquence</label>
                        <select class="form-select" name="backup_frequence" id="backup_frequence">
                            <option value="daily"   <?php echo $backupFrequence === 'daily'   ? 'selected' : ''; ?>>Quotidien</option>
                            <option value="weekly"  <?php echo $backupFrequence === 'weekly'  ? 'selected' : ''; ?>>Hebdomadaire (lundi)</option>
                            <option value="monthly" <?php echo $backupFrequence === 'monthly' ? 'selected' : ''; ?>>Mensuel (1er du mois)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Heure d'exécution</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="backup_heure" id="backup_heure"
                                   value="<?php echo $backupHeure; ?>" min="0" max="23">
                            <span class="input-group-text">h</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Type de sauvegarde</label>
                        <select class="form-select" name="backup_type_auto">
                            <option value="complet"  <?php echo $backupType === 'complet'  ? 'selected' : ''; ?>>Complet (BDD + fichiers)</option>
                            <option value="bdd"      <?php echo $backupType === 'bdd'      ? 'selected' : ''; ?>>Base de données</option>
                            <option value="fichiers" <?php echo $backupType === 'fichiers' ? 'selected' : ''; ?>>Fichiers</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Rétention (jours)</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="backup_retention_jours"
                                   value="<?php echo $retentionJours; ?>" min="0" max="365">
                            <span class="input-group-text">jours</span>
                        </div>
                        <div class="form-text">0 = illimité</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Nombre max de sauvegardes</label>
                        <input type="number" class="form-control" name="backup_max_fichiers"
                               value="<?php echo $maxFichiers; ?>" min="0" max="100">
                        <div class="form-text">0 = illimité</div>
                    </div>
                </div>

                <div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Expression cron à configurer :</strong>
                    <code id="cronExpr"><?php
                        $cronExprs2 = [
                            'daily'   => "0 {$backupHeure} * * *",
                            'weekly'  => "0 {$backupHeure} * * 1",
                            'monthly' => "0 {$backupHeure} 1 * *",
                        ];
                        echo htmlspecialchars($cronExprs2[$backupFrequence] ?? "0 $backupHeure * * *");
                    ?></code>
                    &nbsp;→&nbsp; commande : <code>php <?php echo htmlspecialchars(realpath(__DIR__ . '/../cron/backup.php') ?: '/path/to/cron/backup.php'); ?></code>
                    <br><small class="text-muted">Vous pouvez également activer ce cron dans <a href="cron-jobs.php">Tâches Automatisées</a>.</small>
                </div>

                <button type="submit" class="btn btn-success">
                    <i class="bi bi-save me-2"></i>Enregistrer la configuration
                </button>
            </form>
        </div>

    </div>
    </div>

    <!-- Formulaire caché pour restauration -->
    <form id="restoreForm" method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <input type="hidden" name="action" id="restoreAction" value="">
        <input type="hidden" name="sauvegarde_id" id="restoreSauvegardeId" value="">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function confirmRestore(id, action, nom) {
        const msg = action === 'restore_bdd'
            ? '⚠️ ATTENTION : Restaurer la base de données remplacera TOUTES les données actuelles par celles de la sauvegarde.\n\nSauvegarde : ' + nom + '\n\nÊtes-vous certain de vouloir continuer ?'
            : '⚠️ ATTENTION : Restaurer les fichiers remplacera les fichiers actuels par ceux de la sauvegarde.\n\nSauvegarde : ' + nom + '\n\nÊtes-vous certain de vouloir continuer ?';
        if (confirm(msg)) {
            document.getElementById('restoreAction').value = action;
            document.getElementById('restoreSauvegardeId').value = id;
            document.getElementById('restoreForm').submit();
        }
    }

    // Mettre à jour l'expression cron en temps réel
    function updateCronExpr() {
        const freq = document.getElementById('backup_frequence').value;
        const heure = document.getElementById('backup_heure').value || '2';
        const exprs = {
            daily:   `0 ${heure} * * *`,
            weekly:  `0 ${heure} * * 1`,
            monthly: `0 ${heure} 1 * *`,
        };
        document.getElementById('cronExpr').textContent = exprs[freq] || `0 ${heure} * * *`;
    }

    document.getElementById('backup_frequence')?.addEventListener('change', updateCronExpr);
    document.getElementById('backup_heure')?.addEventListener('input', updateCronExpr);
    </script>
</body>
</html>
