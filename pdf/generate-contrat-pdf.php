<?php
/**
 * Génération simplifiée du PDF du contrat de bail
 * Version minimaliste : Template HTML + Variables + Signatures + PDF
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// Style CSS pour les images de signature (sans bordures)
define('SIGNATURE_IMG_STYLE', 'width: 40mm; height: auto; display: block; margin-bottom: 15mm; border: 0; border-width: 0; border-style: none; border-color: transparent; outline: none; outline-width: 0; box-shadow: none; padding: 0; background: transparent;');

/**
 * Générer le PDF du contrat de bail
 * @param int $contratId ID du contrat
 * @return string|false Chemin du fichier PDF généré ou false en cas d'erreur
 */
function generateContratPDF($contratId) {
    global $config, $pdo;
    
    // 1. Validation
    $contratId = (int)$contratId;
    if ($contratId <= 0) {
        error_log("Erreur: ID de contrat invalide");
        return false;
    }
    
    try {
        // 2. Récupérer les données du contrat
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   l.reference,
                   l.adresse,
                   l.appartement,
                   l.type,
                   l.surface,
                   l.loyer,
                   l.charges,
                   l.depot_garantie,
                   l.parking
            FROM contrats c
            INNER JOIN logements l ON c.logement_id = l.id
            WHERE c.id = ?
        ");
        $stmt->execute([$contratId]);
        $contrat = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$contrat) {
            error_log("Erreur: Contrat #$contratId non trouvé");
            return false;
        }
        
        // 3. Récupérer les locataires
        $stmt = $pdo->prepare("SELECT * FROM locataires WHERE contrat_id = ? ORDER BY ordre ASC");
        $stmt->execute([$contratId]);
        $locataires = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($locataires)) {
            error_log("Erreur: Aucun locataire trouvé");
            return false;
        }
        
        // 4. Récupérer la template HTML depuis la configuration
        $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'contrat_template_html'");
        $stmt->execute();
        $templateHtml = $stmt->fetchColumn();
        
        if (empty($templateHtml)) {
            // Utiliser le template par défaut si non configuré
            $templateHtml = getDefaultContractTemplate();
        }
        
        error_log("Template HTML récupérée");
        
        // 5. Remplacer les variables {{nom_variable}} par leurs valeurs
        $html = replaceContratTemplateVariables($templateHtml, $contrat, $locataires);
        error_log("Variables remplacées");
        
        // 6. Injecter les signatures via <img>
        $html = injectSignatures($html, $contrat, $locataires);
        error_log("Signatures injectées via <img>");
        
        // 7. Générer le PDF à partir du HTML final
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('MY INVEST IMMOBILIER');
        $pdf->SetTitle('Contrat de Bail - ' . $contrat['reference_unique']);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // 8. Sauvegarder le PDF
        $filename = 'bail-' . $contrat['reference_unique'] . '.pdf';
        $pdfDir = dirname(__DIR__) . '/pdf/contrats/';
        
        if (!is_dir($pdfDir)) {
            mkdir($pdfDir, 0755, true);
        }
        
        $filepath = $pdfDir . $filename;
        $pdf->Output($filepath, 'F');
        
        error_log("PDF généré avec succès");
        
        return $filepath;
        
    } catch (Exception $e) {
        error_log("Erreur génération PDF: " . $e->getMessage());
        return false;
    }
}

/**
 * Remplacer les variables dans la template du contrat PDF
 */
