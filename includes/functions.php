<?php
/**
 * Fonctions utilitaires
 * My Invest Immobilier
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Générer un token CSRF
 * @return string
 */
function generateCsrfToken() {
    global $config;
    if (!isset($_SESSION[$config['CSRF_TOKEN_NAME']])) {
        $_SESSION[$config['CSRF_TOKEN_NAME']] = bin2hex(random_bytes(32));
    }
    return $_SESSION[$config['CSRF_TOKEN_NAME']];
}

/**
 * Vérifier un token CSRF
 * @param string $token
 * @return bool
 */
function verifyCsrfToken($token) {
    global $config;
    return isset($_SESSION[$config['CSRF_TOKEN_NAME']]) && hash_equals($_SESSION[$config['CSRF_TOKEN_NAME']], $token);
}

/**
 * Générer un token unique pour un contrat
 * @return string
 */
function generateContractToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Nettoyer et échapper une chaîne
 * @param string $data
 * @return string
 */
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Obtenir l'adresse IP du client
 * @return string
 */
function getClientIp() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}

/**
 * Enregistrer un log dans la base de données
 * @param int|null $contratId
 * @param string $action
 * @param string $details
 * @return bool
 */
function logAction($contratId, $action, $details = '') {
    $sql = "INSERT INTO logs (contrat_id, action, details, ip_address) VALUES (?, ?, ?, ?)";
    $stmt = executeQuery($sql, [$contratId, $action, $details, getClientIp()]);
    return $stmt !== false;
}

/**
 * Créer un nouveau contrat
 * @param int $logementId
 * @param int $nbLocataires
 * @return array|false ['id' => int, 'token' => string, 'expiration' => string]
 */
function createContract($logementId, $nbLocataires = 1) {
    global $config;
    $token = generateContractToken();
    $expiration = date('Y-m-d H:i:s', strtotime('+' . $config['TOKEN_EXPIRY_HOURS'] . ' hours'));
    
    $sql = "INSERT INTO contrats (reference_unique, logement_id, nb_locataires, date_expiration) 
            VALUES (?, ?, ?, ?)";
    
    if (executeQuery($sql, [$token, $logementId, $nbLocataires, $expiration])) {
        $contractId = getLastInsertId();
        logAction($contractId, 'creation_contrat', "Logement ID: $logementId, Nb locataires: $nbLocataires");
        return [
            'id' => $contractId,
            'token' => $token,
            'expiration' => $expiration
        ];
    }
    
    return false;
}

/**
 * Obtenir un contrat par son token
 * @param string $token
 * @return array|false
 */
function getContractByToken($token) {
    $sql = "SELECT c.*, l.* 
            FROM contrats c 
            INNER JOIN logements l ON c.logement_id = l.id 
            WHERE c.reference_unique = ?";
    return fetchOne($sql, [$token]);
}

/**
 * Vérifier si un contrat est valide (non expiré)
 * @param array $contract
 * @return bool
 */
function isContractValid($contract) {
    if (!$contract) {
        return false;
    }
    
    // Accept both 'en_attente' and 'contrat_envoye' statuses
    // 'contrat_envoye' is set when signature link is sent via envoyer-signature.php
    $validStatuses = ['en_attente', 'contrat_envoye'];
    if (!in_array($contract['statut'], $validStatuses)) {
        return false;
    }
    
    $expiration = strtotime($contract['date_expiration']);
    return time() < $expiration;
}

/**
 * Obtenir les locataires d'un contrat
 * @param int $contratId
 * @return array
 */
function getTenantsByContract($contratId) {
    $sql = "SELECT * FROM locataires WHERE contrat_id = ? ORDER BY ordre ASC";
    return fetchAll($sql, [$contratId]);
}

/**
 * Créer un locataire
 * @param int $contratId
 * @param int $ordre
 * @param array $data
 * @return int|false
 */
function createTenant($contratId, $ordre, $data) {
    $sql = "INSERT INTO locataires (contrat_id, ordre, nom, prenom, date_naissance, email) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    if (executeQuery($sql, [
        $contratId,
        $ordre,
        $data['nom'],
        $data['prenom'],
        $data['date_naissance'],
        $data['email']
    ])) {
        return getLastInsertId();
    }
    
    return false;
}

/**
 * Mettre à jour la signature d'un locataire
 * @param int $locataireId
 * @param string $signatureData
 * @param string $mentionLuApprouve
 * @return bool
 */
