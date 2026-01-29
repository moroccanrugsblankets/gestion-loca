<?php
/**
 * Templates d'emails
 * My Invest Immobilier
 */

// Charger PHPMailer
// Si installé via Composer, utiliser l'autoload standard
if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

// Sinon, charger manuellement (installation manuelle)
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    require_once dirname(__DIR__) . '/vendor/phpmailer/phpmailer/src/Exception.php';
    require_once dirname(__DIR__) . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
    require_once dirname(__DIR__) . '/vendor/phpmailer/phpmailer/src/SMTP.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

/**
 * Template email d'invitation à signer le bail
 * @param string $signatureLink
 * @param array $logement
 * @return array ['subject' => string, 'body' => string]
 */
function getInvitationEmailTemplate($signatureLink, $logement) {
    global $config;
    $subject = "Contrat de bail à signer – Action immédiate requise";
    
    $body = "Bonjour,

Merci de prendre connaissance de la procédure ci-dessous.

Procédure de signature du bail

Merci de compléter l'ensemble de la procédure dans un délai de 24 heures, à compter de la réception du présent message, incluant :
	1.	La signature du contrat de bail en ligne
	2.	La transmission d'une pièce d'identité en cours de validité (carte nationale d'identité ou passeport)
	3.	Le règlement immédiat du dépôt de garantie, correspondant à deux mois de loyer, par virement bancaire instantané

La prise d'effet du bail ainsi que la remise des clés interviendront uniquement après réception complète de l'ensemble des éléments ci-dessus.

À défaut de réception complète du dossier dans le délai indiqué, la réservation du logement pourra être remise en disponibilité sans autre formalité.

Pour accéder au contrat de bail : $signatureLink

Nous restons à votre disposition en cas de question.

Cordialement,
MY Invest Immobilier
" . $config['COMPANY_EMAIL'];
    
    return [
        'subject' => $subject,
        'body' => $body
    ];
}

/**
 * Template email de finalisation (après signature)
 * @param array $contrat
 * @param array $logement
 * @param array $locataires
 * @return array ['subject' => string, 'body' => string]
 */
function getFinalisationEmailTemplate($contrat, $logement, $locataires) {
    global $config;
    $subject = "Contrat de bail – Finalisation";
    
    $depotGarantie = formatMontant($logement['depot_garantie']);
    
    $body = "Bonjour,

Nous vous remercions pour votre confiance.

Veuillez trouver ci-joint une copie du contrat de bail dûment complété.

Nous vous rappelons que :

La prise d'effet du bail intervient après le règlement immédiat du dépôt de garantie, correspondant à deux mois de loyer ($depotGarantie), par virement bancaire instantané sur le compte suivant :

MY Invest Immobilier
IBAN : FR76 1027 8021 6000 0206 1834 585
BIC : CMCIFRA

Dès réception du règlement, nous vous confirmerons la prise d'effet du bail ainsi que les modalités de remise des clés.

Nous restons à votre disposition pour toute question.

Cordialement,
MY Invest Immobilier
" . $config['COMPANY_EMAIL'];
    
    return [
        'subject' => $subject,
        'body' => $body
    ];
}

/**
 * Envoyer un email avec PHPMailer
 * @param string $to Email du destinataire
 * @param string $subject Sujet de l'email
 * @param string $body Corps de l'email (peut être HTML ou texte)
 * @param string|array|null $attachmentPath Chemin(s) vers pièce(s) jointe(s) - peut être un string ou array de ['path' => ..., 'name' => ...]
 * @param bool $isHtml Si true, le corps sera traité comme HTML (par défaut: true)
 * @param bool $isAdminEmail Si true, envoie aussi à l'adresse secondaire si configurée
 * @param string|null $replyTo Email de réponse personnalisé (optionnel)
 * @param string|null $replyToName Nom pour l'email de réponse (optionnel)
 * @return bool True si l'email a été envoyé avec succès
 */
function sendEmail($to, $subject, $body, $attachmentPath = null, $isHtml = true, $isAdminEmail = false, $replyTo = null, $replyToName = null) {
    global $config, $pdo;
    $mail = new PHPMailer(true);
    
    try {
        // Get email signature from parametres if HTML email
        $signature = '';
        if ($isHtml && $pdo) {
            try {
                $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'email_signature' LIMIT 1");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result && !empty($result['valeur'])) {
                    $signature = $result['valeur'];
                }
            } catch (Exception $e) {
                // Silently fail if parametres table doesn't exist yet
                error_log("Could not fetch email signature: " . $e->getMessage());
            }
        }
        
        // Configuration du serveur SMTP
        if ($config['SMTP_AUTH']) {
            $mail->isSMTP();
            $mail->Host       = $config['SMTP_HOST'];
            $mail->SMTPAuth   = $config['SMTP_AUTH'];
            $mail->Username   = $config['SMTP_USERNAME'];
            $mail->Password   = $config['SMTP_PASSWORD'];
            $mail->SMTPSecure = $config['SMTP_SECURE'];
            $mail->Port       = $config['SMTP_PORT'];
            $mail->SMTPDebug  = $config['SMTP_DEBUG'];
        }
        
        // Encodage
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        
        // Expéditeur
        $mail->setFrom($config['MAIL_FROM'], $config['MAIL_FROM_NAME']);
        
        // Email de réponse personnalisé ou par défaut
        if ($replyTo) {
            $mail->addReplyTo($replyTo, $replyToName ?: $replyTo);
        } else {
            $mail->addReplyTo($config['MAIL_FROM'], $config['MAIL_FROM_NAME']);
        }
        
        // Destinataire principal
        $mail->addAddress($to);
        
        // Si c'est un email admin et qu'une adresse secondaire est configurée
        if ($isAdminEmail && !empty($config['ADMIN_EMAIL_SECONDARY'])) {
            $mail->addCC($config['ADMIN_EMAIL_SECONDARY']);
        }
        
        // Ajouter BCC pour contact@myinvest-immobilier.com si c'est un email admin
        if ($isAdminEmail && !empty($config['ADMIN_EMAIL_BCC'])) {
            $mail->addBCC($config['ADMIN_EMAIL_BCC']);
        }
        
        // Append signature to body if HTML and signature exists
        $finalBody = $body;
        if ($isHtml && !empty($signature)) {
            $finalBody = $body . '<br><br>' . $signature;
        }
        
        // Contenu
        $mail->isHTML($isHtml);
        $mail->Subject = $subject;
        $mail->Body    = $finalBody;
        
        // Si le contenu est HTML, générer une version texte alternative
        if ($isHtml) {
            $mail->AltBody = strip_tags($finalBody);
        }
        
        // Pièces jointes - supporter un seul fichier ou un array de fichiers
        if ($attachmentPath) {
            if (is_array($attachmentPath)) {
                foreach ($attachmentPath as $attachment) {
                    if (is_string($attachment) && file_exists($attachment)) {
                        // Simple chemin de fichier
                        $mail->addAttachment($attachment);
                    } elseif (is_array($attachment) && !empty($attachment['path']) && file_exists($attachment['path'])) {
                        // Array avec path et name optionnel
                        $mail->addAttachment($attachment['path'], $attachment['name'] ?? '');
                    }
                }
            } elseif (is_string($attachmentPath) && file_exists($attachmentPath)) {
                // Un seul fichier (backward compatibility)
                $mail->addAttachment($attachmentPath);
            }
        }
        
        // Envoyer l'email
        $result = $mail->send();
        
        // Logger le succès
        error_log("Email envoyé avec succès à: $to - Sujet: $subject");
        
        return $result;
        
    } catch (Exception $e) {
        // Logger l'erreur avec contexte approprié
        if ($mail instanceof PHPMailer) {
            error_log("Erreur PHPMailer lors de l'envoi à $to: {$mail->ErrorInfo}");
        }
        error_log("Exception lors de l'envoi d'email à $to: " . $e->getMessage());
        
        // En cas d'échec SMTP, essayer avec la fonction mail() native en fallback
        if ($config['SMTP_AUTH']) {
            error_log("Tentative de fallback avec mail() natif...");
            return sendEmailFallback($to, $subject, $body, $attachmentPath, $isHtml);
        }
        
        return false;
    }
}

