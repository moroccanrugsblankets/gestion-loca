<?php
/**
 * Génération du PDF du bail signé
 * My Invest Immobilier
 * 
 * Note: Cette implémentation utilise FPDF. Pour une version production, 
 * il est recommandé d'utiliser TCPDF ou mPDF pour plus de fonctionnalités.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

/**
 * Générer le PDF du bail
 * @param int $contratId
 * @return string|false Chemin du fichier PDF généré
 */
function generateBailPDF($contratId) {
    // Récupérer les données du contrat
    $contrat = fetchOne("SELECT c.*, l.* FROM contrats c INNER JOIN logements l ON c.logement_id = l.id WHERE c.id = ?", [$contratId]);
    
    if (!$contrat) {
        return false;
    }
    
    $locataires = getTenantsByContract($contratId);
    
    if (empty($locataires)) {
        return false;
    }
    
    // Créer le contenu HTML du PDF
    $html = generateBailHTML($contrat, $locataires);
    
    // Nom du fichier PDF
    $filename = 'bail_' . $contrat['reference'] . '_' . date('Ymd_His') . '.pdf';
    $filepath = PDF_DIR . $filename;
    
    // Créer le dossier PDF s'il n'existe pas
    if (!is_dir(PDF_DIR)) {
        mkdir(PDF_DIR, 0755, true);
    }
    
    // Pour une implémentation simple, on sauvegarde en HTML
    // En production, utiliser TCPDF, mPDF ou wkhtmltopdf
    $htmlFilepath = PDF_DIR . 'bail_' . $contrat['reference'] . '_' . date('Ymd_His') . '.html';
    file_put_contents($htmlFilepath, $html);
    
    // Si wkhtmltopdf est installé, on peut générer le PDF
    if (commandExists('wkhtmltopdf')) {
        $command = "wkhtmltopdf --page-size A4 --margin-top 20mm --margin-bottom 20mm " . 
                   escapeshellarg($htmlFilepath) . " " . escapeshellarg($filepath);
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($filepath)) {
            unlink($htmlFilepath); // Supprimer le fichier HTML temporaire
            return $filepath;
        }
    }
    
    // Retourner le fichier HTML comme fallback
    return $htmlFilepath;
}

/**
 * Vérifier si une commande existe
 */
function commandExists($command) {
    $return = shell_exec(sprintf("which %s", escapeshellarg($command)));
    return !empty($return);
}

/**
 * Générer le contenu HTML du bail
 */
