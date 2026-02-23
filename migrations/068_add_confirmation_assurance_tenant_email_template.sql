-- Migration 068: Ajout du template email de confirmation pour le locataire après envoi des documents assurance/Visale
-- Date: 2026-02-23

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
    'confirmation_assurance_visale_locataire',
    'Confirmation réception documents assurance/Visale - Locataire',
    'My Invest Immobilier - Confirmation de réception de vos documents',
    '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); color: white; padding: 30px 20px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
        .success-box { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0;">Documents reçus</h1>
            <p style="margin: 10px 0 0 0; opacity: 0.9;">My Invest Immobilier</p>
        </div>
        <div class="content">
            <p>Bonjour {{prenom}} {{nom}},</p>

            <div class="success-box">
                <strong>✅ Confirmation :</strong> Nous avons bien reçu vos documents d''assurance habitation et/ou Visale pour le contrat <strong>{{reference}}</strong>.
            </div>

            <p>Notre équipe va procéder à la vérification de vos documents dans les meilleurs délais. Vous serez contacté(e) prochainement pour organiser l''entrée dans les lieux.</p>

            <p>Si vous avez des questions, n''hésitez pas à nous contacter.</p>

            <p>Cordialement,</p>

            {{signature}}
        </div>
        <div class="footer">
            <p>My Invest Immobilier - Gestion locative professionnelle<br>
            © 2026 My Invest Immobilier - Tous droits réservés</p>
        </div>
    </div>
</body>
</html>',
    '["nom", "prenom", "reference"]',
    'Email de confirmation envoyé au locataire après réception de ses documents assurance habitation et Visale',
    1,
    (SELECT ordre FROM (SELECT COALESCE(MAX(ordre), 0) + 1 AS ordre FROM email_templates) AS temp),
    NOW()
) ON DUPLICATE KEY UPDATE
    nom = VALUES(nom),
    sujet = VALUES(sujet),
    corps_html = VALUES(corps_html),
    variables_disponibles = VALUES(variables_disponibles),
    description = VALUES(description),
    updated_at = NOW();
