-- Migration to rename date_expiration_lien to date_expiration in contrats table
-- This fixes the column mismatch between the schema and the code

ALTER TABLE contrats 
CHANGE COLUMN date_expiration_lien date_expiration TIMESTAMP NULL;
