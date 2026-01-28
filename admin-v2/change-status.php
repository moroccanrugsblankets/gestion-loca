<?php
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';
require_once '../includes/mail-templates.php';

$pdo = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: candidatures.php');
    exit;
}

$candidature_id = isset($_POST['candidature_id']) ? (int)$_POST['candidature_id'] : 0;
$nouveau_statut = isset($_POST['nouveau_statut']) ? trim($_POST['nouveau_statut']) : '';
$commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';
$send_email = isset($_POST['send_email']);

if (!$candidature_id || !$nouveau_statut) {
    $_SESSION['error'] = "Données invalides";
    header('Location: candidatures.php');
    exit;
}

// Get current candidature
$stmt = $pdo->prepare("SELECT * FROM candidatures WHERE id = ?");
$stmt->execute([$candidature_id]);
$candidature = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$candidature) {
    $_SESSION['error'] = "Candidature non trouvée";
    header('Location: candidatures.php');
    exit;
}

$ancien_statut = $candidature['statut'];

// Update status
$stmt = $pdo->prepare("UPDATE candidatures SET statut = ? WHERE id = ?");
$stmt->execute([$nouveau_statut, $candidature_id]);

// Log the action
$action = "Changement de statut: $ancien_statut → $nouveau_statut";
$details = $commentaire ? "Commentaire: $commentaire" : null;

$stmt = $pdo->prepare("
    INSERT INTO logs (candidature_id, action, details, ip_address, created_at)
    VALUES (?, ?, ?, ?, NOW())
");
$stmt->execute([
    $candidature_id,
    $action,
    $details,
    $_SERVER['REMOTE_ADDR']
]);

// Send email if requested
if ($send_email) {
    $to = $candidature['email'];
    $nom_complet = $candidature['prenom'] . ' ' . $candidature['nom'];
    
    // Email templates based on status
    $subject = '';
    
    switch ($nouveau_statut) {
        case 'Accepté':
            $subject = "Candidature acceptée - MyInvest Immobilier";
            break;
            
        case 'Refusé':
            $subject = "Suite à votre candidature - MyInvest Immobilier";
            break;
            
        case 'Visite planifiée':
            $subject = "Visite de logement planifiée - MyInvest Immobilier";
            break;
            
        case 'Contrat envoyé':
            $subject = "Contrat de bail - MyInvest Immobilier";
            break;
            
        case 'Contrat signé':
            $subject = "Contrat signé - MyInvest Immobilier";
            break;
    }
    
    if ($subject) {
        // Générer l'email HTML
        $htmlBody = getStatusChangeEmailHTML($nom_complet, $nouveau_statut, $commentaire);
        
        // Envoyer l'email avec PHPMailer
        $emailSent = sendEmail($to, $subject, $htmlBody, null, true);
        
        if ($emailSent) {
            // Log email sent
            $stmt = $pdo->prepare("
                INSERT INTO logs (candidature_id, action, details, ip_address, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $candidature_id,
                "Email envoyé",
                "Objet: $subject",
                $_SERVER['REMOTE_ADDR']
            ]);
        } else {
            error_log("Erreur lors de l'envoi de l'email à $to pour le changement de statut");
        }
    }
}

$_SESSION['success'] = "Statut mis à jour avec succès";
header('Location: candidature-detail.php?id=' . $candidature_id);
exit;