function updateTenantSignature($locataireId, $signatureData, $mentionLuApprouve) {
    // Validate signature data size (LONGTEXT max is ~4GB, but we set a reasonable limit)
    // Canvas PNG data URLs are typically 100-500KB
    $maxSize = 2 * 1024 * 1024; // 2MB limit
    if (strlen($signatureData) > $maxSize) {
        error_log("Signature data too large: " . strlen($signatureData) . " bytes for locataire ID: $locataireId");
        return false;
    }
    
    // Validate that signature data is a valid data URL
    if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $signatureData)) {
        error_log("Invalid signature data format for locataire ID: $locataireId");
        return false;
    }
    
    $sql = "UPDATE locataires 
            SET signature_data = ?, signature_ip = ?, signature_timestamp = NOW(), mention_lu_approuve = ?
            WHERE id = ?";
    
    $stmt = executeQuery($sql, [$signatureData, getClientIp(), $mentionLuApprouve, $locataireId]);
    
    if ($stmt === false) {
        error_log("Failed to update signature for locataire ID: $locataireId");
        return false;
    }
    
    return true;
}

/**
 * Mettre à jour les pièces d'identité d'un locataire
 * @param int $locataireId
 * @param string $recto
 * @param string $verso
 * @return bool
 */
function updateTenantDocuments($locataireId, $recto, $verso) {
    $sql = "UPDATE locataires SET piece_identite_recto = ?, piece_identite_verso = ? WHERE id = ?";
    $stmt = executeQuery($sql, [$recto, $verso, $locataireId]);
    return $stmt !== false;
}

/**
 * Finaliser un contrat (marquer comme signé)
 * @param int $contratId
 * @return bool
 */
function finalizeContract($contratId) {
    $sql = "UPDATE contrats SET statut = 'signe', date_signature = NOW() WHERE id = ?";
    $stmt = executeQuery($sql, [$contratId]);
    
    if ($stmt) {
        logAction($contratId, 'signature_contrat', 'Contrat finalisé et signé');
        return true;
    }
    
    return false;
}

/**
 * Valider un fichier uploadé
 * @param array $file
 * @return array ['success' => bool, 'error' => string, 'filename' => string]
 */
function validateUploadedFile($file) {
    global $config;
    // Vérifier les erreurs d'upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Erreur lors de l\'upload du fichier.'];
    }
    
    // Vérifier la taille
    if ($file['size'] > $config['MAX_FILE_SIZE']) {
        return ['success' => false, 'error' => 'Le fichier est trop volumineux (max 5 Mo).'];
    }
    
    // Vérifier l'extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $config['ALLOWED_EXTENSIONS'])) {
        return ['success' => false, 'error' => 'Type de fichier non autorisé. Utilisez JPG, PNG ou PDF.'];
    }
    
    // Vérifier le type MIME réel
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $config['ALLOWED_MIME_TYPES'])) {
        return ['success' => false, 'error' => 'Type de fichier invalide.'];
    }
    
    // Générer un nom de fichier unique
    $newFilename = bin2hex(random_bytes(16)) . '.' . $extension;
    
    return ['success' => true, 'filename' => $newFilename];
}

/**
 * Sauvegarder un fichier uploadé
 * @param array $file
 * @param string $newFilename
 * @return bool
 */
function saveUploadedFile($file, $newFilename) {
    global $config;
    $destination = $config['UPLOAD_DIR'] . $newFilename;
    
    // Créer le dossier uploads s'il n'existe pas
    if (!is_dir($config['UPLOAD_DIR'])) {
        mkdir($config['UPLOAD_DIR'], 0755, true);
    }
    
    return move_uploaded_file($file['tmp_name'], $destination);
}

/**
 * Obtenir un logement par sa référence
 * @param string $reference
 * @return array|false
 */
function getLogementByReference($reference) {
    $sql = "SELECT * FROM logements WHERE reference = ?";
    return fetchOne($sql, [$reference]);
}

/**
 * Obtenir tous les logements
 * @return array
 */
function getAllLogements() {
    $sql = "SELECT * FROM logements ORDER BY reference ASC";
    return fetchAll($sql);
}

/**
 * Obtenir tous les contrats avec informations sur le logement
 * @param string|null $statut
 * @return array
 */
function getAllContracts($statut = null) {
    if ($statut) {
        $sql = "SELECT c.*, l.reference, l.adresse, l.appartement 
                FROM contrats c 
                INNER JOIN logements l ON c.logement_id = l.id 
                WHERE c.statut = ?
                ORDER BY c.date_creation DESC";
        return fetchAll($sql, [$statut]);
    } else {
        $sql = "SELECT c.*, l.reference, l.adresse, l.appartement 
                FROM contrats c 
                INNER JOIN logements l ON c.logement_id = l.id 
                ORDER BY c.date_creation DESC";
        return fetchAll($sql);
    }
}

