-- Migration: Update email signature format with logo and company info
-- Date: 2026-01-30
-- Description: Update email signature to include logo and formatted company information

-- Ensure the email_signature parameter exists, then update it
INSERT INTO parametres (cle, valeur, type, description, groupe)
VALUES (
    'email_signature',
    '<p>Sincères salutations</p><p style="margin-top: 20px;"><table style="border: none; border-collapse: collapse;"><tbody><tr><td style="padding-right: 15px;"><img src="https://www.myinvest-immobilier.com/images/logo.png" alt="MY Invest Immobilier" style="max-width: 120px; border: none; border-style: none; outline: none; display: block;" border="0"></td><td><h3 style="margin: 0; color: #2c3e50;">MY INVEST IMMOBILIER</h3></td></tr></tbody></table></p>',
    'string',
    'Signature ajoutée à tous les emails envoyés',
    'email'
)
ON DUPLICATE KEY UPDATE 
    valeur = '<p>Sincères salutations</p><p style="margin-top: 20px;"><table style="border: none; border-collapse: collapse;"><tbody><tr><td style="padding-right: 15px;"><img src="https://www.myinvest-immobilier.com/images/logo.png" alt="MY Invest Immobilier" style="max-width: 120px; border: none; border-style: none; outline: none; display: block;" border="0"></td><td><h3 style="margin: 0; color: #2c3e50;">MY INVEST IMMOBILIER</h3></td></tr></tbody></table></p>',
    updated_at = NOW();

-- Add parameter for additional admin email (if it doesn't exist)
INSERT INTO parametres (cle, valeur, type, description, groupe)
VALUES ('email_admin_candidature', '', 'string', 'Email d\'un administrateur supplémentaire pour recevoir les notifications de candidatures', 'email')
ON DUPLICATE KEY UPDATE description = 'Email d\'un administrateur supplémentaire pour recevoir les notifications de candidatures';
