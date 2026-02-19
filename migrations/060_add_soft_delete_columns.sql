-- Migration: Add soft delete support to critical tables
-- Date: 2026-02-19
-- Description: Add deleted_at column to enable soft deletes instead of physical DELETE queries
--              This preserves data integrity and maintains audit trails

-- Add deleted_at column to candidatures table
ALTER TABLE candidatures 
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete timestamp - NULL = active, NOT NULL = deleted',
ADD INDEX idx_deleted_at (deleted_at);

-- Add deleted_at column to contrats table
ALTER TABLE contrats 
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete timestamp - NULL = active, NOT NULL = deleted',
ADD INDEX idx_deleted_at (deleted_at);

-- Add deleted_at column to logements table
ALTER TABLE logements 
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete timestamp - NULL = active, NOT NULL = deleted',
ADD INDEX idx_deleted_at (deleted_at);

-- Add deleted_at column to inventaires table
ALTER TABLE inventaires 
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete timestamp - NULL = active, NOT NULL = deleted',
ADD INDEX idx_deleted_at (deleted_at);

-- Add deleted_at column to etats_lieux table
ALTER TABLE etats_lieux 
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete timestamp - NULL = active, NOT NULL = deleted',
ADD INDEX idx_deleted_at (deleted_at);

-- Add deleted_at column to quittances table
ALTER TABLE quittances 
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete timestamp - NULL = active, NOT NULL = deleted',
ADD INDEX idx_deleted_at (deleted_at);

-- Add deleted_at column to administrateurs table
ALTER TABLE administrateurs 
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete timestamp - NULL = active, NOT NULL = deleted',
ADD INDEX idx_deleted_at (deleted_at);

-- Add deleted_at column to inventaire_categories table
ALTER TABLE inventaire_categories 
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete timestamp - NULL = active, NOT NULL = deleted',
ADD INDEX idx_deleted_at (deleted_at);

-- Add deleted_at column to inventaire_sous_categories table
ALTER TABLE inventaire_sous_categories 
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete timestamp - NULL = active, NOT NULL = deleted',
ADD INDEX idx_deleted_at (deleted_at);

-- Add deleted_at column to inventaire_equipements table
ALTER TABLE inventaire_equipements 
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete timestamp - NULL = active, NOT NULL = deleted',
ADD INDEX idx_deleted_at (deleted_at);

-- Add deleted_at column to etat_lieux_photos table
ALTER TABLE etat_lieux_photos 
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete timestamp - NULL = active, NOT NULL = deleted',
ADD INDEX idx_deleted_at (deleted_at);

-- Add deleted_at column to candidature_documents table
ALTER TABLE candidature_documents 
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete timestamp - NULL = active, NOT NULL = deleted',
ADD INDEX idx_deleted_at (deleted_at);

-- Add deleted_at column to inventaire_locataires table (if not already exists)
ALTER TABLE inventaire_locataires 
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete timestamp - NULL = active, NOT NULL = deleted',
ADD INDEX idx_deleted_at (deleted_at);
