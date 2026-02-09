-- Migration: Add payment proof request email template
-- Date: 2026-02-09
-- Description: Add email template for requesting payment proof after contract signature

INSERT INTO email_templates (identifiant, nom, sujet, corps_html, variables_disponibles, description) VALUES
(
    'demande_justificatif_paiement',
    'Demande de justificatif de paiement',
    'Justificatif de virement - Contrat {{reference}}',
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
        .contact-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0;">📄 Justificatif de Paiement</h1>
        </div>
        <div class="content">
            <h2>Bonjour {{prenom}} {{nom}},</h2>
            
            <p>Nous vous confirmons que <strong>votre contrat de bail a été signé avec succès</strong>.</p>
            
            <div class="info-box">
                <strong>📋 Référence du contrat :</strong> {{reference}}
            </div>
            
            <h3>Justificatif de virement requis</h3>
            
            <p>Afin de finaliser votre dossier, nous vous remercions de bien vouloir nous transmettre <strong>le justificatif de virement</strong> du dépôt de garantie d''un montant de <strong>{{depot_garantie}}</strong>.</p>
            
            <div class="bank-info">
                <h3>Rappel des Coordonnées Bancaires</h3>
                <div class="bank-detail">
                    <strong>Bénéficiaire :</strong> MY Invest Immobilier
                </div>
                <div class="bank-detail">
                    <strong>IBAN :</strong> FR76 1027 8021 6000 0206 1834 585
                </div>
                <div class="bank-detail">
                    <strong>BIC :</strong> CMCIFRA
                </div>
                <div class="bank-detail">
                    <strong>Montant :</strong> {{depot_garantie}}
                </div>
            </div>
            
            <div class="contact-box">
                <h3 style="margin-top: 0;">📧 Comment transmettre votre justificatif ?</h3>
                <p style="margin-bottom: 0;">Merci d''envoyer votre justificatif de virement (capture d''écran ou PDF) par email à :</p>
                <p style="font-size: 18px; margin: 10px 0;"><strong>contact@myinvest-immobilier.fr</strong></p>
                <p style="margin-bottom: 0;">Ou par téléphone : <strong>01 23 45 67 89</strong></p>
            </div>
            
            <p><strong>Important :</strong> La prise d''effet du bail et la remise des clés interviendront uniquement après réception et vérification du justificatif de paiement.</p>
            
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
    'Email automatique envoyé après signature du contrat pour demander le justificatif de paiement du dépôt de garantie'
)
ON DUPLICATE KEY UPDATE identifiant=identifiant;
