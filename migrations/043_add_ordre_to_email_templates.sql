-- Migration: Add ordre column to email_templates table
-- Date: 2026-02-10
-- Description: Add ordre column to support drag & drop reordering of email templates

-- Add ordre column
ALTER TABLE email_templates 
ADD COLUMN ordre INT NOT NULL DEFAULT 0 COMMENT 'Display order for templates' 
AFTER actif;

-- Initialize ordre values based on current ID order
UPDATE email_templates SET ordre = id WHERE ordre = 0;

-- Add index on ordre column for better query performance
CREATE INDEX idx_ordre ON email_templates(ordre);
