-- Migration: Add email templates for états des lieux
-- Date: 2026-02-06
-- Description: Add email templates for sending état des lieux documents to tenants

-- Insert email templates for état des lieux
INSERT INTO email_templates (identifiant, nom, sujet, corps_html, variables_disponibles, description) VALUES
(
    'etat_lieux_entree_envoye',
    'État des lieux d''entrée envoyé',
    'État des lieux d''entrée - {{adresse}}',
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
            <p style="margin: 15px 0;">Bonjour {{locataire_nom}},</p>
            
            <p style="margin: 15px 0;">📋 <strong>Veuillez trouver ci-joint l''état des lieux d''entrée pour le logement situé au :</strong></p>
            
            <div style="background: #f8f9fa; padding: 15px; border-left: 4px solid #667eea; margin: 20px 0;">
                <p style="margin: 0;"><strong>{{adresse}}</strong></p>
                <p style="margin: 5px 0 0 0; color: #666;">Date de l''état des lieux : {{date_etat}}</p>
            </div>
            
            <p style="margin: 15px 0;">Ce document est à conserver précieusement. Il servira de référence lors de l''état des lieux de sortie.</p>
            
            <p style="margin: 15px 0; color: #666; font-size: 14px;"><em>Le PDF de l''état des lieux est joint à cet email.</em></p>
            
            <p style="margin: 15px 0;">Pour toute question concernant ce document, n''hésitez pas à nous contacter.</p>
            
            {{signature}}
        </div>
        <div style="background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #e9ecef;">
            <p style="margin: 0;">MY Invest Immobilier - Gestion locative professionnelle</p>
            <p style="margin: 5px 0 0 0;">SCI My Invest Immobilier - Représentée par Maxime ALEXANDRE</p>
        </div>
    </div>
</body>
</html>',
    '["locataire_nom", "adresse", "date_etat", "reference"]',
    'Email envoyé au locataire avec l''état des lieux d''entrée'
),
(
    'etat_lieux_sortie_envoye',
    'État des lieux de sortie envoyé',
    'État des lieux de sortie - {{adresse}}',
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
            <p style="margin: 15px 0;">Bonjour {{locataire_nom}},</p>
            
            <p style="margin: 15px 0;">📋 <strong>Veuillez trouver ci-joint l''état des lieux de sortie pour le logement situé au :</strong></p>
            
            <div style="background: #f8f9fa; padding: 15px; border-left: 4px solid #667eea; margin: 20px 0;">
                <p style="margin: 0;"><strong>{{adresse}}</strong></p>
                <p style="margin: 5px 0 0 0; color: #666;">Date de l''état des lieux : {{date_etat}}</p>
            </div>
            
            <p style="margin: 15px 0;">Ce document fait état de la comparaison avec l''état des lieux d''entrée et indique les conditions de restitution du dépôt de garantie.</p>
            
            <p style="margin: 15px 0; color: #666; font-size: 14px;"><em>Le PDF de l''état des lieux est joint à cet email.</em></p>
            
            <p style="margin: 15px 0;">Pour toute question concernant ce document ou les modalités de restitution du dépôt de garantie, n''hésitez pas à nous contacter.</p>
            
            {{signature}}
        </div>
        <div style="background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #e9ecef;">
            <p style="margin: 0;">MY Invest Immobilier - Gestion locative professionnelle</p>
            <p style="margin: 5px 0 0 0;">SCI My Invest Immobilier - Représentée par Maxime ALEXANDRE</p>
        </div>
    </div>
</body>
</html>',
    '["locataire_nom", "adresse", "date_etat", "reference"]',
    'Email envoyé au locataire avec l''état des lieux de sortie'
),
(
    'etat_lieux_admin_copie',
    'Copie état des lieux (admin)',
    '[Admin] État des lieux {{type}} - {{adresse}}',
    '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0;">
    <div style="max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); color: white; padding: 30px 20px; text-align: center;">
            <h1 style="margin: 0; font-size: 28px;">Copie Administrative</h1>
        </div>
        <div style="padding: 30px;">
            <p style="margin: 15px 0;"><strong>État des lieux {{type}} - Copie interne</strong></p>
            
            <div style="background: #f8f9fa; padding: 15px; border-left: 4px solid #2c3e50; margin: 20px 0;">
                <p style="margin: 0;"><strong>Référence :</strong> {{reference}}</p>
                <p style="margin: 5px 0 0 0;"><strong>Type :</strong> {{type}}</p>
                <p style="margin: 5px 0 0 0;"><strong>Adresse :</strong> {{adresse}}</p>
                <p style="margin: 5px 0 0 0;"><strong>Locataire :</strong> {{locataire_nom}}</p>
                <p style="margin: 5px 0 0 0;"><strong>Date :</strong> {{date_etat}}</p>
            </div>
            
            <p style="margin: 15px 0;">Le PDF de l''état des lieux est joint à cet email et a été envoyé au(x) locataire(s).</p>
            
            <p style="margin: 15px 0; color: #666; font-size: 14px;"><em>Cet email est une copie interne pour archivage.</em></p>
        </div>
        <div style="background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #e9ecef;">
            <p style="margin: 0;">MY Invest Immobilier - Système de gestion automatisé</p>
        </div>
    </div>
</body>
</html>',
    '["locataire_nom", "adresse", "date_etat", "reference", "type"]',
    'Email envoyé à l''administrateur en copie de l''état des lieux'
)
ON DUPLICATE KEY UPDATE identifiant=identifiant;
