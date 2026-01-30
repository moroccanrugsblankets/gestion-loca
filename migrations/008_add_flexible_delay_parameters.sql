-- Migration: Add flexible delay parameters for automatic responses
-- Date: 2026-01-30
-- Description: Add unit-based delay configuration (minutes/hours/days)

-- Add new parameters for flexible delay configuration
INSERT INTO parametres (cle, valeur, type, description, groupe) VALUES
('delai_reponse_unite', 'jours', 'string', 'Unité de temps pour le délai de réponse automatique (minutes/heures/jours)', 'workflow'),
('delai_reponse_valeur', '4', 'integer', 'Valeur du délai de réponse automatique', 'workflow')
ON DUPLICATE KEY UPDATE cle=cle;

-- Note: We keep delai_reponse_jours for backward compatibility
-- The new parameters (delai_reponse_unite + delai_reponse_valeur) will take precedence when set