function replaceContratTemplateVariables($template, $contrat, $locataires) {
    global $config;
    
    // Préparer les informations des locataires
    $locatairesInfo = [];
    foreach ($locataires as $loc) {
        $dateNaissance = 'N/A';
        if (!empty($loc['date_naissance'])) {
            $timestamp = strtotime($loc['date_naissance']);
            if ($timestamp !== false) {
                $dateNaissance = date('d/m/Y', $timestamp);
            }
        }
        $locatairesInfo[] = htmlspecialchars($loc['prenom']) . ' ' . htmlspecialchars($loc['nom']) . 
                           ', né(e) le ' . $dateNaissance . '<br>' .
                           'Email : ' . htmlspecialchars($loc['email']);
    }
    $locatairesInfoHtml = implode('<br>', $locatairesInfo);
    
    // Formatter les dates
    $datePriseEffet = 'N/A';
    if (!empty($contrat['date_prise_effet'])) {
        $timestamp = strtotime($contrat['date_prise_effet']);
        if ($timestamp !== false) {
            $datePriseEffet = date('d/m/Y', $timestamp);
        }
    }
    
    $dateSignature = date('d/m/Y'); // Date actuelle par défaut
    if (!empty($contrat['date_signature'])) {
        $timestamp = strtotime($contrat['date_signature']);
        if ($timestamp !== false) {
            $dateSignature = date('d/m/Y', $timestamp);
        }
    }
    
    // Formatter les montants
    $loyer = number_format((float)$contrat['loyer'], 2, ',', ' ');
    $charges = number_format((float)$contrat['charges'], 2, ',', ' ');
    $loyerTotal = number_format((float)$contrat['loyer'] + (float)$contrat['charges'], 2, ',', ' ');
    $depotGarantie = number_format((float)$contrat['depot_garantie'], 2, ',', ' ');
    
    // Récupérer IBAN et BIC
    $iban = isset($config['IBAN']) ? $config['IBAN'] : '[IBAN non configuré]';
    $bic = isset($config['BIC']) ? $config['BIC'] : '[BIC non configuré]';
    
    // Map des variables à remplacer
    $variables = [
        '{{reference_unique}}' => htmlspecialchars($contrat['reference_unique']),
        '{{locataires_info}}' => $locatairesInfoHtml,
        '{{adresse}}' => htmlspecialchars($contrat['adresse']),
        '{{appartement}}' => htmlspecialchars($contrat['appartement']),
        '{{type}}' => htmlspecialchars($contrat['type']),
        '{{surface}}' => htmlspecialchars($contrat['surface']),
        '{{parking}}' => htmlspecialchars($contrat['parking']),
        '{{date_prise_effet}}' => $datePriseEffet,
        '{{date_signature}}' => $dateSignature,
        '{{loyer}}' => $loyer,
        '{{charges}}' => $charges,
        '{{loyer_total}}' => $loyerTotal,
        '{{depot_garantie}}' => $depotGarantie,
        '{{iban}}' => htmlspecialchars($iban),
        '{{bic}}' => htmlspecialchars($bic),
    ];
    
    // Remplacer toutes les variables
    return str_replace(array_keys($variables), array_values($variables), $template);
}

/**
 * Injecter les signatures dans le HTML
 */
function injectSignatures($html, $contrat, $locataires) {
    global $pdo;
    
    // Construire le tableau de signatures
    $signaturesTable = buildSignaturesTable($contrat, $locataires);
    
    // Remplacer la variable {{signatures_table}}
    $html = str_replace('{{signatures_table}}', $signaturesTable, $html);
    
    return $html;
}

/**
 * Construire le tableau de signatures
 */
function buildSignaturesTable($contrat, $locataires) {
    global $pdo;
    
    $nbLocataires = count($locataires);
    $baseDir = dirname(__DIR__);
    
    // Calculer la largeur des colonnes
    $nbCols = $nbLocataires + 1; // +1 pour la signature agence
    $colWidth = 100 / $nbCols;
    
    $html = '<table style="width: 100%; border-collapse: collapse; margin-top: 20px;">';
    $html .= '<tr>';
    
    // Colonne signature agence (bailleur)
    $html .= '<td style="width: ' . $colWidth . '%; vertical-align: top; padding: 10px;">';
    $html .= '<p><strong>Le bailleur :</strong></p>';
    
    // Signature agence si contrat validé
    if ($contrat['statut'] === 'valide') {
        $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'signature_societe_image'");
        $stmt->execute();
        $signatureSociete = $stmt->fetchColumn();
        
        if (!empty($signatureSociete)) {
            // Récupérer le chemin physique de la signature
            if (preg_match('/^uploads\/signatures\//', $signatureSociete)) {
                $fullPath = $baseDir . '/' . $signatureSociete;
                if (file_exists($fullPath)) {
                    $html .= '<img src="' . htmlspecialchars($fullPath) . '" alt="Signature Société" style="' . SIGNATURE_IMG_STYLE . '">';
                }
            }
        }
        
        // Afficher la date de validation et le nom de la société
        if (!empty($contrat['date_validation'])) {
            $timestamp = strtotime($contrat['date_validation']);
            if ($timestamp !== false) {
                $html .= 'Validé le : ' . date('d/m/Y H:i:s', $timestamp) . '<br>';
            }
        }
        $html .= 'MY INVEST IMMOBILIER';
    }
    
    $html .= '</td>';
    
    // Colonnes signatures locataires
    foreach ($locataires as $i => $locataire) {
        $html .= '<td style="width: ' . $colWidth . '%; vertical-align: top; padding: 10px;">';
        
        if ($nbLocataires === 1) {
            $html .= '<p><strong>Locataire :</strong></p>';
        } else {
            $html .= '<p><strong>Locataire ' . ($i + 1) . ' :</strong></p>';
        }
        
        $html .= '<p>' . htmlspecialchars($locataire['prenom']) . ' ' . htmlspecialchars($locataire['nom']) . '</p>';
        
        // Signature si disponible
        if (!empty($locataire['signature_data'])) {
            // Récupérer le chemin physique de la signature depuis /uploads/signatures/
            if (preg_match('/^uploads\/signatures\//', $locataire['signature_data'])) {
                $fullPath = $baseDir . '/' . $locataire['signature_data'];
                if (file_exists($fullPath)) {
                    $html .= '<img src="' . htmlspecialchars($fullPath) . '" alt="Signature Locataire ' . ($i + 1) . '" style="' . SIGNATURE_IMG_STYLE . '">';
                }
            }
        }
        
        // Horodatage et IP
        if (!empty($locataire['signature_timestamp']) || !empty($locataire['signature_ip'])) {
            $html .= '<div style="margin-top: 10px; font-size: 8pt; color: #666;">';
            if (!empty($locataire['signature_timestamp'])) {
                $timestamp = strtotime($locataire['signature_timestamp']);
                if ($timestamp !== false) {
                    $html .= 'Signé le ' . date('d/m/Y à H:i', $timestamp) . '<br>';
                }
            }
            if (!empty($locataire['signature_ip'])) {
                $html .= 'IP : ' . htmlspecialchars($locataire['signature_ip']);
            }
            $html .= '</div>';
        }
        
        $html .= '</td>';
    }
    
    $html .= '</tr>';
    $html .= '</table>';
    
    return $html;
}

