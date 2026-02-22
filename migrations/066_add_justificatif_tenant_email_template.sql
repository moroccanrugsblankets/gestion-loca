-- Migration 066: Ajout du template email de confirmation pour le locataire après envoi du justificatif
-- Date: 2026-02-22

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
    'confirmation_justificatif_paiement_locataire',
    'Confirmation - Justificatif de paiement reçu (locataire)',
    'Confirmation de réception de votre justificatif - Contrat {{reference}}',
    '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 30px 20px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
        .success-box { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .info-box { background: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0;">✅ Justificatif reçu</h1>
        </div>
        <div class="content">
            <p>Bonjour {{prenom}} {{nom}},</p>

            <div class="success-box">
                <strong>✅ Votre justificatif de paiement a bien été reçu.</strong>
            </div>

            <div class="info-box">
                <strong>📋 Référence du contrat :</strong> {{reference}}
            </div>

            <p>Nous avons bien reçu votre justificatif de virement du dépôt de garantie. Notre équipe va procéder à sa vérification dans les meilleurs délais.</p>

            <p><strong>Prochaines étapes :</strong></p>
            <ol>
                <li>Notre équipe vérifie votre justificatif de paiement</li>
                <li>Vous serez recontacté(e) pour confirmer la réception et organiser la remise des clés</li>
            </ol>

            <p>Pour toute question, n''hésitez pas à nous contacter.</p>

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
    'Email de confirmation envoyé au locataire après réception de son justificatif de paiement du dépôt de garantie',
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
