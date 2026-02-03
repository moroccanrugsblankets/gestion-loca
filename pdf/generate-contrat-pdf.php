<?php
/**
 * Génération du PDF du contrat de bail - Format MY INVEST IMMOBILIER
 * Utilise TCPDF pour une mise en page professionnelle
 * Format: 1 page, style original conforme au modèle
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// Constantes pour la validation des images
define('BASE64_OVERHEAD_RATIO', 4 / 3); // Base64 est ~33% plus grand que les données brutes
define('MAX_TENANT_SIGNATURE_SIZE', 5 * 1024 * 1024); // 5 MB pour signatures locataires
define('MAX_COMPANY_SIGNATURE_SIZE', 2 * 1024 * 1024); // 2 MB pour signature société

// Style CSS pour les images de signature (évite les bordures grises)
// margin-bottom augmenté de 5mm à 15mm pour éviter chevauchement avec texte suivant
define('SIGNATURE_IMG_STYLE', 'width: 40mm; height: auto; display: block; margin-bottom: 15mm; border: none; border-style: none; background: transparent;');

// Style pour les placeholders de signature dans le HTML
define('SIGNATURE_PLACEHOLDER_STYLE', 'margin-top: 15mm;');

/**
 * Créer un placeholder HTML pour une signature
 * @param string $placeholderId ID unique du placeholder (ex: SIGNATURE_LOCATAIRE_1)
 * @return string HTML du placeholder
 */
function createSignaturePlaceholder($placeholderId) {
    return '<p style="' . SIGNATURE_PLACEHOLDER_STYLE . '">[' . $placeholderId . ']</p>';
}

/**
 * Sauvegarder une signature data URI en fichier physique PNG
 * @param string $signatureData Data URI de la signature (base64)
 * @param string $prefix Préfixe du nom de fichier (agency ou tenant)
 * @param int $contratId ID du contrat
 * @param int|null $locataireId ID du locataire (optionnel)
 * @return string|false Chemin relatif du fichier sauvegardé ou false en cas d'erreur
 */
function saveSignatureAsPhysicalFile($signatureData, $prefix, $contratId, $locataireId = null) {
    // Vérifier que c'est un data URI valide
    if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,(.+)$/', $signatureData, $matches)) {
        error_log("saveSignatureAsPhysicalFile: Format data URI invalide");
        return false;
    }
    
    $imageFormat = $matches[1];
    $base64Data = $matches[2];
    
    // Décoder le base64
    $imageData = base64_decode($base64Data);
    if ($imageData === false) {
        error_log("saveSignatureAsPhysicalFile: Échec du décodage base64");
        return false;
    }
    
    // Créer le répertoire si nécessaire (utiliser chemin absolu)
    $baseDir = dirname(__DIR__); // Répertoire racine du projet
    $uploadsDir = $baseDir . '/uploads/signatures';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }
    
    // Générer le nom du fichier
    $suffix = $locataireId ? "_locataire_{$locataireId}" : "";
    $filename = "{$prefix}_contrat_{$contratId}{$suffix}_" . time() . ".png";
    $filepath = $uploadsDir . '/' . $filename;
    
    // Sauvegarder le fichier
    if (file_put_contents($filepath, $imageData) === false) {
        error_log("saveSignatureAsPhysicalFile: Échec de l'écriture du fichier: $filepath");
        return false;
    }
    
    // Retourner le chemin relatif depuis le répertoire pdf/
    $relativePath = '../uploads/signatures/' . $filename;
    error_log("saveSignatureAsPhysicalFile: ✓ Image physique sauvegardée: $relativePath");
    
    return $relativePath;
}

/**
 * Générer le PDF du contrat de bail
 * @param int $contratId ID du contrat
 * @return string|false Chemin du fichier PDF généré ou false en cas d'erreur
 */
function generateContratPDF($contratId) {
    global $config, $pdo;
    
    // Validate and sanitize contract ID: ensure it's a positive integer
    $originalId = $contratId;
    $contratId = (int)$contratId;
    
    // Return early if invalid ID
    if ($contratId <= 0) {
        // Sanitize original ID for logging (remove newlines and control characters)
        $safeOriginalId = preg_replace('/[\x00-\x1F\x7F]/', '', (string)$originalId);
        error_log("PDF Generation: ERREUR - ID de contrat invalide: '$safeOriginalId' (cast: $contratId)");
        return false;
    }
    
    error_log("=== PDF Generation START pour contrat #$contratId ===");
    
    try {
        // Récupérer les données du contrat
        // Important: Select c.* first, then explicitly name logements columns to avoid column name collision
        // Both tables have 'statut' column, and we need contrats.statut, not logements.statut
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
                   l.parking,
                   ca.nom as candidat_nom, 
                   ca.prenom as candidat_prenom, 
                   ca.email as candidat_email
            FROM contrats c
            INNER JOIN logements l ON c.logement_id = l.id
            LEFT JOIN candidatures ca ON c.candidature_id = ca.id
            WHERE c.id = ?
        ");
        $stmt->execute([$contratId]);
        $contrat = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$contrat) {
            error_log("PDF Generation: ERREUR - Contrat #$contratId non trouvé");
            return false;
        }
        
        error_log("PDF Generation: Contrat #$contratId trouvé (statut: " . $contrat['statut'] . ", ref: " . $contrat['reference_unique'] . ")");
        
        // Récupérer les locataires
        $stmt = $pdo->prepare("
            SELECT * FROM locataires 
            WHERE contrat_id = ? 
            ORDER BY ordre ASC
        ");
        $stmt->execute([$contratId]);
        $locataires = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($locataires)) {
            error_log("PDF Generation: ERREUR - Aucun locataire trouvé pour le contrat #$contratId");
            return false;
        }
        
        error_log("PDF Generation: " . count($locataires) . " locataire(s) trouvé(s)");
        
        // Récupérer la template HTML depuis la configuration (/admin-v2/contrat-configuration.php)
        error_log("PDF Generation: === RÉCUPÉRATION TEMPLATE HTML ===");
        error_log("PDF Generation: Recherche de la template dans la table 'parametres' (cle='contrat_template_html')");
        $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'contrat_template_html'");
        if (!$stmt || !$stmt->execute()) {
            error_log("PDF Generation: ERREUR - Impossible de récupérer la template HTML depuis la base de données");
            error_log("PDF Generation: Basculement vers le système LEGACY (mise en page prédéfinie)");
            return generateContratPDFLegacy($contratId, $contrat, $locataires);
        }
        $templateHtml = $stmt->fetchColumn();
        
        // Si pas de template, utiliser le template par défaut ou l'ancien système
        if (empty($templateHtml)) {
            error_log("PDF Generation: Aucune template HTML trouvée dans la configuration");
            error_log("PDF Generation: Basculement vers le système LEGACY (mise en page prédéfinie)");
            return generateContratPDFLegacy($contratId, $contrat, $locataires);
        }
        
        error_log("PDF Generation: Template HTML récupérée avec SUCCÈS depuis /admin-v2/contrat-configuration.php");
        error_log("PDF Generation: Longueur de la template: " . strlen($templateHtml) . " caractères");
        error_log("PDF Generation: Le PDF sera généré à partir de la TEMPLATE HTML CONFIGURÉE (PAS le système legacy)");
        
        // Remplacer les variables dans la template et récupérer les données de signature
        $replacementResult = replaceContratTemplateVariables($templateHtml, $contrat, $locataires);
        $html = $replacementResult['html'];
        $signatureData = $replacementResult['signatures'];
        
        error_log("PDF Generation: === GÉNÉRATION DU PDF AVEC TCPDF ===");
        
        // Créer le PDF avec TCPDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('MY INVEST IMMOBILIER');
        $pdf->SetAuthor('MY INVEST IMMOBILIER');
        $pdf->SetTitle('Contrat de Bail - ' . $contrat['reference_unique']);
        $pdf->SetSubject('Contrat de Location Meublée');
        
        // Configuration
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->SetHeaderMargin(0);
        $pdf->SetFooterMargin(0);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        
        error_log("PDF Generation: Conversion HTML vers PDF en cours");
        
        // Convertir HTML en PDF
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Insert signatures directly using TCPDF::Image() method to avoid gray borders
        // This must be done AFTER writeHTML() to properly position signatures
        error_log("PDF Generation: Nombre de signatures à insérer via TCPDF::Image(): " . count($signatureData));
        if (!empty($signatureData)) {
            insertSignaturesDirectly($pdf, $signatureData);
        }
        
        // Sauvegarder le PDF
        $filename = 'bail-' . $contrat['reference_unique'] . '.pdf';
        $pdfDir = dirname(__DIR__) . '/pdf/contrats/';
        
        if (!is_dir($pdfDir)) {
            mkdir($pdfDir, 0755, true);
            error_log("PDF Generation: Répertoire de sortie créé: $pdfDir");
        }
        
        $filepath = $pdfDir . $filename;
        $pdf->Output($filepath, 'F');
        
        error_log("PDF Generation: PDF généré avec succès: $filepath");
        error_log("PDF Generation: Taille du fichier: " . (file_exists($filepath) ? filesize($filepath) . ' octets' : 'inconnu'));
        error_log("=== PDF Generation END pour contrat #$contratId - SUCCÈS (source: Template HTML) ===");
        
        return $filepath;
        
    } catch (Exception $e) {
        error_log("PDF Generation: EXCEPTION - " . $e->getMessage());
        error_log("PDF Generation: Stack trace: " . $e->getTraceAsString());
        error_log("=== PDF Generation END pour contrat #$contratId - ÉCHEC ===");
        return false;
    }
}

