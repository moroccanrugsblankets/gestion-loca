-- Migration 094 : Mise à jour du template confirmation_responsabilite_locataire
-- Date: 2026-03-05
-- Description:
--   1. Ne plus fermer automatiquement le dossier lors de la confirmation de responsabilité locataire.
--   2. Modifier le template email pour informer le locataire que l''intervention sera à sa charge
--      avec le barème tarifaire et un bouton pour accepter l''intervention.

UPDATE email_templates
SET
    corps_html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Information signalement</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #e67e22 0%, #d35400 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="margin: 0; font-size: 26px;">🔧 Intervention à votre charge</h1>
        <p style="margin: 10px 0 0; font-size: 16px;">{{company}}</p>
    </div>
    <div style="background: #ffffff; padding: 30px; border: 1px solid #e0e0e0; border-top: none;">
        <p>Bonjour {{prenom}} {{nom}},</p>
        <p>Suite à l''analyse de votre signalement, nous vous informons que la responsabilité pour ce problème a été déterminée <strong>à votre charge</strong>.</p>
        <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 0 5px 5px 0;">
            <p style="margin: 5px 0;"><strong>Référence :</strong> <code>{{reference}}</code></p>
            <p style="margin: 5px 0;"><strong>Titre :</strong> {{titre}}</p>
            <p style="margin: 5px 0;"><strong>Logement :</strong> {{adresse}}</p>
            <p style="margin: 5px 0;"><strong>Responsabilité :</strong> <strong style="color: #e74c3c;">À la charge du locataire</strong></p>
        </div>
        <p>Notre équipe peut prendre en charge cette intervention. Les tarifs applicables sont les suivants :</p>
        <table style="width: 100%; border-collapse: collapse; margin: 20px 0; background: #fffaf0;">
            <thead>
                <tr style="background: #ffc107; color: #333;">
                    <th style="padding: 10px 15px; text-align: left; border: 1px solid #dee2e6;">Prestation</th>
                    <th style="padding: 10px 15px; text-align: right; border: 1px solid #dee2e6; white-space: nowrap;">Tarif TTC</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="padding: 10px 15px; border: 1px solid #dee2e6;">Forfait déplacement + diagnostic<br><small style="color: #666;">(incluant jusqu''à 1 heure sur place)</small></td>
                    <td style="padding: 10px 15px; text-align: right; font-weight: bold; border: 1px solid #dee2e6; white-space: nowrap;">80 € TTC</td>
                </tr>
                <tr>
                    <td style="padding: 10px 15px; border: 1px solid #dee2e6;">Heure supplémentaire entamée</td>
                    <td style="padding: 10px 15px; text-align: right; font-weight: bold; border: 1px solid #dee2e6; white-space: nowrap;">60 € TTC</td>
                </tr>
                <tr>
                    <td style="padding: 10px 15px; border: 1px solid #dee2e6;">Fournitures et pièces</td>
                    <td style="padding: 10px 15px; text-align: right; font-weight: bold; border: 1px solid #dee2e6; white-space: nowrap;">Coût réel</td>
                </tr>
            </tbody>
        </table>
        <p>Si vous souhaitez que notre équipe réalise cette intervention, veuillez cliquer sur le bouton ci-dessous pour confirmer votre accord. L''intervention sera ensuite planifiée et facturée selon le barème ci-dessus.</p>
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{lien_acceptation}}" style="display: inline-block; background: #e67e22; color: white; padding: 16px 32px; text-decoration: none; border-radius: 8px; font-size: 16px; font-weight: bold;">
                ✅ J''accepte — Planifier l''intervention
            </a>
        </div>
        <p style="color: #666; font-size: 13px;">Si vous avez des questions ou souhaitez discuter de cette situation, n''hésitez pas à contacter votre gestionnaire.</p>
    </div>
    <div style="background: #f8f9fa; padding: 15px; text-align: center; border-radius: 0 0 10px 10px; border: 1px solid #e0e0e0; border-top: none;">
        <p style="margin: 0; color: #666; font-size: 12px;">{{company}}</p>
    </div>
    {{signature}}
</body>
</html>',
    variables_disponibles = 'prenom,nom,reference,titre,adresse,company,responsabilite,lien_acceptation',
    description = 'Email envoyé au locataire lorsque la responsabilité du signalement est confirmée à sa charge (avec barème tarifaire et bouton d''acceptation)',
    updated_at = NOW()
WHERE identifiant = 'confirmation_responsabilite_locataire';
