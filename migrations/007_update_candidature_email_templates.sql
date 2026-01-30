-- Migration: Update email templates for candidature responses
-- Date: 2026-01-30
-- Description: Update templates for positive and negative candidature responses based on improved content

-- Update candidature_acceptee template
UPDATE email_templates 
SET 
    sujet = 'Suite à votre candidature',
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
        .content h2 { color: #28a745; margin-top: 0; }
        .content p { margin: 15px 0; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #e9ecef; }
        .signature { margin-top: 30px; }
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
            
            <div class="signature">
                <p>Sincères salutations<br>
                Le Bureau<br>
                <strong>MY Invest Immobilier</strong></p>
            </div>
        </div>
        <div class="footer">
            <p>MY Invest Immobilier - Gestion locative professionnelle</p>
        </div>
    </div>
</body>
</html>',
    updated_at = NOW()
WHERE identifiant = 'candidature_acceptee';

-- Update candidature_refusee template
UPDATE email_templates 
SET 
    sujet = 'Réponse à votre candidature',
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
        .signature { margin-top: 30px; }
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
            
            <div class="signature">
                <p>Sincères salutations<br>
                Le Bureau<br>
                <strong>MY Invest Immobilier</strong></p>
            </div>
        </div>
        <div class="footer">
            <p>MY Invest Immobilier - Gestion locative professionnelle</p>
        </div>
    </div>
</body>
</html>',
    updated_at = NOW()
WHERE identifiant = 'candidature_refusee';