function generateBailHTML($contrat, $locataires) {
    $dateCreation = formatDateFr($contrat['date_signature'] ?? $contrat['date_creation'], 'd/m/Y');
    
    $locatairesParts = [];
    foreach ($locataires as $i => $locataire) {
        $locatairesParts[] = htmlspecialchars($locataire['prenom']) . ' ' . htmlspecialchars($locataire['nom']) . 
                            ', né(e) le ' . formatDateFr($locataire['date_naissance']);
    }
    $locatairesText = implode(' et ', $locatairesParts);
    
    $loyer = formatMontant($contrat['loyer']);
    $charges = formatMontant($contrat['charges']);
    $depotGarantie = formatMontant($contrat['depot_garantie']);
    $loyerTotal = formatMontant($contrat['loyer'] + $contrat['charges']);
    
    $html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Contrat de Bail - ' . htmlspecialchars($contrat['reference']) . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #000;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            text-align: center;
            font-size: 18pt;
            margin-bottom: 30px;
            color: #0d6efd;
        }
        h2 {
            font-size: 14pt;
            margin-top: 25px;
            margin-bottom: 15px;
            color: #0d6efd;
        }
        h3 {
            font-size: 12pt;
            margin-top: 20px;
            margin-bottom: 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        .info-block {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border-left: 4px solid #0d6efd;
        }
        .signature-block {
            margin-top: 50px;
            page-break-inside: avoid;
        }
        .signature-item {
            margin-bottom: 30px;
            border: 1px solid #dee2e6;
            padding: 15px;
            background-color: #f8f9fa;
        }
        .signature-image {
            max-width: 300px;
            border: 1px solid #ccc;
            padding: 5px;
            background-color: white;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table td {
            padding: 8px;
            border: 1px solid #dee2e6;
        }
        .footer {
            margin-top: 50px;
            font-size: 9pt;
            color: #6c757d;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>CONTRAT DE BAIL D\'HABITATION</h1>
        <p><strong>MY Invest Immobilier</strong></p>
        <p>Date: ' . $dateCreation . '</p>
    </div>

    <h2>1. LES PARTIES</h2>
    <div class="info-block">
        <p><strong>BAILLEUR :</strong> MY Invest Immobilier</p>
        <p><strong>Email :</strong> ' . COMPANY_EMAIL . '</p>
    </div>
    
    <div class="info-block">
        <p><strong>LOCATAIRE(S) :</strong></p>
        <p>' . $locatairesText . '</p>';
    
    foreach ($locataires as $locataire) {
        $html .= '<p>Email : ' . htmlspecialchars($locataire['email']) . '</p>';
    }
    
    $html .= '</div>

    <h2>2. DÉSIGNATION DU LOGEMENT</h2>
    <div class="info-block">
        <p><strong>Référence :</strong> ' . htmlspecialchars($contrat['reference']) . '</p>
        <p><strong>Adresse :</strong> ' . htmlspecialchars($contrat['adresse']) . '</p>
        <p><strong>Appartement :</strong> ' . htmlspecialchars($contrat['appartement']) . '</p>
        <p><strong>Type :</strong> ' . htmlspecialchars($contrat['type']) . '</p>
        <p><strong>Surface :</strong> ' . htmlspecialchars($contrat['surface']) . ' m²</p>
        <p><strong>Parking :</strong> ' . htmlspecialchars($contrat['parking']) . '</p>
    </div>

    <h2>3. DURÉE DU BAIL</h2>
    <p>Le présent bail est consenti pour une durée de trois (3) ans à compter de la date de prise d\'effet.</p>

    <h2>4. CONDITIONS FINANCIÈRES</h2>
    <table>
        <tr>
            <td><strong>Loyer mensuel hors charges</strong></td>
            <td style="text-align: right;">' . $loyer . '</td>
        </tr>
        <tr>
            <td><strong>Charges mensuelles</strong></td>
            <td style="text-align: right;">' . $charges . '</td>
        </tr>
        <tr>
            <td><strong>Loyer total charges comprises</strong></td>
            <td style="text-align: right;"><strong>' . $loyerTotal . '</strong></td>
        </tr>
        <tr>
            <td><strong>Dépôt de garantie</strong></td>
            <td style="text-align: right;">' . $depotGarantie . '</td>
        </tr>
    </table>

    <h2>5. PAIEMENT DU LOYER</h2>
    <p>Le loyer est payable mensuellement et d\'avance, avant le 5 de chaque mois, par virement bancaire sur le compte suivant :</p>
    <div class="info-block">
        <p><strong>' . BANK_NAME . '</strong></p>
        <p>IBAN : ' . IBAN . '</p>
        <p>BIC : ' . BIC . '</p>
    </div>

    <h2>6. DÉPÔT DE GARANTIE</h2>
    <p>Le dépôt de garantie d\'un montant de ' . $depotGarantie . ' doit être versé avant la prise d\'effet du bail et la remise des clés.</p>

    <h2>7. OBLIGATIONS DU LOCATAIRE</h2>
    <ul>
        <li>Payer le loyer et les charges aux termes convenus</li>
        <li>Utiliser paisiblement le logement</li>
        <li>Entretenir le logement et effectuer les réparations locatives</li>
        <li>Souscrire une assurance habitation</li>
        <li>Ne pas transformer le logement sans accord écrit du bailleur</li>
    </ul>

    <h2>8. OBLIGATIONS DU BAILLEUR</h2>
    <ul>
        <li>Délivrer au locataire un logement décent</li>
        <li>Assurer la jouissance paisible du logement</li>
        <li>Entretenir le logement en bon état d\'usage</li>
        <li>Effectuer les réparations nécessaires autres que locatives</li>
    </ul>

    <h2>9. RÉSILIATION</h2>
    <p>Le locataire peut résilier le bail à tout moment en respectant un préavis de trois (3) mois, ramené à un (1) mois dans certains cas prévus par la loi.</p>

    <h2>10. SIGNATURES ÉLECTRONIQUES</h2>
    <p>Les parties ont apposé leur signature électronique pour valider le présent contrat :</p>
    
    <div class="signature-block">';
    
    foreach ($locataires as $i => $locataire) {
        $html .= '
        <div class="signature-item">
            <h3>Locataire ' . ($i + 1) . ' : ' . htmlspecialchars($locataire['prenom']) . ' ' . htmlspecialchars($locataire['nom']) . '</h3>
            <p><strong>Date et heure de signature :</strong> ' . formatDateFr($locataire['signature_timestamp'], 'd/m/Y à H:i:s') . '</p>
            <p><strong>Adresse IP :</strong> ' . htmlspecialchars($locataire['signature_ip']) . '</p>
            <p><strong>Mention :</strong> ' . htmlspecialchars($locataire['mention_lu_approuve']) . '</p>';
        
        if ($locataire['signature_data']) {
            $html .= '<p><strong>Signature :</strong></p>
            <img src="' . htmlspecialchars($locataire['signature_data']) . '" alt="Signature" class="signature-image">';
        }
        
        $html .= '</div>';
    }
    
    $html .= '
    </div>

    <div class="footer">
        <p>Document généré électroniquement par MY Invest Immobilier</p>
        <p>Contrat de bail - Référence : ' . htmlspecialchars($contrat['reference']) . '</p>
        <p>' . COMPANY_EMAIL . '</p>
    </div>
</body>
</html>';
    
    return $html;
}
