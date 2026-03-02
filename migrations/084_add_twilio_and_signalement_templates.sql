-- Migration 084: Paramètres Twilio et templates emails signalements
-- Date: 2026-03-02
-- Description:
--   1. Ajoute les paramètres Twilio pour l'envoi automatique de messages WhatsApp
--   2. Ajoute les templates emails pour les notifications de signalement :
--      - nouveau_signalement_locataire : confirmation au locataire
--      - nouveau_signalement_admin     : notification à l'administrateur

-- 1. Paramètres Twilio (groupe 'twilio')
INSERT INTO parametres (cle, valeur, type, description, groupe) VALUES
('twilio_account_sid',   '', 'string', 'Twilio Account SID (depuis console.twilio.com)', 'twilio'),
('twilio_auth_token',    '', 'string', 'Twilio Auth Token (depuis console.twilio.com)', 'twilio'),
('twilio_whatsapp_from', '', 'string', 'Numéro WhatsApp Twilio expéditeur, ex: +14155238886', 'twilio')
ON DUPLICATE KEY UPDATE cle = cle;

-- 2. Template email de confirmation au locataire
INSERT INTO email_templates (
    identifiant,
    nom,
    sujet,
    corps_html,
    variables_disponibles,
    description,
    actif,
    ordre,
    created_at
) VALUES (
    'nouveau_signalement_locataire',
    'Confirmation de réception d''un signalement (locataire)',
    'Votre signalement a bien été enregistré — Réf. {{reference}}',
    '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signalement enregistré</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="margin: 0; font-size: 26px;">✅ Signalement enregistré</h1>
        <p style="margin: 10px 0 0; font-size: 16px;">{{company}}</p>
    </div>
    <div style="background: #ffffff; padding: 30px; border: 1px solid #e0e0e0; border-top: none;">
        <p>Bonjour {{prenom}} {{nom}},</p>
        <p>Votre signalement a bien été transmis à notre équipe de gestion. Un suivi vous sera communiqué dans les meilleurs délais.</p>
        <div style="background: #f8f9fa; border-left: 4px solid #3498db; padding: 15px; margin: 20px 0; border-radius: 0 5px 5px 0;">
            <p style="margin: 5px 0;"><strong>Référence :</strong> <code>{{reference}}</code></p>
            <p style="margin: 5px 0;"><strong>Titre :</strong> {{titre}}</p>
            <p style="margin: 5px 0;"><strong>Priorité :</strong> {{priorite}}</p>
            <p style="margin: 5px 0;"><strong>Logement :</strong> {{adresse}}</p>
            <p style="margin: 5px 0;"><strong>Date :</strong> {{date}}</p>
        </div>
        <p style="color: #666; font-size: 13px;">Conservez la référence <strong>{{reference}}</strong> pour tout suivi auprès de votre gestionnaire.</p>
    </div>
    <div style="background: #f8f9fa; padding: 15px; text-align: center; border-radius: 0 0 10px 10px; border: 1px solid #e0e0e0; border-top: none;">
        <p style="margin: 0; color: #666; font-size: 12px;">{{company}}</p>
    </div>
    {{signature}}
</body>
</html>',
    'prenom,nom,reference,titre,priorite,adresse,date,company',
    'Email envoyé au locataire pour confirmer la réception de son signalement',
    1,
    90,
    NOW()
) ON DUPLICATE KEY UPDATE identifiant = identifiant;

-- 3. Template email de notification admin
INSERT INTO email_templates (
    identifiant,
    nom,
    sujet,
    corps_html,
    variables_disponibles,
    description,
    actif,
    ordre,
    created_at
) VALUES (
    'nouveau_signalement_admin',
    'Nouveau signalement reçu (admin)',
    '[Signalement {{priorite}}] {{titre}} — {{adresse}}',
    '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau signalement</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="margin: 0; font-size: 26px;">🔔 Nouveau Signalement</h1>
        <p style="margin: 10px 0 0; font-size: 16px;">Un locataire a ouvert un ticket</p>
    </div>
    <div style="background: #ffffff; padding: 30px; border: 1px solid #e0e0e0; border-top: none;">
        <div style="background: #f8f9fa; border-left: 4px solid #e74c3c; padding: 15px; margin-bottom: 20px; border-radius: 0 5px 5px 0;">
            <p style="margin: 5px 0;"><strong>Référence :</strong> <code>{{reference}}</code></p>
            <p style="margin: 5px 0;"><strong>Titre :</strong> {{titre}}</p>
            <p style="margin: 5px 0;"><strong>Priorité :</strong> {{priorite}}</p>
            <p style="margin: 5px 0;"><strong>Logement :</strong> {{adresse}}</p>
            <p style="margin: 5px 0;"><strong>Locataire :</strong> {{locataire}}</p>
            <p style="margin: 5px 0;"><strong>Date :</strong> {{date}}</p>
        </div>
        <p><strong>Description :</strong></p>
        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; white-space: pre-wrap;">{{description}}</div>
        <p style="margin-top: 20px; text-align: center;">
            <a href="{{lien_admin}}" style="background: #3498db; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">
                Voir le signalement →
            </a>
        </p>
    </div>
    {{signature}}
</body>
</html>',
    'reference,titre,priorite,adresse,locataire,description,date,lien_admin',
    'Email envoyé à l''administrateur à chaque nouveau signalement soumis par un locataire',
    1,
    91,
    NOW()
) ON DUPLICATE KEY UPDATE identifiant = identifiant;
