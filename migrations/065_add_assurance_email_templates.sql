-- Migration 065: Ajout des templates email pour la demande d'assurance habitation et Visale
-- Date: 2026-02-22

-- Template 1: Email envoyé au locataire pour demander l'assurance habitation + Visale
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
    'demande_assurance_visale',
    'Demande d''assurance habitation et Visale',
    'My Invest Immobilier - Documents requis après signature de votre bail',
    '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); color: white; padding: 30px 20px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
        .info-box { background: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .docs-list { background: #fff; border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .upload-btn { display: inline-block; padding: 15px 40px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0;">Documents requis</h1>
            <p style="margin: 10px 0 0 0; opacity: 0.9;">My Invest Immobilier</p>
        </div>
        <div class="content">
            <p>Bonjour {{prenom}} {{nom}},</p>

            <p>Suite à la contre-signature de votre contrat de bail par My Invest Immobilier, nous vous remercions pour votre confiance.</p>

            <p>Afin de finaliser définitivement votre dossier, nous vous remercions de bien vouloir nous transmettre dans les meilleurs délais les éléments suivants :</p>

            <div class="docs-list">
                <ul style="margin: 0; padding-left: 20px;">
                    <li style="margin-bottom: 10px;">Votre <strong>attestation d''assurance habitation</strong> en cours de validité couvrant le logement loué</li>
                    <li>Votre <strong>numéro de garantie Visale</strong> (ainsi que le visa certifié si disponible)</li>
                </ul>
            </div>

            <p>Ces documents sont obligatoires et doivent être en notre possession pour valider l''entrée effective dans les lieux.</p>

            <div style="background: #e8f5e9; border-left: 4px solid #4caf50; padding: 20px; margin: 20px 0; border-radius: 4px; text-align: center;">
                <h3 style="margin-top: 0; color: #2e7d32;">📤 Transmettre vos documents</h3>
                <p style="margin-bottom: 15px;">Cliquez sur le bouton ci-dessous pour nous faire parvenir vos documents :</p>
                <a href="{{lien_upload}}" class="upload-btn">
                    Envoyer mes documents
                </a>
                <p style="margin-top: 15px; font-size: 12px; color: #666;">Formats acceptés : JPG, PNG, PDF (max 5 Mo par fichier)</p>
            </div>

            <p>Nous restons naturellement à votre disposition pour toute question.</p>

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
    '["nom", "prenom", "reference", "lien_upload"]',
    'Email envoyé au locataire après validation du contrat pour demander l''attestation d''assurance habitation et le numéro Visale',
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

-- Template 2: Notification admin quand les documents assurance/visale sont reçus
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
    'notification_assurance_visale_admin',
    'Notification Admin - Documents assurance/Visale reçus',
    'Documents assurance/Visale reçus - Contrat {{reference}}',
    '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #28a745; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
        .success-box { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .btn { display: inline-block; padding: 12px 30px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0;">📄 Documents assurance/Visale reçus</h1>
        </div>
        <div class="content">
            <div class="success-box">
                <strong>✅ Nouveau dépôt :</strong> Un locataire a transmis ses documents d''assurance habitation et/ou Visale.
            </div>

            <h2>Informations du contrat</h2>

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
                <li>Vérifier l''attestation d''assurance habitation dans le dossier du contrat</li>
                <li>Vérifier le numéro de garantie Visale et le visa certifié si fourni</li>
                <li>Confirmer la validité des documents et organiser l''entrée dans les lieux</li>
            </ol>

            <p style="text-align: center;">
                <a href="{{lien_admin}}" class="btn">Voir le contrat</a>
            </p>
        </div>
    </div>
</body>
</html>',
    '["reference", "logement", "locataires", "date_envoi", "lien_admin"]',
    'Notification envoyée aux administrateurs quand un locataire dépose ses documents assurance habitation et Visale',
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