/**
 * Insérer les signatures directement dans le PDF via TCPDF::Image()
 * Note: Signatures are positioned using calculated coordinates based on signature type
 * @param TCPDF $pdf Instance TCPDF
 * @param array $signatureData Données des signatures avec positions
 */
function insertSignaturesDirectly($pdf, $signatureData) {
    error_log("PDF Generation: === INSERTION DES SIGNATURES VIA TCPDF::Image() ===");
    error_log("PDF Generation: Début insertion signatures - " . count($signatureData) . " signature(s) à insérer");
    
    if (empty($signatureData)) {
        error_log("PDF Generation: Aucune signature à insérer");
        return;
    }
    
    // Récupérer le contenu actuel du PDF
    $pageCount = $pdf->getNumPages();
    error_log("PDF Generation: Nombre de pages dans le PDF: $pageCount");
    
    foreach ($signatureData as $index => $sig) {
        if (empty($sig['base64Data'])) {
            error_log("PDF Generation: Signature #$index ignorée (données manquantes): " . ($sig['type'] ?? 'unknown'));
            continue;
        }
        
        try {
            // Décoder les données base64
            $imageData = base64_decode($sig['base64Data']);
            if ($imageData === false) {
                error_log("PDF Generation: ERREUR - Décodage base64 échoué pour: " . $sig['type']);
                continue;
            }
            
            $format = strtoupper($sig['format']);
            $placeholderText = '[' . $sig['type'] . ']';
            
            // Search for placeholder in all pages and replace with image
            $found = false;
            for ($pageNum = 1; $pageNum <= $pageCount; $pageNum++) {
                $pdf->setPage($pageNum);
                
                // Calculate position based on signature type
                // Note: TCPDF doesn't provide direct text search, so we position signatures
                // at calculated coordinates instead of searching for placeholder text
                if ($sig['type'] === 'SIGNATURE_AGENCE') {
                    // Agency signature towards bottom of page
                    // Adjust Y position based on page layout (typically after client signatures)
                    $yPos = 240; // mm from top
                } else {
                    // Client signatures
                    $locataireNum = $sig['locataireNum'] ?? 1;
                    // First client at ~200mm, each subsequent one 30mm lower
                    $yPos = 200 + ($locataireNum - 1) * 30;
                }
                
                $xPos = 20; // Left margin 20mm
                
                // Insert image using TCPDF::Image() with border=0 to prevent gray borders
                // The @ prefix allows passing binary image data directly
                $pdf->Image(
                    '@' . $imageData,      // @ prefix for binary data
                    $xPos,                  // X position (mm)
                    $yPos,                  // Y position (mm)
                    40,                     // Width (mm) - fixed size
                    20,                     // Height (mm) - fixed size
                    $format,                // Format (PNG, JPEG)
                    '',                     // Link
                    '',                     // Alignment
                    false,                  // Resize
                    300,                    // DPI for quality
                    '',                     // Palette alignment
                    false,                  // Mask
                    false,                  // Image mask
                    0,                      // BORDER = 0 (removes border!)
                    false,                  // Fit box
                    false,                  // Hidden
                    false                   // Fit on page
                );
                
                error_log("PDF Generation: ✓ Signature insérée via TCPDF::Image() sans bordure - Type: " . $sig['type'] . 
                         ", Page: $pageNum, Position: ({$xPos}mm, {$yPos}mm), Dimensions: 40x20mm, Format: " . $format);
                $found = true;
                break; // One insertion per signature
            }
            
            if (!$found) {
                error_log("PDF Generation: AVERTISSEMENT - Impossible d'insérer: " . $sig['type']);
            }
            
        } catch (Exception $e) {
            error_log("PDF Generation: ERREUR lors de l'insertion de signature " . $sig['type'] . ": " . $e->getMessage());
        }
    }
    
    error_log("PDF Generation: === FIN INSERTION SIGNATURES ===");
}

/**
 * Remplacer les variables dans la template HTML du contrat
 * @param string $template Template HTML avec variables {{variable}}
 * @param array $contrat Données du contrat
 * @param array $locataires Liste des locataires
 * @return string HTML avec variables remplacées
 */
