-- Migration 071: Ajout champ date_demande_depart et statut 'fin' pour les contrats
-- Date: 2026-02-24

-- 1. Ajouter la colonne date_demande_depart à la table contrats
ALTER TABLE contrats
    ADD COLUMN IF NOT EXISTS date_demande_depart TIMESTAMP NULL DEFAULT NULL
        COMMENT 'Date à laquelle le locataire a confirmé sa demande de départ';

-- 2. Ajouter le statut 'fin' à l'ENUM (remise des clés / contrat clôturé)
ALTER TABLE contrats MODIFY COLUMN statut
    ENUM('en_attente','signe','en_verification','valide','expire','annule','actif','termine','fin')
    DEFAULT 'en_attente';

-- 3. Ajouter le template email "Confirmation réception courrier AR24"
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
    'confirmation_courrier_ar24',
    'Confirmation Réception Courrier AR24',
    'My Invest Immobilier - Confirmation de réception de votre courrier',
    '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: white; padding: 30px; border: 1px solid #e0e0e0; border-top: none; }
        .info-box { background: #d1ecf1; border-left: 4px solid #17a2b8; padding: 15px; margin: 20px 0; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 10px 10px; border: 1px solid #e0e0e0; border-top: none; }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin: 0; font-size: 24px;">My Invest Immobilier</h1>
        <p style="margin: 10px 0 0 0; font-size: 14px; opacity: 0.9;">Confirmation de réception de courrier</p>
    </div>
    <div class="content">
        <p>Bonjour {{prenom}} {{nom}},</p>
        <p>Nous vous confirmons la bonne réception de votre courrier envoyé via AR24 concernant votre demande de départ pour le logement suivant :</p>
        <div class="info-box">
            <strong>Logement :</strong> {{logement}}<br>
            <strong>Référence contrat :</strong> {{reference}}<br>
            <strong>Date de réception :</strong> {{date_reception}}
        </div>
        <p>Votre demande est bien enregistrée. Notre équipe prendra contact avec vous prochainement pour organiser :</p>
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
    '["nom", "prenom", "logement", "reference", "date_reception", "signature"]',
    'Email de confirmation envoyé au locataire pour confirmer la réception de son courrier AR24',
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
