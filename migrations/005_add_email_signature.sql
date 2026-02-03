-- Migration: Add email signature parameter
-- Date: 2026-01-29
-- Description: Add email signature to parameters table

INSERT INTO parametres (cle, valeur, type, description, groupe) VALUES
('email_signature', '<table><tbody><tr><td><img src="https://www.myinvest-immobilier.com/images/logo.png" style="border: 0; border-style: none; outline: none; display: block;"></td><td>&nbsp;</td><td><h3>MY INVEST IMMOBILIER</h3></td></tr></tbody></table>', 'string', 'Signature ajoutée à tous les emails envoyés', 'email')
ON DUPLICATE KEY UPDATE valeur=VALUES(valeur), description=VALUES(description), groupe=VALUES(groupe);
