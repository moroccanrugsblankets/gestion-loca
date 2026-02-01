-- Migration: Add contract workflow email templates
-- Date: 2026-02-01
-- Description: Add email templates for contract signature workflow notifications

INSERT INTO email_templates (identifiant, nom, sujet, corps_html, variables_disponibles, description) VALUES
(
    'contrat_signe_client_admin',
    'Notification Admin - Contrat signé par le client',
    'Contrat signé - {{reference}} - Vérification requise',
    '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
        .content { background: #f8f9fa; padding: 30px; }
        .info-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
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
            <h1>Contrat Signé - Vérification Requise</h1>
        </div>
        <div class="content">
            <div class="info-box">
                <strong>⚠️ Action requise :</strong> Un contrat a été signé par le client et nécessite votre vérification.
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
                    <td>Date de signature</td>
                    <td>{{date_signature}}</td>
                </tr>
            </table>
            
            <p><strong>Prochaines étapes :</strong></p>
            <ol>
                <li>Vérifier les informations du contrat</li>
                <li>Vérifier les signatures et documents</li>
                <li>Valider le contrat (ajout automatique de la signature société) OU</li>
                <li>Annuler le contrat si des corrections sont nécessaires</li>
            </ol>
            
            <p style="text-align: center;">
                <a href="{{lien_admin}}" class="button">Vérifier le Contrat</a>
            </p>
        </div>
        <div class="footer">
            <p>MY Invest Immobilier - Système de gestion des contrats</p>
        </div>
    </div>
</body>
</html>',
    '["reference", "logement", "locataires", "date_signature", "lien_admin"]',
    'Email envoyé aux administrateurs quand un client signe le contrat'
),
(
    'contrat_valide_client',
    'Contrat Validé - Client',
    'Votre contrat a été validé - {{reference}}',
    '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #28a745; color: white; padding: 20px; text-align: center; }
        .content { background: #f8f9fa; padding: 30px; }
        .success-box { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; }
        .button { display: inline-block; padding: 12px 30px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✅ Contrat Validé</h1>
        </div>
        <div class="content">
            <h2>Bonjour {{prenom}} {{nom}},</h2>
            
            <div class="success-box">
                <strong>Félicitations !</strong> Votre contrat de bail a été validé par MY Invest Immobilier.
            </div>
            
            <p><strong>Référence du contrat :</strong> {{reference}}</p>
            <p><strong>Logement :</strong> {{logement}}</p>
            <p><strong>Date de prise d''effet :</strong> {{date_prise_effet}}</p>
            
            <p>Le contrat final signé par toutes les parties est maintenant disponible en téléchargement.</p>
            
            <p style="text-align: center;">
                <a href="{{lien_telecharger}}" class="button">Télécharger le Contrat</a>
            </p>
            
            <p><strong>Prochaines étapes :</strong></p>
            <ol>
                <li>Versement du dépôt de garantie ({{depot_garantie}} €)</li>
                <li>Prise de possession du logement le {{date_prise_effet}}</li>
                <li>État des lieux d''entrée</li>
            </ol>
            
            <p>Nous restons à votre disposition pour toute question.</p>
            
            <p>Cordialement,<br>
            <strong>MY Invest Immobilier</strong><br>
            contact@myinvest-immobilier.com</p>
        </div>
        <div class="footer">
            <p>MY Invest Immobilier - Gestion locative professionnelle</p>
        </div>
    </div>
</body>
</html>',
    '["nom", "prenom", "reference", "logement", "date_prise_effet", "depot_garantie", "lien_telecharger"]',
    'Email envoyé au client quand le contrat est validé'
),
(
    'contrat_valide_admin',
    'Notification Admin - Contrat Validé',
    'Contrat validé - {{reference}}',
    '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #28a745; color: white; padding: 20px; text-align: center; }
        .content { background: #f8f9fa; padding: 30px; }
        .info-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .info-table td { padding: 8px; border-bottom: 1px solid #ddd; }
        .info-table td:first-child { font-weight: bold; width: 40%; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✅ Contrat Validé</h1>
        </div>
        <div class="content">
            <h2>Contrat validé avec succès</h2>
            
            <p>Le contrat a été validé et la signature électronique de la société a été ajoutée automatiquement.</p>
            
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
                    <td>Validé par</td>
                    <td>{{admin_nom}}</td>
                </tr>
                <tr>
                    <td>Date de validation</td>
                    <td>{{date_validation}}</td>
                </tr>
            </table>
            
            <p>Le client a été notifié par email et peut maintenant télécharger le contrat final.</p>
        </div>
        <div class="footer">
            <p>MY Invest Immobilier - Système de gestion des contrats</p>
        </div>
    </div>
</body>
</html>',
    '["reference", "logement", "locataires", "admin_nom", "date_validation"]',
    'Email envoyé aux administrateurs quand un contrat est validé'
),
(
    'contrat_annule_client',
    'Contrat Annulé - Régénération Nécessaire',
    'Contrat annulé - {{reference}} - Action requise',
    '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
        .content { background: #f8f9fa; padding: 30px; }
        .warning-box { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Contrat Annulé</h1>
        </div>
        <div class="content">
            <h2>Bonjour {{prenom}} {{nom}},</h2>
            
            <div class="warning-box">
                <strong>Information importante :</strong> Votre contrat a été annulé suite à une vérification.
            </div>
            
            <p><strong>Référence du contrat :</strong> {{reference}}</p>
            <p><strong>Logement :</strong> {{logement}}</p>
            
            <p><strong>Raison de l''annulation :</strong></p>
            <p>{{motif_annulation}}</p>
            
            <p><strong>Prochaines étapes :</strong></p>
            <p>Notre équipe va générer un nouveau contrat avec les informations corrigées et vous l''enverra dans les plus brefs délais.</p>
            
            <p>Nous nous excusons pour ce désagrément et restons à votre disposition pour toute question.</p>
            
            <p>Cordialement,<br>
            <strong>MY Invest Immobilier</strong><br>
            contact@myinvest-immobilier.com</p>
        </div>
        <div class="footer">
            <p>MY Invest Immobilier - Gestion locative professionnelle</p>
        </div>
    </div>
</body>
</html>',
    '["nom", "prenom", "reference", "logement", "motif_annulation"]',
    'Email envoyé au client quand un contrat est annulé'
),
(
    'contrat_annule_admin',
    'Notification Admin - Contrat Annulé',
    'Contrat annulé - {{reference}}',
    '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
        .content { background: #f8f9fa; padding: 30px; }
        .info-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .info-table td { padding: 8px; border-bottom: 1px solid #ddd; }
        .info-table td:first-child { font-weight: bold; width: 40%; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Contrat Annulé</h1>
        </div>
        <div class="content">
            <h2>Contrat annulé</h2>
            
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
                    <td>Annulé par</td>
                    <td>{{admin_nom}}</td>
                </tr>
                <tr>
                    <td>Date d''annulation</td>
                    <td>{{date_annulation}}</td>
                </tr>
                <tr>
                    <td>Motif</td>
                    <td>{{motif_annulation}}</td>
                </tr>
            </table>
            
            <p>Le client a été notifié de l''annulation. Vous pouvez maintenant générer un nouveau contrat si nécessaire.</p>
        </div>
        <div class="footer">
            <p>MY Invest Immobilier - Système de gestion des contrats</p>
        </div>
    </div>
</body>
</html>',
    '["reference", "logement", "locataires", "admin_nom", "date_annulation", "motif_annulation"]',
    'Email envoyé aux administrateurs quand un contrat est annulé'
)
ON DUPLICATE KEY UPDATE identifiant=identifiant;