function replaceContratTemplateVariables($template, $contrat, $locataires) {
    global $config;
    
    // Log: Début du remplacement des variables de template
    error_log("PDF Generation: Début du remplacement des variables pour contrat #" . $contrat['id']);
    
    // Tableau pour stocker les données de signature à insérer via TCPDF::Image()
    $signatureData = [];
    
    // Préparer les informations des locataires
    $locatairesInfo = [];
    foreach ($locataires as $i => $loc) {
        $dateNaissance = 'N/A';
        if (isset($loc['date_naissance']) && !empty($loc['date_naissance'])) {
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
    
    // Préparer les signatures des locataires
    $locatairesSignatures = [];
    $nbLocataires = count($locataires);
    error_log("PDF Generation: === TRAITEMENT DES SIGNATURES CLIENTS ===");
    error_log("PDF Generation: Nombre de locataires à traiter: " . $nbLocataires);
    
    foreach ($locataires as $i => $locataire) {
        // Increase margin-bottom to 40px for better spacing between client signatures and text below
        // Previous value was 30px, increased to prevent overlapping
        $sig = '<div style="margin-bottom: 40px;">';
        
        // Adapter le label selon le nombre de locataires
        // Si un seul locataire: "Locataire :" sans numéro
        // Si plusieurs locataires: "Locataire 1 :", "Locataire 2 :", etc.
        if ($nbLocataires === 1) {
            $locataireLabel = 'Locataire';
            $sig .= '<p><strong>' . $locataireLabel . ' :</strong></p>';
            $sig .= '<p>' . htmlspecialchars($locataire['prenom']) . ' ' . htmlspecialchars($locataire['nom']) . '</p>';
            error_log("PDF Generation: Locataire unique - Label: '$locataireLabel'");
        } else {
            $locataireLabel = 'Locataire ' . ($i + 1);
            $sig .= '<p><strong>' . $locataireLabel . ' :</strong></p>';
            $sig .= '<p>' . htmlspecialchars($locataire['prenom']) . ' ' . htmlspecialchars($locataire['nom']) . '</p>';
            error_log("PDF Generation: Locataire " . ($i + 1) . "/" . $nbLocataires . " - Label: '$locataireLabel'");
        }
        
        // Mention "Lu et approuvé"
        if (!empty($locataire['mention_lu_approuve'])) {
            $sig .= '<p>' . htmlspecialchars($locataire['mention_lu_approuve']) . '</p>';
        } else {
            $sig .= '<p>Lu et approuvé</p>';
        }
        
        // Préparer placeholder pour la signature (sera insérée via TCPDF::Image())
        if (!empty($locataire['signature_data'])) {
            error_log("PDF Generation: Signature client " . ($i + 1) . " - Données présentes (taille: " . strlen($locataire['signature_data']) . " octets)");
            
            // Décoder les données de signature pour les stocker dans $signatureData
            $signatureBase64 = null;
            $signatureFormat = 'PNG';
            
            // Check if it's a physical file path (new format) or base64 data URI (legacy format)
            if (preg_match('/^uploads\/signatures\//', $locataire['signature_data'])) {
                // New format: physical file path - load and encode
                $baseDir = dirname(__DIR__);
                $fullPath = $baseDir . '/' . $locataire['signature_data'];
                
                if (file_exists($fullPath)) {
                    error_log("PDF Generation: Signature client " . ($i + 1) . " - Format: Fichier physique, Chemin: " . $locataire['signature_data']);
                    $imageData = file_get_contents($fullPath);
                    if ($imageData !== false) {
                        $signatureBase64 = base64_encode($imageData);
                        // Detect format from extension
                        if (preg_match('/\.(jpe?g)$/i', $fullPath)) {
                            $signatureFormat = 'JPEG';
                        }
                    }
                } else {
                    error_log("PDF Generation: ERREUR - Fichier de signature introuvable: " . $fullPath);
                }
            } elseif (preg_match('/^data:image\/(png|jpeg|jpg);base64,(.+)$/', $locataire['signature_data'], $matches)) {
                // Legacy format: base64 data URI - extract base64 part
                $signatureBase64 = $matches[2];
                $signatureFormat = strtoupper($matches[1]);
                // Vérifier la taille
                if (strlen($signatureBase64) < MAX_TENANT_SIGNATURE_SIZE * BASE64_OVERHEAD_RATIO) {
                    error_log("PDF Generation: Signature client " . ($i + 1) . " - Format: Legacy base64, Taille: " . strlen($signatureBase64) . " octets");
                } else {
                    error_log("PDF Generation: AVERTISSEMENT - Signature client " . ($i + 1) . " trop volumineuse (" . strlen($signatureBase64) . " octets), ignorée");
                    $signatureBase64 = null; // Ignore oversized signature
                }
            } else {
                error_log("PDF Generation: ERREUR - Format de signature client " . ($i + 1) . " invalide (ni fichier physique ni data URI valide)");
            }
            
            // Add signature to data array for later insertion via TCPDF::Image()
            if ($signatureBase64 !== null) {
                $placeholderId = 'SIGNATURE_LOCATAIRE_' . ($i + 1);
                $signatureData[] = [
                    'type' => $placeholderId,
                    'base64Data' => $signatureBase64,
                    'format' => $signatureFormat,
                    'locataireNum' => $i + 1
                ];
                
                // Insert placeholder in HTML
                $sig .= createSignaturePlaceholder($placeholderId);
                error_log("PDF Generation: ✓ Placeholder créé: [" . $placeholderId . "] - Signature sera insérée via TCPDF::Image() sans bordure");
            }
        } else {
            error_log("PDF Generation: Signature client " . ($i + 1) . " - Non disponible (champ vide)");
        }
        
        // Horodatage et IP avec margin-top augmenté pour éviter chevauchement avec signature
        if (!empty($locataire['signature_timestamp']) || !empty($locataire['signature_ip'])) {
            $sig .= '<div style="margin-top: 15px;">';
            
            if (!empty($locataire['signature_timestamp'])) {
                $timestamp = strtotime($locataire['signature_timestamp']);
                if ($timestamp !== false) {
                    $formattedTimestamp = date('d/m/Y à H:i:s', $timestamp);
                    // white-space: nowrap pour forcer l'affichage sur une seule ligne
                    $sig .= '<p style="font-size: 8pt; color: #666; white-space: nowrap; margin-bottom: 2px;"><em>Horodatage : ' . $formattedTimestamp . '</em></p>';
                    error_log("PDF Generation: ✓ Horodatage affiché sur une seule ligne");
                }
            }
            if (!empty($locataire['signature_ip'])) {
                $sig .= '<p style="font-size: 8pt; color: #666; white-space: nowrap; margin-top: 0;"><em>Adresse IP : ' . htmlspecialchars($locataire['signature_ip']) . '</em></p>';
            }
            
            $sig .= '</div>';
        }
        
        $sig .= '</div>';
        $locatairesSignatures[] = $sig;
    }
    $locatairesSignaturesHtml = implode('', $locatairesSignatures);
    error_log("PDF Generation: === FIN TRAITEMENT SIGNATURES CLIENTS - " . count($locatairesSignatures) . " signature(s) générée(s) ===");
    
    // Préparer la signature de l'agence (si contrat validé)
    error_log("PDF Generation: === TRAITEMENT SIGNATURE AGENCE ===");
    $signatureAgence = '';
    if (isset($contrat['statut']) && $contrat['statut'] === 'valide') {
        error_log("PDF Generation: Contrat validé (statut='valide'), traitement de la signature agence");
        error_log("PDF Generation: Date validation: " . ($contrat['date_validation'] ?? 'NON DÉFINIE'));
        
        // Récupérer les paramètres de signature depuis la base de données
        require_once __DIR__ . '/../includes/functions.php';
        $signatureImage = getParametreValue('signature_societe_image');
        $signatureEnabledRaw = getParametreValue('signature_societe_enabled');
        $signatureEnabled = toBooleanParam($signatureEnabledRaw);
        
        error_log("PDF Generation: Configuration signature agence - Activée: " . ($signatureEnabled ? 'OUI' : 'NON') . ", Image présente: " . (!empty($signatureImage) ? 'OUI (' . strlen($signatureImage) . ' octets)' : 'NON'));
        if (!empty($signatureImage)) {
            error_log("PDF Generation: Début du data URI: " . substr($signatureImage, 0, 50) . '...');
        }
        
        if ($signatureEnabled && !empty($signatureImage)) {
            // Décoder les données de signature pour les stocker dans $signatureData
            $signatureBase64 = null;
            $signatureFormat = 'PNG';
            
            // Check if signature is a file path or a data URI
            // A file path should not start with 'data:' and should contain 'uploads/signatures/'
            $isFilePath = (strpos($signatureImage, 'data:') !== 0 && strpos($signatureImage, 'uploads/signatures/') !== false);
            
            if ($isFilePath) {
                // Signature is stored as a file path - load and encode
                $baseDir = dirname(__DIR__);
                $absolutePath = $baseDir . '/' . $signatureImage;
                
                if (file_exists($absolutePath)) {
                    error_log("PDF Generation: Signature agence depuis fichier physique: $signatureImage");
                    $imageData = file_get_contents($absolutePath);
                    if ($imageData !== false) {
                        $signatureBase64 = base64_encode($imageData);
                        // Detect format from extension
                        if (preg_match('/\.(jpe?g)$/i', $absolutePath)) {
                            $signatureFormat = 'JPEG';
                        }
                    }
                } else {
                    error_log("PDF Generation: ERREUR - Fichier de signature introuvable: $absolutePath");
                }
            } elseif (preg_match('/^data:image\/(png|jpeg|jpg);base64,(.+)$/', $signatureImage, $matches)) {
                // Legacy support: signature is a data URI - extract base64 part
                $imageFormat = $matches[1];
                $base64Data = $matches[2];
                error_log("PDF Generation: Signature agence - Format: $imageFormat, Taille base64: " . strlen($base64Data) . " octets");
                
                // Vérifier la taille
                if (strlen($base64Data) < MAX_COMPANY_SIGNATURE_SIZE * BASE64_OVERHEAD_RATIO) {
                    $signatureBase64 = $base64Data;
                    $signatureFormat = strtoupper($imageFormat);
                } else {
                    error_log("PDF Generation: ERREUR - Signature agence trop volumineuse (" . strlen($base64Data) . " octets), ignorée");
                }
            } else {
                error_log("PDF Generation: ERREUR - Format de signature agence invalide (ni fichier ni data URI valide)");
                if (!empty($signatureImage)) {
                    error_log("PDF Generation: Contenu trouvé (début): " . substr($signatureImage, 0, 100));
                }
            }
            
            // Add signature to data array for later insertion via TCPDF::Image()
            if ($signatureBase64 !== null) {
                $placeholderId = 'SIGNATURE_AGENCE';
                $signatureData[] = [
                    'type' => $placeholderId,
                    'base64Data' => $signatureBase64,
                    'format' => $signatureFormat
                ];
                
                // Create HTML with placeholder
                $signatureAgence = '<div style="margin-top: 40px;">';
                $signatureAgence .= '<p style="margin-bottom: 15px;"><strong>Signature électronique de la société</strong></p>';
                
                // Insert placeholder instead of actual image
                $signatureAgence .= createSignaturePlaceholder($placeholderId);
                
                // Ajouter le texte "Validé le" avec margin-top augmenté pour éviter chevauchement
                if (!empty($contrat['date_validation'])) {
                    $validationTimestamp = strtotime($contrat['date_validation']);
                    if ($validationTimestamp !== false) {
                        $dateValidation = date('d/m/Y à H:i:s', $validationTimestamp);
                        $signatureAgence .= '<p style="font-size: 8pt; color: #666; margin-top: 15px;"><em>Validé le : ' . $dateValidation . '</em></p>';
                        error_log("PDF Generation: ✓ Texte 'Validé le' ajouté avec margin-top de 15px");
                    }
                }
                $signatureAgence .= '</div>';
                error_log("PDF Generation: ✓ Placeholder signature agence créé: [" . $placeholderId . "]");
                error_log("PDF Generation: Signature agence sera insérée via TCPDF::Image() après writeHTML");
            }
        } else {
            if (!$signatureEnabled) {
                error_log("PDF Generation: ✗ SIGNATURE AGENCE NON ACTIVÉE");
                error_log("PDF Generation: Action requise → Activer la signature dans /admin-v2/contrat-configuration.php");
                error_log("PDF Generation: Paramètre à vérifier: signature_societe_enabled doit être défini à '1' ou 'true'");
            } else {
                error_log("PDF Generation: ✗ IMAGE SIGNATURE AGENCE NON TROUVÉE");
                error_log("PDF Generation: Action requise → Télécharger une image de signature dans /admin-v2/contrat-configuration.php");
                error_log("PDF Generation: Paramètre à vérifier: signature_societe_image doit contenir un chemin de fichier ou un data URI d'image");
            }
        }
    } else {
        error_log("PDF Generation: Contrat NON validé (statut: " . ($contrat['statut'] ?? 'inconnu') . "), signature agence ne sera PAS ajoutée");
    }
    error_log("PDF Generation: === FIN TRAITEMENT SIGNATURE AGENCE - " . (empty($signatureAgence) ? 'NON GÉNÉRÉE' : 'GÉNÉRÉE (longueur: ' . strlen($signatureAgence) . ')') . " ===");
    
    // Préparer les dates
    $dateSignature = '___________';
    if (isset($contrat['date_signature']) && !empty($contrat['date_signature'])) {
        $timestamp = strtotime($contrat['date_signature']);
        if ($timestamp !== false) {
            $dateSignature = date('d/m/Y', $timestamp);
        }
    }
    
    $datePriseEffet = '___________';
    if (isset($contrat['date_prise_effet']) && !empty($contrat['date_prise_effet'])) {
        $timestamp = strtotime($contrat['date_prise_effet']);
        if ($timestamp !== false) {
            $datePriseEffet = date('d/m/Y', $timestamp);
        }
    }
    
    // Préparer les montants
    $loyer = number_format($contrat['loyer'], 2, ',', ' ');
    $charges = number_format($contrat['charges'], 2, ',', ' ');
    $loyerTotal = number_format($contrat['loyer'] + $contrat['charges'], 2, ',', ' ');
    $depotGarantie = number_format($contrat['depot_garantie'], 2, ',', ' ');
    
    // Récupérer IBAN et BIC depuis la config
    $iban = isset($config['IBAN']) ? $config['IBAN'] : '[IBAN non configuré]';
    $bic = isset($config['BIC']) ? $config['BIC'] : '[BIC non configuré]';
    
    // Map des variables à remplacer
    $variables = [
        '{{reference_unique}}' => htmlspecialchars($contrat['reference_unique']),
        '{{locataires_info}}' => $locatairesInfoHtml,
        '{{locataires_signatures}}' => $locatairesSignaturesHtml,
        '{{signature_agence}}' => $signatureAgence,
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
    
    // Log: Variables à remplacer
    error_log("PDF Generation: === REMPLACEMENT DES VARIABLES TEMPLATE ===");
    error_log("PDF Generation: Variables disponibles: " . implode(', ', array_keys($variables)));
    error_log("PDF Generation: {{signature_agence}} contient " . (empty($variables['{{signature_agence}}']) ? 'VIDE' : strlen($variables['{{signature_agence}}']) . ' caractères'));
    error_log("PDF Generation: {{locataires_signatures}} contient " . strlen($variables['{{locataires_signatures}}']) . ' caractères');
    
    // Log: Vérifier si {{signature_agence}} est présente dans la template AVANT remplacement
    $signatureAgencePresent = strpos($template, '{{signature_agence}}') !== false;
    error_log("PDF Generation: {{signature_agence}} PRÉSENTE dans template: " . ($signatureAgencePresent ? 'OUI' : 'NON'));
    if ($signatureAgencePresent) {
        error_log("PDF Generation: Valeur brute de {{signature_agence}} avant remplacement: " . (empty($variables['{{signature_agence}}']) ? '(VIDE)' : substr($variables['{{signature_agence}}'], 0, 200) . '...'));
    }
    
    // Remplacer toutes les variables
    $html = str_replace(array_keys($variables), array_values($variables), $template);
    
    // Log: Vérifier le résultat du remplacement
    $signatureAgenceReplaced = strpos($html, '{{signature_agence}}') === false;
    error_log("PDF Generation: {{signature_agence}} remplacée avec succès: " . ($signatureAgenceReplaced ? 'OUI' : 'NON (reste dans le HTML!)'));
    if (!$signatureAgenceReplaced) {
        error_log("PDF Generation: ERREUR - {{signature_agence}} n'a PAS été remplacée dans le HTML final!");
    }
    // Log: Vérifier si l'image de signature agence est présente dans le HTML final
    if (!empty($variables['{{signature_agence}}'])) {
        $signatureImageInFinal = strpos($html, 'alt="Signature Société"') !== false;
        error_log("PDF Generation: Image signature agence dans HTML final: " . ($signatureImageInFinal ? 'OUI' : 'NON'));
    }
    
    // Convertir les chemins d'images relatifs en chemins absolus pour le PDF
    // Gestion des chemins relatifs commençant par ../ ou ./
    global $config;
    $siteUrl = rtrim($config['SITE_URL'] ?? 'https://contrat.myinvest-immobilier.com', '/');
    
    // Log: Traitement des images
    error_log("PDF Generation: === TRAITEMENT DES IMAGES TEMPLATE ===");
    error_log("PDF Generation: URL de base pour conversion des chemins: $siteUrl");
    
    // Remplacer les chemins relatifs par des chemins absolus
    $imageCount = 0;
    $imageSuccessCount = 0;
    $html = preg_replace_callback(
        '/<img([^>]*?)src=["\']([^"\']+)["\']([^>]*?)>/i',
        function($matches) use ($siteUrl, &$imageCount, &$imageSuccessCount) {
            $beforeSrc = $matches[1];
            $src = $matches[2];
            $afterSrc = $matches[3];
            $imageCount++;
            
            // Ne pas modifier les data URIs (signatures encodées en base64)
            if (strpos($src, 'data:') === 0) {
                error_log("PDF Generation: Image #$imageCount - Type: Data URI (signature/image encodée), conservée telle quelle");
                $imageSuccessCount++;
                return $matches[0];
            }
            
            // Ne pas modifier les URLs absolues (http/https) - c'est déjà une URL complète
            if (preg_match('/^https?:\/\//i', $src)) {
                error_log("PDF Generation: Image #$imageCount - Type: URL absolue, conservée: $src");
                $imageSuccessCount++;
                return $matches[0];
            }
            
            // Convertir les chemins relatifs en absolus
            $newSrc = $src;
            $pathType = '';
            
            // Pour les fichiers de signature locaux, utiliser le chemin absolu du système de fichiers au lieu d'une URL
            // Ceci évite les problèmes de TCPDF qui ne peut pas charger les images via HTTP en mode CLI
            if (preg_match('/^\.\.\/uploads\/signatures\//', $src)) {
                // Convertir ../uploads/signatures/file.png en chemin absolu du système de fichiers
                $baseDir = dirname(__DIR__);
                $relativePath = preg_replace('/^\.\.\//', '', $src);
                $newSrc = $baseDir . '/' . $relativePath;
                $pathType = 'Signature locale (fichier système)';
                
                // Vérifier que le fichier existe
                if (!file_exists($newSrc)) {
                    error_log("PDF Generation: AVERTISSEMENT - Image #$imageCount - Fichier signature introuvable: $newSrc");
                }
            }
            // Supprimer les ../ au début et construire l'URL absolue (pour autres images)
            elseif (preg_match('/^\.\.\//', $src)) {
                $newSrc = $siteUrl . '/' . preg_replace('/^\.\.\//', '', $src);
                $pathType = 'Chemin relatif ../';
            } 
            // Supprimer les ./ au début
            elseif (preg_match('/^\.\//', $src)) {
                $newSrc = $siteUrl . '/' . preg_replace('/^\.\//', '', $src);
                $pathType = 'Chemin relatif ./';
            }
            // Chemins commençant par /
            elseif (preg_match('/^\//', $src)) {
                $newSrc = $siteUrl . $src;
                $pathType = 'Chemin absolu /';
            }
            // Autres chemins relatifs (sans préfixe)
            else {
                $newSrc = $siteUrl . '/' . $src;
                $pathType = 'Chemin relatif simple';
            }
            
            error_log("PDF Generation: Image #$imageCount - Type: $pathType, Converti: '$src' => '$newSrc'");
            $imageSuccessCount++;
            
            return '<img' . $beforeSrc . 'src="' . $newSrc . '"' . $afterSrc . '>';
        },
        $html
    );
    
    error_log("PDF Generation: === FIN TRAITEMENT IMAGES - $imageSuccessCount/$imageCount image(s) traitée(s) avec succès ===");
    error_log("PDF Generation: Remplacement des variables terminé avec succès");
    error_log("PDF Generation: Source du PDF: Template HTML depuis /admin-v2/contrat-configuration.php");
    error_log("PDF Generation: Nombre de signatures à insérer via TCPDF::Image(): " . count($signatureData));
    
    return [
        'html' => $html,
        'signatures' => $signatureData
    ];
}

/**
 * Générer le PDF avec l'ancien système (legacy)
 * @param int $contratId ID du contrat
 * @param array $contrat Données du contrat
 * @param array $locataires Liste des locataires
 * @return string|false Chemin du fichier PDF généré ou false en cas d'erreur
 */
function generateContratPDFLegacy($contratId, $contrat, $locataires) {
    error_log("PDF Generation: === GÉNÉRATION LEGACY ===");
    error_log("PDF Generation: ATTENTION - Utilisation du système LEGACY pour contrat #$contratId");
    error_log("PDF Generation: Ce mode utilise une mise en page prédéfinie, PAS la template HTML de /admin-v2/contrat-configuration.php");
    error_log("PDF Generation: Pour utiliser la template HTML, configurez-la dans l'interface admin");
    
    try {
        // Créer le PDF
        $pdf = new ContratBailPDF();
        $pdf->SetCreator('MY INVEST IMMOBILIER');
        $pdf->SetAuthor('MY INVEST IMMOBILIER');
        $pdf->SetTitle('Contrat de Bail - ' . $contrat['reference_unique']);
        $pdf->SetSubject('Contrat de Location Meublée');
        
        // Configuration
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();
        
        error_log("PDF Generation Legacy: Génération du contenu");
        
        // Générer le contenu
        $pdf->generateContrat($contrat, $locataires);
        
        // Sauvegarder le PDF
        $filename = 'bail-' . $contrat['reference_unique'] . '.pdf';
        $pdfDir = dirname(__DIR__) . '/pdf/contrats/';
        
        if (!is_dir($pdfDir)) {
            mkdir($pdfDir, 0755, true);
        }
        
        $filepath = $pdfDir . $filename;
        $pdf->Output($filepath, 'F');
        
        error_log("PDF Generation Legacy: PDF généré avec succès: $filepath");
        
        return $filepath;
        
    } catch (Exception $e) {
        error_log("PDF Generation Legacy: ERREUR - " . $e->getMessage());
        return false;
    }
}

/**
 * Classe personnalisée pour le PDF du contrat de bail
 */
class ContratBailPDF extends TCPDF {
    
    /**
     * En-tête du document
     */
    public function Header() {
        // Logo et titre
        $this->SetFont('helvetica', 'B', 16);
        $this->SetTextColor(0, 51, 102); // Bleu foncé
        $this->Cell(0, 10, 'MY INVEST IMMOBILIER', 0, 1, 'C');
        
        $this->SetFont('helvetica', '', 10);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 5, 'CONTRAT DE BAIL', 0, 1, 'C');
        $this->Cell(0, 5, '(Location meublée - résidence principale)', 0, 1, 'C');
        $this->Ln(3);
    }
    
    /**
     * Pied de page
     */
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 5, 'MY INVEST IMMOBILIER - contact@myinvest-immobilier.com', 0, 0, 'C');
    }
    
    /**
     * Générer le contenu du contrat
     */
    public function generateContrat($contrat, $locataires) {
        // Utiliser une taille de police réduite pour tenir sur 1 page
        $this->SetFont('helvetica', '', 9);
        
        // 1. Parties
        $this->addSection('1. Parties');
        $this->addSubSection('Bailleur');
        $this->addText('MY INVEST IMMOBILIER (SCI)');
        $this->addText('Représenté par : Maxime ALEXANDRE');
        $this->addText('Email : contact@myinvest-immobilier.com');
        
        $this->addSubSection('Locataire' . (count($locataires) > 1 ? 's' : ''));
        foreach ($locataires as $i => $loc) {
            $dateNaissance = isset($loc['date_naissance']) ? date('d/m/Y', strtotime($loc['date_naissance'])) : 'N/A';
            $this->addText($loc['prenom'] . ' ' . $loc['nom'] . ', né(e) le ' . $dateNaissance);
            $this->addText('Email : ' . $loc['email']);
        }
        
        // 2. Désignation du logement
        $this->addSection('2. Désignation du logement');
        $this->addText('Adresse : ' . $contrat['adresse']);
        if (!empty($contrat['appartement'])) {
            $this->addText('Appartement : ' . $contrat['appartement']);
        }
        $this->addText('Type : ' . $contrat['type'] . ' - Logement meublé');
        $this->addText('Surface habitable : ~ ' . $contrat['surface'] . ' m²');
        $this->addText('Usage : Résidence principale');
        $this->addCheckbox('Parking : ' . $contrat['parking'], true);
        $this->addCheckbox('Mobilier conforme à la réglementation', true);
        $this->addCheckbox('Cuisine équipée', true);
        
        // 3. Durée
        $this->addSection('3. Durée');
        $datePriseEffet = isset($contrat['date_prise_effet']) && $contrat['date_prise_effet'] 
            ? date('d/m/Y', strtotime($contrat['date_prise_effet'])) 
            : date('d/m/Y');
        $this->addText('Durée : 1 an, à compter du ' . $datePriseEffet);
        $this->addText('Renouvelable par tacite reconduction.');
        
        // 4. Conditions financières
        $this->addSection('4. Conditions financières');
        $loyer = number_format($contrat['loyer'], 2, ',', ' ');
        $charges = number_format($contrat['charges'], 2, ',', ' ');
        $total = number_format($contrat['loyer'] + $contrat['charges'], 2, ',', ' ');
        $depot = number_format($contrat['depot_garantie'], 2, ',', ' ');
        
        $this->addText('Loyer mensuel HC : ' . $loyer . ' €');
        $this->addText('Charges mensuelles : ' . $charges . ' €');
        $this->addText('Total mensuel : ' . $total . ' €');
        $this->addText('Paiement : mensuel, avant le 5 de chaque mois');
        $this->addText('Modalité : Virement bancaire');
        
        // 5. Dépôt de garantie
        $this->addSection('5. Dépôt de garantie');
        $this->addText('Montant : ' . $depot . ' € (2 mois de loyer HC)');
        $this->addText('Condition suspensive : Le contrat prend effet à réception du dépôt.');
        
        // 6. Charges
        $this->addSection('6. Charges');
        $this->addCheckbox('Provisionnelles avec régularisation annuelle', true);
        $this->addText('Incluses : eau, électricité, ordures ménagères, internet');
        
        // 7. État des lieux
        $this->addSection('7. État des lieux');
        $this->addText('Établi contradictoirement à l\'entrée et à la sortie.');
        
        // 8. Obligations du locataire
        $this->addSection('8. Obligations');
        $this->addText('Le locataire s\'engage à user paisiblement du logement, le maintenir en bon état,');
        $this->addText('répondre des dégradations et être assuré pour les risques locatifs.');
        
        // 9. Clause résolutoire
        $this->addSection('9. Clause résolutoire');
        $this->addText('Résiliation de plein droit en cas de non-paiement ou défaut d\'assurance.');
        
        // 10. Interdictions
        $this->addSection('10. Interdictions');
        $this->addCheckbox('Sous-location interdite sans accord écrit', true);
        $this->addText('Animaux tolérés sous conditions (aucune nuisance/dégradation).');
        
        // 11. Résiliation
        $this->addSection('11. Résiliation');
        $this->addText('Par le locataire : préavis 1 mois (LRE obligatoire via AR24).');
        $this->addText('Par le bailleur : conditions légales.');
        
        // 12. DPE
        $this->addSection('12. DPE');
        $this->addText('Classe énergie : D | Classe climat : B | Validité : 01/06/2035');
        
        // 13. Informations bancaires
        $this->addSection('13. Coordonnées bancaires');
        $iban = isset($config['IBAN']) ? $config['IBAN'] : 'FR76 1027 8021 6000 0206 1834 585';
        $bic = isset($config['BIC']) ? $config['BIC'] : 'CMCIFRA';
        $this->addText('IBAN : ' . $iban);
        $this->addText('BIC : ' . $bic);
        $this->addText('Titulaire : MY INVEST IMMOBILIER');
        
        // 14. Signatures
        $this->addSection('14. Signatures');
        $dateSignature = isset($contrat['date_signature']) && $contrat['date_signature']
            ? date('d/m/Y', strtotime($contrat['date_signature']))
            : date('d/m/Y');
        $this->addText('Fait à Annemasse, le ' . $dateSignature);
        
        $this->Ln(2);
        
        // Signature du bailleur
        $this->SetFont('helvetica', 'B', 9);
        $this->Cell(0, 5, 'Le bailleur', 0, 1, 'L');
        $this->SetFont('helvetica', '', 8);
        
        // Only show full details and signature when contract is validated
        if (isset($contrat['statut']) && $contrat['statut'] === 'valide') {
            $this->Cell(0, 4, 'MY INVEST IMMOBILIER', 0, 1, 'L');
            $this->Cell(0, 4, 'Représenté par M. ALEXANDRE', 0, 1, 'L');
            $this->Cell(0, 4, 'Lu et approuvé', 0, 1, 'L');
        }
        
        // Add company signature image if contract is validated and signature is enabled
        if (isset($contrat['statut']) && $contrat['statut'] === 'valide') {
            error_log("PDF Generation Legacy: === TRAITEMENT SIGNATURE AGENCE ===");
            error_log("PDF Generation Legacy: Contrat validé, ajout de la signature agence");
            error_log("PDF Generation Legacy: Date validation: " . ($contrat['date_validation'] ?? 'NON DÉFINIE'));
            
            $signatureImage = getParametreValue('signature_societe_image');
            $signatureEnabledRaw = getParametreValue('signature_societe_enabled');
            $signatureEnabled = toBooleanParam($signatureEnabledRaw);
            
            error_log("PDF Generation Legacy: Configuration - Activée: " . ($signatureEnabled ? 'OUI' : 'NON') . ", Image présente: " . (!empty($signatureImage) ? 'OUI (' . strlen($signatureImage) . ' octets)' : 'NON'));
            if (!empty($signatureImage)) {
                error_log("PDF Generation Legacy: Début du data URI: " . substr($signatureImage, 0, 50) . '...');
            }
            
            if ($signatureEnabled && !empty($signatureImage)) {
                // Check if it's a data URI
                if (preg_match('/^data:image\/(png|jpeg|jpg);base64,(.+)$/', $signatureImage, $matches)) {
                    $imageFormat = strtoupper($matches[1] === 'jpg' ? 'JPEG' : $matches[1]);
                    $imgData = base64_decode($matches[2]);
                    
                    if ($imgData !== false) {
                        // Create temporary file for signature in uploads directory for better security
                        $uploadsDir = __DIR__ . '/../uploads/temp';
                        if (!is_dir($uploadsDir)) {
                            @mkdir($uploadsDir, 0755, true);
                        }
                        $extension = strtolower($imageFormat) === 'jpeg' ? 'jpg' : 'png';
                        $tempFile = $uploadsDir . '/company_sig_' . uniqid() . '.' . $extension;
                        
                        if (file_put_contents($tempFile, $imgData) !== false) {
                            try {
                                // Signature agence avec taille adaptée (20mm) pour un rendu équilibré
                                error_log("PDF Generation Legacy: Signature agence - Format: $imageFormat, Ajoutée avec taille (20mm)");
                                $this->Image($tempFile, $this->GetX(), $this->GetY(), 20, 0, $imageFormat, '', '', false, 300, '', false, false, 0);
                                error_log("PDF Generation Legacy: Signature agence AJOUTÉE avec succès");
                                @unlink($tempFile);
                            } catch (Exception $e) {
                                error_log("PDF Generation Legacy: ERREUR lors du rendu de la signature agence: " . $e->getMessage());
                                @unlink($tempFile);
                            }
                        } else {
                            error_log("PDF Generation Legacy: ERREUR - Impossible de créer le fichier temporaire pour la signature agence");
                        }
                    } else {
                        error_log("PDF Generation Legacy: ERREUR - Données base64 invalides pour la signature agence");
                    }
                } else {
                    error_log("PDF Generation Legacy: ERREUR - Format de signature agence invalide (n'est pas un data URI image valide)");
                }
            } else {
                if (!$signatureEnabled) {
                    error_log("PDF Generation Legacy: Signature agence DÉSACTIVÉE dans la configuration");
                } else {
                    error_log("PDF Generation Legacy: ERREUR - Image de signature agence non trouvée");
                }
            }
            error_log("PDF Generation Legacy: === FIN TRAITEMENT SIGNATURE AGENCE ===");
        } else {
            error_log("PDF Generation Legacy: Contrat NON validé (statut: " . ($contrat['statut'] ?? 'inconnu') . "), signature agence ne sera PAS ajoutée");
        }
        
        $this->Ln(3);
        
        // Signatures des locataires
        $nbLocataires = count($locataires);
        error_log("PDF Generation Legacy: === TRAITEMENT SIGNATURES CLIENTS ($nbLocataires locataire(s)) ===");
        
        foreach ($locataires as $i => $locataire) {
            $this->SetFont('helvetica', 'B', 9);
            // Adapter le label selon le nombre de locataires
            // Si un seul locataire: "Locataire :" sans numéro
            // Si plusieurs locataires: "Locataire 1 :", "Locataire 2 :", etc.
            if ($nbLocataires === 1) {
                $locataireLabel = 'Locataire :';
                error_log("PDF Generation Legacy: Locataire unique - Label: 'Locataire'");
            } else {
                $locataireLabel = 'Locataire ' . ($i + 1) . ' :';
                error_log("PDF Generation Legacy: Locataire " . ($i + 1) . "/" . $nbLocataires . " - Label: 'Locataire " . ($i + 1) . "'");
            }
            $this->Cell(0, 5, $locataireLabel, 0, 1, 'L');
            $this->SetFont('helvetica', '', 8);
            
            // Nom du locataire
            $this->Cell(0, 4, $locataire['prenom'] . ' ' . $locataire['nom'], 0, 1, 'L');
            
            // Mention "Lu et approuvé"
            if (!empty($locataire['mention_lu_approuve'])) {
                $this->Cell(0, 4, $locataire['mention_lu_approuve'], 0, 1, 'L');
            } else {
                $this->Cell(0, 4, 'Lu et approuvé', 0, 1, 'L');
            }
            
            // Afficher la signature si disponible
            if (!empty($locataire['signature_data'])) {
                $this->Ln(1);
                error_log("PDF Generation Legacy: Signature client " . ($i + 1) . " - Données présentes (" . strlen($locataire['signature_data']) . " octets)");
                $imgData = $locataire['signature_data'];
                
                // Check if it's a physical file path (new format) or base64 data URI (legacy format)
                if (preg_match('/^uploads\/signatures\//', $imgData)) {
                    // New format: physical file path
                    $baseDir = dirname(dirname(__DIR__));
                    $fullPath = $baseDir . '/' . $imgData;
                    
                    if (file_exists($fullPath)) {
                        error_log("PDF Generation Legacy: Signature client " . ($i + 1) . " - Format: Fichier physique");
                        try {
                            // Signature client (15mm) with increased spacing
                            $this->Image($fullPath, $this->GetX(), $this->GetY(), 15, 0, 'PNG', '', '', false, 300, '', false, false, 0);
                            $this->Ln(12); // Increased spacing after image to prevent overlap
                        } catch (Exception $e) {
                            error_log("PDF Generation Legacy: ERREUR lors du rendu de la signature physique: " . $e->getMessage());
                        }
                    } else {
                        error_log("PDF Generation Legacy: ERREUR - Fichier de signature introuvable: " . $fullPath);
                    }
                } elseif (preg_match('/^data:image\/(png|jpeg|jpg);base64,(.+)$/', $imgData, $matches)) {
                    // Legacy format: base64 data URI
                    $imageFormat = strtoupper($matches[1] === 'jpg' ? 'JPEG' : $matches[1]);
                    $imageData = base64_decode($matches[2], true);
                    
                    // Vérifier que le décodage a réussi et que les données ne sont pas vides
                    if ($imageData !== false && !empty($imageData)) {
                        // Créer un fichier temporaire unique et sécurisé
                        $tempFile = tempnam(sys_get_temp_dir(), 'sig_');
                        
                        if ($tempFile !== false) {
                            // Écrire les données de l'image dans le fichier temporaire
                            if (file_put_contents($tempFile, $imageData) !== false) {
                                try {
                                    // Signature client (15mm) with increased spacing
                                    error_log("PDF Generation Legacy: Signature client " . ($i + 1) . " - Format: Legacy base64, Ajoutée avec taille (15mm)");
                                    $this->Image($tempFile, $this->GetX(), $this->GetY(), 15, 0, $imageFormat, '', '', false, 300, '', false, false, 0);
                                    $this->Ln(12); // Increased spacing after image to prevent overlap
                                } catch (Exception $e) {
                                    // Log l'erreur mais continue la génération du PDF
                                    error_log("PDF Generation Legacy: ERREUR lors du rendu de la signature: " . $e->getMessage());
                                }
                                
                                // Supprimer le fichier temporaire
                                if (file_exists($tempFile)) {
                                    if (!unlink($tempFile)) {
                                        error_log("PDF Generation Legacy: Impossible de supprimer le fichier temporaire: $tempFile");
                                    }
                                }
                            } else {
                                error_log("PDF Generation Legacy: Impossible d'écrire le fichier temporaire pour la signature");
                            }
                        } else {
                            error_log("PDF Generation Legacy: Impossible de créer le fichier temporaire pour la signature");
                        }
                    } else {
                        error_log("PDF Generation Legacy: Décodage base64 invalide pour la signature du locataire");
                    }
                } else {
                    error_log("PDF Generation Legacy: Format de signature invalide (ni fichier physique ni data URI valide)");
                }
            } else {
                error_log("PDF Generation Legacy: Signature client " . ($i + 1) . " - Non disponible (champ vide)");
            }
            
            // Horodatage et IP si disponibles
            if (!empty($locataire['signature_timestamp']) || !empty($locataire['signature_ip'])) {
                $this->SetFont('helvetica', 'I', 7);
                if (!empty($locataire['signature_timestamp'])) {
                    $timestampParsed = strtotime($locataire['signature_timestamp']);
                    if ($timestampParsed !== false) {
                        $timestamp = date('d/m/Y à H:i:s', $timestampParsed);
                        $this->Cell(0, 3, 'Horodatage : ' . $timestamp, 0, 1, 'L');
                    } else {
                        error_log("Impossible de parser le timestamp de signature: " . $locataire['signature_timestamp']);
                    }
                }
                if (!empty($locataire['signature_ip'])) {
                    $this->Cell(0, 3, 'Adresse IP : ' . $locataire['signature_ip'], 0, 1, 'L');
                }
                $this->SetFont('helvetica', '', 8);
            }
            
            $this->Ln(2);
        }
    }
    
    /**
     * Ajouter une section (titre principal)
     */
    private function addSection($title) {
        $this->Ln(2);
        $this->SetFont('helvetica', 'B', 9);
        $this->Cell(0, 5, $title, 0, 1, 'L');
        $this->SetFont('helvetica', '', 9);
    }
    
    /**
     * Ajouter une sous-section
     */
    private function addSubSection($title) {
        $this->SetFont('helvetica', 'B', 9);
        $this->Cell(0, 4, $title, 0, 1, 'L');
        $this->SetFont('helvetica', '', 9);
    }
    
    /**
     * Ajouter du texte
     */
    private function addText($text) {
        $this->MultiCell(0, 4, $text, 0, 'L');
    }
    
    /**
     * Ajouter une case à cocher
     */
    private function addCheckbox($text, $checked = true) {
        $checkbox = $checked ? '☒' : '☐';
        $this->MultiCell(0, 4, $checkbox . ' ' . $text, 0, 'L');
    }
}
