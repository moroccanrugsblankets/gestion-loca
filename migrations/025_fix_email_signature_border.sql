-- Migration: Fix email signature image border
-- Date: 2026-02-03
-- Description: Update email signature to remove border from logo image using both HTML attribute and CSS styles for maximum compatibility

UPDATE parametres 
SET valeur = '<p>Sincères salutations</p><p style="margin-top: 20px;"><table style="border: 0; border-collapse: collapse;"><tbody><tr><td style="padding-right: 15px;"><img src="https://www.myinvest-immobilier.com/images/logo.png" alt="MY Invest Immobilier" style="max-width: 120px; border: 0; border-style: none; outline: none; display: block;" border="0"></td><td><h3 style="margin: 0; color: #2c3e50;">MY INVEST IMMOBILIER</h3></td></tr></tbody></table></p>',
    updated_at = NOW()
WHERE cle = 'email_signature';
