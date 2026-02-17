-- Migration 058: Ajout du template email de rappel de loyer impayé pour les locataires
-- Date: 2026-02-17
-- Description: Crée un nouveau template pour envoyer des rappels de paiement directement aux locataires

-- Insert the new email template for tenant payment reminder
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
    'rappel_loyer_impaye_locataire',
    'Rappel Loyer Impayé - Locataire',
    'My Invest Immobilier - Rappel loyer non réceptionné',
    '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: white;
            padding: 30px;
            border: 1px solid #e0e0e0;
            border-top: none;
        }
        .greeting {
            font-size: 16px;
            margin-bottom: 20px;
        }
        .message {
            font-size: 14px;
            margin-bottom: 15px;
            text-align: justify;
        }
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 20px 0;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-radius: 0 0 10px 10px;
            border: 1px solid #e0e0e0;
            border-top: none;
        }
        .signature {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin: 0; font-size: 24px;">My Invest Immobilier</h1>
        <p style="margin: 10px 0 0 0; font-size: 14px; opacity: 0.9;">Rappel de paiement</p>
    </div>
    
    <div class="content">
        <div class="greeting">
            Bonjour {{locataire_prenom}} {{locataire_nom}},
        </div>
        
        <div class="message">
            Sauf erreur de notre part, nous n\'avons pas encore réceptionné le règlement du loyer relatif à la période en cours.
        </div>
        
        <div class="info-box">
            <strong>Période concernée :</strong> {{periode}}<br>
            <strong>Montant attendu :</strong> {{montant_total}} €<br>
            <strong>Logement :</strong> {{adresse}}
        </div>
        
        <div class="message">
            Il peut bien entendu s\'agir d\'un simple oubli ou d\'un décalage bancaire. Nous vous serions reconnaissants de bien vouloir vérifier la situation et, le cas échéant, procéder au règlement dans les meilleurs délais.
        </div>
        
        <div class="message">
            Si le paiement a déjà été effectué, nous vous remercions de nous fournir la preuve de règlement.
        </div>
        
        <div class="message">
            Nous restons naturellement à votre disposition pour toute question.
        </div>
        
        <div class="signature">
            {{signature}}
        </div>
    </div>
    
    <div class="footer">
        <p style="margin: 0;">My Invest Immobilier - Gestion locative professionnelle</p>
        <p style="margin: 5px 0 0 0;">Cet email a été envoyé automatiquement, merci de ne pas y répondre directement.</p>
    </div>
</body>
</html>',
    '["locataire_nom", "locataire_prenom", "periode", "adresse", "montant_total", "signature"]',
    'Template d\'email envoyé aux locataires pour leur rappeler un loyer impayé. Ce rappel est envoyé lorsque le paiement n\'a pas été réceptionné pour la période en cours.',
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
