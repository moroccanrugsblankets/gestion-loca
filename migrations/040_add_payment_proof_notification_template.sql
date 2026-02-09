-- Migration: Add admin notification email template for payment proof submission
-- Date: 2026-02-09
-- Description: Add email template to notify admins when a client submits payment proof

INSERT INTO email_templates (identifiant, nom, sujet, corps_html, variables_disponibles, description) VALUES
(
    'notification_justificatif_paiement_admin',
    'Notification Admin - Justificatif de paiement reçu',
    'Justificatif reçu - Contrat {{reference}}',
    '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #28a745; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
        .info-box { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .info-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .info-table td { padding: 8px; border-bottom: 1px solid #ddd; }
        .info-table td:first-child { font-weight: bold; width: 40%; }
        .button { display: inline-block; padding: 12px 30px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0;">📄 Justificatif de paiement reçu</h1>
        </div>
        <div class="content">
            <div class="info-box">
                <strong>✅ Nouveau justificatif :</strong> Un client a transmis son justificatif de virement du dépôt de garantie.
            </div>
            
            <h2>Informations du contrat</h2>
            
            <table class="info-table">
                <tr>
                    <td>Référence</td>
                    <td>{{reference}}</td>
                </tr>
                <tr>
                    <td>Logement</td>
                    <td>{{logement}}</td>
                </tr>
                <tr>
                    <td>Locataires</td>
                    <td>{{locataires}}</td>
                </tr>
                <tr>
                    <td>Date de réception</td>
                    <td>{{date_envoi}}</td>
                </tr>
            </table>
            
            <p><strong>Action requise :</strong></p>
            <ol>
                <li>Vérifier le justificatif de paiement dans le dossier du contrat</li>
                <li>Valider que le montant correspond au dépôt de garantie</li>
                <li>Confirmer la réception et organiser la remise des clés</li>
            </ol>
            
            <p style="text-align: center;">
                <a href="{{lien_admin}}" class="button">Voir le contrat</a>
            </p>
        </div>
        <div class="footer">
            <p>MY Invest Immobilier - Système de gestion des contrats</p>
        </div>
    </div>
</body>
</html>',
    '["reference", "logement", "locataires", "date_envoi", "lien_admin"]',
    'Email envoyé aux administrateurs quand un client envoie son justificatif de paiement'
)
ON DUPLICATE KEY UPDATE identifiant=identifiant;
