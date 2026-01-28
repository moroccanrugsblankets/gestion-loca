<?php
/**
 * Templates d'emails
 * My Invest Immobilier
 */

// Charger l'autoload de Composer pour PHPMailer
require_once dirname(__DIR__) . '/vendor/autoload.php';

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
" . COMPANY_EMAIL;
    
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
" . COMPANY_EMAIL;
    
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
 * @param string|null $attachmentPath Chemin vers une pièce jointe (optionnel)
 * @param bool $isHtml Si true, le corps sera traité comme HTML (par défaut: true)
 * @return bool True si l'email a été envoyé avec succès
 */
function sendEmail($to, $subject, $body, $attachmentPath = null, $isHtml = true) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuration du serveur SMTP
        if (SMTP_AUTH) {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = SMTP_AUTH;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port       = SMTP_PORT;
            $mail->SMTPDebug  = SMTP_DEBUG;
        }
        
        // Encodage
        $mail->CharSet = 'UTF-8';
        
        // Expéditeur
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addReplyTo(MAIL_FROM, MAIL_FROM_NAME);
        
        // Destinataire
        $mail->addAddress($to);
        
        // Contenu
        $mail->isHTML($isHtml);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        
        // Si le contenu est HTML, générer une version texte alternative
        if ($isHtml) {
            $mail->AltBody = strip_tags($body);
        }
        
        // Pièce jointe
        if ($attachmentPath && file_exists($attachmentPath)) {
            $mail->addAttachment($attachmentPath);
        }
        
        // Envoyer l'email
        $result = $mail->send();
        
        // Logger le succès
        error_log("Email envoyé avec succès à: $to - Sujet: $subject");
        
        return $result;
        
    } catch (Exception $e) {
        // Logger l'erreur
        error_log("Erreur lors de l'envoi de l'email à $to: {$mail->ErrorInfo}");
        error_log("Exception: " . $e->getMessage());
        
        // En cas d'échec SMTP, essayer avec la fonction mail() native en fallback
        if (SMTP_AUTH) {
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
    try {
        $headers = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
        $headers .= "Reply-To: " . MAIL_FROM . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        
        if ($isHtml) {
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        }
        
        if ($attachmentPath && file_exists($attachmentPath)) {
            // Email avec pièce jointe
            $boundary = md5(time());
            $headers = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
            $headers .= "Reply-To: " . MAIL_FROM . "\r\n";
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
            
            return @mail($to, $subject, $message, $headers);
        } else {
            // Email simple
            return @mail($to, $subject, $body, $headers);
        }
    } catch (Exception $e) {
        error_log("Fallback mail() a également échoué: " . $e->getMessage());
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
            
            <p><strong>Prochaines étapes :</strong></p>
            <ul>
                <li>Votre dossier sera étudié dans les meilleurs délais</li>
                <li>Vous recevrez une réponse par email dans un délai de <strong>4 jours ouvrés</strong></li>
                <li>Si votre candidature est retenue, nous vous contacterons pour organiser une visite</li>
            </ul>
            
            <p>Nous restons à votre disposition pour toute question.</p>
            
            <p style="margin-top: 30px;">
                Cordialement,<br>
                <strong>MY Invest Immobilier</strong><br>
                <a href="mailto:' . COMPANY_EMAIL . '">' . COMPANY_EMAIL . '</a>
            </p>
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
                <a href="mailto:' . COMPANY_EMAIL . '">' . COMPANY_EMAIL . '</a>
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
                <a href="mailto:' . COMPANY_EMAIL . '">' . COMPANY_EMAIL . '</a>
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
