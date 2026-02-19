-- Migration: Add cron job for rappel-loyers.php
-- Date: 2026-02-19
-- Description: Ensure the rappel-loyers cron job is configured in cron_jobs table
--              This addresses the issue where the cron doesn't execute automatically

-- Insert or update the cron job entry for rappel-loyers.php
INSERT INTO cron_jobs (nom, description, fichier, frequence, cron_expression, actif) VALUES
(
    'Rappel Loyers',
    'Envoi automatique de rappels concernant le paiement des loyers aux administrateurs selon les dates configurées (par défaut: 7, 9, 15 du mois)',
    'cron/rappel-loyers.php',
    'daily',
    '0 9 * * *',
    1
)
ON DUPLICATE KEY UPDATE 
    nom = VALUES(nom),
    description = VALUES(description),
    frequence = VALUES(frequence),
    cron_expression = VALUES(cron_expression);

-- Ensure the required parameters exist (if not already created by migration_loyers_tracking.sql)
INSERT IGNORE INTO parametres (cle, valeur, type, description) VALUES
('rappel_loyers_dates_envoi', '[7, 9, 15]', 'json', 'Jours du mois où les rappels de loyers sont envoyés automatiquement'),
('rappel_loyers_destinataires', '[]', 'json', 'Liste des emails administrateurs destinataires des rappels'),
('rappel_loyers_actif', '1', 'boolean', 'Active ou désactive les rappels automatiques de loyers'),
('rappel_loyers_inclure_bouton', '1', 'boolean', 'Inclure un bouton vers l''interface de gestion dans les emails de rappel'),
('rappel_loyers_heure_execution', '09:00', 'string', 'Heure d''exécution du cron job de rappel de loyers (format HH:MM)');
