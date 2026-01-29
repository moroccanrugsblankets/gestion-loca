-- Migration: Add new fields to logements table
-- Date: 2026-01-29
-- Description: Add total_mensuel, revenus_requis, and update type field

-- Add new fields to logements table
-- Use COALESCE to handle NULL values in calculations
ALTER TABLE logements 
ADD COLUMN total_mensuel DECIMAL(10,2) GENERATED ALWAYS AS (COALESCE(loyer, 0) + COALESCE(charges, 0)) STORED COMMENT 'Total mensuel (loyer + charges)',
ADD COLUMN revenus_requis DECIMAL(10,2) GENERATED ALWAYS AS ((COALESCE(loyer, 0) + COALESCE(charges, 0)) * 3) STORED COMMENT 'Revenus requis (3x total mensuel)';

-- Update date_disponibilite column if not exists (already in schema, but ensure it's there)
-- ALTER TABLE logements ADD COLUMN IF NOT EXISTS date_disponibilite DATE;

-- Note: The 'type' field will be constrained in the application layer to allow only 'T1 Bis' and 'T2'
-- For now, we keep it as VARCHAR to maintain backward compatibility

-- Fix statut field values to be consistent
-- Database uses lowercase, UI uses capitalized - we'll standardize on lowercase in DB
UPDATE logements SET statut = LOWER(statut) WHERE statut NOT IN ('disponible', 'en_location', 'maintenance', 'indisponible');

-- Map French capitalized values to lowercase database values
UPDATE logements SET statut = 'disponible' WHERE statut = 'Disponible';
UPDATE logements SET statut = 'en_location' WHERE statut IN ('Loué', 'Réservé');
UPDATE logements SET statut = 'maintenance' WHERE statut = 'Maintenance';
