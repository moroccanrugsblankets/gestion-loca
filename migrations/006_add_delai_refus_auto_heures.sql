-- Migration: Add automatic rejection delay in hours parameter
-- Date: 2026-01-30
-- Description: Add parameter to configure automatic rejection response delay in hours

INSERT INTO parametres (cle, valeur, type, description, groupe) VALUES
('delai_refus_auto_heures', '48', 'integer', 'Délai en heures avant l\'envoi automatique de la réponse de refus de candidature', 'workflow')
ON DUPLICATE KEY UPDATE cle=cle;
