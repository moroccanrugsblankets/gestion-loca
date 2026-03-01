-- Migration 080: Paramètres de sauvegarde automatique
-- Date: 2026-03-01
-- Description: Ajoute les paramètres de configuration pour le système de sauvegarde/restauration

-- Créer la table des sauvegardes
CREATE TABLE IF NOT EXISTS sauvegardes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    type ENUM('bdd','fichiers','complet') NOT NULL DEFAULT 'complet',
    fichier VARCHAR(500) NOT NULL COMMENT 'Chemin relatif vers le fichier de sauvegarde',
    taille BIGINT DEFAULT 0 COMMENT 'Taille en octets',
    statut ENUM('en_cours','termine','erreur') DEFAULT 'termine',
    notes TEXT,
    created_by ENUM('manuel','cron') DEFAULT 'manuel',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Paramètres de configuration des sauvegardes
INSERT INTO parametres (cle, valeur, type, description, groupe) VALUES
('backup_actif', '0', 'boolean', 'Activer les sauvegardes automatiques', 'backup'),
('backup_frequence', 'daily', 'string', 'Fréquence des sauvegardes automatiques (daily, weekly, monthly)', 'backup'),
('backup_heure', '2', 'integer', 'Heure d\'exécution des sauvegardes automatiques (0-23)', 'backup'),
('backup_type', 'complet', 'string', 'Type de sauvegarde par défaut (bdd, fichiers, complet)', 'backup'),
('backup_retention_jours', '30', 'integer', 'Nombre de jours de rétention des sauvegardes (0 = illimité)', 'backup'),
('backup_max_fichiers', '10', 'integer', 'Nombre maximum de sauvegardes à conserver (0 = illimité)', 'backup')
ON DUPLICATE KEY UPDATE cle = cle;

-- Enregistrer le cron de sauvegarde
INSERT INTO cron_jobs (nom, description, fichier, frequence, cron_expression, actif) VALUES
(
    'Sauvegarde automatique',
    'Sauvegarde automatique de la base de données et/ou des fichiers selon la configuration définie dans Sauvegardes',
    'cron/backup.php',
    'daily',
    '0 2 * * *',
    0
)
ON DUPLICATE KEY UPDATE
    nom = VALUES(nom),
    description = VALUES(description),
    frequence = VALUES(frequence),
    cron_expression = VALUES(cron_expression);

