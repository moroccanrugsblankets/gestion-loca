-- Migration: Add admin email parameter
-- Date: 2026-02-09
-- Description: Add parameter for admin notification email address

-- Create parametres table if it doesn't exist
CREATE TABLE IF NOT EXISTS parametres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cle VARCHAR(100) NOT NULL UNIQUE,
    valeur TEXT,
    type ENUM('string', 'integer', 'float', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    groupe VARCHAR(50) DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add parameter for admin email
INSERT INTO parametres (cle, valeur, type, description, groupe) VALUES
('email_admin', 'location@myinvest-immobilier.com', 'string', 'Adresse email principale pour les notifications administrateur', 'email')
ON DUPLICATE KEY UPDATE cle=cle;
