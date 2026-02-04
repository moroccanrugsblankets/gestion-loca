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

/**
 * Générer le PDF de l'état des lieux
 * 
 * @param int $contratId ID du contrat
 * @param string $type Type d'état des lieux: 'entree' ou 'sortie'
 * @return string|false Chemin du fichier PDF généré, ou false en cas d'erreur
 */
function generateEtatDesLieuxPDF($contratId, $type = 'entree') {
    global $config, $pdo;

    // Validation
    $contratId = (int)$contratId;
    if ($contratId <= 0) {
        error_log("Erreur: ID de contrat invalide");
        return false;
    }

    if (!in_array($type, ['entree', 'sortie'])) {
        error_log("Erreur: Type invalide (doit être 'entree' ou 'sortie')");
        return false;
    }

    try {
        // Récupérer les données du contrat
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

        // Vérifier si un état des lieux existe déjà
        $stmt = $pdo->prepare("SELECT * FROM etat_lieux WHERE contrat_id = ? AND type = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$contratId, $type]);
        $etatLieux = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si pas d'état des lieux, créer un brouillon avec données par défaut
        if (!$etatLieux) {
            $etatLieux = createDefaultEtatLieux($contratId, $type, $contrat, $locataires);
        }

        // Générer le HTML
        if ($type === 'entree') {
            $html = generateEntreeHTML($contrat, $locataires, $etatLieux);
        } else {
            $html = generateSortieHTML($contrat, $locataires, $etatLieux);
        }

        // Créer le PDF avec TCPDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('MY INVEST IMMOBILIER');
        
        $typeLabel = ($type === 'entree') ? 'Entrée' : 'Sortie';
        $pdf->SetTitle("État des lieux $typeLabel - " . $contrat['reference']);
        
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');

        // Sauvegarder le PDF
        $pdfDir = dirname(__DIR__) . '/pdf/etat_des_lieux/';
        if (!is_dir($pdfDir)) {
            mkdir($pdfDir, 0755, true);
        }

        $dateStr = date('Ymd');
        $filename = "etat_lieux_{$type}_{$contrat['reference']}_{$dateStr}.pdf";
        $filepath = $pdfDir . $filename;
        $pdf->Output($filepath, 'F');

        // Mettre à jour le statut de l'état des lieux
        if ($etatLieux && isset($etatLieux['id'])) {
            $stmt = $pdo->prepare("UPDATE etat_lieux SET statut = 'finalise', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$etatLieux['id']]);
        }

        error_log("PDF État des lieux généré: $filepath");
        return $filepath;

    } catch (Exception $e) {
        error_log("Erreur génération PDF État des lieux: " . $e->getMessage());
        return false;
    }
}

/**
 * Créer un état des lieux par défaut avec données de base
 */
function createDefaultEtatLieux($contratId, $type, $contrat, $locataires) {
    global $pdo, $config;

    $referenceUnique = 'EDL-' . strtoupper($type) . '-' . $contrat['reference'] . '-' . date('YmdHis');
    
    $stmt = $pdo->prepare("
        INSERT INTO etat_lieux (
            contrat_id, 
            type, 
            reference_unique,
            date_etat,
            adresse,
            appartement,
            bailleur_nom,
            bailleur_representant,
            piece_principale,
            coin_cuisine,
            salle_eau_wc,
            etat_general,
            lieu_signature,
            statut
        ) VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, 'brouillon')
    ");
    
    $defaultTexts = getDefaultPropertyDescriptions($type);
    
    $stmt->execute([
        $contratId,
        $type,
        $referenceUnique,
        $contrat['adresse'],
        $contrat['appartement'] ?? '',
        $config['COMPANY_NAME'] ?? 'MY INVEST IMMOBILIER',
        $config['BAILLEUR_REPRESENTANT'] ?? '',
        $defaultTexts['piece_principale'],
        $defaultTexts['coin_cuisine'],
        $defaultTexts['salle_eau_wc'],
        $defaultTexts['etat_general'],
        '' // lieu_signature
    ]);
    
    $etatLieuxId = $pdo->lastInsertId();
    
    // Ajouter les locataires
    foreach ($locataires as $i => $loc) {
        $stmt = $pdo->prepare("
            INSERT INTO etat_lieux_locataires (
                etat_lieux_id,
                locataire_id,
                ordre,
                nom,
                prenom,
                email
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $etatLieuxId,
            $loc['id'],
            $i + 1,
            $loc['nom'],
            $loc['prenom'],
            $loc['email']
        ]);
    }
    
    // Récupérer l'état des lieux créé
    $stmt = $pdo->prepare("SELECT * FROM etat_lieux WHERE id = ?");
    $stmt->execute([$etatLieuxId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
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
 * Générer le HTML pour l'état des lieux d'entrée
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
    $clesTotal = (int)($etatLieux['cles_total'] ?? 0);
    if ($clesTotal === 0) $clesTotal = $clesAppart + $clesBoite;
    
    // Description
    $piecePrincipale = nl2br(htmlspecialchars($etatLieux['piece_principale'] ?? ''));
    $coinCuisine = nl2br(htmlspecialchars($etatLieux['coin_cuisine'] ?? ''));
    $salleEauWC = nl2br(htmlspecialchars($etatLieux['salle_eau_wc'] ?? ''));
    $etatGeneral = nl2br(htmlspecialchars($etatLieux['etat_general'] ?? ''));
    
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
            margin-top: 20px; 
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
            border-bottom: 1px solid #000; 
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
    
    <div class="section">
        <h2>2. RELEVÉ DES COMPTEURS</h2>
        <table class="data-table">
            <tr>
                <th>Type de compteur</th>
                <th>Index relevé</th>
                <th>Observations</th>
            </tr>
            <tr>
                <td>Électricité</td>
                <td>$compteurElec</td>
                <td>Photo facultative (conservée dans dossier interne)</td>
            </tr>
            <tr>
                <td>Eau froide</td>
                <td>$compteurEau</td>
                <td>Photo facultative (conservée dans dossier interne)</td>
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
                <td><strong>TOTAL</strong></td>
                <td><strong>$clesTotal</strong></td>
            </tr>
        </table>
        <p><em>Photo facultative (conservée dans dossier interne)</em></p>
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
        $signaturesHTML
    </div>
    
</body>
</html>
HTML;

    return $html;
}

/**
 * Générer le HTML pour l'état des lieux de sortie
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
    $clesTotal = (int)($etatLieux['cles_total'] ?? 0);
    if ($clesTotal === 0) $clesTotal = $clesAppart + $clesBoite;
    
    $clesConformite = $etatLieux['cles_conformite'] ?? 'non_applicable';
    $conformiteLabels = [
        'conforme' => '☑ Conforme',
        'non_conforme' => '☑ Non conforme',
        'non_applicable' => '☐ Non applicable'
    ];
    $clesConformiteHTML = $conformiteLabels[$clesConformite] ?? '☐ Non vérifié';
    $clesObservations = htmlspecialchars($etatLieux['cles_observations'] ?? '');
    
    // Description
    $piecePrincipale = nl2br(htmlspecialchars($etatLieux['piece_principale'] ?? ''));
    $coinCuisine = nl2br(htmlspecialchars($etatLieux['coin_cuisine'] ?? ''));
    $salleEauWC = nl2br(htmlspecialchars($etatLieux['salle_eau_wc'] ?? ''));
    $etatGeneral = nl2br(htmlspecialchars($etatLieux['etat_general'] ?? ''));
    
    // Conclusion
    $comparaisonEntree = nl2br(htmlspecialchars($etatLieux['comparaison_entree'] ?? 'Comparaison avec l\'état des lieux d\'entrée : [À compléter]'));
    
    $depotStatus = $etatLieux['depot_garantie_status'] ?? 'non_applicable';
    $depotLabels = [
        'restitution_totale' => '☑ Restitution totale du dépôt de garantie',
        'restitution_partielle' => '☑ Restitution partielle du dépôt de garantie',
        'retenue_totale' => '☑ Retenue totale du dépôt de garantie',
        'non_applicable' => '☐ Non applicable'
    ];
    $depotHTML = '';
    foreach ($depotLabels as $key => $label) {
        if ($key === $depotStatus) {
            $depotHTML .= "<p>$label</p>";
        } else {
            $depotHTML .= "<p>☐ " . substr($label, strpos($label, ' ') + 1) . "</p>";
        }
    }
    
    if (!empty($etatLieux['depot_garantie_montant_retenu']) && $etatLieux['depot_garantie_montant_retenu'] > 0) {
        $montantRetenu = number_format((float)$etatLieux['depot_garantie_montant_retenu'], 2, ',', ' ');
        $depotHTML .= "<p><strong>Montant retenu :</strong> $montantRetenu €</p>";
    }
    
    if (!empty($etatLieux['depot_garantie_motif_retenue'])) {
        $motifRetenue = nl2br(htmlspecialchars($etatLieux['depot_garantie_motif_retenue']));
        $depotHTML .= "<p><strong>Motif de la retenue :</strong><br>$motifRetenue</p>";
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
            margin-top: 20px; 
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
            border-bottom: 1px solid #000; 
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
    
    <div class="section">
        <h2>2. RELEVÉ DES COMPTEURS À LA SORTIE</h2>
        <table class="data-table">
            <tr>
                <th>Type de compteur</th>
                <th>Index relevé</th>
                <th>Observations</th>
            </tr>
            <tr>
                <td>Électricité</td>
                <td>$compteurElec</td>
                <td>Photo facultative (conservée dans dossier interne)</td>
            </tr>
            <tr>
                <td>Eau froide</td>
                <td>$compteurEau</td>
                <td>Photo facultative (conservée dans dossier interne)</td>
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
        $signaturesHTML
    </div>
    
</body>
</html>
HTML;

    return $html;
}

/**
 * Construire le tableau de signatures pour l'état des lieux
 */
function buildSignaturesTableEtatLieux($contrat, $locataires, $etatLieux) {
    global $pdo, $config;

    $nbCols = count($locataires) + 1; // +1 pour le bailleur
    $colWidth = 100 / $nbCols;

    $html = '<table class="signature-table" style="width: 100%; border-collapse: collapse; margin-top: 20px;"><tr>';

    // Bailleur
    $html .= '<td style="width:' . $colWidth . '%; vertical-align: top; text-align:center; padding:10px;">';
    $html .= '<p><strong>Le bailleur :</strong></p>';
    
    // Signature du bailleur depuis parametres
    if ($etatLieux && $etatLieux['statut'] === 'finalise') {
        $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'signature_societe_image'");
        $stmt->execute();
        $signatureSociete = $stmt->fetchColumn();

        if (!empty($signatureSociete) && preg_match('/^uploads\/signatures\//', $signatureSociete)) {
            $publicUrl = rtrim($config['SITE_URL'], '/') . '/' . ltrim($signatureSociete, '/');
            $html .= '<div class="signature-box"><img src="' . htmlspecialchars($publicUrl) . '" alt="Signature Bailleur" style="max-width:150px; max-height:60px;"></div>';
        } else {
            $html .= '<div class="signature-box">&nbsp;</div>';
        }
    } else {
        $html .= '<div class="signature-box">&nbsp;</div>';
    }
    
    $lieuSignature = !empty($etatLieux['lieu_signature']) ? htmlspecialchars($etatLieux['lieu_signature']) : '';
    if ($lieuSignature) {
        $html .= '<p style="font-size:8pt;">Fait à ' . $lieuSignature . '</p>';
    }
    
    if (!empty($etatLieux['date_signature'])) {
        $dateSign = date('d/m/Y', strtotime($etatLieux['date_signature']));
        $html .= '<p style="font-size:8pt;">Le ' . $dateSign . '</p>';
    }
    
    $html .= '<p style="font-size:9pt;">' . htmlspecialchars($etatLieux['bailleur_nom'] ?? $config['COMPANY_NAME']) . '</p>';
    $html .= '</td>';

    // Locataires
    foreach ($locataires as $i => $loc) {
        $html .= '<td style="width:' . $colWidth . '%; vertical-align: top; text-align:center; padding:10px;">';

        if ($nbCols === 2) {
            $html .= '<p><strong>Locataire :</strong></p>';
        } else {
            $html .= '<p><strong>Locataire ' . ($i + 1) . ' :</strong></p>';
        }

        // Récupérer la signature du locataire pour cet état des lieux
        if ($etatLieux && isset($etatLieux['id'])) {
            $stmt = $pdo->prepare("
                SELECT signature_data, signature_timestamp, signature_ip 
                FROM etat_lieux_locataires 
                WHERE etat_lieux_id = ? AND locataire_id = ?
            ");
            $stmt->execute([$etatLieux['id'], $loc['id']]);
            $locSignature = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($locSignature && !empty($locSignature['signature_data'])) {
                if (preg_match('/^uploads\/signatures\//', $locSignature['signature_data'])) {
                    $publicUrl = rtrim($config['SITE_URL'], '/') . '/' . ltrim($locSignature['signature_data'], '/');
                    $html .= '<div class="signature-box"><img src="' . htmlspecialchars($publicUrl) . '" alt="Signature Locataire" style="max-width:150px; max-height:60px;"></div>';
                } else {
                    $html .= '<div class="signature-box">&nbsp;</div>';
                }
                
                if (!empty($locSignature['signature_timestamp'])) {
                    $dateSign = date('d/m/Y à H:i', strtotime($locSignature['signature_timestamp']));
                    $html .= '<p style="font-size:8pt;">Signé le ' . $dateSign . '</p>';
                }
            } else {
                $html .= '<div class="signature-box">&nbsp;</div>';
            }
        } else {
            $html .= '<div class="signature-box">&nbsp;</div>';
        }

        $html .= '<p style="font-size:9pt;">' . htmlspecialchars($loc['prenom']) . ' ' . htmlspecialchars($loc['nom']) . '</p>';
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
        $stmt = $pdo->prepare("SELECT * FROM etat_lieux WHERE contrat_id = ? AND type = ? ORDER BY created_at DESC LIMIT 1");
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
                UPDATE etat_lieux 
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
