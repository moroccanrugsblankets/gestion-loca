<?php
/**
 * Générateur d'aperçu des emails HTML
 * Crée des fichiers HTML de démonstration pour visualiser les emails
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/mail-templates.php';

// Créer le dossier de démonstration
$demoDir = __DIR__ . '/demo-emails';
if (!is_dir($demoDir)) {
    mkdir($demoDir, 0755, true);
}

echo "Génération des aperçus d'emails HTML...\n\n";

// 1. Email de candidature reçue
$logement = [
    'reference' => 'LOG-2024-001',
    'type' => 'Appartement T2',
    'adresse' => '123 Avenue des Champs-Élysées, 75008 Paris',
    'loyer' => 1500
];

$html1 = getCandidatureRecueEmailHTML('Jean', 'Dupont', $logement, 5);
file_put_contents($demoDir . '/1-candidature-recue.html', $html1);
echo "✓ 1-candidature-recue.html créé\n";

// 2. Email d'invitation à signer
$signatureLink = 'https://myinvest-immobilier.com/signature/index.php?token=abc123def456';
$html2 = getInvitationSignatureEmailHTML($signatureLink, '123 Avenue des Champs-Élysées, 75008 Paris', 2);
file_put_contents($demoDir . '/2-invitation-signature.html', $html2);
echo "✓ 2-invitation-signature.html créé\n";

// 3. Emails de changement de statut
$statuts = ['Accepté', 'Refusé', 'Visite planifiée', 'Contrat envoyé', 'Contrat signé'];
foreach ($statuts as $index => $statut) {
    $html = getStatusChangeEmailHTML('Jean Dupont', $statut, 'Ceci est un commentaire de test pour le statut.');
    $filename = ($index + 3) . '-statut-' . strtolower(str_replace(' ', '-', $statut)) . '.html';
    file_put_contents($demoDir . '/' . $filename, $html);
    echo "✓ $filename créé\n";
}

echo "\n=== Aperçus générés avec succès ===\n";
echo "Les fichiers HTML se trouvent dans le dossier: $demoDir\n";
echo "Ouvrez-les dans votre navigateur pour voir les designs des emails.\n";

// Créer un fichier index.html pour faciliter la navigation
$indexHtml = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aperçu des Templates Email - My Invest Immobilier</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        h1 { color: #667eea; }
        .template-list { list-style: none; padding: 0; }
        .template-list li { margin: 15px 0; }
        .template-list a { 
            display: block; 
            padding: 15px; 
            background: #f8f9fa; 
            border-left: 4px solid #667eea; 
            text-decoration: none; 
            color: #333; 
            border-radius: 4px;
            transition: all 0.3s;
        }
        .template-list a:hover { 
            background: #e9ecef; 
            transform: translateX(5px);
        }
        .description { 
            color: #666; 
            font-size: 14px; 
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <h1>📧 Aperçu des Templates Email</h1>
    <p>My Invest Immobilier - PHPMailer avec design HTML</p>
    
    <h2>Templates disponibles :</h2>
    <ul class="template-list">
        <li>
            <a href="1-candidature-recue.html" target="_blank">
                <strong>1. Email de Candidature Reçue</strong>
                <div class="description">Envoyé au candidat après soumission de sa candidature</div>
            </a>
        </li>
        <li>
            <a href="2-invitation-signature.html" target="_blank">
                <strong>2. Email d\'Invitation à Signer</strong>
                <div class="description">Envoyé pour inviter le locataire à signer le contrat de bail</div>
            </a>
        </li>
        <li>
            <a href="3-statut-accepté.html" target="_blank">
                <strong>3. Email Candidature Acceptée</strong>
                <div class="description">Notification de candidature acceptée</div>
            </a>
        </li>
        <li>
            <a href="4-statut-refusé.html" target="_blank">
                <strong>4. Email Candidature Refusée</strong>
                <div class="description">Notification de candidature refusée</div>
            </a>
        </li>
        <li>
            <a href="5-statut-visite-planifiée.html" target="_blank">
                <strong>5. Email Visite Planifiée</strong>
                <div class="description">Notification de visite planifiée</div>
            </a>
        </li>
        <li>
            <a href="6-statut-contrat-envoyé.html" target="_blank">
                <strong>6. Email Contrat Envoyé</strong>
                <div class="description">Notification d\'envoi du contrat</div>
            </a>
        </li>
        <li>
            <a href="7-statut-contrat-signé.html" target="_blank">
                <strong>7. Email Contrat Signé</strong>
                <div class="description">Confirmation de signature du contrat</div>
            </a>
        </li>
    </ul>
    
    <hr style="margin: 40px 0;">
    <p style="color: #666; font-size: 14px;">
        <strong>Note :</strong> Ces aperçus sont générés pour la démonstration. 
        Les emails réels seront personnalisés avec les données de chaque candidat/contrat.
    </p>
</body>
</html>';

file_put_contents($demoDir . '/index.html', $indexHtml);
echo "\n✓ index.html créé pour la navigation\n";
echo "\nOuvrez demo-emails/index.html dans votre navigateur pour voir tous les templates.\n";
