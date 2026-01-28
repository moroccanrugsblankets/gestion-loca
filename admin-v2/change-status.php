<?php
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

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
    $message = '';
    
    switch ($nouveau_statut) {
        case 'Accepté':
            $subject = "Candidature acceptée - MyInvest Immobilier";
            $message = "Bonjour $nom_complet,\n\n";
            $message .= "Nous avons le plaisir de vous informer que votre candidature a été acceptée.\n\n";
            $message .= "Nous vous contacterons prochainement pour organiser une visite du logement.\n\n";
            $message .= "Cordialement,\nMyInvest Immobilier";
            break;
            
        case 'Refusé':
            $subject = "Suite à votre candidature - MyInvest Immobilier";
            $message = "Bonjour $nom_complet,\n\n";
            $message .= "Nous vous remercions pour l'intérêt que vous portez à nos logements.\n\n";
            $message .= "Malheureusement, nous ne pouvons pas donner suite à votre candidature à ce stade.\n\n";
            $message .= "Nous vous souhaitons bonne continuation dans vos recherches.\n\n";
            $message .= "Cordialement,\nMyInvest Immobilier";
            break;
            
        case 'Visite planifiée':
            $subject = "Visite de logement planifiée - MyInvest Immobilier";
            $message = "Bonjour $nom_complet,\n\n";
            $message .= "Votre visite du logement a été planifiée.\n\n";
            $message .= "Nous vous contacterons prochainement pour confirmer la date et l'heure.\n\n";
            $message .= "Cordialement,\nMyInvest Immobilier";
            break;
            
        case 'Contrat envoyé':
            $subject = "Contrat de bail - MyInvest Immobilier";
            $message = "Bonjour $nom_complet,\n\n";
            $message .= "Votre contrat de bail est prêt.\n\n";
            $message .= "Vous allez recevoir un lien pour le signer électroniquement.\n\n";
            $message .= "Cordialement,\nMyInvest Immobilier";
            break;
            
        case 'Contrat signé':
            $subject = "Contrat signé - MyInvest Immobilier";
            $message = "Bonjour $nom_complet,\n\n";
            $message .= "Nous avons bien reçu votre contrat signé.\n\n";
            $message .= "Nous vous contacterons prochainement pour les modalités d'entrée dans le logement.\n\n";
            $message .= "Cordialement,\nMyInvest Immobilier";
            break;
    }
    
    if ($subject && $message) {
        if ($commentaire) {
            $message .= "\n\nNote: $commentaire";
        }
        
        $headers = "From: contact@myinvest-immobilier.com\r\n";
        $headers .= "Reply-To: contact@myinvest-immobilier.com\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        mail($to, $subject, $message, $headers);
        
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
    }
}

$_SESSION['success'] = "Statut mis à jour avec succès";
header('Location: candidature-detail.php?id=' . $candidature_id);
exit;
