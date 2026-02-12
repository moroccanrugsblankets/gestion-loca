-- Migration 047: Add bilan_sections_data column to etats_lieux table
-- This column stores the simplified bilan data organized by section

ALTER TABLE etats_lieux 
ADD COLUMN bilan_sections_data JSON NULL 
COMMENT 'Simplified bilan data organized by section (compteurs, cles, piece_principale, cuisine, salle_eau)';
