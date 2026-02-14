-- Migration 050: Add bilan_sent status to etats_lieux table
-- This column tracks whether the bilan has been marked as ready to send to tenant(s)

ALTER TABLE etats_lieux 
ADD COLUMN bilan_sent BOOLEAN DEFAULT FALSE COMMENT 'Whether the bilan has been marked as ready to send to tenant(s)';
