-- Migration 062: Ajout du lien "Procédure de départ" dans le template contrat_valide_client
--              et ajout du template email pour la procédure de départ
-- Date: 2026-02-21

-- 1. Mettre à jour le template contrat_valide_client pour inclure le lien de départ
UPDATE email_templates
SET corps_html = '<!DOCTYPE html>
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
        .depart-link { display: inline-block; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; font-size: 13px; }
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

            <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">
            <p style="font-size: 13px; color: #666;">
                <strong>Procédure de départ :</strong> Lorsque vous souhaiterez quitter le logement, vous pourrez initier la procédure de départ en cliquant sur le lien ci-dessous.
            </p>
            <p style="text-align: center;">
                <a href="{{lien_procedure_depart}}" class="depart-link">🚪 Demander la procédure de départ</a>
            </p>
            
            <p>Cordialement,<br>
            <strong>MY Invest Immobilier</strong><br>
            contact@myinvest-immobilier.com</p>

            {{signature}}
        </div>
        <div class="footer">
            <p>MY Invest Immobilier - Gestion locative professionnelle</p>
        </div>
    </div>
</body>
</html>',
    variables_disponibles = '["nom", "prenom", "reference", "logement", "date_prise_effet", "depot_garantie", "lien_telecharger", "lien_procedure_depart"]',
    updated_at = NOW()
WHERE identifiant = 'contrat_valide_client';

-- 2. Ajouter le template email pour la procédure de départ
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
    'procedure_depart_client',
    'Procédure de Départ - Confirmation',
    'My Invest Immobilier - Confirmation de votre demande de départ',
    '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #6c757d 0%, #495057 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: white; padding: 30px; border: 1px solid #e0e0e0; border-top: none; }
        .info-box { background: #f8f9fa; border-left: 4px solid #6c757d; padding: 15px; margin: 20px 0; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 10px 10px; border: 1px solid #e0e0e0; border-top: none; }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin: 0; font-size: 24px;">My Invest Immobilier</h1>
        <p style="margin: 10px 0 0 0; font-size: 14px; opacity: 0.9;">Procédure de départ</p>
    </div>
    <div class="content">
        <p>Bonjour {{prenom}} {{nom}},</p>
        <p>Nous avons bien reçu votre demande d''initiation de la procédure de départ pour le logement suivant :</p>
        <div class="info-box">
            <strong>Logement :</strong> {{logement}}<br>
            <strong>Référence contrat :</strong> {{reference}}<br>
            <strong>Date de la demande :</strong> {{date_demande}}
        </div>
        <p>Notre équipe va prendre contact avec vous dans les meilleurs délais afin de planifier :</p>
        <ul>
            <li>L''état des lieux de sortie</li>
            <li>La restitution des clés</li>
            <li>Le remboursement du dépôt de garantie</li>
        </ul>
        <p>Merci de votre confiance.</p>
        <p>Cordialement,<br>
        <strong>MY Invest Immobilier</strong></p>
        {{signature}}
    </div>
    <div class="footer">
        <p style="margin: 0;">My Invest Immobilier - Gestion locative professionnelle</p>
    </div>
</body>
</html>',
    '["nom", "prenom", "logement", "reference", "date_demande", "signature"]',
    'Email de confirmation envoyé au locataire lorsqu''il initie la procédure de départ',
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
