<?php
/**
 * Inventaire Template Functions
 * Contains default templates for inventaire PDF generation
 * My Invest Immobilier
 */

/**
 * Get the default HTML template for inventaire d'entrée (entry inventory) PDF
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
    <title>Inventaire d'Entrée - {{reference}}</title>
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
            background-color: #fffef0;
            padding: 10px;
            border-left: 3px solid #f39c12;
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
        <p style="font-size: 12pt; font-weight: bold; margin-top: 10px;">INVENTAIRE DES ÉQUIPEMENTS - ENTRÉE</p>
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
        <div class="info-row">
            <span class="info-label">Appartement :</span>
            <span class="info-value">{{appartement}}</span>
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
        <p>Fait le {{date}}</p>
        <p style="margin-top: 20px;">
            <strong>Signature du bailleur :</strong> _______________________
        </p>
        <p style="margin-top: 20px;">
            <strong>Signature du locataire :</strong> _______________________
        </p>
    </div>

    <div class="footer">
        <p>Document généré électroniquement par MY Invest Immobilier</p>
        <p>Inventaire d'entrée - Référence : {{reference}}</p>
    </div>
</body>
</html>
HTML;
}

/**
 * Get the default HTML template for inventaire de sortie (exit inventory) PDF
 * This template includes exit-specific sections like comparison and damages assessment
 * 
 * @return string HTML template with placeholders for exit inventory
 */
function getDefaultInventaireSortieTemplate() {
    return <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Inventaire de Sortie - {{reference}}</title>
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
            border-bottom: 2px solid #e74c3c;
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
            border-left: 4px solid #e74c3c;
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
            background-color: #e74c3c;
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
        .comparison-section {
            background-color: #fff3cd;
            padding: 15px;
            margin: 15px 0;
            border: 1px solid #ffc107;
            border-radius: 5px;
        }
        .comparison-title {
            font-weight: bold;
            color: #856404;
            font-size: 11pt;
            margin-bottom: 10px;
        }
        .observations {
            white-space: pre-wrap;
            word-wrap: break-word;
            background-color: #fffef0;
            padding: 10px;
            border-left: 3px solid #f39c12;
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
        .alert {
            padding: 12px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .alert-warning {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
        }
        .alert-danger {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>MY INVEST IMMOBILIER</h1>
        <p style="font-size: 12pt; font-weight: bold; margin-top: 10px;">INVENTAIRE DES ÉQUIPEMENTS - SORTIE</p>
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
        <div class="info-row">
            <span class="info-label">Appartement :</span>
            <span class="info-value">{{appartement}}</span>
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

    <h2>4. Comparaison avec l'inventaire d'entrée</h2>
    
    <div class="comparison-section">
        <div class="comparison-title">Résumé des différences constatées :</div>
        {{comparaison}}
    </div>

    <h2>5. Observations générales</h2>
    
    <div class="observations">
{{observations}}
    </div>

    <div class="signature-section">
        <h3>Signatures</h3>
        <p>Fait le {{date}}</p>
        <p style="margin-top: 20px;">
            <strong>Signature du bailleur :</strong> _______________________
        </p>
        <p style="margin-top: 20px;">
            <strong>Signature du locataire :</strong> _______________________
        </p>
    </div>

    <div class="footer">
        <p>Document généré électroniquement par MY Invest Immobilier</p>
        <p>Inventaire de sortie - Référence : {{reference}}</p>
    </div>
</body>
</html>
HTML;
}
