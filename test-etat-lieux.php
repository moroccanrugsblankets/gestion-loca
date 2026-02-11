<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/pdf/generate-etat-lieux.php';

// ID d’un contrat existant en base
$contratId = 52; 
$type = 'entree'; // ou 'sortie'

// Générer le PDF
$pdfPath = generateEtatDesLieuxPDF($contratId, $type);

if ($pdfPath) {
    echo "PDF généré avec succès : " . $pdfPath;
} else {
    echo "Erreur lors de la génération du PDF.";
}