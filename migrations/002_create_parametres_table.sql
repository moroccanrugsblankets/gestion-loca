-- Migration: Create parameters table
-- Date: 2026-01-29
-- Description: Create table to store application parameters

CREATE TABLE IF NOT EXISTS parametres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cle VARCHAR(100) UNIQUE NOT NULL COMMENT 'Parameter key',
    valeur TEXT NOT NULL COMMENT 'Parameter value',
    type ENUM('string', 'integer', 'float', 'boolean', 'json') DEFAULT 'string' COMMENT 'Value type',
    description TEXT COMMENT 'Parameter description',
    groupe VARCHAR(50) DEFAULT 'general' COMMENT 'Parameter group',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_cle (cle),
    INDEX idx_groupe (groupe)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default parameters
INSERT INTO parametres (cle, valeur, type, description, groupe) VALUES
('delai_reponse_jours', '4', 'integer', 'Délai en jours ouvrés avant envoi de la réponse automatique', 'workflow'),
('jours_ouvres_debut', '1', 'integer', 'Premier jour ouvré de la semaine (1 = Lundi)', 'workflow'),
('jours_ouvres_fin', '5', 'integer', 'Dernier jour ouvré de la semaine (5 = Vendredi)', 'workflow'),
('revenus_min_requis', '3000', 'integer', 'Revenus nets mensuels minimum requis (en euros)', 'criteres'),
('statuts_pro_acceptes', '["CDI", "CDD"]', 'json', 'Statuts professionnels acceptés automatiquement', 'criteres'),
('type_revenus_accepte', 'Salaires', 'string', 'Type de revenus accepté', 'criteres'),
('nb_occupants_acceptes', '["1", "2"]', 'json', 'Nombres d\'occupants acceptés', 'criteres'),
('garantie_visale_requise', 'true', 'boolean', 'Garantie Visale requise (true/false)', 'criteres')
ON DUPLICATE KEY UPDATE cle=cle;
