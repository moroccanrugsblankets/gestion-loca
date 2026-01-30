-- Migration: Fix signature data storage issues
-- Description: Change signature_data column from TEXT to LONGTEXT to support large canvas signatures
-- Author: System
-- Date: 2026-01-30

-- Change signature_data column type to LONGTEXT
-- TEXT max size: ~64KB
-- LONGTEXT max size: ~4GB
-- Canvas PNG data URLs can be 100-500KB depending on signature complexity
ALTER TABLE locataires 
MODIFY COLUMN signature_data LONGTEXT;
