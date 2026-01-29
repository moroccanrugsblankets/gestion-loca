<?php
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $candidature_id = (int)$_POST['candidature_id'];
    $sujet = $_POST['sujet'];
    $message = $_POST['message'];
    
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
    
    $html_message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #2c3e50;'>Bonjour " . htmlspecialchars($candidature['prenom']) . " " . htmlspecialchars($candidature['nom']) . ",</h2>
            <div style='background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;'>
                " . nl2br(htmlspecialchars($message)) . "
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
        // In production, use PHPMailer
        if (defined('SMTP_HOST') && function_exists('sendEmailWithPHPMailer')) {
            // Use PHPMailer if available
            $result = sendEmailWithPHPMailer($to, $sujet, $html_message);
        } else {
            // Fallback to mail()
            $result = mail($to, $sujet, $html_message, $headers);
        }
        
        if ($result) {
            // Log the action
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO logs (candidature_id, action, details, created_at) 
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $candidature_id,
                    'Email envoyé',
                    'Sujet: ' . $sujet
                ]);
            } catch (PDOException $e) {
                // If candidature_id column doesn't exist, try polymorphic structure
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO logs (type_entite, entite_id, action, details, created_at) 
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        'candidature',
                        $candidature_id,
                        'Email envoyé',
                        'Sujet: ' . $sujet
                    ]);
                } catch (PDOException $e2) {
                    // Log error but don't fail
                    error_log("Error logging email action: " . $e2->getMessage());
                }
            }
            
            $_SESSION['success'] = "Email envoyé avec succès";
        } else {
            $_SESSION['error'] = "Erreur lors de l'envoi de l'email";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
    }
    
    header('Location: candidature-detail.php?id=' . $candidature_id);
    exit;
}

header('Location: candidatures.php');
exit;
