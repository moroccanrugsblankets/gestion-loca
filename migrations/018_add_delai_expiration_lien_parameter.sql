-- Migration: Add contract link expiration delay parameter
-- Date: 2026-02-01
-- Description: Add parameter to configure contract link expiration delay (in hours)

-- Add parameter for contract link expiration delay
INSERT INTO parametres (cle, valeur, type, description, groupe) VALUES
('delai_expiration_lien_contrat', '24', 'integer', 'Délai d\'expiration du lien de signature (en heures)', 'general')
ON DUPLICATE KEY UPDATE cle=cle;
