<?php
/**
 * Génération du PDF pour État des lieux d'entrée/sortie
 * My Invest Immobilier
 * 
 * Génère un document PDF structuré pour l'état des lieux d'entrée ou de sortie
 * avec toutes les sections obligatoires et signatures.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mail-templates.php';

// Import the default template function
require_once __DIR__ . '/../includes/etat-lieux-template.php';

// Signature image display size constants (for PDF rendering)
define('ETAT_LIEUX_SIGNATURE_MAX_WIDTH', '30mm');
define('ETAT_LIEUX_SIGNATURE_MAX_HEIGHT', '15mm');

// Style CSS pour les images de signature (sans bordures) - identique aux contrats
define('ETAT_LIEUX_SIGNATURE_IMG_STYLE', 'max-width: 30mm; max-height: 15mm; display: block; border: 0; border-width: 0; border-style: none; border-color: transparent; outline: none; outline-width: 0; box-shadow: none; background: transparent; padding: 0; margin: 0 auto;');

/**
 * Générer le PDF de l'état des lieux
 * 
 * @param int $contratId ID du contrat
 * @param string $type Type d'état des lieux: 'entree' ou 'sortie'
 * @return string|false Chemin du fichier PDF généré, ou false en cas d'erreur
 */
function generateEtatDesLieuxPDF($contratId, $type = 'entree') {
    global $config, $pdo;

    error_log("=== generateEtatDesLieuxPDF - START ===");
    error_log("Input - Contrat ID: $contratId, Type: $type");

    // Validation
    $contratId = (int)$contratId;
    if ($contratId <= 0) {
        error_log("ERROR: ID de contrat invalide: $contratId");
        return false;
    }

    if (!in_array($type, ['entree', 'sortie'])) {
        error_log("ERROR: Type invalide: $type (doit être 'entree' ou 'sortie')");
        return false;
    }

    try {
        // Récupérer les données du contrat
        error_log("Fetching contrat data from database...");
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   l.reference,
                   l.adresse,
                   l.appartement,
                   l.type as type_logement,
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
            error_log("ERROR: Contrat #$contratId non trouvé");
            return false;
        }
        
        error_log("Contrat found - Reference: " . ($contrat['reference'] ?? 'NULL'));
        error_log("Logement - Adresse: " . ($contrat['adresse'] ?? 'NULL'));

        // Récupérer les locataires
        error_log("Fetching locataires...");
        $stmt = $pdo->prepare("SELECT * FROM locataires WHERE contrat_id = ? ORDER BY ordre ASC");
        $stmt->execute([$contratId]);
        $locataires = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($locataires)) {
            error_log("ERROR: Aucun locataire trouvé pour contrat #$contratId");
            return false;
        }
        
        error_log("Found " . count($locataires) . " locataire(s)");

        // Vérifier si un état des lieux existe déjà
        error_log("Checking for existing état des lieux...");
        $stmt = $pdo->prepare("SELECT * FROM etats_lieux WHERE contrat_id = ? AND type = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$contratId, $type]);
        $etatLieux = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si pas d'état des lieux, créer un brouillon avec données par défaut
        if (!$etatLieux) {
            error_log("No existing état des lieux found, creating default...");
            $etatLieux = createDefaultEtatLieux($contratId, $type, $contrat, $locataires);
            if (!$etatLieux) {
                error_log("ERROR: Failed to create default état des lieux");
                return false;
            }
        } else {
            error_log("Existing état des lieux found - ID: " . $etatLieux['id']);
        }

        // Récupérer le template HTML depuis la base de données
        error_log("Fetching HTML template from database...");
        $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'etat_lieux_template_html'");
        $stmt->execute();
        $templateHtml = $stmt->fetchColumn();
        
        // Si pas de template en base, utiliser le template par défaut
        if (empty($templateHtml)) {
            error_log("No template found in database, using default template");
            if (function_exists('getDefaultEtatLieuxTemplate')) {
                $templateHtml = getDefaultEtatLieuxTemplate();
            } else {
                error_log("ERROR: getDefaultEtatLieuxTemplate function not found");
                return false;
            }
        } else {
            error_log("Template loaded from database - Length: " . strlen($templateHtml) . " characters");
        }
        
        // Générer le HTML en remplaçant les variables
        error_log("Replacing template variables...");
        $html = replaceEtatLieuxTemplateVariables($templateHtml, $contrat, $locataires, $etatLieux, $type);
        
        if (!$html) {
            error_log("ERROR: HTML generation failed");
            return false;
        }
        
        error_log("HTML generated - Length: " . strlen($html) . " characters");

        // Créer le PDF avec TCPDF
        error_log("Creating TCPDF instance...");
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('MY INVEST IMMOBILIER');
        
        $typeLabel = ($type === 'entree') ? 'Entrée' : 'Sortie';
        $pdf->SetTitle("État des lieux $typeLabel - " . $contrat['reference']);
        
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        
        // Write HTML to PDF with error handling
        error_log("Writing HTML to PDF...");
        try {
            $pdf->writeHTML($html, true, false, true, false, '');
            error_log("HTML written to PDF successfully");
        } catch (Exception $htmlException) {
            error_log("TCPDF writeHTML ERROR: " . $htmlException->getMessage());
            error_log("HTML content length: " . strlen($html));
            error_log("Stack trace: " . $htmlException->getTraceAsString());
            throw new Exception("Erreur lors de la conversion HTML vers PDF: " . $htmlException->getMessage());
        }

        // Sauvegarder le PDF
        error_log("Saving PDF to file...");
        $pdfDir = dirname(__DIR__) . '/pdf/etat_des_lieux/';
        if (!is_dir($pdfDir)) {
            error_log("Creating directory: $pdfDir");
            mkdir($pdfDir, 0755, true);
        }

        $dateStr = date('Ymd');
        $filename = "etat_lieux_{$type}_{$contrat['reference']}_{$dateStr}.pdf";
        $filepath = $pdfDir . $filename;
        
        error_log("Saving to: $filepath");
        $pdf->Output($filepath, 'F');
        
        if (!file_exists($filepath)) {
            error_log("ERROR: PDF file not created at: $filepath");
            return false;
        }
        
        error_log("PDF file created successfully - Size: " . filesize($filepath) . " bytes");

        // Mettre à jour le statut de l'état des lieux
        if ($etatLieux && isset($etatLieux['id'])) {
            error_log("Updating etat_lieux status to 'finalise'...");
            $stmt = $pdo->prepare("UPDATE etats_lieux SET statut = 'finalise' WHERE id = ?");
            $stmt->execute([$etatLieux['id']]);
        }

        error_log("=== generateEtatDesLieuxPDF - SUCCESS ===");
        error_log("PDF Generated: $filepath");
        return $filepath;

    } catch (Exception $e) {
        error_log("=== generateEtatDesLieuxPDF - ERROR ===");
        error_log("Exception type: " . get_class($e));
        error_log("Error message: " . $e->getMessage());
        error_log("Error file: " . $e->getFile() . ":" . $e->getLine());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

/**
 * Créer un état des lieux par défaut avec données de base
 */
function createDefaultEtatLieux($contratId, $type, $contrat, $locataires) {
    global $pdo, $config;

    error_log("=== createDefaultEtatLieux - START ===");
    error_log("Creating default état des lieux for contrat #$contratId, type: $type");

    try {
        $referenceUnique = 'EDL-' . strtoupper($type) . '-' . $contrat['reference'] . '-' . date('YmdHis');
        error_log("Generated reference: $referenceUnique");
        
        // Get first locataire for email
        if (empty($locataires)) {
            error_log("ERROR: No locataires provided to createDefaultEtatLieux");
            throw new Exception("Aucun locataire fourni pour créer l'état des lieux");
        }
        
        $firstLocataire = $locataires[0];
        $locataireEmail = $firstLocataire['email'] ?? '';
        $locataireNomComplet = trim(($firstLocataire['prenom'] ?? '') . ' ' . ($firstLocataire['nom'] ?? ''));
        
        error_log("First locataire: $locataireNomComplet ($locataireEmail)");
        
        $stmt = $pdo->prepare("
            INSERT INTO etats_lieux (
                contrat_id, 
                type, 
                reference_unique,
                date_etat,
                adresse,
                appartement,
                bailleur_nom,
                bailleur_representant,
                locataire_email,
                locataire_nom_complet,
                piece_principale,
                coin_cuisine,
                salle_eau_wc,
                etat_general,
                lieu_signature,
                statut
            ) VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'brouillon')
        ");
        
        $defaultTexts = getDefaultPropertyDescriptions($type);
        
        $params = [
            $contratId,
            $type,
            $referenceUnique,
            $contrat['adresse'],
            $contrat['appartement'] ?? '',
            $config['COMPANY_NAME'] ?? 'MY INVEST IMMOBILIER',
            $config['BAILLEUR_REPRESENTANT'] ?? '',
            $locataireEmail,
            $locataireNomComplet,
            $defaultTexts['piece_principale'],
            $defaultTexts['coin_cuisine'],
            $defaultTexts['salle_eau_wc'],
            $defaultTexts['etat_general'],
            '' // lieu_signature
        ];
        
        error_log("Executing INSERT with " . count($params) . " parameters");
        $stmt->execute($params);
        
        $etatLieuxId = $pdo->lastInsertId();
        error_log("État des lieux created with ID: $etatLieuxId");
        
        // Ajouter les locataires
        error_log("Adding " . count($locataires) . " locataire(s)...");
        foreach ($locataires as $i => $loc) {
            $stmt = $pdo->prepare("
                INSERT INTO etat_lieux_locataires (
                    etat_lieux_id,
                    locataire_id,
                    ordre,
                    nom,
                    prenom,
                    email,
                    signature_data,
                    signature_timestamp,
                    signature_ip
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $etatLieuxId,
                $loc['id'],
                $i + 1,
                $loc['nom'],
                $loc['prenom'],
                $loc['email'],
                $loc['signature_data'] ?? null,
                $loc['signature_timestamp'] ?? null,
                $loc['signature_ip'] ?? null
            ]);
            error_log("Added locataire " . ($i+1) . ": " . $loc['prenom'] . ' ' . $loc['nom']);
        }
    
        // Récupérer l'état des lieux créé
        error_log("Fetching created état des lieux...");
        $stmt = $pdo->prepare("SELECT * FROM etats_lieux WHERE id = ?");
        $stmt->execute([$etatLieuxId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("=== createDefaultEtatLieux - SUCCESS ===");
        return $result;
        
    } catch (Exception $e) {
        error_log("=== createDefaultEtatLieux - ERROR ===");
        error_log("Exception type: " . get_class($e));
        error_log("Error message: " . $e->getMessage());
        error_log("Error file: " . $e->getFile() . ":" . $e->getLine());
        error_log("Stack trace: " . $e->getTraceAsString());
        throw $e;
    }
}

/**
 * Remplacer les variables dans le template HTML de l'état des lieux
 * 
 * @param string $template Template HTML avec variables
 * @param array $contrat Données du contrat
 * @param array $locataires Liste des locataires
 * @param array $etatLieux Données de l'état des lieux
 * @param string $type Type: 'entree' ou 'sortie'
 * @return string HTML avec variables remplacées
 */
function replaceEtatLieuxTemplateVariables($template, $contrat, $locataires, $etatLieux, $type) {
    global $config;
    
    // Type label
    $typeLabel = ($type === 'entree') ? "D'ENTRÉE" : "DE SORTIE";
    
    // Dates
    $dateEtat = !empty($etatLieux['date_etat']) ? date('d/m/Y', strtotime($etatLieux['date_etat'])) : date('d/m/Y');
    $dateSignature = !empty($etatLieux['date_signature']) ? date('d/m/Y', strtotime($etatLieux['date_signature'])) : $dateEtat;
    
    // Reference
    $reference = htmlspecialchars($etatLieux['reference_unique'] ?? $contrat['reference'] ?? 'N/A');
    
    // Adresse
    $adresse = htmlspecialchars($etatLieux['adresse'] ?? $contrat['adresse'] ?? '');
    $appartement = htmlspecialchars($etatLieux['appartement'] ?? $contrat['appartement'] ?? '');
    $typeLogement = htmlspecialchars($contrat['type_logement'] ?? $contrat['type'] ?? '');
    $surface = htmlspecialchars($contrat['surface'] ?? '');
    
    // Bailleur
    $bailleurNom = htmlspecialchars($etatLieux['bailleur_nom'] ?? $config['COMPANY_NAME'] ?? 'MY INVEST IMMOBILIER');
    $bailleurRepresentant = htmlspecialchars($etatLieux['bailleur_representant'] ?? $config['BAILLEUR_REPRESENTANT'] ?? '');
    
    // Locataires - build table rows
    $locatairesInfo = '';
    foreach ($locataires as $i => $loc) {
        $locatairesInfo .= '<tr>';
        $locatairesInfo .= '<td class="info-label">Locataire' . (count($locataires) > 1 ? ' ' . ($i + 1) : '') . ' :</td>';
        $locatairesInfo .= '<td>' . htmlspecialchars($loc['prenom']) . ' ' . htmlspecialchars($loc['nom']);
        if (!empty($loc['email'])) {
            $locatairesInfo .= '<br>Email : ' . htmlspecialchars($loc['email']);
        }
        $locatairesInfo .= '</td>';
        $locatairesInfo .= '</tr>';
    }
    
    // Description - use defaults if empty
    $defaultTexts = getDefaultPropertyDescriptions($type);
    $piecePrincipale = getValueOrDefault($etatLieux, 'piece_principale', $defaultTexts['piece_principale']);
    $coinCuisine = getValueOrDefault($etatLieux, 'coin_cuisine', $defaultTexts['coin_cuisine']);
    $salleEauWC = getValueOrDefault($etatLieux, 'salle_eau_wc', $defaultTexts['salle_eau_wc']);
    $etatGeneral = getValueOrDefault($etatLieux, 'etat_general', $defaultTexts['etat_general']);
    
    // Replace <br> tags with newlines before escaping HTML
    $piecePrincipale = str_ireplace(['<br>', '<br/>', '<br />'], "\n", $piecePrincipale);
    $coinCuisine = str_ireplace(['<br>', '<br/>', '<br />'], "\n", $coinCuisine);
    $salleEauWC = str_ireplace(['<br>', '<br/>', '<br />'], "\n", $salleEauWC);
    $etatGeneral = str_ireplace(['<br>', '<br/>', '<br />'], "\n", $etatGeneral);
    
    // Escape HTML for descriptions (preserve newlines)
    $piecePrincipale = nl2br(htmlspecialchars($piecePrincipale));
    $coinCuisine = nl2br(htmlspecialchars($coinCuisine));
    $salleEauWC = nl2br(htmlspecialchars($salleEauWC));
    $etatGeneral = nl2br(htmlspecialchars($etatGeneral));
    
    // Observations - trim, replace <br> with newlines, escape and convert to <br> for HTML
    $observations = trim($etatLieux['observations'] ?? '');
    $observations = str_ireplace(['<br>', '<br/>', '<br />'], "\n", $observations);
    $observationsEscaped = nl2br(htmlspecialchars($observations));
    
    // Lieu de signature
    $lieuSignature = htmlspecialchars(!empty($etatLieux['lieu_signature']) ? $etatLieux['lieu_signature'] : ($config['DEFAULT_SIGNATURE_LOCATION'] ?? 'Annemasse'));
    
    // Build signatures table
    $signaturesTable = buildSignaturesTableEtatLieux($contrat, $locataires, $etatLieux);
    
    // Company name for signature section
    $companyName = htmlspecialchars($config['COMPANY_NAME'] ?? 'MY INVEST IMMOBILIER');
    
    // Prepare variable replacements
    $vars = [
        '{{reference}}' => $reference,
        '{{type}}' => strtolower($type),
        '{{type_label}}' => $typeLabel,
        '{{date_etat}}' => $dateEtat,
        '{{adresse}}' => $adresse,
        '{{appartement}}' => $appartement,
        '{{type_logement}}' => $typeLogement,
        '{{surface}}' => $surface,
        '{{bailleur_nom}}' => $bailleurNom,
        '{{bailleur_representant}}' => $bailleurRepresentant,
        '{{locataires_info}}' => $locatairesInfo,
        '{{piece_principale}}' => $piecePrincipale,
        '{{coin_cuisine}}' => $coinCuisine,
        '{{salle_eau_wc}}' => $salleEauWC,
        '{{etat_general}}' => $etatGeneral,
        '{{observations}}' => $observationsEscaped,
        '{{lieu_signature}}' => $lieuSignature,
        '{{date_signature}}' => $dateSignature,
        '{{signatures_table}}' => $signaturesTable,
        '{{signature_agence}}' => $companyName,
    ];
    
    // Handle conditional rows (use already-escaped variables)
    if (!empty($appartement)) {
        $vars['{{appartement_row}}'] = '<tr><td class="info-label">Appartement :</td><td>' . $appartement . '</td></tr>';
    } else {
        $vars['{{appartement_row}}'] = '';
    }
    
    if (!empty($bailleurRepresentant)) {
        $vars['{{bailleur_representant_row}}'] = '<tr><td class="info-label">Représenté par :</td><td>' . $bailleurRepresentant . '</td></tr>';
    } else {
        $vars['{{bailleur_representant_row}}'] = '';
    }
    
    if (!empty($observations)) {
        $vars['{{observations_section}}'] = '<h3>Observations complémentaires</h3><p class="observations">' . $observationsEscaped . '</p>';
    } else {
        $vars['{{observations_section}}'] = '';
    }
    
    // Replace all variables
    $html = str_replace(array_keys($vars), array_values($vars), $template);
    
    return $html;
}

/**
 * Obtenir les descriptions par défaut du logement
 */
function getDefaultPropertyDescriptions($type) {
    if ($type === 'entree') {
        return [
            'piece_principale' => "État général : Bon état. Murs et plafonds propres. Revêtement de sol en bon état. Fenêtres et volets fonctionnels.",
            'coin_cuisine' => "État général : Bon état. Équipements (évier, plaques, réfrigérateur) fonctionnels et propres. Placards en bon état.",
            'salle_eau_wc' => "État général : Bon état. Sanitaires (lavabo, douche/baignoire, WC) propres et fonctionnels. Carrelage en bon état.",
            'etat_general' => "Le logement est remis en bon état général, propre et conforme à l'usage d'habitation."
        ];
    } else {
        return [
            'piece_principale' => "État constaté à la sortie : [À compléter]",
            'coin_cuisine' => "État constaté à la sortie : [À compléter]",
            'salle_eau_wc' => "État constaté à la sortie : [À compléter]",
            'etat_general' => "État général du logement à la sortie : [À compléter]"
        ];
    }
}

/**
 * Helper to get field value or default if empty
 * Trims whitespace and returns default if empty
 */
function getValueOrDefault($etatLieux, $field, $default) {
    $value = trim($etatLieux[$field] ?? '');
    return nl2br(htmlspecialchars(empty($value) ? $default : $value));
}


/**
 * Générer le HTML pour l'état des lieux d'entrée
 * @deprecated Use replaceEtatLieuxTemplateVariables() instead
 */
function generateEntreeHTML($contrat, $locataires, $etatLieux) {
    global $config;
    
    $dateEtat = !empty($etatLieux['date_etat']) ? date('d/m/Y', strtotime($etatLieux['date_etat'])) : date('d/m/Y');
    $adresse = htmlspecialchars($etatLieux['adresse'] ?? $contrat['adresse']);
    $appartement = htmlspecialchars($etatLieux['appartement'] ?? $contrat['appartement'] ?? '');
    
    // Bailleur
    $bailleurNom = htmlspecialchars($etatLieux['bailleur_nom'] ?? $config['COMPANY_NAME']);
    $bailleurRepresentant = htmlspecialchars($etatLieux['bailleur_representant'] ?? $config['BAILLEUR_REPRESENTANT'] ?? '');
    
    // Locataires
    $locatairesHTML = '';
    foreach ($locataires as $i => $loc) {
        $locatairesHTML .= '<p>' . htmlspecialchars($loc['prenom']) . ' ' . htmlspecialchars($loc['nom']);
        if (!empty($loc['email'])) {
            $locatairesHTML .= '<br>Email : ' . htmlspecialchars($loc['email']);
        }
        $locatairesHTML .= '</p>';
    }
    
    // Compteurs
    $compteurElec = htmlspecialchars($etatLieux['compteur_electricite'] ?? '___________');
    $compteurEau = htmlspecialchars($etatLieux['compteur_eau_froide'] ?? '___________');
    
    // Clés
    $clesAppart = (int)($etatLieux['cles_appartement'] ?? 0);
    $clesBoite = (int)($etatLieux['cles_boite_lettres'] ?? 0);
    $clesAutre = (int)($etatLieux['cles_autre'] ?? 0);
    $clesTotal = (int)($etatLieux['cles_total'] ?? 0);
    if ($clesTotal === 0) $clesTotal = $clesAppart + $clesBoite + $clesAutre;
    
    // Description - use defaults if empty
    $defaultTexts = getDefaultPropertyDescriptions('entree');
    $piecePrincipale = getValueOrDefault($etatLieux, 'piece_principale', $defaultTexts['piece_principale']);
    $coinCuisine = getValueOrDefault($etatLieux, 'coin_cuisine', $defaultTexts['coin_cuisine']);
    $salleEauWC = getValueOrDefault($etatLieux, 'salle_eau_wc', $defaultTexts['salle_eau_wc']);
    $etatGeneral = getValueOrDefault($etatLieux, 'etat_general', $defaultTexts['etat_general']);
    
    // Replace <br> tags with newlines before processing
    $piecePrincipale = str_ireplace(['<br>', '<br/>', '<br />'], "\n", $piecePrincipale);
    $coinCuisine = str_ireplace(['<br>', '<br/>', '<br />'], "\n", $coinCuisine);
    $salleEauWC = str_ireplace(['<br>', '<br/>', '<br />'], "\n", $salleEauWC);
    $etatGeneral = str_ireplace(['<br>', '<br/>', '<br />'], "\n", $etatGeneral);
    
    // Observations complémentaires - replace <br> with newlines
    $observations = $etatLieux['observations'] ?? '';
    $observations = str_ireplace(['<br>', '<br/>', '<br />'], "\n", $observations);
    $observations = nl2br(htmlspecialchars($observations));
    
    // Signatures
    $signaturesHTML = buildSignaturesTableEtatLieux($contrat, $locataires, $etatLieux);
    
    $html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>État des lieux d'entrée</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            font-size: 10pt; 
            line-height: 1.5; 
            color: #000; 
        }
        h1 { 
            text-align: center; 
            font-size: 16pt; 
            margin-bottom: 20px; 
            font-weight: bold; 
            text-decoration: underline;
        }
        h2 { 
            font-size: 12pt; 
            margin-top: 0; 
            margin-bottom: 10px; 
            font-weight: bold; 
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
        }
        h3 { 
            font-size: 11pt; 
            margin-top: 15px; 
            margin-bottom: 8px; 
            font-weight: bold; 
        }
        p { 
            margin: 8px 0; 
            text-align: justify; 
        }
        .section { 
            margin-bottom: 20px; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 10px 0; 
        }
        table.data-table { 
            border: 1px solid #000; 
        }
        table.data-table th, 
        table.data-table td { 
            border: 1px solid #000; 
            padding: 8px; 
            text-align: left; 
        }
        table.data-table th { 
            background-color: #f0f0f0; 
            font-weight: bold; 
        }
        .signature-table { 
            margin-top: 30px; 
        }
        .signature-table td { 
            vertical-align: top; 
            text-align: center; 
            padding: 10px; 
        }
        .signature-box { 
            min-height: 80px; 
            margin-bottom: 5px; 
        }
        .text-field { 
            border-bottom: 1px dotted #333; 
            display: inline-block; 
            min-width: 200px; 
            padding: 2px 5px; 
        }
    </style>
</head>
<body>
    <h1>ÉTAT DES LIEUX D'ENTRÉE</h1>
    
    <div class="section">
        <h2>1. IDENTIFICATION</h2>
        <p><strong>Date de l'état des lieux :</strong> $dateEtat</p>
        <p><strong>Adresse du logement :</strong><br>$adresse
HTML;

    if ($appartement) {
        $html .= "<br>Appartement : $appartement";
    }
    
    $html .= <<<HTML
</p>
        <p><strong>Bailleur :</strong><br>$bailleurNom
HTML;

    if ($bailleurRepresentant) {
        $html .= "<br>Représenté par : $bailleurRepresentant";
    }
    
    $html .= <<<HTML
</p>
        <p><strong>Locataire(s) :</strong><br>$locatairesHTML</p>
    </div>
    
    <div class="section" style="margin-top: 10px;">
        <h2>2. RELEVÉ DES COMPTEURS</h2>
        <table class="data-table">
            <tr>
                <th>Type de compteur</th>
                <th>Index relevé</th>
            </tr>
            <tr>
                <td>Électricité</td>
                <td>$compteurElec</td>
            </tr>
            <tr>
                <td>Eau froide</td>
                <td>$compteurEau</td>
            </tr>
        </table>
    </div>
    
    <div class="section">
        <h2>3. REMISE DES CLÉS</h2>
        <table class="data-table">
            <tr>
                <th>Type de clé</th>
                <th>Nombre remis</th>
            </tr>
            <tr>
                <td>Clés de l'appartement</td>
                <td>$clesAppart</td>
            </tr>
            <tr>
                <td>Clés de la boîte aux lettres</td>
                <td>$clesBoite</td>
            </tr>
            <tr>
                <td>Autre</td>
                <td>$clesAutre</td>
            </tr>
            <tr>
                <td><strong>TOTAL</strong></td>
                <td><strong>$clesTotal</strong></td>
            </tr>
        </table>
    </div>
    
    <div class="section">
        <h2>4. DESCRIPTION DU LOGEMENT</h2>
        
        <h3>4.1 Pièce principale</h3>
        <p>$piecePrincipale</p>
        
        <h3>4.2 Coin cuisine</h3>
        <p>$coinCuisine</p>
        
        <h3>4.3 Salle d'eau / WC</h3>
        <p>$salleEauWC</p>
        
        <h3>4.4 État général</h3>
        <p>$etatGeneral</p>
    </div>
    
    <div class="section">
        <h2>5. SIGNATURES</h2>
        <p>Le présent état des lieux d'entrée a été établi contradictoirement entre les parties.</p>
HTML;

    if (!empty($observations)) {
        $html .= <<<HTML
        <p><strong>Observations complémentaires :</strong></p>
        <p>$observations</p>
HTML;
    }

    $html .= <<<HTML
        $signaturesHTML
    </div>
    
</body>
</html>
HTML;

    return $html;
}

/**
 * Générer le HTML pour l'état des lieux de sortie
 * @deprecated Use replaceEtatLieuxTemplateVariables() instead
 */
function generateSortieHTML($contrat, $locataires, $etatLieux) {
    global $config;
    
    $dateEtat = !empty($etatLieux['date_etat']) ? date('d/m/Y', strtotime($etatLieux['date_etat'])) : date('d/m/Y');
    $adresse = htmlspecialchars($etatLieux['adresse'] ?? $contrat['adresse']);
    $appartement = htmlspecialchars($etatLieux['appartement'] ?? $contrat['appartement'] ?? '');
    
    // Bailleur
    $bailleurNom = htmlspecialchars($etatLieux['bailleur_nom'] ?? $config['COMPANY_NAME']);
    $bailleurRepresentant = htmlspecialchars($etatLieux['bailleur_representant'] ?? $config['BAILLEUR_REPRESENTANT'] ?? '');
    
    // Locataires
    $locatairesHTML = '';
    foreach ($locataires as $i => $loc) {
        $locatairesHTML .= '<p>' . htmlspecialchars($loc['prenom']) . ' ' . htmlspecialchars($loc['nom']);
        if (!empty($loc['email'])) {
            $locatairesHTML .= '<br>Email : ' . htmlspecialchars($loc['email']);
        }
        $locatairesHTML .= '</p>';
    }
    
    // Compteurs
    $compteurElec = htmlspecialchars($etatLieux['compteur_electricite'] ?? '___________');
    $compteurEau = htmlspecialchars($etatLieux['compteur_eau_froide'] ?? '___________');
    
    // Clés
    $clesAppart = (int)($etatLieux['cles_appartement'] ?? 0);
    $clesBoite = (int)($etatLieux['cles_boite_lettres'] ?? 0);
    $clesAutre = (int)($etatLieux['cles_autre'] ?? 0);
    $clesTotal = (int)($etatLieux['cles_total'] ?? 0);
    if ($clesTotal === 0) $clesTotal = $clesAppart + $clesBoite + $clesAutre;
    
    $clesConformite = $etatLieux['cles_conformite'] ?? 'non_applicable';
    $conformiteLabels = [
        'conforme' => '☑ Conforme',
        'non_conforme' => '☑ Non conforme',
        'non_applicable' => '☐ Non applicable'
    ];
    $clesConformiteHTML = $conformiteLabels[$clesConformite] ?? '☐ Non vérifié';
    $clesObservations = htmlspecialchars($etatLieux['cles_observations'] ?? '');
    
    // Description - use defaults if empty
    $defaultTexts = getDefaultPropertyDescriptions('sortie');
    $piecePrincipale = getValueOrDefault($etatLieux, 'piece_principale', $defaultTexts['piece_principale']);
    $coinCuisine = getValueOrDefault($etatLieux, 'coin_cuisine', $defaultTexts['coin_cuisine']);
    $salleEauWC = getValueOrDefault($etatLieux, 'salle_eau_wc', $defaultTexts['salle_eau_wc']);
    $etatGeneral = getValueOrDefault($etatLieux, 'etat_general', $defaultTexts['etat_general']);
    
    // Replace <br> tags with newlines before processing
    $piecePrincipale = str_ireplace(['<br>', '<br/>', '<br />'], "\n", $piecePrincipale);
    $coinCuisine = str_ireplace(['<br>', '<br/>', '<br />'], "\n", $coinCuisine);
    $salleEauWC = str_ireplace(['<br>', '<br/>', '<br />'], "\n", $salleEauWC);
    $etatGeneral = str_ireplace(['<br>', '<br/>', '<br />'], "\n", $etatGeneral);
    
    // Observations complémentaires - replace <br> with newlines
    $observations = $etatLieux['observations'] ?? '';
    $observations = str_ireplace(['<br>', '<br/>', '<br />'], "\n", $observations);
    $observations = nl2br(htmlspecialchars($observations));
    
    // Conclusion - replace <br> with newlines
    $comparaisonEntree = $etatLieux['comparaison_entree'] ?? 'Comparaison avec l\'état des lieux d\'entrée : [À compléter]';
    $comparaisonEntree = str_ireplace(['<br>', '<br/>', '<br />'], "\n", $comparaisonEntree);
    $comparaisonEntree = nl2br(htmlspecialchars($comparaisonEntree));
    
    $depotStatus = $etatLieux['depot_garantie_status'] ?? 'non_applicable';
    $depotLabels = [
        'restitution_totale' => '☑ Aucune dégradation imputable au(x) locataire(s) - Restitution totale du dépôt de garantie',
        'restitution_partielle' => '☑ Dégradations mineures imputables au(x) locataire(s) - Restitution partielle du dépôt de garantie',
        'retenue_totale' => '☑ Dégradations importantes imputables au(x) locataire(s) - Retenue totale du dépôt de garantie',
        'non_applicable' => '☐ Non applicable'
    ];
    $depotHTML = '';
    foreach ($depotLabels as $key => $label) {
        if ($key === $depotStatus) {
            $depotHTML .= "<p>$label</p>";
        } else {
            // Extract the label text after the checkbox symbol and optional dash
            $labelText = $label;
            if (strpos($label, ' - ') !== false) {
                // Format: "☑ Text - More text" -> "More text"
                // Offset 3 accounts for ' - ' (space, dash, space)
                $labelText = substr($label, strpos($label, ' - ') + 3);
            } else {
                // Format: "☑ Text" -> "Text"
                // Offset 1 accounts for the space after the checkbox symbol
                $labelText = substr($label, strpos($label, ' ') + 1);
            }
            $depotHTML .= "<p>☐ " . $labelText . "</p>";
        }
    }
    
    if (!empty($etatLieux['depot_garantie_montant_retenu']) && $etatLieux['depot_garantie_montant_retenu'] > 0) {
        $montantRetenu = number_format((float)$etatLieux['depot_garantie_montant_retenu'], 2, ',', ' ');
        $depotHTML .= "<p><strong>Montant retenu :</strong> $montantRetenu €</p>";
    }
    
    if (!empty($etatLieux['depot_garantie_motif_retenue'])) {
        $motifRetenue = nl2br(htmlspecialchars($etatLieux['depot_garantie_motif_retenue']));
        $depotHTML .= "<p><strong>Justificatif / Motif de la retenue :</strong><br>$motifRetenue</p>";
    }
    
    // Signatures
    $signaturesHTML = buildSignaturesTableEtatLieux($contrat, $locataires, $etatLieux);
    
    $html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>État des lieux de sortie</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            font-size: 10pt; 
            line-height: 1.5; 
            color: #000; 
        }
        h1 { 
            text-align: center; 
            font-size: 16pt; 
            margin-bottom: 20px; 
            font-weight: bold; 
            text-decoration: underline;
        }
        h2 { 
            font-size: 12pt; 
            margin-top: 0; 
            margin-bottom: 10px; 
            font-weight: bold; 
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
        }
        h3 { 
            font-size: 11pt; 
            margin-top: 15px; 
            margin-bottom: 8px; 
            font-weight: bold; 
        }
        p { 
            margin: 8px 0; 
            text-align: justify; 
        }
        .section { 
            margin-bottom: 20px; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 10px 0; 
        }
        table.data-table { 
            border: 1px solid #000; 
        }
        table.data-table th, 
        table.data-table td { 
            border: 1px solid #000; 
            padding: 8px; 
            text-align: left; 
        }
        table.data-table th { 
            background-color: #f0f0f0; 
            font-weight: bold; 
        }
        .signature-table { 
            margin-top: 30px; 
        }
        .signature-table td { 
            vertical-align: top; 
            text-align: center; 
            padding: 10px; 
        }
        .signature-box { 
            min-height: 80px; 
            margin-bottom: 5px; 
        }
    </style>
</head>
<body>
    <h1>ÉTAT DES LIEUX DE SORTIE</h1>
    
    <div class="section">
        <h2>1. IDENTIFICATION</h2>
        <p><strong>Date de l'état des lieux :</strong> $dateEtat</p>
        <p><strong>Adresse du logement :</strong><br>$adresse
HTML;

    if ($appartement) {
        $html .= "<br>Appartement : $appartement";
    }
    
    $html .= <<<HTML
</p>
        <p><strong>Bailleur :</strong><br>$bailleurNom
HTML;

    if ($bailleurRepresentant) {
        $html .= "<br>Représenté par : $bailleurRepresentant";
    }
    
    $html .= <<<HTML
</p>
        <p><strong>Locataire(s) sortant(s) :</strong><br>$locatairesHTML</p>
    </div>
    
    <div class="section" style="margin-top: 10px;">
        <h2>2. RELEVÉ DES COMPTEURS À LA SORTIE</h2>
        <table class="data-table">
            <tr>
                <th>Type de compteur</th>
                <th>Index relevé</th>
            </tr>
            <tr>
                <td>Électricité</td>
                <td>$compteurElec</td>
            </tr>
            <tr>
                <td>Eau froide</td>
                <td>$compteurEau</td>
            </tr>
        </table>
    </div>
    
    <div class="section">
        <h2>3. RESTITUTION DES CLÉS</h2>
        <table class="data-table">
            <tr>
                <th>Type de clé</th>
                <th>Nombre restitué</th>
            </tr>
            <tr>
                <td>Clés de l'appartement</td>
                <td>$clesAppart</td>
            </tr>
            <tr>
                <td>Clés de la boîte aux lettres</td>
                <td>$clesBoite</td>
            </tr>
            <tr>
                <td>Autre</td>
                <td>$clesAutre</td>
            </tr>
            <tr>
                <td><strong>TOTAL</strong></td>
                <td><strong>$clesTotal</strong></td>
            </tr>
        </table>
        <p><strong>Conformité :</strong> $clesConformiteHTML</p>
HTML;

    if ($clesObservations) {
        $html .= "<p><strong>Observations :</strong> $clesObservations</p>";
    }
    
    $html .= <<<HTML
    </div>
    
    <div class="section">
        <h2>4. DESCRIPTION DU LOGEMENT</h2>
        
        <h3>4.1 Pièce principale</h3>
        <p>$piecePrincipale</p>
        
        <h3>4.2 Coin cuisine</h3>
        <p>$coinCuisine</p>
        
        <h3>4.3 Salle d'eau / WC</h3>
        <p>$salleEauWC</p>
        
        <h3>4.4 État général</h3>
        <p>$etatGeneral</p>
    </div>
    
    <div class="section">
        <h2>5. CONCLUSION</h2>
        
        <h3>5.1 Comparaison avec l'état des lieux d'entrée</h3>
        <p>$comparaisonEntree</p>
        
        <h3>5.2 Dépôt de garantie</h3>
        $depotHTML
    </div>
    
    <div class="section">
        <h2>6. SIGNATURES</h2>
        <p>Le présent état des lieux de sortie a été établi contradictoirement entre les parties.</p>
HTML;

    if (!empty($observations)) {
        $html .= <<<HTML
        <p><strong>Observations complémentaires :</strong></p>
        <p>$observations</p>
HTML;
    }

    $html .= <<<HTML
        $signaturesHTML
    </div>
    
</body>
</html>
HTML;

    return $html;
}

/**
 * Convert base64 signature to physical file
 * Returns file path or original data if conversion fails
 */
function convertSignatureToPhysicalFile($signatureData, $prefix, $etatLieuxId, $tenantId = null) {
    // If already a file path, return it
    if (!preg_match('/^data:image\/(jpeg|jpg|png);base64,/', $signatureData)) {
        return $signatureData;
    }
    
    error_log("Converting base64 signature to physical file for {$prefix}");
    
    // Extract base64 data
    if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,(.+)$/', $signatureData, $matches)) {
        error_log("Invalid data URI format for signature");
        return $signatureData; // Return original if invalid
    }
    
    $imageFormat = $matches[1];
    $base64Data = $matches[2];
    
    // Decode base64
    $imageData = base64_decode($base64Data, true);
    if ($imageData === false) {
        error_log("Failed to decode base64 signature");
        return $signatureData;
    }
    
    // Create uploads/signatures directory if it doesn't exist
    $uploadsDir = dirname(__DIR__) . '/uploads/signatures';
    if (!is_dir($uploadsDir)) {
        if (!mkdir($uploadsDir, 0755, true)) {
            error_log("Failed to create signatures directory");
            return $signatureData;
        }
    }
    
    // Generate unique filename
    $timestamp = time();
    $suffix = $tenantId ? "_tenant_{$tenantId}" : "";
    $filename = "{$prefix}_etat_lieux_{$etatLieuxId}{$suffix}_{$timestamp}.jpg";
    $filepath = $uploadsDir . '/' . $filename;
    
    // Save physical file
    if (file_put_contents($filepath, $imageData) === false) {
        error_log("Failed to save signature file: $filepath");
        return $signatureData;
    }
    
    // Return relative path
    $relativePath = 'uploads/signatures/' . $filename;
    error_log("✓ Signature converted to physical file: $relativePath");
    
    return $relativePath;
}

