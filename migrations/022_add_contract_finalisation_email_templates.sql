-- Migration: Add contract finalisation email templates
-- Date: 2026-02-02
-- Description: Add HTML email templates for contract finalisation (client and admin)

INSERT INTO email_templates (identifiant, nom, sujet, corps_html, variables_disponibles, description) VALUES
(
    'contrat_finalisation_client',
    'Contrat de bail - Finalisation Client',
    'Contrat de bail – Finalisation',
    '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
        .info-box { background: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .bank-info { background: #fff; border: 2px solid #3498db; padding: 20px; margin: 20px 0; border-radius: 8px; }
        .bank-info h3 { color: #2c3e50; margin-top: 0; }
        .bank-detail { margin: 10px 0; }
        .bank-detail strong { display: inline-block; min-width: 120px; color: #555; }
        .highlight { color: #e74c3c; font-weight: bold; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0;">✅ Contrat de Bail Finalisé</h1>
        </div>
        <div class="content">
            <h2>Bonjour {{prenom}} {{nom}},</h2>
            
            <p>Nous vous remercions pour votre confiance.</p>
            
            <p>Veuillez trouver ci-joint une copie du <strong>contrat de bail dûment complété</strong>.</p>
            
            <div class="info-box">
                <strong>📋 Référence du contrat :</strong> {{reference}}
            </div>
            
            <h3>Informations importantes</h3>
            
            <p>La prise d''effet du bail intervient après le <span class="highlight">règlement immédiat du dépôt de garantie</span>, correspondant à deux mois de loyer (<strong>{{depot_garantie}}</strong>), par virement bancaire instantané sur le compte suivant :</p>
            
            <div class="bank-info">
                <h3>Coordonnées Bancaires</h3>
                <div class="bank-detail">
                    <strong>Bénéficiaire :</strong> MY Invest Immobilier
                </div>
                <div class="bank-detail">
                    <strong>IBAN :</strong> FR76 1027 8021 6000 0206 1834 585
                </div>
                <div class="bank-detail">
                    <strong>BIC :</strong> CMCIFRA
                </div>
            </div>
            
            <p><strong>Prochaines étapes :</strong></p>
            <ol>
                <li>Effectuer le virement du dépôt de garantie ({{depot_garantie}})</li>
                <li>Attendre la confirmation de réception du règlement</li>
                <li>Recevoir les modalités de remise des clés</li>
            </ol>
            
            <p>Dès réception du règlement, nous vous confirmerons la prise d''effet du bail ainsi que les modalités de remise des clés.</p>
            
            <p>Nous restons à votre disposition pour toute question.</p>
            
            {{signature}}
        </div>
        <div class="footer">
            <p>MY Invest Immobilier - Gestion locative professionnelle<br>
            © 2026 MY Invest Immobilier - Tous droits réservés</p>
        </div>
    </div>
</body>
</html>',
    '["nom", "prenom", "reference", "depot_garantie"]',
    'Email HTML envoyé au client lors de la finalisation du contrat avec le PDF joint'
),
(
    'contrat_finalisation_admin',
    'Notification Admin - Contrat Finalisé',
    'Contrat signé - {{reference}} - Vérification requise',
    '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 30px 20px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
        .alert-box { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .info-table { width: 100%; border-collapse: collapse; margin: 20px 0; background: #fff; }
        .info-table td { padding: 12px; border-bottom: 1px solid #ddd; }
        .info-table td:first-child { font-weight: bold; width: 40%; background: #f8f9fa; }
        .button { display: inline-block; padding: 12px 30px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0;">📝 Contrat Signé - Notification Admin</h1>
        </div>
        <div class="content">
            <div class="alert-box">
                <strong>✅ Nouveau contrat signé !</strong> Un contrat de bail a été finalisé et signé par le(s) locataire(s).
            </div>
            
            <h2>Détails du contrat</h2>
            
            <table class="info-table">
                <tr>
                    <td>Référence</td>
                    <td><strong>{{reference}}</strong></td>
                </tr>
                <tr>
                    <td>Logement</td>
                    <td>{{logement}}</td>
                </tr>
                <tr>
                    <td>Locataire(s)</td>
                    <td>{{locataires}}</td>
                </tr>
                <tr>
                    <td>Dépôt de garantie</td>
                    <td>{{depot_garantie}}</td>
                </tr>
                <tr>
                    <td>Date de finalisation</td>
                    <td>{{date_finalisation}}</td>
                </tr>
            </table>
            
            <h3>Actions à effectuer :</h3>
            <ol>
                <li>Vérifier la réception du dépôt de garantie</li>
                <li>Confirmer la prise d''effet du bail</li>
                <li>Organiser la remise des clés</li>
                <li>Planifier l''état des lieux d''entrée</li>
            </ol>
            
            <p style="text-align: center;">
                <a href="{{lien_admin}}" class="button">Voir le Contrat dans l''Admin</a>
            </p>
            
            <p><strong>Note :</strong> Le contrat PDF signé est joint à cet email.</p>
            
            {{signature}}
        </div>
        <div class="footer">
            <p>MY Invest Immobilier - Système de gestion des contrats<br>
            © 2026 MY Invest Immobilier</p>
        </div>
    </div>
</body>
</html>',
    '["reference", "logement", "locataires", "depot_garantie", "date_finalisation", "lien_admin"]',
    'Email HTML envoyé aux administrateurs quand un contrat est finalisé et signé'
)
ON DUPLICATE KEY UPDATE identifiant=identifiant;
