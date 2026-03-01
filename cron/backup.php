#!/usr/bin/env php
<?php
/**
 * Cron de sauvegarde automatique
 *
 * Exécute une sauvegarde automatique de la base de données et/ou des fichiers
 * selon la configuration définie dans /admin-v2/sauvegardes.php
 *
 * Usage: php cron/backup.php [type]
 *   type: bdd | fichiers | complet (défaut: valeur configurée)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

function logBackup(string $msg, bool $isError = false): void {
    $level = $isError ? '[ERROR]' : '[INFO]';
    echo "[" . date('Y-m-d H:i:s') . "] $level $msg\n";
}

$backupActif = getParameter('backup_actif', false);
if (!$backupActif) {
    logBackup('Sauvegardes automatiques désactivées.');
    exit(0);
}

$typeArg = $argv[1] ?? null;
$type = $typeArg ?? getParameter('backup_type', 'complet');
if (!in_array($type, ['bdd', 'fichiers', 'complet'])) {
    logBackup("Type de sauvegarde invalide: $type", true);
    exit(1);
}

logBackup("Démarrage sauvegarde de type: $type");

$backupDir = __DIR__ . '/../backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$timestamp = date('Y-m-d_H-i-s');
$errors = [];
$filesCreated = [];

// ─── Sauvegarde Base de Données ───────────────────────────────────────────────
if ($type === 'bdd' || $type === 'complet') {
    logBackup("Sauvegarde de la base de données...");
    $dbFile = $backupDir . '/bdd_' . $timestamp . '.sql.gz';

    $host     = $config['DB_HOST'] ?? 'localhost';
    $dbname   = $config['DB_NAME'] ?? '';
    $user     = $config['DB_USER'] ?? '';
    $password = $config['DB_PASS'] ?? '';

    if (empty($dbname) || empty($user)) {
        $errors[] = "Configuration base de données manquante.";
        logBackup("Configuration DB manquante.", true);
    } else {
        // Utiliser mysqldump via proc_open pour éviter d'exposer le mot de passe dans les arguments
        $passFile = tempnam(sys_get_temp_dir(), bin2hex(random_bytes(4)));
        file_put_contents($passFile, "[client]\npassword=" . $password . "\n");
        chmod($passFile, 0600);

        $cmd = "mysqldump --defaults-extra-file=" . escapeshellarg($passFile)
             . " -h " . escapeshellarg($host)
             . " -u " . escapeshellarg($user)
             . " " . escapeshellarg($dbname)
             . " | gzip > " . escapeshellarg($dbFile);

        exec($cmd, $output, $returnCode);
        if (file_exists($passFile)) {
            unlink($passFile);
        }

        if ($returnCode !== 0 || !file_exists($dbFile)) {
            $errors[] = "Échec mysqldump (code: $returnCode).";
            logBackup("Échec mysqldump (code: $returnCode).", true);
        } else {
            $size = filesize($dbFile);
            logBackup("✅ BDD sauvegardée: bdd_$timestamp.sql.gz (" . round($size / 1024, 1) . " Ko)");
            $filesCreated[] = ['file' => 'bdd_' . $timestamp . '.sql.gz', 'type' => 'bdd', 'size' => $size];
        }
    }
}

// ─── Sauvegarde Fichiers ──────────────────────────────────────────────────────
if ($type === 'fichiers' || $type === 'complet') {
    logBackup("Sauvegarde des fichiers...");
    $filesArchive = $backupDir . '/fichiers_' . $timestamp . '.tar.gz';

    // Répertoires à sauvegarder (uploads, assets personnalisés)
    $root = realpath(__DIR__ . '/..');
    $dirsToBackup = ['uploads', 'assets/img'];
    $existingDirs = array_filter($dirsToBackup, fn($d) => is_dir($root . '/' . $d));

    if (empty($existingDirs)) {
        logBackup("Aucun répertoire à sauvegarder trouvé.");
    } else {
        $dirArgs = implode(' ', array_map(fn($d) => escapeshellarg($d), $existingDirs));
        $cmd = "cd " . escapeshellarg($root) . " && tar -czf " . escapeshellarg($filesArchive) . " $dirArgs";
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($filesArchive)) {
            $errors[] = "Échec archive fichiers (code: $returnCode).";
            logBackup("Échec archive fichiers (code: $returnCode).", true);
        } else {
            $size = filesize($filesArchive);
            logBackup("✅ Fichiers sauvegardés: fichiers_$timestamp.tar.gz (" . round($size / 1024, 1) . " Ko)");
            $filesCreated[] = ['file' => 'fichiers_' . $timestamp . '.tar.gz', 'type' => 'fichiers', 'size' => $size];
        }
    }
}

// ─── Enregistrer en base ──────────────────────────────────────────────────────
foreach ($filesCreated as $f) {
    try {
        $pdo->prepare("
            INSERT INTO sauvegardes (nom, type, fichier, taille, statut, created_by)
            VALUES (?, ?, ?, ?, 'termine', 'cron')
        ")->execute([$f['file'], $f['type'], 'backups/' . $f['file'], $f['size']]);
    } catch (Exception $e) {
        logBackup("Erreur enregistrement DB: " . $e->getMessage(), true);
    }
}

// ─── Nettoyage des anciennes sauvegardes ─────────────────────────────────────
$retentionJours = (int)getParameter('backup_retention_jours', 30);
$maxFichiers    = (int)getParameter('backup_max_fichiers', 10);

if ($retentionJours > 0) {
    try {
        $oldStmt = $pdo->prepare("
            SELECT id, fichier FROM sauvegardes
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $oldStmt->execute([$retentionJours]);
        $oldBackups = $oldStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($oldBackups as $old) {
            $filePath = __DIR__ . '/../' . $old['fichier'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $pdo->prepare("DELETE FROM sauvegardes WHERE id = ?")->execute([$old['id']]);
            logBackup("Ancienne sauvegarde supprimée: " . $old['fichier']);
        }
    } catch (Exception $e) {
        logBackup("Erreur nettoyage: " . $e->getMessage(), true);
    }
}

if ($maxFichiers > 0) {
    try {
        $countStmt = $pdo->query("SELECT COUNT(*) FROM sauvegardes");
        $count = (int)$countStmt->fetchColumn();
        if ($count > $maxFichiers) {
            $toDelete = $count - $maxFichiers;
            $oldStmt = $pdo->prepare("SELECT id, fichier FROM sauvegardes ORDER BY created_at ASC LIMIT ?");
            $oldStmt->bindValue(1, $toDelete, PDO::PARAM_INT);
            $oldStmt->execute();
            $oldBackups = $oldStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($oldBackups as $old) {
                $filePath = __DIR__ . '/../' . $old['fichier'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                $pdo->prepare("DELETE FROM sauvegardes WHERE id = ?")->execute([$old['id']]);
                logBackup("Sauvegarde supprimée (quota dépassé): " . $old['fichier']);
            }
        }
    } catch (Exception $e) {
        logBackup("Erreur quota: " . $e->getMessage(), true);
    }
}

if (!empty($errors)) {
    logBackup("Cron backup terminé avec " . count($errors) . " erreur(s).", true);
    exit(1);
}

logBackup("Cron backup terminé avec succès.");
exit(0);
