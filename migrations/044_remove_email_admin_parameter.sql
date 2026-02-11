-- Migration: Remove email_admin parameter
-- Date: 2026-02-11
-- Description: Remove the email_admin parameter as the admin email should only be configured in includes/config.php

-- Remove the email_admin parameter
DELETE FROM parametres WHERE cle = 'email_admin';
