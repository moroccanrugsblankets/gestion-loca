-- Migration 093 : Ajout de {{action_buttons_html}} dans le template nouveau_signalement_service_technique
-- Date: 2026-03-05
-- Description:
--   Ajoute la variable {{action_buttons_html}} dans le template email du service technique
--   pour les nouvelles notifications de signalement (lien admin vers le détail).

UPDATE email_templates
SET
    corps_html = '<!DOCTYPE html>
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
            <p style="margin: 5px 0;"><strong>Téléphone :</strong> {{telephone}}</p>
            <p style="margin: 5px 0;"><strong>Date :</strong> {{date}}</p>
            {{disponibilites_html}}
        </div>
        <p><strong>Description :</strong></p>
        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; white-space: pre-wrap;">{{description}}</div>
        {{photos_html}}
        {{action_buttons_html}}
    </div>
    {{signature}}
</body>
</html>',
    variables_disponibles = 'reference,titre,priorite,adresse,locataire,telephone,description,date,disponibilites_html,photos_html,action_buttons_html',
    updated_at = NOW()
WHERE identifiant = 'nouveau_signalement_service_technique';
