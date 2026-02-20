-- Migration: Fix rappel-loyers cron expression and ensure correct configuration
-- Date: 2026-02-20
-- Description: Updates the cron_expression for rappel-loyers to 00 11 * * * and
--              ensures rappel_loyers_actif defaults to enabled (1).
--              The cron executes daily but only sends emails on configured days.

-- Update cron expression to 00 11 * * * (daily at 11:00)
-- Note: Configure your OVH/server cron to match this expression
UPDATE cron_jobs
SET cron_expression = '00 11 * * *',
    frequence = 'Quotidien (vérifie si c''est un jour de rappel configuré)'
WHERE fichier = 'cron/rappel-loyers.php';

-- Ensure the module is active by default if not already configured
INSERT INTO parametres (cle, valeur, type, description) VALUES
('rappel_loyers_actif', '1', 'boolean', 'Active ou désactive les rappels automatiques de loyers')
ON DUPLICATE KEY UPDATE
    type = VALUES(type),
    description = VALUES(description);
