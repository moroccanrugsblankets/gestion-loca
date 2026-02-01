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
ALTER TABLE contrats
ADD COLUMN IF NOT EXISTS date_verification TIMESTAMP NULL COMMENT 'Date de vérification par admin',
ADD COLUMN IF NOT EXISTS date_validation TIMESTAMP NULL COMMENT 'Date de validation finale',
ADD COLUMN IF NOT EXISTS validation_notes TEXT NULL COMMENT 'Notes de vérification/validation',
ADD COLUMN IF NOT EXISTS motif_annulation TEXT NULL COMMENT 'Raison de l''annulation du contrat',
ADD COLUMN IF NOT EXISTS verified_by INT NULL COMMENT 'Admin qui a vérifié',
ADD COLUMN IF NOT EXISTS validated_by INT NULL COMMENT 'Admin qui a validé',
ADD FOREIGN KEY (verified_by) REFERENCES administrateurs(id) ON DELETE SET NULL,
ADD FOREIGN KEY (validated_by) REFERENCES administrateurs(id) ON DELETE SET NULL;
