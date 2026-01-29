<?php
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

// Helper function to log candidature actions
function logCandidatureAction($pdo, $candidature_id, $action, $details = '') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO logs (candidature_id, action, details, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$candidature_id, $action, $details]);
    } catch (PDOException $e) {
        // If candidature_id column doesn't exist, try polymorphic structure
        try {
            $stmt = $pdo->prepare("
                INSERT INTO logs (type_entite, entite_id, action, details, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute(['candidature', $candidature_id, $action, $details]);
        } catch (PDOException $e2) {
            // Log error but don't fail
            error_log("Error logging action: " . $e2->getMessage());
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $candidature_id = (int)$_POST['candidature_id'];
    $sujet = trim($_POST['sujet']);
    $message = trim($_POST['message']);
    
    // Validate inputs
    if (empty($sujet) || empty($message)) {
        $_SESSION['error'] = "Le sujet et le message sont requis";
        header('Location: candidature-detail.php?id=' . $candidature_id);
        exit;
    }
    
    // Prevent email header injection
    if (preg_match("/[\r\n]/", $sujet)) {
        $_SESSION['error'] = "Le sujet contient des caractères invalides";
        header('Location: candidature-detail.php?id=' . $candidature_id);
        exit;
    }
    
    // Limit lengths
    if (strlen($sujet) > 200 || strlen($message) > 5000) {
        $_SESSION['error'] = "Le sujet ou le message est trop long";
        header('Location: candidature-detail.php?id=' . $candidature_id);
        exit;
    }
    
    // Get candidature details
    $stmt = $pdo->prepare("SELECT * FROM candidatures WHERE id = ?");
    $stmt->execute([$candidature_id]);
    $candidature = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$candidature) {
        $_SESSION['error'] = "Candidature non trouvée";
        header('Location: candidatures.php');
        exit;
    }
    
    // Send email using PHPMailer or mail function
    // For now, we'll use PHP's mail() function (should be replaced with PHPMailer in production)
    $to = $candidature['email'];
    $headers = "From: " . SMTP_FROM_EMAIL . "\r\n";
    $headers .= "Reply-To: " . SMTP_FROM_EMAIL . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    // Sanitize message content
    $safe_message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    
    $html_message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #2c3e50;'>Bonjour " . htmlspecialchars($candidature['prenom'], ENT_QUOTES, 'UTF-8') . " " . htmlspecialchars($candidature['nom'], ENT_QUOTES, 'UTF-8') . ",</h2>
            <div style='background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;'>
                " . nl2br($safe_message) . "
            </div>
            <hr style='border: none; border-top: 1px solid #dee2e6; margin: 20px 0;'>
            <p style='color: #6c757d; font-size: 0.9em;'>
                Cordialement,<br>
                L'équipe MY Invest Immobilier
            </p>
        </div>
    </body>
    </html>
    ";
    
    try {
        // Use mail() function (should be replaced with PHPMailer in production)
        $result = mail($to, $sujet, $html_message, $headers);
        
        if ($result) {
            // Log the action - helper function to avoid code duplication
            logCandidatureAction($pdo, $candidature_id, 'Email envoyé', 'Sujet: ' . $sujet);
            $_SESSION['success'] = "Email envoyé avec succès";
        } else {
            $_SESSION['error'] = "Erreur lors de l'envoi de l'email";
        }
    } catch (Exception $e) {
        error_log("Error sending email: " . $e->getMessage());
        $_SESSION['error'] = "Erreur lors de l'envoi de l'email";
    }
    
    header('Location: candidature-detail.php?id=' . $candidature_id);
    exit;
}

header('Location: candidatures.php');
exit;
