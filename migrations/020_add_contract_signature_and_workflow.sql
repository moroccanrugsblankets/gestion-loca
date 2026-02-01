-- Migration: Add contract signature and workflow enhancements
-- Date: 2026-02-01
-- Description: Add company signature image and enhanced workflow states for contract validation

-- Add company signature image to parametres table
INSERT INTO parametres (cle, valeur, type, description, groupe) VALUES
('signature_societe_image', '', 'string', 'Image de la signature électronique de la société (base64 ou chemin fichier)', 'contrats'),
('signature_societe_enabled', 'false', 'boolean', 'Activer l''ajout automatique de la signature société lors de la validation', 'contrats')
ON DUPLICATE KEY UPDATE cle=cle;

-- Add new contract workflow status values
-- Update the contrats table to support enhanced workflow
ALTER TABLE contrats 
MODIFY COLUMN statut ENUM(
    'en_attente', 
    'signe', 
    'en_verification', 
    'valide', 
    'expire', 
    'annule', 
    'actif', 
    'termine'
) DEFAULT 'en_attente';

-- Add validation tracking fields to contrats table
-- Check and add columns one by one to handle IF NOT EXISTS properly
SET @dbname = DATABASE();

-- Add date_verification column if it doesn't exist
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'contrats' AND COLUMN_NAME = 'date_verification');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE contrats ADD COLUMN date_verification TIMESTAMP NULL COMMENT ''Date de vérification par admin''', 'SELECT "Column date_verification already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add date_validation column if it doesn't exist
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'contrats' AND COLUMN_NAME = 'date_validation');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE contrats ADD COLUMN date_validation TIMESTAMP NULL COMMENT ''Date de validation finale''', 'SELECT "Column date_validation already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add validation_notes column if it doesn't exist
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'contrats' AND COLUMN_NAME = 'validation_notes');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE contrats ADD COLUMN validation_notes TEXT NULL COMMENT ''Notes de vérification/validation''', 'SELECT "Column validation_notes already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add motif_annulation column if it doesn't exist
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'contrats' AND COLUMN_NAME = 'motif_annulation');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE contrats ADD COLUMN motif_annulation TEXT NULL COMMENT ''Raison de l''''annulation du contrat''', 'SELECT "Column motif_annulation already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add verified_by column if it doesn't exist
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'contrats' AND COLUMN_NAME = 'verified_by');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE contrats ADD COLUMN verified_by INT NULL COMMENT ''Admin qui a vérifié''', 'SELECT "Column verified_by already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add validated_by column if it doesn't exist
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'contrats' AND COLUMN_NAME = 'validated_by');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE contrats ADD COLUMN validated_by INT NULL COMMENT ''Admin qui a validé''', 'SELECT "Column validated_by already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign keys if they don't already exist
-- Note: MySQL doesn't support IF NOT EXISTS for foreign keys, so we use dynamic SQL
SET @tablename = 'contrats';
SET @fk1_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = @dbname AND TABLE_NAME = @tablename AND CONSTRAINT_NAME = 'fk_contrats_verified_by');
SET @fk2_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = @dbname AND TABLE_NAME = @tablename AND CONSTRAINT_NAME = 'fk_contrats_validated_by');

SET @sql = IF(@fk1_exists = 0, 'ALTER TABLE contrats ADD CONSTRAINT fk_contrats_verified_by FOREIGN KEY (verified_by) REFERENCES administrateurs(id) ON DELETE SET NULL', 'SELECT "FK verified_by already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(@fk2_exists = 0, 'ALTER TABLE contrats ADD CONSTRAINT fk_contrats_validated_by FOREIGN KEY (validated_by) REFERENCES administrateurs(id) ON DELETE SET NULL', 'SELECT "FK validated_by already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

