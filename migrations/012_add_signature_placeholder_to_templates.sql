-- Migration: Add {{signature}} placeholder to email templates
-- Date: 2026-01-30
-- Description: Add {{signature}} placeholder to all email templates to support dynamic signature insertion

-- Update candidature_recue template - Add {{signature}} placeholder
UPDATE email_templates 
SET 
    corps_html = '<!DOCTYPE html>
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
            
            {{signature}}
        </div>
        <div class="footer">
            <p>Date de soumission : {{date}}</p>
        </div>
    </div>
</body>
</html>',
    updated_at = NOW()
WHERE identifiant = 'candidature_recue';

-- Update candidature_acceptee template - Add {{signature}} placeholder
UPDATE email_templates 
SET 
    corps_html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 28px; }
        .content { padding: 30px; }
        .content p { margin: 15px 0; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #e9ecef; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>MY Invest Immobilier</h1>
        </div>
        <div class="content">
            <p>Bonjour,</p>
            
            <p>Nous vous remercions pour l''intérêt que vous portez à notre logement et pour votre candidature.</p>
            
            <p>Après une première analyse de votre dossier, nous avons le plaisir de vous informer qu''il a été retenu pour la suite du processus.<br>
            Nous reviendrons vers vous prochainement afin de convenir ensemble d''une date de visite.</p>
            
            <p>Nous vous remercions encore pour votre démarche et restons à votre disposition pour toute information complémentaire.</p>
            
            {{signature}}
        </div>
        <div class="footer">
            <p>MY Invest Immobilier - Gestion locative professionnelle</p>
        </div>
    </div>
</body>
</html>',
    updated_at = NOW()
WHERE identifiant = 'candidature_acceptee';

-- Update candidature_refusee template - Add {{signature}} placeholder
UPDATE email_templates 
SET 
    corps_html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 28px; }
        .content { padding: 30px; }
        .content p { margin: 15px 0; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #e9ecef; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>MY Invest Immobilier</h1>
        </div>
        <div class="content">
            <p>Bonjour,</p>
            
            <p>Nous vous remercions pour l''intérêt que vous portez à notre logement et pour le temps consacré à votre candidature.</p>
            
            <p>Après étude de l''ensemble des dossiers reçus, nous vous informons que nous ne donnerons pas suite à votre demande pour ce logement.</p>
            
            <p>Nous vous remercions pour votre démarche et vous souhaitons pleine réussite dans vos recherches.</p>
            
            {{signature}}
        </div>
        <div class="footer">
            <p>MY Invest Immobilier - Gestion locative professionnelle</p>
        </div>
    </div>
</body>
</html>',
    updated_at = NOW()
WHERE identifiant = 'candidature_refusee';

-- Update admin_nouvelle_candidature template - Add {{signature}} placeholder
UPDATE email_templates 
SET 
    corps_html = '<!DOCTYPE html>
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
            
            {{signature}}
        </div>
        <div class="footer">
            <p>MY Invest Immobilier - Système de gestion des candidatures</p>
        </div>
    </div>
</body>
</html>',
    updated_at = NOW()
WHERE identifiant = 'admin_nouvelle_candidature';
