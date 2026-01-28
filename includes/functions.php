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
    $token = generateContractToken();
    $expiration = date('Y-m-d H:i:s', strtotime('+' . TOKEN_EXPIRY_HOURS . ' hours'));
    
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
    if (!$contract || $contract['statut'] !== 'en_attente') {
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
    $sql = "UPDATE locataires 
            SET signature_data = ?, signature_ip = ?, signature_timestamp = NOW(), mention_lu_approuve = ?
            WHERE id = ?";
    
    $stmt = executeQuery($sql, [$signatureData, getClientIp(), $mentionLuApprouve, $locataireId]);
    return $stmt !== false;
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