/**
 * Fonction de fallback utilisant mail() natif de PHP
 * Utilisée si PHPMailer échoue
 */
function sendEmailFallback($to, $subject, $body, $attachmentPath = null, $isHtml = true) {
    global $config;
    try {
        if ($attachmentPath && file_exists($attachmentPath)) {
            // Email avec pièce jointe
            $boundary = md5(time());
            $headers = "From: " . $config['MAIL_FROM_NAME'] . " <" . $config['MAIL_FROM'] . ">\r\n";
            $headers .= "Reply-To: " . $config['MAIL_FROM'] . "\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
            
            $contentType = $isHtml ? "text/html" : "text/plain";
            $message = "--$boundary\r\n";
            $message .= "Content-Type: $contentType; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $message .= $body . "\r\n\r\n";
            
            // Pièce jointe
            $filename = basename($attachmentPath);
            $fileContent = chunk_split(base64_encode(file_get_contents($attachmentPath)));
            $message .= "--$boundary\r\n";
            $message .= "Content-Type: application/pdf; name=\"$filename\"\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n";
            $message .= "Content-Disposition: attachment; filename=\"$filename\"\r\n\r\n";
            $message .= $fileContent . "\r\n";
            $message .= "--$boundary--";
            
            $result = mail($to, $subject, $message, $headers);
        } else {
            // Email simple
            $headers = "From: " . $config['MAIL_FROM_NAME'] . " <" . $config['MAIL_FROM'] . ">\r\n";
            $headers .= "Reply-To: " . $config['MAIL_FROM'] . "\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
            
            if ($isHtml) {
                $headers .= "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            } else {
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            }
            
            $result = mail($to, $subject, $body, $headers);
        }
        
        if (!$result) {
            error_log("Fallback mail() a échoué pour l'envoi à $to");
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Exception dans fallback mail(): " . $e->getMessage());
        return false;
    }
}

/**
 * Template HTML pour l'email de candidature reçue
 * @param string $prenom Prénom du candidat
 * @param string $nom Nom du candidat
 * @param array $logement Informations du logement
 * @param int $uploaded_count Nombre de documents uploadés
 * @return string HTML de l'email
 */
function getCandidatureRecueEmailHTML($prenom, $nom, $logement, $uploaded_count) {
    global $config;
    $html = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px 20px; }
        .info-box { background: #f8f9fa; border-left: 4px solid #667eea; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .info-box h3 { margin-top: 0; color: #667eea; }
        .info-item { margin: 10px 0; }
        .info-item strong { color: #555; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
        .btn { display: inline-block; padding: 12px 24px; background: #667eea; color: #ffffff; text-decoration: none; border-radius: 4px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✓ Candidature Reçue</h1>
        </div>
        <div class="content">
            <p>Bonjour <strong>' . htmlspecialchars($prenom . ' ' . $nom) . '</strong>,</p>
            
            <p>Nous avons bien reçu votre candidature pour le logement <strong>' . htmlspecialchars($logement['reference']) . '</strong>.</p>
            
            <div class="info-box">
                <h3>📋 Informations de votre candidature</h3>
                <div class="info-item"><strong>Logement :</strong> ' . htmlspecialchars($logement['reference']) . ' - ' . htmlspecialchars($logement['type']) . '</div>
                <div class="info-item"><strong>Adresse :</strong> ' . htmlspecialchars($logement['adresse']) . '</div>
                <div class="info-item"><strong>Loyer :</strong> ' . htmlspecialchars($logement['loyer']) . ' €/mois</div>
                <div class="info-item"><strong>Documents joints :</strong> ' . $uploaded_count . ' pièce(s) justificative(s)</div>
            </div>
            
            <p>Il est actuellement en cours d\'étude. Une réponse vous sera apportée sous 1 à 4 jours ouvrés.</p>
            
            <p style="margin-top: 30px;">Sincères salutations</p>
            <br><br>
            <table style="border-collapse: collapse;">
                <tbody>
                    <tr>
                        <td style="vertical-align: middle;">
                            <img src="https://www.myinvest-immobilier.com/images/logo.png" alt="MY Invest Immobilier" style="max-width: 150px; height: auto;">
                        </td>
                        <td style="width: 20px;">&nbsp;</td>
                        <td style="vertical-align: middle;">
                            <h3 style="margin: 0; font-size: 18px; color: #333;">
                                MY INVEST IMMOBILIER
                            </h3>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="footer">
            <p>© ' . date('Y') . ' MY Invest Immobilier - Tous droits réservés</p>
            <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre directement.</p>
        </div>
    </div>
</body>
</html>';
    
    return $html;
}

/**
 * Template HTML pour l'email d'invitation à signer le bail
 * @param string $signatureLink Lien de signature
 * @param string $adresse Adresse du logement
 * @param int $nb_locataires Nombre de locataires
 * @return string HTML de l'email
 */
function getInvitationSignatureEmailHTML($signatureLink, $adresse, $nb_locataires) {
    global $config;
    $html = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px 20px; }
        .alert-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .info-box { background: #f8f9fa; border-left: 4px solid #667eea; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .btn { display: inline-block; padding: 15px 30px; background: #667eea; color: #ffffff !important; text-decoration: none; border-radius: 4px; margin: 20px 0; font-weight: bold; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📝 Contrat de Bail à Signer</h1>
        </div>
        <div class="content">
            <p>Bonjour,</p>
            
            <p>Merci de prendre connaissance de la procédure ci-dessous.</p>
            
            <div class="alert-box">
                <strong>⏰ Action immédiate requise</strong><br>
                Délai de 24 heures à compter de la réception de ce message
            </div>
            
            <h3>📋 Procédure de signature du bail</h3>
            <p>Merci de compléter l\'ensemble de la procédure dans un délai de 24 heures, incluant :</p>
            <ol>
                <li><strong>La signature du contrat de bail en ligne</strong></li>
                <li><strong>La transmission d\'une pièce d\'identité</strong> en cours de validité (CNI ou passeport)</li>
                <li><strong>Le règlement du dépôt de garantie</strong> (2 mois de loyer) par virement bancaire instantané</li>
            </ol>
            
            <div class="info-box">
                <p style="margin: 0;"><strong>Important :</strong></p>
                <ul style="margin: 10px 0 0 0;">
                    <li>La prise d\'effet du bail et la remise des clés interviendront uniquement après réception complète de l\'ensemble des éléments</li>
                    <li>À défaut de réception complète du dossier dans le délai indiqué, la réservation du logement pourra être remise en disponibilité sans autre formalité</li>
                </ul>
            </div>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="' . htmlspecialchars($signatureLink) . '" class="btn">🖊️ Accéder au Contrat de Bail</a>
            </div>
            
            <p>Nous restons à votre disposition en cas de question.</p>
            
            <p style="margin-top: 30px;">
                Cordialement,<br>
                <strong>MY Invest Immobilier</strong><br>
                <a href="mailto:' . $config['COMPANY_EMAIL'] . '">' . $config['COMPANY_EMAIL'] . '</a>
            </p>
        </div>
        <div class="footer">
            <p>© ' . date('Y') . ' MY Invest Immobilier - Tous droits réservés</p>
        </div>
    </div>
</body>
</html>';
    
    return $html;
}

/**
 * Template HTML pour les emails de changement de statut
 * @param string $nom_complet Nom complet du candidat
 * @param string $statut Nouveau statut
 * @param string $commentaire Commentaire optionnel
 * @return string HTML de l'email
 */
function getStatusChangeEmailHTML($nom_complet, $statut, $commentaire = '') {
    global $config;
    $title = '';
    $message = '';
    $color = '#667eea';
    
    switch ($statut) {
        case 'Accepté':
            $title = '✓ Candidature Acceptée';
            $message = 'Nous avons le plaisir de vous informer que votre candidature a été acceptée.';
            $message .= '<br><br>Nous vous contacterons prochainement pour organiser une visite du logement.';
            $color = '#28a745';
            break;
            
        case 'Refusé':
            $title = 'Suite à votre candidature';
            $message = 'Nous vous remercions pour l\'intérêt que vous portez à nos logements.';
            $message .= '<br><br>Malheureusement, nous ne pouvons pas donner suite à votre candidature à ce stade.';
            $message .= '<br><br>Nous vous souhaitons bonne continuation dans vos recherches.';
            $color = '#dc3545';
            break;
            
        case 'Visite planifiée':
            $title = '📅 Visite de Logement Planifiée';
            $message = 'Votre visite du logement a été planifiée.';
            $message .= '<br><br>Nous vous contacterons prochainement pour confirmer la date et l\'heure.';
            $color = '#17a2b8';
            break;
            
        case 'Contrat envoyé':
            $title = '📄 Contrat de Bail';
            $message = 'Votre contrat de bail est prêt.';
            $message .= '<br><br>Vous allez recevoir un lien pour le signer électroniquement.';
            $color = '#ffc107';
            break;
            
        case 'Contrat signé':
            $title = '✓ Contrat Signé';
            $message = 'Nous avons bien reçu votre contrat signé.';
            $message .= '<br><br>Nous vous contacterons prochainement pour les modalités d\'entrée dans le logement.';
            $color = '#28a745';
            break;
            
        default:
            $title = 'Mise à jour de votre candidature';
            $message = 'Votre candidature a été mise à jour.';
    }
    
    $html = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, ' . $color . ' 0%, ' . $color . 'dd 100%); color: #ffffff; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px 20px; }
        .message-box { background: #f8f9fa; border-left: 4px solid ' . $color . '; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>' . $title . '</h1>
        </div>
        <div class="content">
            <p>Bonjour <strong>' . htmlspecialchars($nom_complet) . '</strong>,</p>
            
            <div class="message-box">
                <p>' . $message . '</p>
            </div>';
    
    if ($commentaire) {
        $html .= '
            <div class="message-box" style="border-left-color: #6c757d;">
                <p><strong>Note :</strong> ' . nl2br(htmlspecialchars($commentaire)) . '</p>
            </div>';
    }
    
    $html .= '
            <p>Nous restons à votre disposition pour toute question.</p>
            
            <p style="margin-top: 30px;">
                Cordialement,<br>
                <strong>MY Invest Immobilier</strong><br>
                <a href="mailto:' . $config['COMPANY_EMAIL'] . '">' . $config['COMPANY_EMAIL'] . '</a>
            </p>
        </div>
        <div class="footer">
            <p>© ' . date('Y') . ' MY Invest Immobilier - Tous droits réservés</p>
        </div>
    </div>
</body>
</html>';
    
    return $html;
}

/**
 * Envoyer un email aux administrateurs (emails principal et secondaire)
 * @param string $subject Sujet de l'email
 * @param string $body Corps de l'email (peut être HTML ou texte)
 * @param string|array|null $attachmentPath Chemin(s) vers pièce(s) jointe(s) - peut être un string ou array
 * @param bool $isHtml Si true, le corps sera traité comme HTML (par défaut: true)
 * @param string|null $replyTo Email de réponse personnalisé (optionnel)
 * @param string|null $replyToName Nom pour l'email de réponse (optionnel)
 * @return array ['success' => bool, 'sent_to' => array, 'errors' => array]
 */
function sendEmailToAdmins($subject, $body, $attachmentPath = null, $isHtml = true, $replyTo = null, $replyToName = null) {
    global $config;
    
    $results = [
        'success' => false,
        'sent_to' => [],
        'errors' => []
    ];
    
    // Liste des emails administrateurs
    $adminEmails = [];
    
    // Email principal
    if (!empty($config['ADMIN_EMAIL'])) {
        // Validate email format
        if (filter_var($config['ADMIN_EMAIL'], FILTER_VALIDATE_EMAIL)) {
            $adminEmails[] = $config['ADMIN_EMAIL'];
        } else {
            $results['errors'][] = "Invalid ADMIN_EMAIL format: " . $config['ADMIN_EMAIL'];
            error_log("Invalid ADMIN_EMAIL configured: " . $config['ADMIN_EMAIL']);
        }
    }
    
    // Email secondaire (si configuré)
    if (!empty($config['ADMIN_EMAIL_SECONDARY'])) {
        // Validate email format
        if (filter_var($config['ADMIN_EMAIL_SECONDARY'], FILTER_VALIDATE_EMAIL)) {
            $adminEmails[] = $config['ADMIN_EMAIL_SECONDARY'];
        } else {
            $results['errors'][] = "Invalid ADMIN_EMAIL_SECONDARY format: " . $config['ADMIN_EMAIL_SECONDARY'];
            error_log("Invalid ADMIN_EMAIL_SECONDARY configured: " . $config['ADMIN_EMAIL_SECONDARY']);
        }
    }
    
    // Si aucun email admin configuré, utiliser l'email de la société
    if (empty($adminEmails) && !empty($config['COMPANY_EMAIL'])) {
        if (filter_var($config['COMPANY_EMAIL'], FILTER_VALIDATE_EMAIL)) {
            $adminEmails[] = $config['COMPANY_EMAIL'];
        }
    }
    
    // Count configured emails for partial success detection
    $totalConfigured = count($adminEmails);
    
    // Envoyer à chaque administrateur
    foreach ($adminEmails as $adminEmail) {
        try {
            $sent = sendEmail($adminEmail, $subject, $body, $attachmentPath, $isHtml, false, $replyTo, $replyToName);
            if ($sent) {
                $results['sent_to'][] = $adminEmail;
                $results['success'] = true; // Au moins un email envoyé
            } else {
                $results['errors'][] = "Échec d'envoi à $adminEmail";
            }
        } catch (Exception $e) {
            $results['errors'][] = "Exception lors de l'envoi à $adminEmail: " . $e->getMessage();
            error_log("Erreur sendEmailToAdmins pour $adminEmail: " . $e->getMessage());
        }
    }
    
    // Log warning if partial success (some but not all emails sent)
    if ($results['success'] && count($results['sent_to']) < $totalConfigured) {
        $partialMessage = "Partial success: " . count($results['sent_to']) . " of $totalConfigured admin emails sent";
        error_log("WARNING: $partialMessage");
        $results['partial_success'] = true;
    }
    
    return $results;
}

/**
 * Template HTML pour notification admin - Nouvelle candidature reçue
 * @param array $candidature Données de la candidature (doit inclure 'response_token')
 * @param array $logement Informations du logement
 * @param int $nb_documents Nombre de documents uploadés
 * @return string HTML de l'email
 */
function getAdminNewCandidatureEmailHTML($candidature, $logement, $nb_documents) {
    global $config;
    
    // Générer les liens de réponse si un token est fourni
    $responseLinksHtml = '';
    if (!empty($candidature['response_token'])) {
        $baseUrl = !empty($config['SITE_URL']) ? $config['SITE_URL'] : 'https://www.myinvest-immobilier.com';
        $linkPositive = $baseUrl . '/candidature/reponse-candidature.php?token=' . urlencode($candidature['response_token']) . '&action=positive';
        $linkNegative = $baseUrl . '/candidature/reponse-candidature.php?token=' . urlencode($candidature['response_token']) . '&action=negative';
        
        $responseLinksHtml = '
            <div class="info-box" style="background: #fff3cd; border-left-color: #ffc107;">
                <h3 style="color: #856404;">⚡ Actions Rapides</h3>
                <div style="text-align: center; margin: 15px 0;">
                    <a href="' . htmlspecialchars($linkPositive) . '" class="btn" style="background: #28a745; margin: 5px;">
                        ✓ Accepter la candidature
                    </a>
                    <a href="' . htmlspecialchars($linkNegative) . '" class="btn" style="background: #dc3545; margin: 5px;">
                        ✗ Refuser la candidature
                    </a>
                </div>
            </div>';
    }
    
    $html = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: #ffffff; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px 20px; }
        .info-box { background: #f8f9fa; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .info-box h3 { margin-top: 0; color: #28a745; font-size: 16px; }
        .info-item { margin: 8px 0; }
        .info-item strong { color: #555; display: inline-block; width: 180px; }
        .btn { display: inline-block; padding: 12px 24px; background: #28a745; color: #ffffff !important; text-decoration: none; border-radius: 4px; margin: 20px 0; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔔 Nouvelle Candidature Reçue</h1>
        </div>
        <div class="content">
            <p><strong>Une nouvelle candidature vient d\'être soumise.</strong></p>
            
            <div class="info-box">
                <h3>👤 Informations du Candidat</h3>
                <div class="info-item"><strong>Nom :</strong> ' . htmlspecialchars($candidature['nom']) . ' ' . htmlspecialchars($candidature['prenom']) . '</div>
                <div class="info-item"><strong>Email :</strong> <a href="mailto:' . htmlspecialchars($candidature['email']) . '">' . htmlspecialchars($candidature['email']) . '</a></div>
                <div class="info-item"><strong>Téléphone :</strong> ' . htmlspecialchars($candidature['telephone']) . '</div>
                <div class="info-item"><strong>Référence :</strong> ' . htmlspecialchars($candidature['reference']) . '</div>
            </div>
            
            <div class="info-box">
                <h3>🏠 Logement</h3>
                <div class="info-item"><strong>Référence :</strong> ' . htmlspecialchars($logement['reference']) . '</div>
                <div class="info-item"><strong>Type :</strong> ' . htmlspecialchars($logement['type']) . '</div>
                <div class="info-item"><strong>Adresse :</strong> ' . htmlspecialchars($logement['adresse']) . '</div>
                <div class="info-item"><strong>Loyer :</strong> ' . htmlspecialchars($logement['loyer']) . ' €/mois</div>
            </div>
            
            <div class="info-box">
                <h3>💼 Situation Professionnelle</h3>
                <div class="info-item"><strong>Statut :</strong> ' . htmlspecialchars($candidature['statut_professionnel']) . '</div>
                <div class="info-item"><strong>Période d\'essai :</strong> ' . htmlspecialchars($candidature['periode_essai']) . '</div>
                <div class="info-item"><strong>Revenus mensuels :</strong> ' . htmlspecialchars($candidature['revenus_mensuels']) . '</div>
                <div class="info-item"><strong>Type de revenus :</strong> ' . htmlspecialchars($candidature['type_revenus']) . '</div>
            </div>
            
            <div class="info-box">
                <h3>📎 Documents</h3>
                <div class="info-item"><strong>Nombre de pièces :</strong> ' . $nb_documents . ' document(s)</div>
            </div>
            
            ' . $responseLinksHtml . '
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="' . $config['SITE_URL'] . '/admin-v2/candidature-detail.php?id=' . $candidature['id'] . '" class="btn">
                    Voir la Candidature
                </a>
            </div>
            
            <p style="margin-top: 30px;">Sincères salutations</p>
            <br><br>
            <table style="border-collapse: collapse;">
                <tbody>
                    <tr>
                        <td style="vertical-align: middle;">
                            <img src="https://www.myinvest-immobilier.com/images/logo.png" alt="MY Invest Immobilier" style="max-width: 150px; height: auto;">
                        </td>
                        <td style="width: 20px;">&nbsp;</td>
                        <td style="vertical-align: middle;">
                            <h3 style="margin: 0; font-size: 18px; color: #333;">
                                MY INVEST IMMOBILIER
                            </h3>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="footer">
            <p>© ' . date('Y') . ' MY Invest Immobilier - Système de Gestion des Candidatures</p>
        </div>
    </div>
</body>
</html>';
    
    return $html;
}
