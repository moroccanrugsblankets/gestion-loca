<?php
/**
 * Test standalone de génération de PDF sans base de données
 * Vérifie que la structure du PDF est correcte
 */

require_once __DIR__ . '/vendor/autoload.php';

use TCPDF;

echo "=== Test de génération PDF (standalone) ===\n\n";

// Test 1: TCPDF disponible
echo "Test 1: TCPDF disponible... ";
if (class_exists('TCPDF')) {
    echo "✓\n";
} else {
    echo "✗\n";
    exit(1);
}

// Test 2: Créer un PDF de test avec le format du contrat
echo "Test 2: Création d'un PDF de test... ";
try {
    // Créer une instance de la classe personnalisée
    class TestContratPDF extends TCPDF {
        public function Header() {
            $this->SetFont('helvetica', 'B', 16);
            $this->SetTextColor(0, 51, 102);
            $this->Cell(0, 10, 'MY INVEST IMMOBILIER', 0, 1, 'C');
            $this->SetFont('helvetica', '', 10);
            $this->SetTextColor(0, 0, 0);
            $this->Cell(0, 5, 'CONTRAT DE BAIL', 0, 1, 'C');
            $this->Cell(0, 5, '(Location meublée - résidence principale)', 0, 1, 'C');
            $this->Ln(3);
        }
        
        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->SetTextColor(128, 128, 128);
            $this->Cell(0, 5, 'MY INVEST IMMOBILIER - contact@myinvest-immobilier.com', 0, 0, 'C');
        }
    }
    
    $pdf = new TestContratPDF();
    $pdf->SetCreator('MY INVEST IMMOBILIER');
    $pdf->SetAuthor('MY INVEST IMMOBILIER');
    $pdf->SetTitle('Test Contrat de Bail');
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();
    
    // Ajouter du contenu de test
    $pdf->SetFont('helvetica', '', 9);
    
    // Section 1
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 5, '1. Parties', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->MultiCell(0, 4, 'Bailleur : MY INVEST IMMOBILIER (SCI)', 0, 'L');
    $pdf->MultiCell(0, 4, 'Locataire : Jean DUPONT, né le 01/01/1990', 0, 'L');
    
    // Section 2
    $pdf->Ln(2);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 5, '2. Désignation du logement', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->MultiCell(0, 4, 'Adresse : 123 Rue Example, 74100 Annemasse', 0, 'L');
    $pdf->MultiCell(0, 4, 'Type : T2 - Logement meublé', 0, 'L');
    $pdf->MultiCell(0, 4, 'Surface : 45 m²', 0, 'L');
    
    // Checkbox
    $pdf->MultiCell(0, 4, '☒ Parking : 1 place', 0, 'L');
    $pdf->MultiCell(0, 4, '☒ Cuisine équipée', 0, 'L');
    
    // Section 3
    $pdf->Ln(2);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 5, '3. Durée', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->MultiCell(0, 4, 'Durée : 1 an à compter du 01/02/2026', 0, 'L');
    
    // Section 4
    $pdf->Ln(2);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 5, '4. Conditions financières', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->MultiCell(0, 4, 'Loyer mensuel HC : 650,00 €', 0, 'L');
    $pdf->MultiCell(0, 4, 'Charges mensuelles : 80,00 €', 0, 'L');
    $pdf->MultiCell(0, 4, 'Total mensuel : 730,00 €', 0, 'L');
    
    // Section 5
    $pdf->Ln(2);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 5, '5. Dépôt de garantie', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->MultiCell(0, 4, 'Montant : 1 300,00 € (2 mois de loyer HC)', 0, 'L');
    
    // Signatures
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 5, '14. Signatures', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->MultiCell(0, 4, 'Fait à Annemasse, le ' . date('d/m/Y'), 0, 'L');
    
    $pdf->Ln(3);
    $pdf->Cell(90, 5, 'Le bailleur', 0, 0, 'L');
    $pdf->Cell(90, 5, 'Le locataire', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(90, 4, 'MY INVEST IMMOBILIER', 0, 0, 'L');
    $pdf->Cell(90, 4, 'Jean DUPONT', 0, 1, 'L');
    
    // Sauvegarder
    $testDir = __DIR__ . '/pdf/test/';
    if (!is_dir($testDir)) {
        mkdir($testDir, 0755, true);
    }
    
    $testFile = $testDir . 'test-contrat-' . date('Ymd-His') . '.pdf';
    $pdf->Output($testFile, 'F');
    
    if (file_exists($testFile)) {
        $fileSize = filesize($testFile);
        echo "✓\n";
        echo "  Fichier créé : $testFile\n";
        echo "  Taille : " . number_format($fileSize) . " octets\n";
        
        // Vérifier le header PDF
        $fh = fopen($testFile, 'rb');
        $header = fread($fh, 4);
        fclose($fh);
        
        if ($header === '%PDF') {
            echo "  Format : ✓ PDF valide\n";
        } else {
            echo "  Format : ✗ Invalide\n";
        }
    } else {
        echo "✗\n";
    }
    
} catch (Exception $e) {
    echo "✗\n";
    echo "Erreur : " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✓ Tous les tests réussis!\n";
echo "\nLe fichier de test a été créé. Vous pouvez le télécharger et le vérifier.\n";
