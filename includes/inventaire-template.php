<?php
/**
 * Inventaire Template Functions
 * Contains default templates for inventaire PDF generation
 * My Invest Immobilier
 */

/**
 * Get the default HTML template for inventaire PDF (unified entry/exit)
 * This template contains placeholders that will be replaced with actual data
 * 
 * @return string HTML template with placeholders
 */
function getDefaultInventaireTemplate() {
    return <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Inventaire - {{reference}}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.5;
            color: #000;
            margin: 0;
            padding: 15px;
        }
        h1 {
            text-align: center;
            font-size: 16pt;
            margin-bottom: 10px;
            font-weight: bold;
            color: #2c3e50;
        }
        h2 {
            font-size: 13pt;
            margin-top: 20px;
            margin-bottom: 10px;
            font-weight: bold;
            color: #34495e;
            border-bottom: 2px solid #3498db;
            padding-bottom: 5px;
        }
        h3 {
            font-size: 11pt;
            margin-top: 15px;
            margin-bottom: 8px;
            font-weight: bold;
            color: #2c3e50;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }
        .subtitle {
            text-align: center;
            font-style: italic;
            margin-bottom: 25px;
            color: #555;
        }
        p {
            margin: 5px 0;
        }
        .info-section {
            margin-bottom: 15px;
            padding: 10px;
            background-color: #f9f9f9;
            border-left: 4px solid #3498db;
        }
        .info-row {
            margin: 5px 0;
            padding: 3px 0;
        }
        .info-label {
            font-weight: bold;
            color: #2c3e50;
            display: inline-block;
            width: 200px;
        }
        .info-value {
            display: inline-block;
            color: #34495e;
        }
        table {
            width: 100%;
            margin: 15px 0;
            border-collapse: collapse;
        }
        table th {
            background-color: #3498db;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: bold;
        }
        table td {
            padding: 8px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        table tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .equipement-item {
            margin: 10px 0;
            padding: 10px;
            background-color: white;
            border: 1px solid #e0e0e0;
            border-radius: 3px;
        }
        .equipement-category {
            font-weight: bold;
            color: #2c3e50;
            font-size: 11pt;
            margin-bottom: 5px;
        }
        .observations {
            white-space: pre-wrap;
            word-wrap: break-word;
            background-color: #f9f9f9;
            padding: 10px;
            margin: 10px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ccc;
            font-size: 9pt;
            text-align: center;
            color: #666;
        }
        .signature-section {
            margin-top: 30px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>MY INVEST IMMOBILIER</h1>
        <p style="font-size: 12pt; font-weight: bold; margin-top: 10px;">INVENTAIRE DES ÉQUIPEMENTS</p>
    </div>
    
    <div class="subtitle">
        Référence : {{reference}}<br>
        Date : {{date}}
    </div>

    <h2>1. Informations du logement</h2>
    
    <div class="info-section">
        <div class="info-row">
            <span class="info-label">Adresse :</span>
            <span class="info-value">{{adresse}}</span>
        </div>
    </div>

    <h2>2. Locataire</h2>
    
    <div class="info-section">
        <div class="info-row">
            <span class="info-label">Nom du locataire :</span>
            <span class="info-value">{{locataire_nom}}</span>
        </div>
    </div>

    <h2>3. Liste des équipements</h2>
    
    <div style="margin: 15px 0;">
        {{equipements}}
    </div>

    <h2>4. Observations générales</h2>
    
    <div class="observations">
{{observations}}
    </div>

    <div class="signature-section">
        <h3>Signatures</h3>
        <p>Fait à {{lieu_signature}}, le {{date_signature}}</p>
        
        <div class="signatures-section">
            {{signatures_table}}
        </div>
    </div>

    <div class="footer">
        <p>Document généré électroniquement par My Invest Immobilier</p>
        <p>Inventaire - Référence : {{reference}}</p>
    </div>
</body>
</html>
HTML;
}

/**
 * Get the default HTML template for inventaire PDF (unified, same as entry)
 * This template is now identical to the entry template since we no longer distinguish
 * 
 * @return string HTML template with placeholders for inventory
 */
function getDefaultInventaireSortieTemplate() {
    // Return the same template as entry inventory - unified
    return getDefaultInventaireTemplate();
}
