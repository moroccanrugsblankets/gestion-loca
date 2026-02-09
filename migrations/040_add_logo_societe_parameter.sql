-- Migration: Add logo_societe parameter
-- Date: 2026-02-09
-- Description: Add company logo parameter to parametres table

INSERT INTO parametres (cle, valeur, type, description, groupe) VALUES
('logo_societe', '/assets/images/logo-my-invest-immobilier-carre.jpg', 'string', 'Logo de la société à afficher dans le menu (chemin relatif)', 'general')
ON DUPLICATE KEY UPDATE cle=cle;
