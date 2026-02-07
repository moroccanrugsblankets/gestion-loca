<?php
/**
 * Génération du PDF du contrat de bail
 * Version finale : Template HTML + Variables + Signatures + PDF
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// Style CSS pour les images de signature (sans bordures)
define('SIGNATURE_IMG_STYLE', 'width: 25mm; height: auto; display: block; margin-bottom: 15mm; border: none; outline: none; box-shadow: none; background: transparent;');

/**
 * Générer le PDF du contrat de bail
 */
function generateContratPDF($contratId) {
    global $config, $pdo;

    $contratId = (int)$contratId;
    if ($contratId <= 0) {
        error_log("Erreur: ID de contrat invalide");
        return false;
    }

    try {
        // Récupérer les données du contrat
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

        // Récupérer les locataires
        $stmt = $pdo->prepare("SELECT * FROM locataires WHERE contrat_id = ? ORDER BY ordre ASC");
        $stmt->execute([$contratId]);
        $locataires = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($locataires)) {
            error_log("Erreur: Aucun locataire trouvé");
            return false;
        }

        // Récupérer la template HTML
        $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'contrat_template_html'");
        $stmt->execute();
        $templateHtml = $stmt->fetchColumn();

        if (empty($templateHtml)) {
            $templateHtml = getDefaultContractTemplate();
        }

        // Remplacer les variables
        $html = replaceContratTemplateVariables($templateHtml, $contrat, $locataires);

        // Injecter les signatures
        $html = injectSignatures($html, $contrat, $locataires);

        // Générer le PDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('MY INVEST IMMOBILIER');
        $pdf->SetTitle('Contrat de Bail - ' . $contrat['reference_unique']);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');

        // Sauvegarder le PDF
        $filename = 'bail-' . $contrat['reference_unique'] . '.pdf';
        $pdfDir = dirname(__DIR__) . '/pdf/contrats/';
        if (!is_dir($pdfDir)) mkdir($pdfDir, 0755, true);
        $filepath = $pdfDir . $filename;
        $pdf->Output($filepath, 'F');

        return $filepath;

    } catch (Exception $e) {
        error_log("Erreur génération PDF: " . $e->getMessage());
        return false;
    }
}

/**
 * Remplacer les variables dans la template
 */
function replaceContratTemplateVariables($template, $contrat, $locataires) {
    global $config;

    $locatairesInfo = [];
    foreach ($locataires as $loc) {
        $dateNaissance = 'N/A';
        if (!empty($loc['date_naissance'])) {
            $ts = strtotime($loc['date_naissance']);
            if ($ts !== false) $dateNaissance = date('d/m/Y', $ts);
        }
        $locatairesInfo[] = htmlspecialchars($loc['prenom']) . ' ' . htmlspecialchars($loc['nom']) .
            ', né(e) le ' . $dateNaissance . '<br>Email : ' . htmlspecialchars($loc['email']);
    }
    $locatairesInfoHtml = implode('<br>', $locatairesInfo);

    $datePriseEffet = !empty($contrat['date_prise_effet']) ? date('d/m/Y', strtotime($contrat['date_prise_effet'])) : 'N/A';
    $dateSignature = !empty($contrat['date_signature']) ? date('d/m/Y', strtotime($contrat['date_signature'])) : date('d/m/Y');

    $loyer = number_format((float)($contrat['loyer'] ?? 0), 2, ',', ' ');
    $charges = number_format((float)($contrat['charges'] ?? 0), 2, ',', ' ');
    $loyerTotal = number_format((float)($contrat['loyer'] ?? 0) + (float)($contrat['charges'] ?? 0), 2, ',', ' ');
    $depotGarantie = number_format((float)($contrat['depot_garantie'] ?? 0), 2, ',', ' ');

    $iban = $config['IBAN'] ?? '[IBAN non configuré]';
    $bic = $config['BIC'] ?? '[BIC non configuré]';

    $vars = [
        '{{reference_unique}}' => htmlspecialchars($contrat['reference_unique'] ?? ''),
        '{{locataires_info}}' => $locatairesInfoHtml,
        '{{adresse}}' => htmlspecialchars($contrat['adresse'] ?? ''),
        '{{appartement}}' => htmlspecialchars($contrat['appartement'] ?? ''),
        '{{type}}' => htmlspecialchars($contrat['type'] ?? ''),
        '{{surface}}' => htmlspecialchars($contrat['surface'] ?? ''),
        '{{parking}}' => htmlspecialchars($contrat['parking'] ?? ''),
        '{{date_prise_effet}}' => $datePriseEffet,
        '{{date_signature}}' => $dateSignature,
        '{{loyer}}' => $loyer,
        '{{charges}}' => $charges,
        '{{loyer_total}}' => $loyerTotal,
        '{{depot_garantie}}' => $depotGarantie,
        '{{iban}}' => htmlspecialchars($iban),
        '{{bic}}' => htmlspecialchars($bic),
    ];

    return str_replace(array_keys($vars), array_values($vars), $template);
}

