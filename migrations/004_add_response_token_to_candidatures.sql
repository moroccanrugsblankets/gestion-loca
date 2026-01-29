-- Migration: Add response_token field to candidatures table
-- This token will be used for secure email-based candidature approval/rejection

ALTER TABLE candidatures 
ADD COLUMN response_token VARCHAR(64) UNIQUE NULL COMMENT 'Token sécurisé pour réponses par email (accept/reject)' 
AFTER reference_unique;

-- Create index for faster lookups
CREATE INDEX idx_response_token ON candidatures(response_token);
