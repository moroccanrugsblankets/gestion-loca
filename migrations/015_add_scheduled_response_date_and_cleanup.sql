-- Migration 015: Add scheduled_response_date column and remove obsolete parameters
-- This migration fixes the issue where changing the automatic response delay parameter
-- would affect already scheduled tasks. The scheduled_response_date will store the
-- fixed date calculated when the candidature is refused.

-- Add the scheduled_response_date column to candidatures table
ALTER TABLE candidatures 
ADD COLUMN scheduled_response_date DATETIME NULL 
COMMENT 'Date fixe de réponse prévue, calculée lors du refus' 
AFTER date_reponse_auto;

-- Remove obsolete parameters that are no longer used
-- These have been replaced by delai_reponse_valeur and delai_reponse_unite
DELETE FROM parametres WHERE cle = 'delai_reponse_jours';
DELETE FROM parametres WHERE cle = 'delai_refus_auto_heures';
