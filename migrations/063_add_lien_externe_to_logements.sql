-- Migration 063: Add lien_externe field to logements table
-- Allows storing an external URL for each logement (e.g., listing on another website)

ALTER TABLE logements ADD COLUMN IF NOT EXISTS lien_externe VARCHAR(2048) NULL DEFAULT NULL COMMENT 'Lien externe vers la page du logement sur un autre site';
