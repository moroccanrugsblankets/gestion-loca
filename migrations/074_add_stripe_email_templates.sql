-- Migration 074: Templates email pour le paiement Stripe
-- Date: 2026-02-26
-- Description: Ajoute les templates email pour l'invitation de paiement et les rappels locataires

-- 1. Template: Invitation de paiement en ligne (envoyé au début du mois)
INSERT INTO email_templates (
    identifiant,
    nom,
    sujet,
    corps_html,
    variables_disponibles,
    description,
    actif,
    ordre
) VALUES (
    'stripe_invitation_paiement',
    'Invitation paiement loyer en ligne',
    'My Invest Immobilier - Paiement de votre loyer {{periode}}',
    '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement loyer</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="margin: 0; font-size: 26px;">💳 Paiement de votre loyer</h1>
        <p style="margin: 10px 0 0; font-size: 16px;">{{periode}}</p>
    </div>

    <div style="background-color: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; border: 1px solid #e9ecef; border-top: none;">
        <p style="font-size: 16px; margin-top: 0;">Bonjour <strong>{{locataire_prenom}} {{locataire_nom}}</strong>,</p>

        <p>Votre loyer du mois de <strong>{{periode}}</strong> est maintenant disponible au paiement en ligne.</p>

        <div style="background-color: white; border-left: 4px solid #667eea; padding: 15px; margin: 20px 0; border-radius: 5px;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 6px 0;"><strong>Logement :</strong></td>
                    <td style="padding: 6px 0; text-align: right;">{{adresse}}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0;"><strong>Période :</strong></td>
                    <td style="padding: 6px 0; text-align: right;">{{periode}}</td>
                </tr>
                <tr style="border-top: 1px solid #e9ecef;">
                    <td style="padding: 6px 0;"><strong>Loyer :</strong></td>
                    <td style="padding: 6px 0; text-align: right;">{{montant_loyer}} €</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0;"><strong>Charges :</strong></td>
                    <td style="padding: 6px 0; text-align: right;">{{montant_charges}} €</td>
                </tr>
                <tr style="border-top: 2px solid #667eea; font-size: 18px; font-weight: bold;">
                    <td style="padding: 10px 0;">Total à payer :</td>
                    <td style="padding: 10px 0; text-align: right; color: #667eea;">{{montant_total}} €</td>
                </tr>
            </table>
        </div>

        <div style="text-align: center; margin: 30px 0;">
            <a href="{{lien_paiement}}"
               style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 16px 40px; border-radius: 8px; text-decoration: none; font-size: 18px; font-weight: bold; display: inline-block;">
                💳 Payer maintenant
            </a>
        </div>

        <p style="font-size: 13px; color: #6c757d;">
            <strong>Méthodes de paiement acceptées :</strong> Carte bancaire (Visa, Mastercard, American Express), Prélèvement SEPA<br>
            <strong>Lien valide jusqu''au :</strong> {{date_expiration}}<br>
            Paiement sécurisé via <strong>Stripe</strong>.
        </p>

        <p style="font-size: 14px; color: #6c757d; margin-top: 20px;">
            Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>
            <a href="{{lien_paiement}}" style="color: #667eea;">{{lien_paiement}}</a>
        </p>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6;">
            {{signature}}
        </div>
    </div>
</body>
</html>',
    '["locataire_nom", "locataire_prenom", "adresse", "periode", "montant_loyer", "montant_charges", "montant_total", "lien_paiement", "date_expiration", "signature"]',
    'Email envoyé aux locataires avec le lien de paiement Stripe pour leur loyer mensuel',
    1,
    (SELECT ordre FROM (SELECT COALESCE(MAX(ordre), 0) + 1 AS ordre FROM email_templates) AS temp)
) ON DUPLICATE KEY UPDATE identifiant = identifiant;

-- 2. Template: Rappel de paiement pour loyer en retard
INSERT INTO email_templates (
    identifiant,
    nom,
    sujet,
    corps_html,
    variables_disponibles,
    description,
    actif,
    ordre
) VALUES (
    'stripe_rappel_paiement',
    'Rappel paiement loyer en retard',
    'RAPPEL - My Invest Immobilier - Loyer {{periode}} en attente de paiement',
    '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rappel paiement</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="margin: 0; font-size: 26px;">⚠️ Rappel de paiement</h1>
        <p style="margin: 10px 0 0; font-size: 16px;">Loyer {{periode}} non réglé</p>
    </div>

    <div style="background-color: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; border: 1px solid #e9ecef; border-top: none;">
        <p style="font-size: 16px; margin-top: 0;">Bonjour <strong>{{locataire_prenom}} {{locataire_nom}}</strong>,</p>

        <p>Nous n''avons pas encore reçu votre paiement pour le mois de <strong>{{periode}}</strong>.</p>

        <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 5px;">
            <strong>⚠️ Montant en attente :</strong> <span style="font-size: 20px; color: #dc3545; font-weight: bold;">{{montant_total}} €</span>
            <br><small>Logement : {{adresse}} | Période : {{periode}}</small>
        </div>

        <p>Vous pouvez régler votre loyer immédiatement et en toute sécurité en cliquant sur le bouton ci-dessous :</p>

        <div style="text-align: center; margin: 30px 0;">
            <a href="{{lien_paiement}}"
               style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 16px 40px; border-radius: 8px; text-decoration: none; font-size: 18px; font-weight: bold; display: inline-block;">
                💳 Payer maintenant
            </a>
        </div>

        <p style="font-size: 13px; color: #6c757d;">
            <strong>Lien valide jusqu''au :</strong> {{date_expiration}}<br>
            Paiement sécurisé via <strong>Stripe</strong> (carte bancaire ou prélèvement SEPA).
        </p>

        <p style="font-size: 14px; color: #6c757d;">
            Si vous avez déjà effectué votre paiement, merci d''ignorer ce message ou de nous contacter.<br>
            En cas de difficulté, n''hésitez pas à nous contacter.
        </p>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6;">
            {{signature}}
        </div>
    </div>
</body>
</html>',
    '["locataire_nom", "locataire_prenom", "adresse", "periode", "montant_loyer", "montant_charges", "montant_total", "lien_paiement", "date_expiration", "signature"]',
    'Email de rappel envoyé aux locataires dont le loyer n''est pas encore payé',
    1,
    (SELECT ordre FROM (SELECT COALESCE(MAX(ordre), 0) + 1 AS ordre FROM email_templates) AS temp)
) ON DUPLICATE KEY UPDATE identifiant = identifiant;