/**
 * Template HTML par défaut
 */
function getDefaultContractTemplate() {
    return <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Contrat de Bail - {{reference_unique}}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.5;
            color: #000;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            text-align: center;
            font-size: 14pt;
            margin-bottom: 10px;
            font-weight: bold;
        }
        h2 {
            font-size: 11pt;
            margin-top: 20px;
            margin-bottom: 10px;
            font-weight: bold;
        }
        h3 {
            font-size: 10pt;
            margin-top: 15px;
            margin-bottom: 8px;
            font-weight: bold;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .subtitle {
            text-align: center;
            font-style: italic;
            margin-bottom: 30px;
        }
        p {
            margin: 8px 0;
            text-align: justify;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>MY INVEST IMMOBILIER</h1>
    </div>
    
    <div class="subtitle">
        CONTRAT DE BAIL<br>
        (Location meublée – résidence principale)
    </div>

    <h2>1. Parties</h2>
    
    <h3>Bailleur</h3>
    <p>My Invest Immobilier (SCI)<br>
    Représentée par : Maxime ALEXANDRE<br>
    Adresse électronique de notification : contact@myinvest-immobilier.com</p>
    
    <h3>Locataire(s)</h3>
    <p>{{locataires_info}}</p>

    <h2>2. Désignation du logement</h2>
    
    <p><strong>Adresse :</strong><br>
    {{adresse}}</p>
    
    <p><strong>Appartement :</strong> {{appartement}}<br>
    <strong>Type :</strong> {{type}} - Logement meublé<br>
    <strong>Surface habitable :</strong> ~ {{surface}} m²<br>
    <strong>Usage :</strong> Résidence principale<br>
    <strong>Parking :</strong> {{parking}}</p>

    <h2>3. Durée</h2>
    
    <p>Le présent contrat est conclu pour une durée de 1 an, à compter du : <strong>{{date_prise_effet}}</strong></p>
    
    <p>Il est renouvelable par tacite reconduction.</p>

    <h2>4. Conditions financières</h2>
    
    <p><strong>Loyer mensuel hors charges :</strong> {{loyer}} €<br>
    <strong>Provision sur charges mensuelles :</strong> {{charges}} €<br>
    <strong>Total mensuel :</strong> {{loyer_total}} €</p>
    
    <p><strong>Modalité de paiement :</strong> mensuel, payable d'avance, au plus tard le 5 de chaque mois.</p>

    <h2>5. Dépôt de garantie</h2>
    
    <p>Le dépôt de garantie, d'un montant de <strong>{{depot_garantie}} €</strong> (correspondant à deux mois de loyer hors charges), est versé à la signature du présent contrat.</p>

    <h2>6. Coordonnées bancaires</h2>
    
    <p><strong>IBAN :</strong> {{iban}}<br>
    <strong>BIC :</strong> {{bic}}<br>
    <strong>Titulaire :</strong> MY INVEST IMMOBILIER</p>

    <h2>7. Signatures</h2>
    
    <p>Fait à Annemasse, le {{date_signature}}</p>
    
    {{signatures_table}}

    <div class="footer" style="margin-top: 40px; font-size: 8pt; text-align: center; color: #666;">
        <p>Document généré électroniquement par MY Invest Immobilier</p>
        <p>Contrat de bail - Référence : {{reference_unique}}</p>
    </div>
</body>
</html>
HTML;
}
