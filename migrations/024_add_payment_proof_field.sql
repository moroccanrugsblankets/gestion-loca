-- Migration: Add payment proof field to locataires table
-- Date: 2026-02-03
-- Description: Add preuve_paiement_depot field to store payment proof document

ALTER TABLE locataires
ADD COLUMN preuve_paiement_depot VARCHAR(255) DEFAULT NULL AFTER piece_identite_verso;
