-- Migration: Remove email_admin_candidature parameter
-- Date: 2026-02-01
-- Description: Remove the email_admin_candidature parameter as notifications should be sent to all administrators from the administrateurs table

-- Remove the email_admin_candidature parameter
DELETE FROM parametres WHERE cle = 'email_admin_candidature';
