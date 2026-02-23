-- Migration: Update admin notification template for payment proof with inline styles
-- Date: 2026-02-22
-- Description: Update notification_justificatif_paiement_admin template to use inline
--              styles instead of CSS classes for better email client compatibility,
--              and ensure the admin link button is prominently displayed.

UPDATE email_templates
SET
    corps_html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background: #28a745; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;">
            <h1 style="margin: 0;">📄 Justificatif de paiement reçu</h1>
        </div>
        <div style="background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px;">
            <div style="background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 4px;">
                <strong>✅ Nouveau justificatif :</strong> Un client a transmis son justificatif de virement du dépôt de garantie.
            </div>

            <h2 style="color: #333;">Informations du contrat</h2>

            <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                <tr>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold; width: 40%;">Référence</td>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd;">{{reference}}</td>
                </tr>
                <tr>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;">Logement</td>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd;">{{logement}}</td>
                </tr>
                <tr>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;">Locataires</td>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd;">{{locataires}}</td>
                </tr>
                <tr>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;">Date de réception</td>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd;">{{date_envoi}}</td>
                </tr>
            </table>

            <p><strong>Action requise :</strong></p>
            <ol>
                <li>Vérifier le justificatif de paiement dans le dossier du contrat</li>
                <li>Valider que le montant correspond au dépôt de garantie</li>
                <li>Confirmer la réception et organiser la remise des clés</li>
            </ol>

            <p style="text-align: center; margin: 30px 0;">
                <a href="{{lien_admin}}" style="display: inline-block; padding: 14px 35px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; font-size: 16px; font-weight: bold;">
                    👁 Voir le détail du contrat
                </a>
            </p>
        </div>
        <div style="text-align: center; padding: 20px; font-size: 12px; color: #666;">
            <p>My Invest Immobilier - Système de gestion des contrats</p>
        </div>
    </div>
</body>
</html>',
    variables_disponibles = '["reference", "logement", "locataires", "date_envoi", "lien_admin"]',
    updated_at = NOW()
WHERE identifiant = 'notification_justificatif_paiement_admin';
