-- Migration: Add email signature parameter
-- Date: 2026-01-29
-- Description: Add email signature to parameters table

INSERT INTO parametres (cle, valeur, type, description, groupe) VALUES
('email_signature', '<table style="border: 0; border-collapse: collapse;"><tbody><tr><td style="padding-right: 15px;"><img src="https://www.myinvest-immobilier.com/images/logo.png" style="max-width: 120px; border: 0; border-style: none; outline: none; display: block;" border="0"></td><td><h3 style="margin: 0; color: #2c3e50;">MY INVEST IMMOBILIER</h3></td></tr></tbody></table>', 'string', 'Signature ajoutée à tous les emails envoyés', 'email')
ON DUPLICATE KEY UPDATE valeur=VALUES(valeur), description=VALUES(description), groupe=VALUES(groupe);
