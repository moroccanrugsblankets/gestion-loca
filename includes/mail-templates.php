<?php
/**
 * Templates d'emails
 * My Invest Immobilier
 */

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

1. La signature du contrat de bail en ligne
2. La transmission d'une pièce d'identité en cours de validité (carte nationale d'identité ou passeport)
3. Le règlement immédiat du dépôt de garantie, correspondant à deux mois de loyer, par virement bancaire instantané

La prise d'effet du bail ainsi que la remise des clés interviendront uniquement après réception complète de l'ensemble des éléments ci-dessus.

À défaut de réception complète du dossier dans le délai indiqué, la réservation du logement pourra être remise en disponibilité sans autre formalité.

Pour accéder au contrat de bail : $signatureLink

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

" . BANK_NAME . "
IBAN : " . IBAN . "
BIC : " . BIC . "

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
 * Envoyer un email
 * @param string $to
 * @param string $subject
 * @param string $body
 * @param string|null $attachmentPath
 * @return bool
 */
function sendEmail($to, $subject, $body, $attachmentPath = null) {
    $headers = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
    $headers .= "Reply-To: " . MAIL_FROM . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    
    if ($attachmentPath && file_exists($attachmentPath)) {
        // Email avec pièce jointe
        $boundary = md5(time());
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
        
        $message = "--$boundary\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
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
        
        return mail($to, $subject, $message, $headers);
    } else {
        // Email simple
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        return mail($to, $subject, $body, $headers);
    }
}
