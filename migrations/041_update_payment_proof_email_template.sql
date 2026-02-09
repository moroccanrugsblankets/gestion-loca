-- Migration: Update payment proof request email with upload button
-- Date: 2026-02-09
-- Description: Replace email contact box with upload button in demande_justificatif_paiement template

UPDATE email_templates 
SET 
    corps_html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center; border-radius: 10px 10px 0 0;">
            <h1 style="margin: 0;">📄 Justificatif de Paiement</h1>
        </div>
        <div style="background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px;">
            <h2>Bonjour {{prenom}} {{nom}},</h2>
            
            <p>Nous vous confirmons que <strong>votre contrat de bail a été signé avec succès</strong>.</p>
            
            <div style="background: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; margin: 20px 0; border-radius: 4px;">
                <strong>📋 Référence du contrat :</strong> {{reference}}
            </div>
            
            <h3>Justificatif de virement requis</h3>
            
            <p>Afin de finaliser votre dossier, nous vous remercions de bien vouloir nous transmettre <strong>le justificatif de virement</strong> du dépôt de garantie d\'un montant de <strong>{{depot_garantie}}</strong>.</p>
            
            <div style="background: #fff; border: 2px solid #3498db; padding: 20px; margin: 20px 0; border-radius: 8px;">
                <h3 style="color: #2c3e50; margin-top: 0;">Rappel des Coordonnées Bancaires</h3>
                <div style="margin: 10px 0;">
                    <strong style="display: inline-block; min-width: 120px; color: #555;">Bénéficiaire :</strong> MY Invest Immobilier
                </div>
                <div style="margin: 10px 0;">
                    <strong style="display: inline-block; min-width: 120px; color: #555;">IBAN :</strong> FR76 1027 8021 6000 0206 1834 585
                </div>
                <div style="margin: 10px 0;">
                    <strong style="display: inline-block; min-width: 120px; color: #555;">BIC :</strong> CMCIFRA
                </div>
                <div style="margin: 10px 0;">
                    <strong style="display: inline-block; min-width: 120px; color: #555;">Montant :</strong> {{depot_garantie}}
                </div>
            </div>
            
            <div style="background: #e8f5e9; border-left: 4px solid #4caf50; padding: 20px; margin: 20px 0; border-radius: 4px; text-align: center;">
                <h3 style="margin-top: 0; color: #2e7d32;">📤 Transmettre votre justificatif</h3>
                <p style="margin-bottom: 15px;">Une fois le virement effectué, cliquez sur le bouton ci-dessous pour envoyer votre justificatif :</p>
                <a href="{{lien_upload}}" style="display: inline-block; padding: 15px 40px; background: #4caf50; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">
                    Envoyer mon justificatif
                </a>
                <p style="margin-top: 15px; font-size: 12px; color: #666;">Formats acceptés : JPG, PNG, PDF (max 5 Mo)</p>
            </div>
            
            <p><strong>Important :</strong> La prise d\'effet du bail et la remise des clés interviendront uniquement après réception et vérification du justificatif de paiement.</p>
            
            <p>Nous restons à votre disposition pour toute question.</p>
            
            {{signature}}
        </div>
        <div style="text-align: center; padding: 20px; font-size: 12px; color: #666; margin-top: 20px;">
            <p>MY Invest Immobilier - Gestion locative professionnelle<br>
            © 2026 MY Invest Immobilier - Tous droits réservés</p>
        </div>
    </div>
</body>
</html>',
    variables_disponibles = ''["nom", "prenom", "reference", "depot_garantie", "lien_upload"]'',
    updated_at = NOW()
WHERE identifiant = ''demande_justificatif_paiement'';
