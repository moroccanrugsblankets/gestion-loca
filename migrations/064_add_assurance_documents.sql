-- Migration 064: Ajout des colonnes pour les documents d'assurance et Visale
-- Date: 2026-02-22

-- Migration 064: Ajout des colonnes pour les documents d'assurance et Visale
-- Date: 2026-02-22

ALTER TABLE contrats
ADD COLUMN token_assurance VARCHAR(100) NULL UNIQUE COMMENT 'Token pour le lien d upload des documents assurance/visale',
ADD COLUMN assurance_habitation VARCHAR(255) NULL COMMENT 'Nom du fichier attestation d''assurance habitation',
ADD COLUMN visa_certifie VARCHAR(255) NULL COMMENT 'Nom du fichier visa certifié Visale (optionnel)',
ADD COLUMN numero_visale VARCHAR(100) NULL COMMENT 'Numéro de garantie Visale',
ADD COLUMN date_envoi_assurance TIMESTAMP NULL COMMENT 'Date d envoi des documents assurance/visale par le locataire';