/**
 * Formater une date en français
 * @param string $date
 * @param string $format
 * @return string
 */
function formatDateFr($date, $format = 'd/m/Y') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

/**
 * Formater un montant en euros
 * @param float $montant
 * @return string
 */
function formatMontant($montant) {
    return number_format($montant, 2, ',', ' ') . ' €';
}

/**
 * Redirection avec message
 * @param string $url
 * @param string|null $message
 * @param string $type
 */
function redirect($url, $message = null, $type = 'success') {
    if ($message) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    header("Location: $url");
    exit();
}

/**
 * Afficher et supprimer le message flash
 * @return array|null ['message' => string, 'type' => string]
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = [
            'message' => $_SESSION['flash_message'],
            'type' => $_SESSION['flash_type'] ?? 'success'
        ];
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        return $message;
    }
    return null;
}

/**
 * Get parameter value from database
 * @param string $cle Parameter key
 * @param mixed $default Default value if parameter not found
 * @return mixed
 */
function getParameter($cle, $default = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT valeur, type FROM parametres WHERE cle = ?");
        $stmt->execute([$cle]);
        $param = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$param) {
            return $default;
        }
        
        // Cast value based on type
        switch ($param['type']) {
            case 'integer':
                return (int)$param['valeur'];
            case 'float':
                return (float)$param['valeur'];
            case 'boolean':
                return $param['valeur'] === 'true' || $param['valeur'] === '1';
            case 'json':
                return json_decode($param['valeur'], true);
            default:
                return $param['valeur'];
        }
    } catch (PDOException $e) {
        error_log("Error getting parameter $cle: " . $e->getMessage());
        return $default;
    }
}

/**
 * Set parameter value in database
 * @param string $cle Parameter key
 * @param mixed $valeur Parameter value
 * @return bool
 */
function setParameter($cle, $valeur) {
    global $pdo;
    
    try {
        // Convert value to string based on type
        if (is_bool($valeur)) {
            $valeur = $valeur ? 'true' : 'false';
        } elseif (is_array($valeur)) {
            $valeur = json_encode($valeur);
        }
        
        // Use INSERT ... ON DUPLICATE KEY UPDATE to handle both insert and update
        $stmt = $pdo->prepare("
            INSERT INTO parametres (cle, valeur, updated_at) 
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE valeur = ?, updated_at = NOW()
        ");
        return $stmt->execute([$cle, $valeur, $valeur]);
    } catch (PDOException $e) {
        error_log("Error setting parameter $cle: " . $e->getMessage());
        return false;
    }
}

/**
 * Get email template from database by identifier
 * @param string $identifiant Template identifier
 * @return array|false Template data or false if not found
 */
function getEmailTemplate($identifiant) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE identifiant = ? AND actif = 1");
        $stmt->execute([$identifiant]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting email template $identifiant: " . $e->getMessage());
        return false;
    }
}

/**
 * Replace template variables with actual values
 * @param string $template Template string with {{variable}} placeholders
 * @param array $data Associative array of variable => value pairs
 * @return string Processed template
 */
function replaceTemplateVariables($template, $data) {
    foreach ($data as $key => $value) {
        $placeholder = '{{' . $key . '}}';
        // Ensure value is a string
        $value = $value !== null ? (string)$value : '';
        $template = str_replace($placeholder, htmlspecialchars($value, ENT_QUOTES, 'UTF-8'), $template);
    }
    
    // Log warning if there are unreplaced variables
    if (preg_match_all('/\{\{([^}]+)\}\}/', $template, $matches)) {
        error_log("Warning: Unreplaced variables in template: " . implode(', ', $matches[1]));
    }
    
    return $template;
}

/**
 * Send email using database template
 * @param string $templateId Template identifier
 * @param string $to Recipient email
 * @param array $variables Variables to replace in template
 * @param string|null $attachmentPath Optional attachment path
 * @param bool $isAdminEmail Whether this is an admin email (for CC to secondary admin)
 * @return bool Success status
 */
function sendTemplatedEmail($templateId, $to, $variables = [], $attachmentPath = null, $isAdminEmail = false) {
    $template = getEmailTemplate($templateId);
    
    if (!$template) {
        error_log("Email template not found: $templateId");
        return false;
    }
    
    // Replace variables in subject and body
    $subject = replaceTemplateVariables($template['sujet'], $variables);
    $body = replaceTemplateVariables($template['corps_html'], $variables);
    
    // Send email using the existing sendEmail function
    return sendEmail($to, $subject, $body, $attachmentPath, true, $isAdminEmail);
}
