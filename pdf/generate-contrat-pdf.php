<?php
/**
 * Génération du PDF du contrat de bail - Format MY INVEST IMMOBILIER
 * Utilise TCPDF pour une mise en page professionnelle
 * Format: 1 page, style original conforme au modèle
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

/**
 * Générer le PDF du contrat de bail
 * @param int $contratId ID du contrat
 * @return string|false Chemin du fichier PDF généré ou false en cas d'erreur
 */
function generateContratPDF($contratId) {
    global $config, $pdo;
    
    try {
        // Récupérer les données du contrat
        $stmt = $pdo->prepare("
            SELECT c.*, l.*, 
                   ca.nom as candidat_nom, ca.prenom as candidat_prenom, ca.email as candidat_email
            FROM contrats c
            INNER JOIN logements l ON c.logement_id = l.id
            LEFT JOIN candidatures ca ON c.candidature_id = ca.id
            WHERE c.id = ?
        ");
        $stmt->execute([$contratId]);
        $contrat = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$contrat) {
            error_log("Contrat #$contratId non trouvé");
            return false;
        }
        
        // Récupérer les locataires
        $stmt = $pdo->prepare("
            SELECT * FROM locataires 
            WHERE contrat_id = ? 
            ORDER BY ordre ASC
        ");
        $stmt->execute([$contratId]);
        $locataires = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($locataires)) {
            error_log("Aucun locataire trouvé pour le contrat #$contratId");
            return false;
        }
        
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
        
        return $filepath;
        
    } catch (Exception $e) {
        error_log("Erreur génération PDF contrat #$contratId: " . $e->getMessage());
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
        $this->Cell(0, 4, 'MY INVEST IMMOBILIER', 0, 1, 'L');
        $this->Cell(0, 4, 'Représenté par M. ALEXANDRE', 0, 1, 'L');
        $this->Cell(0, 4, 'Lu et approuvé', 0, 1, 'L');
        
        // Add company signature image if contract is validated and signature is enabled
        if (isset($contrat['statut']) && $contrat['statut'] === 'valide') {
            $signatureImage = getParametreValue('signature_societe_image');
            $signatureEnabled = getParametreValue('signature_societe_enabled') === 'true';
            
            if ($signatureEnabled && !empty($signatureImage)) {
                // Check if it's a data URI
                if (strpos($signatureImage, 'data:image') === 0) {
                    // Extract the base64 data
                    $parts = explode(',', $signatureImage);
                    if (count($parts) === 2) {
                        $imgData = base64_decode($parts[1]);
                        if ($imgData !== false) {
                            // Create temporary file for signature
                            $tempFile = tempnam(sys_get_temp_dir(), 'company_sig_');
                            if ($tempFile !== false && file_put_contents($tempFile, $imgData) !== false) {
                                try {
                                    // Insert company signature image (max 40mm width, proportional height)
                                    $this->Image($tempFile, $this->GetX(), $this->GetY(), 40, 0);
                                    @unlink($tempFile);
                                } catch (Exception $e) {
                                    error_log("Error rendering company signature: " . $e->getMessage());
                                    @unlink($tempFile);
                                }
                            } else {
                                error_log("Could not create temporary file for company signature");
                            }
                        } else {
                            error_log("Invalid base64 data for company signature");
                        }
                    }
                }
            }
        }
        
        $this->Ln(3);
        
        // Signatures des locataires
        foreach ($locataires as $i => $locataire) {
            $this->SetFont('helvetica', 'B', 9);
            $locataireLabel = count($locataires) > 1 ? 'Le locataire ' . ($i + 1) : 'Le locataire';
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
                // Créer un fichier temporaire pour la signature
                $imgData = $locataire['signature_data'];
                // Vérifier que c'est un data URL base64 PNG (seul format accepté)
                if (preg_match('/^data:image\/png;base64,(.+)$/', $imgData, $matches)) {
                    $imageData = base64_decode($matches[1], true);
                    
                    // Vérifier que le décodage a réussi et que les données ne sont pas vides
                    if ($imageData !== false && !empty($imageData)) {
                        // Créer un fichier temporaire unique et sécurisé
                        $tempFile = tempnam(sys_get_temp_dir(), 'sig_');
                        
                        if ($tempFile !== false) {
                            // Écrire les données de l'image dans le fichier temporaire
                            if (file_put_contents($tempFile, $imageData) !== false) {
                                try {
                                    // Insérer l'image de signature (max 40mm de largeur, hauteur proportionnelle)
                                    $this->Image($tempFile, $this->GetX(), $this->GetY(), 40, 0, 'PNG');
                                    $this->Ln(20); // Espace après l'image
                                } catch (Exception $e) {
                                    // Log l'erreur mais continue la génération du PDF
                                    error_log("Erreur lors du rendu de la signature: " . $e->getMessage());
                                }
                                
                                // Supprimer le fichier temporaire
                                if (file_exists($tempFile)) {
                                    if (!unlink($tempFile)) {
                                        error_log("Impossible de supprimer le fichier temporaire: $tempFile");
                                    }
                                }
                            } else {
                                error_log("Impossible d'écrire le fichier temporaire pour la signature");
                            }
                        } else {
                            error_log("Impossible de créer le fichier temporaire pour la signature");
                        }
                    } else {
                        error_log("Décodage base64 invalide pour la signature du locataire");
                    }
                }
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
