-- Migration: Fix email signature image border
-- Date: 2026-02-03
-- Description: Update email signature to remove border from logo image

UPDATE parametres 
SET valeur = '<table><tbody><tr><td><img src="https://www.myinvest-immobilier.com/images/logo.png" style="border: 0; border-style: none; outline: none; display: block;"></td><td>&nbsp;</td><td><h3>MY INVEST IMMOBILIER</h3></td></tr></tbody></table>'
WHERE cle = 'email_signature';
