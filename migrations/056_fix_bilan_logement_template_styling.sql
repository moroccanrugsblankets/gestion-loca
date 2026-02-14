-- Migration: Fix Bilan Logement Template Styling
-- Date: 2026-02-14
-- Description: Reduce line spacing and logo size in bilan logement HTML template

-- Update bilan logement HTML template with reduced line spacing and smaller logo
UPDATE parametres 
SET valeur = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.3; color: #333; margin: 20px; font-size: 11pt; }
        .header { text-align: center; margin-bottom: 20px; }
        .header img { max-width: 150px; max-height: 80px; margin-bottom: 8px; }
        .header h1 { color: #2c3e50; margin: 8px 0; font-size: 18pt; line-height: 1.2; }
        .header p { margin: 5px 0; line-height: 1.2; }
        .info-section { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px; }
        .info-section h2 { color: #3498db; margin-top: 0; margin-bottom: 10px; font-size: 14pt; line-height: 1.2; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .info-item { padding: 5px 0; line-height: 1.3; }
        .info-item strong { color: #2c3e50; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        table th { background: #3498db; color: white; padding: 8px; text-align: left; font-size: 10pt; line-height: 1.2; }
        table td { border: 1px solid #ddd; padding: 6px; font-size: 10pt; line-height: 1.3; }
        table tr:nth-child(even) { background: #f8f9fa; }
        .commentaire-section { margin: 15px 0; padding: 12px; background: #fff3cd; border-left: 4px solid #ffc107; }
        .commentaire-section h3 { margin-top: 0; margin-bottom: 8px; font-size: 13pt; line-height: 1.2; }
        .commentaire-section p { line-height: 1.3; }
        .total-section { margin-top: 20px; padding: 15px; background: #e8f4f8; border-radius: 5px; }
        .total-section h3 { color: #2c3e50; margin-top: 0; margin-bottom: 8px; font-size: 14pt; line-height: 1.2; }
        .total-section p { line-height: 1.3; }
        .signature-section { margin-top: 30px; padding: 15px; }
        .signature-section p { line-height: 1.3; }
        h2 { font-size: 14pt; margin: 15px 0 10px 0; line-height: 1.2; }
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
        <div class="info-grid">
            <div class="info-item"><strong>Locataire :</strong> {{locataire_nom}}</div>
            <div class="info-item"><strong>Référence :</strong> {{contrat_ref}}</div>
            <div class="info-item"><strong>Adresse :</strong> {{adresse}}</div>
            <div class="info-item"><strong>Date :</strong> {{date}}</div>
        </div>
    </div>
    
    <h2>Détail du Bilan</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 30%;">Poste</th>
                <th style="width: 35%;">Commentaires</th>
                <th style="width: 15%;">Valeur</th>
                <th style="width: 20%;">Montant dû</th>
            </tr>
        </thead>
        <tbody>
            {{bilan_rows}}
        </tbody>
    </table>
    
    {{commentaire_section}}
    
    <div class="total-section">
        <h3>Total à régler</h3>
        <p style="font-size: 20px; font-weight: bold; color: #2c3e50;">{{total_montant}}</p>
    </div>
    
    <div class="signature-section">
        <p><strong>Établi le :</strong> {{date}}</p>
        {{signature_agence}}
    </div>
</body>
</html>'
WHERE cle = 'bilan_logement_template_html';

-- Update email template line spacing
UPDATE email_templates 
SET corps_html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.4; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 15px; text-align: center; }
        .header h1 { margin: 5px 0; font-size: 20px; line-height: 1.3; }
        .content { background: #f8f9fa; padding: 25px; }
        .content h2 { margin-top: 0; margin-bottom: 12px; line-height: 1.3; }
        .content p { margin: 8px 0; line-height: 1.4; }
        .content ul { margin: 10px 0; padding-left: 20px; }
        .content li { margin: 5px 0; line-height: 1.4; }
        .footer { text-align: center; padding: 15px; font-size: 12px; color: #666; line-height: 1.3; }
        .info-box { background: #fff; border-left: 4px solid #3498db; padding: 12px; margin: 15px 0; }
        .info-box p { margin: 5px 0; line-height: 1.4; }
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
</html>'
WHERE identifiant = 'bilan_logement';
