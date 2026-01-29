-- Migration: Update document types for candidature_documents table
-- Date: 2026-01-29
-- Purpose: Add new specific document types for rental applications

-- Update the ENUM type for type_document to include all new document types
ALTER TABLE candidature_documents 
MODIFY COLUMN type_document ENUM(
    'piece_identite', 
    'bulletins_salaire', 
    'contrat_travail', 
    'avis_imposition', 
    'quittances_loyer',
    'justificatif_revenus', 
    'justificatif_domicile', 
    'autre'
) NOT NULL;
