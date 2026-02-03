-- Migration: Update email templates and add status change templates
-- Date: 2026-02-03
-- Description: 
--   1. Update contrat_finalisation_admin subject to match backoffice template
--   2. Add templates for status changes (visite_planifiee, contrat_envoye, contrat_signe)

-- Update the admin contract finalization email subject
UPDATE email_templates 
SET sujet = 'Contrat signé - {{reference}} - Vérification requise'
WHERE identifiant = 'contrat_finalisation_admin';

-- Insert status change email templates
INSERT INTO email_templates (identifiant, nom, sujet, corps_html, variables_disponibles, description) VALUES
(
    'statut_visite_planifiee',
    'Visite planifiée',
    'Visite de logement planifiée - MY Invest Immobilier',
    '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0;">
    <div style="max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center;">
            <h1 style="margin: 0; font-size: 28px;">MY Invest Immobilier</h1>
        </div>
        <div style="padding: 30px;">
            <p style="margin: 15px 0;">Bonjour {{nom}},</p>
            
            <p style="margin: 15px 0;">📅 <strong>Votre visite du logement a été planifiée.</strong></p>
            
            <p style="margin: 15px 0;">Nous vous contacterons prochainement pour confirmer la date et l''heure de la visite.</p>
            
            <p style="margin: 15px 0;">{{commentaire}}</p>
            
            <p style="margin: 15px 0;">Nous restons à votre disposition pour toute question.</p>
            
            {{signature}}
        </div>
        <div style="background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #e9ecef;">
            <p>MY Invest Immobilier - Gestion locative professionnelle</p>
        </div>
    </div>
</body>
</html>',
    '["nom", "prenom", "email", "commentaire"]',
    'Email envoyé au candidat quand une visite est planifiée'
),
(
    'statut_contrat_envoye',
    'Contrat envoyé',
    'Contrat de bail - MY Invest Immobilier',
    '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0;">
    <div style="max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center;">
            <h1 style="margin: 0; font-size: 28px;">MY Invest Immobilier</h1>
        </div>
        <div style="padding: 30px;">
            <p style="margin: 15px 0;">Bonjour {{nom}},</p>
            
            <p style="margin: 15px 0;">📄 <strong>Votre contrat de bail est prêt.</strong></p>
            
            <p style="margin: 15px 0;">Vous allez recevoir un lien pour le signer électroniquement.</p>
            
            <p style="margin: 15px 0;">{{commentaire}}</p>
            
            <p style="margin: 15px 0;">Nous restons à votre disposition pour toute question.</p>
            
            {{signature}}
        </div>
        <div style="background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #e9ecef;">
            <p>MY Invest Immobilier - Gestion locative professionnelle</p>
        </div>
    </div>
</body>
</html>',
    '["nom", "prenom", "email", "commentaire"]',
    'Email envoyé au candidat quand le contrat est envoyé'
),
(
    'statut_contrat_signe',
    'Contrat signé',
    'Contrat signé - MY Invest Immobilier',
    '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0;">
    <div style="max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center;">
            <h1 style="margin: 0; font-size: 28px;">MY Invest Immobilier</h1>
        </div>
        <div style="padding: 30px;">
            <p style="margin: 15px 0;">Bonjour {{nom}},</p>
            
            <p style="margin: 15px 0;">✓ <strong>Nous avons bien reçu votre contrat signé.</strong></p>
            
            <p style="margin: 15px 0;">Nous vous contacterons prochainement pour les modalités d''entrée dans le logement.</p>
            
            <p style="margin: 15px 0;">{{commentaire}}</p>
            
            <p style="margin: 15px 0;">Nous restons à votre disposition pour toute question.</p>
            
            {{signature}}
        </div>
        <div style="background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #e9ecef;">
            <p>MY Invest Immobilier - Gestion locative professionnelle</p>
        </div>
    </div>
</body>
</html>',
    '["nom", "prenom", "email", "commentaire"]',
    'Email envoyé au candidat quand le contrat est signé'
)
ON DUPLICATE KEY UPDATE identifiant=identifiant;
