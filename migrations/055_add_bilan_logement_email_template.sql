-- Migration: Add Bilan Logement Email Template
-- Date: 2026-02-14
-- Description: Add email template for sending bilan logement to tenants with PDF attachment

-- Insert bilan logement email template
INSERT INTO email_templates (identifiant, nom, sujet, corps_html, variables_disponibles, description, ordre) VALUES
(
    'bilan_logement',
    'Envoi du bilan du logement',
    'Bilan de votre logement - {{adresse}}',
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
        .info-box { background: #fff; border-left: 4px solid #3498db; padding: 15px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>MY Invest Immobilier</h1>
        </div>
        <div class="content">
            <h2>Bonjour {{locataire_nom}},</h2>
            
            <p>Suite à votre départ du logement, veuillez trouver ci-joint le bilan de l''état de sortie.</p>
            
            <div class="info-box">
                <p><strong>Logement :</strong> {{adresse}}</p>
                <p><strong>Référence contrat :</strong> {{contrat_ref}}</p>
                <p><strong>Date :</strong> {{date}}</p>
            </div>
            
            <p>Ce document récapitule :</p>
            <ul>
                <li>L''état général du logement à la sortie</li>
                <li>Les éventuelles dégradations constatées</li>
                <li>Les montants dus le cas échéant</li>
            </ul>
            
            {{commentaire}}
            
            <p>Pour toute question concernant ce bilan, n''hésitez pas à nous contacter.</p>
            
            <p>Cordialement,</p>
            {{signature}}
        </div>
        <div class="footer">
            <p>MY Invest Immobilier - Gestion locative professionnelle</p>
        </div>
    </div>
</body>
</html>',
    '["locataire_nom", "adresse", "contrat_ref", "date", "commentaire", "signature", "depot_garantie", "valeur_estimative", "total_solde_debiteur", "total_solde_crediteur", "montant_a_restituer", "reste_du"]',
    'Email envoyé au locataire avec le bilan du logement en pièce jointe (PDF)',
    100
)
ON DUPLICATE KEY UPDATE identifiant=identifiant;

-- Add HTML template parameter for bilan logement in parametres table
INSERT INTO parametres (cle, valeur, type, groupe, description) VALUES
(
    'bilan_logement_template_html',
    '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .header img { max-width: 200px; margin-bottom: 10px; }
        .header h1 { color: #2c3e50; margin: 10px 0; }
        .info-section { background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .info-section h2 { color: #3498db; margin-top: 0; }
        .info-item { padding: 8px 0; }
        .info-item strong { color: #2c3e50; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        table th { background: #3498db; color: white; padding: 12px; text-align: left; }
        table td { border: 1px solid #ddd; padding: 10px; }
        table tr:nth-child(even) { background: #f8f9fa; }
        .commentaire-section { margin: 20px 0; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; }
        .recapitulatif-financier { margin-top: 30px; padding: 20px; background: #e8f4f8; border-radius: 5px; }
        .recapitulatif-financier h3 { color: #2c3e50; margin-top: 0; margin-bottom: 15px; }
        .recapitulatif-financier .disclaimer { font-size: 11px; color: #666; margin-top: 15px; font-style: italic; }
        .signature-section { margin-top: 40px; padding: 20px; }
    </style>
</head>
<body>
    <div class="header">
        {{logo}}
        <h1>Bilan du Logement</h1>
        <p><strong>État de Sortie</strong></p>
    </div>
    
    <div class="info-section">
        <h2>Informations du Contrat</h2>
        <table border="0" cellpadding="5" cellspacing="0" style="border: none;">
            <tr>
                <td style="border: none; width: 50%;"><strong>Locataire :</strong> {{locataire_nom}}</td>
                <td style="border: none; width: 50%;"><strong>Référence :</strong> {{contrat_ref}}</td>
            </tr>
            <tr>
                <td style="border: none;"><strong>Adresse :</strong> {{adresse}}</td>
                <td style="border: none;"><strong>Date :</strong> {{date}}</td>
            </tr>
        </table>
    </div>
    
    <h2>Détail du Bilan</h2>
    {{bilan_rows}}
    
    {{commentaire_section}}
    
    <div class="recapitulatif-financier">
        <h3>Récapitulatif Financier</h3>
        <table border="1" cellpadding="8" cellspacing="0">
            <tr>
                <td style="width: 60%;"><strong>Dépôt de garantie :</strong></td>
                <td style="width: 40%; text-align: right;">{{depot_garantie}}</td>
            </tr>
            <tr>
                <td><strong>Valeur estimative :</strong></td>
                <td style="text-align: right;">{{valeur_estimative}}</td>
            </tr>
            <tr>
                <td><strong>Solde Débiteur :</strong></td>
                <td style="text-align: right;">{{total_solde_debiteur}}</td>
            </tr>
            <tr>
                <td><strong>Solde Créditeur :</strong></td>
                <td style="text-align: right;">{{total_solde_crediteur}}</td>
            </tr>
            <tr style="background-color: #d4edda;">
                <td><strong>Montant à restituer :</strong></td>
                <td style="text-align: right; font-weight: bold;">{{montant_a_restituer}}</td>
            </tr>
            <tr style="background-color: #f8d7da;">
                <td><strong>Reste dû :</strong></td>
                <td style="text-align: right; font-weight: bold;">{{reste_du}}</td>
            </tr>
        </table>
        <p class="disclaimer">Les soldes débiteurs et créditeurs figurant dans le tableau s''entendent comme étant respectivement à la charge ou en faveur du locataire.</p>
    </div>
    
    <div class="signature-section">
        <p><strong>Établi le :</strong> {{date}}</p>
        {{signature_agence}}
    </div>
</body>
</html>',
    'text',
    'bilan_logement',
    'Template HTML du bilan de logement avec variables dynamiques'
)
ON DUPLICATE KEY UPDATE cle=cle;
