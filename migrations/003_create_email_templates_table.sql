-- Migration: Create email templates table
-- Date: 2026-01-29
-- Description: Create table to store email templates for easy management

CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifiant VARCHAR(100) UNIQUE NOT NULL COMMENT 'Unique template identifier',
    nom VARCHAR(255) NOT NULL COMMENT 'Template name',
    sujet VARCHAR(500) NOT NULL COMMENT 'Email subject (supports variables)',
    corps_html TEXT NOT NULL COMMENT 'Email body in HTML (supports variables)',
    variables_disponibles TEXT COMMENT 'Available variables as JSON array',
    description TEXT COMMENT 'Template description',
    actif BOOLEAN DEFAULT TRUE COMMENT 'Is template active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_identifiant (identifiant),
    INDEX idx_actif (actif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default email templates
INSERT INTO email_templates (identifiant, nom, sujet, corps_html, variables_disponibles, description) VALUES
(
    'candidature_recue',
    'Accusé de réception de candidature',
    'Votre candidature a bien été reçue - MY Invest Immobilier',
    '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
        .content { background: #f8f9fa; padding: 30px; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>MY Invest Immobilier</h1>
        </div>
        <div class="content">
            <h2>Bonjour {{prenom}} {{nom}},</h2>
            
            <p>Nous vous confirmons la bonne réception de votre candidature pour le logement <strong>{{logement}}</strong>.</p>
            
            <p><strong>Référence de votre candidature :</strong> {{reference}}</p>
            
            <p>Votre dossier est en cours d''étude. Nous reviendrons vers vous dans les meilleurs délais.</p>
            
            <p>Nous restons à votre disposition pour toute question.</p>
            
            <p>Cordialement,<br>
            <strong>MY Invest Immobilier</strong><br>
            contact@myinvest-immobilier.com</p>
        </div>
        <div class="footer">
            <p>Date de soumission : {{date}}</p>
        </div>
    </div>
</body>
</html>',
    '["nom", "prenom", "email", "logement", "reference", "date"]',
    'Email envoyé au candidat dès la soumission de sa candidature'
),
(
    'candidature_acceptee',
    'Candidature acceptée',
    'Candidature acceptée - MY Invest Immobilier',
    '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
        .content { background: #f8f9fa; padding: 30px; }
        .button { display: inline-block; padding: 12px 30px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>MY Invest Immobilier</h1>
        </div>
        <div class="content">
            <h2>Bonjour {{prenom}} {{nom}},</h2>
            
            <p>Nous avons le plaisir de vous informer que votre candidature locative a été <strong>acceptée</strong>.</p>
            
            <p><strong>Logement :</strong> {{logement}}</p>
            <p><strong>Référence :</strong> {{reference}}</p>
            
            <p><strong>Prochaines étapes :</strong></p>
            <ol>
                <li>Confirmez votre intérêt en cliquant sur le bouton ci-dessous</li>
                <li>Nous vous contacterons via WhatsApp pour organiser une visite du logement</li>
                <li>Si la visite est concluante, nous vous enverrons le contrat de bail à signer</li>
            </ol>
            
            <p style="text-align: center;">
                <a href="{{lien_confirmation}}" class="button">Confirmer mon intérêt</a>
            </p>
            
            <p><em>Ce lien est valable 48 heures.</em></p>
            
            <p>Cordialement,<br>
            <strong>MY Invest Immobilier</strong><br>
            contact@myinvest-immobilier.com</p>
        </div>
        <div class="footer">
            <p>Référence de candidature: {{reference}}</p>
        </div>
    </div>
</body>
</html>',
    '["nom", "prenom", "email", "logement", "reference", "date", "lien_confirmation"]',
    'Email envoyé au candidat si sa candidature est acceptée après le délai'
),
(
    'candidature_refusee',
    'Candidature non retenue',
    'Candidature - MY Invest Immobilier',
    '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
        .content { background: #f8f9fa; padding: 30px; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>MY Invest Immobilier</h1>
        </div>
        <div class="content">
            <h2>Bonjour {{prenom}} {{nom}},</h2>
            
            <p>Nous vous remercions de l''intérêt que vous avez porté à nos logements.</p>
            
            <p>Après étude de votre dossier, nous sommes au regret de vous informer que nous ne pouvons donner suite à votre candidature pour le moment.</p>
            
            <p>Cette décision ne remet pas en cause vos qualités en tant que locataire, mais résulte de critères spécifiques liés au logement proposé.</p>
            
            <p>Nous vous encourageons à postuler à nouveau si votre situation évolue ou pour d''autres opportunités.</p>
            
            <p>Nous vous souhaitons bonne chance dans vos recherches.</p>
            
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
    '["nom", "prenom", "email"]',
    'Email envoyé au candidat si sa candidature est refusée automatiquement'
),
(
    'admin_nouvelle_candidature',
    'Notification admin - Nouvelle candidature',
    'Nouvelle candidature reçue - {{reference}}',
    '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
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
            <h1>Nouvelle candidature</h1>
        </div>
        <div class="content">
            <h2>Candidature reçue</h2>
            
            <table class="info-table">
                <tr>
                    <td>Référence</td>
                    <td>{{reference}}</td>
                </tr>
                <tr>
                    <td>Candidat</td>
                    <td>{{prenom}} {{nom}}</td>
                </tr>
                <tr>
                    <td>Email</td>
                    <td>{{email}}</td>
                </tr>
                <tr>
                    <td>Téléphone</td>
                    <td>{{telephone}}</td>
                </tr>
                <tr>
                    <td>Logement</td>
                    <td>{{logement}}</td>
                </tr>
                <tr>
                    <td>Revenus mensuels</td>
                    <td>{{revenus}}</td>
                </tr>
                <tr>
                    <td>Statut professionnel</td>
                    <td>{{statut_pro}}</td>
                </tr>
                <tr>
                    <td>Date de soumission</td>
                    <td>{{date}}</td>
                </tr>
            </table>
            
            <p><a href="{{lien_admin}}">Voir la candidature dans l''admin</a></p>
        </div>
        <div class="footer">
            <p>MY Invest Immobilier - Système de gestion des candidatures</p>
        </div>
    </div>
</body>
</html>',
    '["nom", "prenom", "email", "telephone", "logement", "reference", "date", "revenus", "statut_pro", "lien_admin"]',
    'Email envoyé aux administrateurs lors d''une nouvelle candidature'
)
ON DUPLICATE KEY UPDATE identifiant=identifiant;
