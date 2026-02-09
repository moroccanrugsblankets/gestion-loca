-- Migration: Add payment proof field to contrats table
-- Date: 2026-02-09
-- Description: Add field to store payment proof filename and date

ALTER TABLE contrats 
ADD COLUMN justificatif_paiement VARCHAR(255) NULL COMMENT 'Nom du fichier justificatif de paiement',
ADD COLUMN date_envoi_justificatif TIMESTAMP NULL COMMENT 'Date d''envoi du justificatif';
