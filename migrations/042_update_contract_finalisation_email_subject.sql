-- Migration: Update contract finalisation email subject to include company name
-- Date: 2026-02-09
-- Description: Add "My Invest Immobilier: " prefix to the contract finalisation email subject

UPDATE email_templates
SET sujet = 'My Invest Immobilier: Contrat de bail – Finalisation'
WHERE identifiant = 'contrat_finalisation_client';
