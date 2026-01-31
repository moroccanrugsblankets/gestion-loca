-- Migration: Update automatic response delay to 10 minutes
-- Date: 2026-01-31
-- Description: Change the default delay from 4 days to 10 minutes for automatic response processing

-- Update the delay parameters to use minutes with a value of 10
UPDATE parametres 
SET valeur = '10' 
WHERE cle = 'delai_reponse_valeur';

UPDATE parametres 
SET valeur = 'minutes' 
WHERE cle = 'delai_reponse_unite';
