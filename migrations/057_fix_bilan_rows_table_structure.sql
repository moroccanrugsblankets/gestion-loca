-- Migration: Fix Bilan Rows Table Structure
-- Date: 2026-02-14
-- Description: Remove table/thead/tbody tags from template as {{bilan_rows}} now contains complete table structure

-- Update bilan logement HTML template to remove table/thead/tbody tags
-- The {{bilan_rows}} placeholder now contains the complete table structure including <table>, <th>, <td> tags
-- but without <thead>, </thead>, <tbody>, </tbody> tags as per requirements
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
    {{bilan_rows}}
    
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