/**
 * Injecter les signatures
 */
function injectSignatures($html, $contrat, $locataires) {
    $signaturesTable = buildSignaturesTable($contrat, $locataires);
    return str_replace('{{signatures_table}}', $signaturesTable, $html);
}

/**
 * Construire le tableau de signatures
 */
function buildSignaturesTable($contrat, $locataires) {
    global $pdo, $config; // <-- ajout de $config

    $nbCols = count($locataires) + 1; // +1 pour le bailleur
    $colWidth = 100 / $nbCols;

    $html = '<table cellspacing="0" cellpadding="10" border="0" style="width: 100%; border: none; margin-top: 20px;"><tbody><tr>';

    // Bailleur
    $html .= '<td style="width:' . $colWidth . '%; vertical-align: top; text-align:center; padding:10px; border: none;">';
    $html .= '<p><strong>Le bailleur :</strong></p>';
    if ($contrat['statut'] === 'valide') {
        $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'signature_societe_image'");
        $stmt->execute();
        $signatureSociete = $stmt->fetchColumn();

        if (!empty($signatureSociete) && preg_match('/^uploads\/signatures\//', $signatureSociete)) {
            $publicUrl = rtrim($config['SITE_URL'], '/') . '/' . ltrim($signatureSociete, '/');
$html .= '<img src="' . htmlspecialchars($publicUrl) . '" alt="Signature Société" style="max-width: 150px; border: none; border-width: 0; border-style: none; border-color: transparent; outline: none; outline-width: 0; padding: 0; background: transparent;">';
        }

        if (!empty($contrat['date_validation'])) {
            $ts = strtotime($contrat['date_validation']);
            if ($ts !== false) {
                $html .= '<div style="margin-top:5px; font-size:8pt; color:#666;"><br>&nbsp;<br>&nbsp;<br>Validé le : ' . date('d/m/Y H:i:s', $ts) . '</div>';
            }
        }
        $html .= '<div style="margin-top:5px; font-size:8pt; color:#666;">MY INVEST IMMOBILIER</div>';
    }
    $html .= '</td>';

    // Locataires
    foreach ($locataires as $i => $loc) {
        $html .= '<td style="width:' . $colWidth . '%; vertical-align: top; text-align:center; padding:10px; border: none;">';

        if ($nbCols === 2) {
            $html .= '<p><strong>Locataire :</strong></p>';
        } else {
            $html .= '<p><strong>Locataire ' . ($i + 1) . ' :</strong></p>';
        }

        $html .= '<p>' . htmlspecialchars($loc['prenom']) . ' ' . htmlspecialchars($loc['nom']) . '</p>';

        if (!empty($loc['signature_data']) && preg_match('/^uploads\/signatures\//', $loc['signature_data'])) {
            $publicUrl = rtrim($config['SITE_URL'], '/') . '/' . ltrim($loc['signature_data'], '/');
			$html .= '<img src="' . htmlspecialchars($publicUrl) . '" alt="Signature Locataire" style="max-width: 150px; border: none; border-width: 0; border-style: none; border-color: transparent; outline: none; outline-width: 0; padding: 0; background: transparent;">';
        }
        
        // Add "Certifié exact" checkbox indicator - always show for clarity
        $checkboxSymbol = (!empty($loc['certifie_exact'])) ? '☑' : '☐';
        $html .= '<p style="margin-top:5px; font-size:9pt;"><strong>' . $checkboxSymbol . ' Certifié exact</strong></p>';

        if (!empty($loc['signature_timestamp']) || !empty($loc['signature_ip'])) {
            $html .= '<div style="margin-top:10px; font-size:8pt; color:#666;">';
            if (!empty($loc['signature_timestamp'])) {
                $ts = strtotime($loc['signature_timestamp']);
                if ($ts !== false) {
                    $html .= '<br>&nbsp;<br>&nbsp;<br>Signé le ' . date('d/m/Y à H:i', $ts) . '<br>';
                }
            }
            if (!empty($loc['signature_ip'])) {
                $html .= 'IP : ' . htmlspecialchars($loc['signature_ip']);
            }
            $html .= '</div>';
        }

        $html .= '</td>';
    }

    $html .= '</tr></tbody></table>';
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
        body { font-family: Arial, sans-serif; font-size: 10pt; line-height: 1.5; color: #000; max-width: 800px; margin: 0 auto; padding: 20px; }
        h1 { text-align: center; font-size: 14pt; margin-bottom: 10px; font-weight: bold; }
        h2 { font-size: 11pt; margin-top: 20px; margin-bottom: 10px; font-weight: bold; }
        h3 { font-size: 10pt; margin-top: 15px; margin-bottom: 8px; font-weight: bold; }
        .header { text-align: center; margin-bottom: 20px; }
        .subtitle { text-align: center; font-style: italic; margin-bottom: 30px; }
        p { margin: 8px 0; text-align: justify; }
    </style>
</head>
<body>
    <div class="header"><h1>MY INVEST IMMOBILIER</h1></div>
    <div class="subtitle">CONTRAT DE BAIL<br>(Location meublée – résidence principale)</div>

    <h2>1. Parties</h2>
    <h3>Bailleur</h3>
    <p>My Invest Immobilier (SCI)<br>Représentée par : Maxime ALEXANDRE<br>Email : contact@myinvest-immobilier.com</p>

    <h3>Locataire(s)</h3>
    <p>{{locataires_info}}</p>

    <h2>2. Désignation du logement</h2>
    <p><strong>Adresse :</strong><br>{{adresse}}</p>
    <p><strong>Appartement :</strong> {{appartement}}<br>
       <strong>Type :</strong> {{type}} - Logement meublé<br>
       <strong>Surface :</strong> ~ {{surface}} m²<br>
       <strong>Usage :</strong> Résidence principale<br>
       <strong>Parking :</strong> {{parking}}</p>

    <h2>3. Durée</h2>
    <p>Contrat conclu pour 1 an à compter du : <strong>{{date_prise_effet}}</strong></p>
    <p>Renouvelable par tacite reconduction.</p>

    <h2>4. Conditions financières</h2>
    <p><strong>Loyer hors charges :</strong> {{loyer}} €<br>
       <strong>Charges :</strong> {{charges}} €<br>
       <strong>Total :</strong> {{loyer_total}} €</p>

    <h2>5. Dépôt de garantie</h2>
    <p>Montant : <strong>{{depot_garantie}} €</strong></p>

    <h2>6. Coordonnées bancaires</h2>
    <p><strong>IBAN :</strong> {{iban}}<br><strong>BIC :</strong> {{bic}}<br><strong>Titulaire :</strong> MY INVEST IMMOBILIER</p>

    <h2>7. Signatures</h2>
    <p>Fait à Annemasse, le {{date_signature}}</p>
    {{signatures_table}}

    <div class="footer" style="margin-top:40px; font-size:8pt; text-align:center; color:#666;">
        <p>Document généré électroniquement par MY Invest Immobilier</p>
        <p>Contrat de bail - Référence : {{reference_unique}}</p>
    </div>
</body>
</html>
HTML;
}