/**
 * Construire le tableau de signatures pour l'état des lieux
 */
function buildSignaturesTableEtatLieux($contrat, $locataires, $etatLieux) {
    global $pdo, $config;

    // Get tenants from etat_lieux_locataires table for this specific état des lieux
    $etatLieuxTenants = [];
    if ($etatLieux && isset($etatLieux['id'])) {
        $stmt = $pdo->prepare("
            SELECT * FROM etat_lieux_locataires 
            WHERE etat_lieux_id = ? 
            ORDER BY ordre ASC
        ");
        $stmt->execute([$etatLieux['id']]);
        $etatLieuxTenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Use etat_lieux_locataires if available, otherwise fall back to locataires
    $tenantsToDisplay = !empty($etatLieuxTenants) ? $etatLieuxTenants : $locataires;
    
    $nbCols = count($tenantsToDisplay) + 1; // +1 for landlord
    $colWidth = 100 / $nbCols;

    $html = '<table class="signature-table" style="width: 100%; border-collapse: collapse; margin-top: 20px;"><tr>';

    // Landlord column - Use signature_societe_etat_lieux_image from parametres
    $html .= '<td style="width:' . $colWidth . '%; vertical-align: top; text-align:center; padding:10px;">';
    $html .= '<p><strong>Le bailleur :</strong></p>';
    
    // Get landlord signature from parametres - use etat_lieux specific signature
    $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'signature_societe_etat_lieux_image'");
    $stmt->execute();
    $landlordSigPath = $stmt->fetchColumn();
    
    // Fallback to general signature if etat_lieux specific one not found
    if (empty($landlordSigPath)) {
        $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'signature_societe_image'");
        $stmt->execute();
        $landlordSigPath = $stmt->fetchColumn();
    }

    if (!empty($landlordSigPath)) {
        // Convert base64 to physical file if needed
        $etatLieuxId = $etatLieux['id'] ?? 0;
        $landlordSigPath = convertSignatureToPhysicalFile($landlordSigPath, 'landlord', $etatLieuxId);
        
        // Update database with physical path if it was converted
        if (!empty($etatLieuxId) && !preg_match('/^data:image/', $landlordSigPath) && preg_match('/^uploads\/signatures\//', $landlordSigPath)) {
            // Update the parameter with the new physical path
            $paramKey = 'signature_societe_etat_lieux_image';
            $updateStmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = ?");
            $updateStmt->execute([$paramKey]);
            $currentValue = $updateStmt->fetchColumn();
            
            // Only update if current value is base64 (to avoid overwriting if already updated)
            if ($currentValue && preg_match('/^data:image/', $currentValue)) {
                $updateStmt = $pdo->prepare("UPDATE parametres SET valeur = ? WHERE cle = ?");
                $updateStmt->execute([$landlordSigPath, $paramKey]);
                error_log("✓ Updated landlord signature in database to physical file");
            }
        }
        
        if (preg_match('/^uploads\/signatures\//', $landlordSigPath)) {
            // Verify file exists before adding to PDF
            $fullPath = dirname(__DIR__) . '/' . $landlordSigPath;
            if (file_exists($fullPath)) {
                // Use public URL for signature image
                $publicUrl = rtrim($config['SITE_URL'], '/') . '/' . ltrim($landlordSigPath, '/');
                $html .= '<div class="signature-box"><img src="' . htmlspecialchars($publicUrl) . '" alt="Signature Bailleur" border="0" style="' . ETAT_LIEUX_SIGNATURE_IMG_STYLE . '"></div>';
            } else {
                error_log("Landlord signature file not found: $fullPath");
                $html .= '<div class="signature-box">&nbsp;</div>';
            }
        } else {
            // Still base64 after conversion attempt - use as fallback but log warning
            error_log("WARNING: Using base64 signature for landlord (conversion may have failed)");
            $html .= '<div class="signature-box"><img src="' . htmlspecialchars($landlordSigPath) . '" alt="Signature Bailleur" border="0" style="' . ETAT_LIEUX_SIGNATURE_IMG_STYLE . '"></div>';
        }
    } else {
        $html .= '<div class="signature-box">&nbsp;</div>';
    }
    
    $placeSignature = !empty($etatLieux['lieu_signature']) ? htmlspecialchars($etatLieux['lieu_signature']) : htmlspecialchars($config['DEFAULT_SIGNATURE_LOCATION'] ?? 'Annemasse');
    $html .= '<p style="font-size:8pt;">Fait à ' . $placeSignature . '</p>';
    
    if (!empty($etatLieux['date_etat'])) {
        $signDate = date('d/m/Y', strtotime($etatLieux['date_etat']));
        $html .= '<p style="font-size:8pt;">Le ' . $signDate . '</p>';
    }
    
    $html .= '<p style="font-size:9pt;">' . htmlspecialchars($etatLieux['bailleur_nom'] ?? $config['COMPANY_NAME']) . '</p>';
    $html .= '</td>';

    // Tenant columns
    foreach ($tenantsToDisplay as $idx => $tenantInfo) {
        $html .= '<td style="width:' . $colWidth . '%; vertical-align: top; text-align:center; padding:10px;">';

        $tenantLabel = ($nbCols === 2) ? 'Locataire :' : 'Locataire ' . ($idx + 1) . ' :';
        $html .= '<p><strong>' . $tenantLabel . '</strong></p>';

        // Display tenant signature if available
        if (!empty($tenantInfo['signature_data'])) {
            $signatureData = $tenantInfo['signature_data'];
            $tenantDbId = $tenantInfo['id'] ?? null;
            $etatLieuxId = $etatLieux['id'] ?? 0;
            
            // Convert base64 to physical file if needed
            $signatureData = convertSignatureToPhysicalFile($signatureData, 'tenant', $etatLieuxId, $tenantDbId);
            
            // Update database if signature was converted from base64
            if ($tenantDbId && !preg_match('/^data:image/', $signatureData) && preg_match('/^uploads\/signatures\//', $signatureData)) {
                // Check if this is the original base64
                if (preg_match('/^data:image/', $tenantInfo['signature_data'])) {
                    $updateStmt = $pdo->prepare("UPDATE etat_lieux_locataires SET signature_data = ? WHERE id = ?");
                    $updateStmt->execute([$signatureData, $tenantDbId]);
                    error_log("✓ Updated tenant signature in database to physical file");
                }
            }
            
            if (preg_match('/^uploads\/signatures\//', $signatureData)) {
                // File path format - verify file exists before using public URL
                $fullPath = dirname(__DIR__) . '/' . $signatureData;
                if (file_exists($fullPath)) {
                    // Use public URL
                    $publicUrl = rtrim($config['SITE_URL'], '/') . '/' . ltrim($signatureData, '/');
                    $html .= '<div class="signature-box"><img src="' . htmlspecialchars($publicUrl) . '" alt="Signature Locataire" border="0" style="' . ETAT_LIEUX_SIGNATURE_IMG_STYLE . '"></div>';
                } else {
                    error_log("Tenant signature file not found: $fullPath");
                    $html .= '<div class="signature-box">&nbsp;</div>';
                }
            } else {
                // Still base64 after conversion attempt - use as fallback but log warning
                error_log("WARNING: Using base64 signature for tenant (conversion may have failed)");
                $html .= '<div class="signature-box"><img src="' . htmlspecialchars($signatureData) . '" alt="Signature Locataire" border="0" style="' . ETAT_LIEUX_SIGNATURE_IMG_STYLE . '"></div>';
            }
            
            if (!empty($tenantInfo['signature_timestamp'])) {
                $signDate = date('d/m/Y à H:i', strtotime($tenantInfo['signature_timestamp']));
                $html .= '<p style="font-size:8pt;">Signé le ' . $signDate . '</p>';
            }
        } else {
            $html .= '<div class="signature-box">&nbsp;</div>';
        }

        $tenantName = htmlspecialchars(($tenantInfo['prenom'] ?? '') . ' ' . ($tenantInfo['nom'] ?? ''));
        $html .= '<p style="font-size:9pt;">' . $tenantName . '</p>';
        $html .= '</td>';
    }

    $html .= '</tr></table>';
    return $html;
}

/**
 * Envoyer l'état des lieux par email au locataire et à gestion@myinvest-immobilier.com
 * 
 * @param int $contratId ID du contrat
 * @param string $type Type d'état des lieux: 'entree' ou 'sortie'
 * @param string $pdfPath Chemin du fichier PDF
 * @return bool True si l'email a été envoyé avec succès
 */
function sendEtatDesLieuxEmail($contratId, $type, $pdfPath) {
    global $pdo, $config;
    
    try {
        // Récupérer le contrat et locataires
        $stmt = $pdo->prepare("
            SELECT c.*, l.adresse, l.appartement, l.reference
            FROM contrats c
            INNER JOIN logements l ON c.logement_id = l.id
            WHERE c.id = ?
        ");
        $stmt->execute([$contratId]);
        $contrat = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$contrat) {
            error_log("Contrat #$contratId non trouvé");
            return false;
        }
        
        // Récupérer les locataires
        $stmt = $pdo->prepare("SELECT * FROM locataires WHERE contrat_id = ? ORDER BY ordre ASC");
        $stmt->execute([$contratId]);
        $locataires = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($locataires)) {
            error_log("Aucun locataire trouvé pour contrat #$contratId");
            return false;
        }
        
        // Récupérer l'état des lieux
        $stmt = $pdo->prepare("SELECT * FROM etats_lieux WHERE contrat_id = ? AND type = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$contratId, $type]);
        $etatLieux = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Préparer le sujet et le corps de l'email
        $typeLabel = ($type === 'entree') ? "d'entrée" : "de sortie";
        $subject = "État des lieux $typeLabel - " . ($contrat['adresse'] ?? '');
        
        $dateEtat = date('d/m/Y');
        if ($etatLieux && !empty($etatLieux['date_etat'])) {
            $dateEtat = date('d/m/Y', strtotime($etatLieux['date_etat']));
        }
        
        $adresse = $contrat['adresse'];
        if (!empty($contrat['appartement'])) {
            $adresse .= ' - ' . $contrat['appartement'];
        }
        
        $body = "Bonjour,\n\n";
        $body .= "Veuillez trouver ci-joint l'état des lieux $typeLabel pour le logement situé au :\n";
        $body .= "$adresse\n\n";
        $body .= "Date de l'état des lieux : $dateEtat\n\n";
        $body .= "Ce document est à conserver précieusement.\n\n";
        $body .= "Cordialement,\n";
        $body .= "MY INVEST IMMOBILIER";
        
        // Envoyer à chaque locataire
        $success = true;
        foreach ($locataires as $locataire) {
            $emailSent = sendEmail(
                $locataire['email'],
                $subject,
                $body,
                $pdfPath,
                false // texte brut
            );
            
            if (!$emailSent) {
                error_log("Erreur envoi email état des lieux à " . $locataire['email']);
                $success = false;
            } else {
                error_log("Email état des lieux envoyé à " . $locataire['email']);
            }
        }
        
        // Envoyer une copie à gestion@myinvest-immobilier.com
        $gestionEmail = 'gestion@myinvest-immobilier.com';
        $emailSent = sendEmail(
            $gestionEmail,
            "[COPIE] $subject",
            $body,
            $pdfPath,
            false
        );
        
        if (!$emailSent) {
            error_log("Erreur envoi copie email état des lieux à $gestionEmail");
            $success = false;
        } else {
            error_log("Copie email état des lieux envoyée à $gestionEmail");
        }
        
        // Mettre à jour le statut de l'email dans la base de données
        if ($etatLieux && $success) {
            $stmt = $pdo->prepare("
                UPDATE etats_lieux 
                SET email_envoye = TRUE, date_envoi_email = NOW(), statut = 'envoye'
                WHERE id = ?
            ");
            $stmt->execute([$etatLieux['id']]);
        }
        
        return $success;
        
    } catch (Exception $e) {
        error_log("Erreur envoi email état des lieux: " . $e->getMessage());
        return false;
    }
}
