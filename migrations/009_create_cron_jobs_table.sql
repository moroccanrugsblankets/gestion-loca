-- Migration: Create cron jobs management table
-- Date: 2026-01-30
-- Description: Table to track and manage scheduled cron jobs

CREATE TABLE IF NOT EXISTS cron_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL COMMENT 'Job name',
    description TEXT COMMENT 'Job description',
    fichier VARCHAR(255) NOT NULL COMMENT 'PHP file path relative to project root',
    frequence VARCHAR(50) DEFAULT 'daily' COMMENT 'Execution frequency: hourly/daily/weekly',
    cron_expression VARCHAR(100) COMMENT 'Cron expression (e.g., 0 9 * * *)',
    actif BOOLEAN DEFAULT TRUE COMMENT 'Job is active/enabled',
    derniere_execution TIMESTAMP NULL COMMENT 'Last execution time',
    prochaine_execution TIMESTAMP NULL COMMENT 'Next scheduled execution',
    statut_derniere_execution ENUM('success', 'error', 'running') DEFAULT NULL COMMENT 'Status of last execution',
    log_derniere_execution TEXT COMMENT 'Log output from last execution',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_actif (actif),
    INDEX idx_prochaine_execution (prochaine_execution)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default cron job for processing candidatures
INSERT INTO cron_jobs (nom, description, fichier, frequence, cron_expression, actif) VALUES
('Traitement des candidatures', 
 'Traite automatiquement les candidatures et envoie les réponses d''acceptation ou de refus selon les critères configurés',
 'cron/process-candidatures.php',
 'daily',
 '0 9 * * *',
 TRUE)
ON DUPLICATE KEY UPDATE nom=nom;
