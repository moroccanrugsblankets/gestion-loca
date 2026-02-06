<?php
/**
 * Fonctions utilitaires
 * My Invest Immobilier
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail-templates.php';

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
    $sql = "INSERT INTO logs (type_entite, entite_id, action, details, ip_address) VALUES (?, ?, ?, ?, ?)";
    $stmt = executeQuery($sql, ['contrat', $contratId, $action, $details, getClientIp()]);
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
    
    // Get expiration delay from parameters table, fallback to config
    $expiryHours = getParameter('delai_expiration_lien_contrat', $config['TOKEN_EXPIRY_HOURS']);
    $expiration = date('Y-m-d H:i:s', strtotime('+' . $expiryHours . ' hours'));
    
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
    // Note: Select l.* first, then c.* to ensure contract fields (especially statut) 
    // take precedence over logement fields in case of column name collisions
    $sql = "SELECT l.*, c.* 
            FROM contrats c 
            INNER JOIN logements l ON c.logement_id = l.id 
            WHERE c.token_signature = ?";
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
    
    // Only 'en_attente' status is valid for unsigned contracts
    $validStatuses = ['en_attente'];
    if (!in_array($contract['statut'], $validStatuses)) {
        return false;
    }
    
    // Check if date_expiration exists and is valid
    if (!isset($contract['date_expiration']) || empty($contract['date_expiration'])) {
        error_log("Contract expiration date is missing or empty for contract ID: " . ($contract['id'] ?? 'unknown'));
        return false;
    }
    
    $expiration = strtotime($contract['date_expiration']);
    
    // Check if strtotime successfully parsed the date
    if ($expiration === false || $expiration === -1) {
        error_log("Failed to parse expiration date '{$contract['date_expiration']}' for contract ID: " . ($contract['id'] ?? 'unknown'));
        return false;
    }
    
    // Contract is valid if current time is before expiration time
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
    // Canvas JPEG data URLs are typically 50-300KB (smaller than PNG)
    $maxSize = 2 * 1024 * 1024; // 2MB limit
    if (strlen($signatureData) > $maxSize) {
        error_log("Signature data too large: " . strlen($signatureData) . " bytes for locataire ID: $locataireId");
        return false;
    }
    
    // Validate that signature data is a valid data URL
    if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,(.+)$/', $signatureData, $matches)) {
        error_log("Invalid signature data format for locataire ID: $locataireId");
        return false;
    }
    
    $imageFormat = $matches[1];
    $base64Data = $matches[2];
    
    // Decode base64 to image data
    $imageData = base64_decode($base64Data);
    if ($imageData === false) {
        error_log("Failed to decode base64 signature for locataire ID: $locataireId");
        return false;
    }
    
    // Create uploads directory if it doesn't exist
    $baseDir = dirname(__DIR__);
    $uploadsDir = $baseDir . '/uploads/signatures';
    if (!is_dir($uploadsDir)) {
        if (!mkdir($uploadsDir, 0755, true)) {
            error_log("Failed to create signatures directory for locataire ID: $locataireId");
            return false;
        }
    }
    
    // Generate unique filename
    $filename = "tenant_locataire_{$locataireId}_" . time() . ".jpg";
    $filepath = $uploadsDir . '/' . $filename;
    
    // Save physical file
    if (file_put_contents($filepath, $imageData) === false) {
        error_log("Failed to save signature file for locataire ID: $locataireId");
        return false;
    }
    
    // Store relative path instead of base64
    $relativePath = 'uploads/signatures/' . $filename;
    error_log("Signature saved as physical file: $relativePath for locataire ID: $locataireId");
    error_log("✓ Signature enregistrée physiquement et intégrée sans bordure - Locataire ID: $locataireId");
    
    $sql = "UPDATE locataires 
            SET signature_data = ?, signature_ip = ?, signature_timestamp = NOW(), mention_lu_approuve = ?
            WHERE id = ?";
    
    $stmt = executeQuery($sql, [$relativePath, getClientIp(), $mentionLuApprouve, $locataireId]);
    
    if ($stmt === false) {
        error_log("Failed to update signature for locataire ID: $locataireId");
        // Clean up the file if database update failed
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        return false;
    }
    
    return true;
}

/**
 * Mettre à jour la signature d'un locataire pour état des lieux
 * @param int $etatLieuxLocataireId ID from etat_lieux_locataires table
 * @param string $signatureData Base64 encoded signature data
 * @param int $etatLieuxId ID of the état des lieux
 * @return bool
 */
function updateEtatLieuxTenantSignature($etatLieuxLocataireId, $signatureData, $etatLieuxId) {
    global $pdo;
    
    // Validate signature data size
    $maxSize = 2 * 1024 * 1024; // 2MB limit
    if (strlen($signatureData) > $maxSize) {
        error_log("Signature data too large: " . strlen($signatureData) . " bytes for etat_lieux_locataire ID: $etatLieuxLocataireId");
        return false;
    }
    
    // Validate that signature data is a valid data URL
    if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,(.+)$/', $signatureData, $matches)) {
        error_log("Invalid signature data format for etat_lieux_locataire ID: $etatLieuxLocataireId");
        return false;
    }
    
    $imageFormat = $matches[1];
    $base64Data = $matches[2];
    
    // Decode base64 to image data
    $imageData = base64_decode($base64Data);
    if ($imageData === false) {
        error_log("Failed to decode base64 signature for etat_lieux_locataire ID: $etatLieuxLocataireId");
        return false;
    }
    
    // Create uploads directory if it doesn't exist
    $baseDir = dirname(__DIR__);
    $uploadsDir = $baseDir . '/uploads/signatures';
    if (!is_dir($uploadsDir)) {
        if (!mkdir($uploadsDir, 0755, true)) {
            error_log("Failed to create signatures directory for etat_lieux_locataire ID: $etatLieuxLocataireId");
            return false;
        }
    }
    
    // Generate unique filename
    $filename = "etat_lieux_tenant_{$etatLieuxId}_{$etatLieuxLocataireId}_" . time() . ".jpg";
    $filepath = $uploadsDir . '/' . $filename;
    
    // Save physical file
    if (file_put_contents($filepath, $imageData) === false) {
        error_log("Failed to save signature file for etat_lieux_locataire ID: $etatLieuxLocataireId");
        return false;
    }
    
    // Store relative path instead of base64
    $relativePath = 'uploads/signatures/' . $filename;
    error_log("État des lieux signature saved as physical file: $relativePath for etat_lieux_locataire ID: $etatLieuxLocataireId");
    
    $sql = "UPDATE etat_lieux_locataires 
            SET signature_data = ?, signature_ip = ?, signature_timestamp = NOW()
            WHERE id = ? AND etat_lieux_id = ?";
    
    $stmt = executeQuery($sql, [$relativePath, getClientIp(), $etatLieuxLocataireId, $etatLieuxId]);
    
    if ($stmt === false) {
        error_log("Failed to update signature for etat_lieux_locataire ID: $etatLieuxLocataireId");
        // Clean up the file if database update failed
        if (file_exists($filepath)) {
            unlink($filepath);
        }
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
 * Mettre à jour la preuve de paiement du dépôt de garantie d'un locataire
 * @param int $locataireId
 * @param string $preuvePaiement
 * @return bool
 */
function updateTenantPaymentProof($locataireId, $preuvePaiement) {
    $sql = "UPDATE locataires SET preuve_paiement_depot = ? WHERE id = ?";
    $stmt = executeQuery($sql, [$preuvePaiement, $locataireId]);
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
        
        // Send email notification to administrators that contract is signed and needs verification
        // Important: Select c.* first, then explicitly name logements columns to avoid column name collision
        $contrat = fetchOne("
            SELECT c.*, 
                   c.id as contrat_id, 
                   c.reference_unique as reference_contrat,
                   l.reference,
                   l.adresse,
                   l.appartement,
                   l.type,
                   l.surface,
                   l.loyer,
                   l.charges,
                   l.depot_garantie,
                   l.parking
            FROM contrats c
            INNER JOIN logements l ON c.logement_id = l.id
            WHERE c.id = ?
        ", [$contratId]);
        
        if ($contrat) {
            $locataires = fetchAll("
                SELECT * FROM locataires 
                WHERE contrat_id = ? 
                ORDER BY ordre
            ", [$contratId]);
            
            $locatairesNames = array_map(function($loc) {
                return $loc['prenom'] . ' ' . $loc['nom'];
            }, $locataires);
            
            // Send email to administrators
            sendTemplatedEmail('contrat_signe_client_admin', ADMIN_EMAIL, [
                'reference' => $contrat['reference_contrat'],
                'logement' => $contrat['reference'] . ' - ' . $contrat['adresse'],
                'locataires' => implode(', ', $locatairesNames),
                'date_signature' => date('d/m/Y H:i'),
                'lien_admin' => BASE_URL . '/admin-v2/contrat-detail.php?id=' . $contratId
            ]);
        }
        
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
        // Don't escape HTML for 'signature' variable since it contains HTML
        if ($key === 'signature') {
            $template = str_replace($placeholder, $value, $template);
        } else {
            $template = str_replace($placeholder, htmlspecialchars($value, ENT_QUOTES, 'UTF-8'), $template);
        }
    }
    
    // Log warning if there are unreplaced variables (but ignore {{signature}} as it's handled in sendEmail)
    if (preg_match_all('/\{\{([^}]+)\}\}/', $template, $matches)) {
        $unreplaced = array_diff($matches[1], ['signature']);
        if (!empty($unreplaced)) {
            error_log("Warning: Unreplaced variables in template: " . implode(', ', $unreplaced));
        }
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

/**
 * Format revenus_mensuels value for display
 * @param string|null $revenus Raw enum value from database
 * @return string Formatted display value
 */
function formatRevenus($revenus) {
    if ($revenus === null || $revenus === '') {
        return 'N/A';
    }
    
    if ($revenus === '< 2300') {
        return '< 2300 €';
    } elseif ($revenus === '2300-3000') {
        return '2300-3000 €';
    } elseif ($revenus === '3000+') {
        return '3000 € et +';
    }
    
    // Return raw value for any unexpected values
    return $revenus;
}

/**
 * Format statut value for display
 * @param string|null $statut Raw enum value from database
 * @return string Formatted display value
 */
function formatStatut($statut) {
    if ($statut === null || $statut === '') {
        return 'N/A';
    }
    
    $mapping = [
        'en_cours' => 'En cours',
        'refuse' => 'Refusé',
        'accepte' => 'Accepté',
        'visite_planifiee' => 'Visite planifiée',
        'contrat_envoye' => 'Contrat envoyé',
        'contrat_signe' => 'Contrat signé'
    ];
    
    return $mapping[$statut] ?? ucfirst(str_replace('_', ' ', $statut));
}

/**
 * Evaluate if a candidature should be accepted based on defined criteria
 * 
 * Accepted candidatures receive status 'en_cours' (awaiting further processing)
 * Rejected candidatures receive status 'refuse' with a detailed rejection reason
 * 
 * Expected enum values for revenus_mensuels: '< 2300', '2300-3000', '3000+'
 * If database enum changes, this function must be updated accordingly.
 * 
 * Returns array with 'accepted' (bool) and 'motif' (string) keys
 * 
 * @param array $candidature Candidature data from database
 * @return array ['accepted' => bool, 'motif' => string, 'statut' => string]
 */
function evaluateCandidature($candidature) {
    // Get parameters from database
    $revenusMinRequis = getParameter('revenus_min_requis', 3000);
    $statutsProAcceptes = getParameter('statuts_pro_acceptes', ['CDI', 'CDD']);
    $typeRevenusAccepte = getParameter('type_revenus_accepte', 'Salaires');
    $nbOccupantsAcceptes = getParameter('nb_occupants_acceptes', ['1', '2']);
    $garantieVisaleRequise = getParameter('garantie_visale_requise', true);
    
    $motifs = [];
    
    // RULE 1: Professional situation - must be CDI or CDD
    if (!in_array($candidature['statut_professionnel'], $statutsProAcceptes)) {
        $motifs[] = "Statut professionnel non accepté (doit être CDI ou CDD)";
    }
    
    // RULE 2: Monthly net income - must be >= 3000€
    // Convert enum values to numeric for comparison
    $revenus = $candidature['revenus_mensuels'];
    if ($revenus === '< 2300' || $revenus === '2300-3000') {
        $motifs[] = "Revenus nets mensuels insuffisants (minimum 3000€ requis)";
    }
    
    // RULE 3: Income type - must be Salaires
    if ($candidature['type_revenus'] !== $typeRevenusAccepte) {
        $motifs[] = "Type de revenus non accepté (doit être: $typeRevenusAccepte)";
    }
    
    // RULE 4: Number of occupants - must be 1 or 2 (not "Autre")
    if (!in_array($candidature['nb_occupants'], $nbOccupantsAcceptes)) {
        $motifs[] = "Nombre d'occupants non accepté (doit être 1 ou 2)";
    }
    
    // RULE 5: Visale guarantee - must be "Oui"
    if ($garantieVisaleRequise && $candidature['garantie_visale'] !== 'Oui') {
        $motifs[] = "Garantie Visale requise";
    }
    
    // RULE 6: If CDI, trial period must be passed
    if ($candidature['statut_professionnel'] === 'CDI' && 
        isset($candidature['periode_essai']) && 
        $candidature['periode_essai'] === 'En cours') {
        $motifs[] = "Période d'essai en cours";
    }
    
    // All criteria must be met for acceptance
    $accepted = empty($motifs);
    $motif = $accepted ? '' : implode(', ', $motifs);
    
    // Determine the status value to use
    $statut = $accepted ? 'en_cours' : 'refuse';
    
    return [
        'accepted' => $accepted,
        'motif' => $motif,
        'statut' => $statut
    ];
}

/**
 * Calculate scheduled response date based on current delay parameters
 * @param DateTime $fromDate The starting date (usually candidature creation date)
 * @return DateTime The calculated scheduled response date
 */
function calculateScheduledResponseDate($fromDate) {
    $delaiValeur = (int)getParameter('delai_reponse_valeur', 4);
    $delaiUnite = getParameter('delai_reponse_unite', 'jours');
    
    $scheduledDate = clone $fromDate;
    
    if ($delaiUnite === 'jours') {
        // Add business days (skip weekends)
        $daysAdded = 0;
        while ($daysAdded < $delaiValeur) {
            $scheduledDate->modify('+1 day');
            // Skip weekends (Saturday = 6, Sunday = 7)
            if ($scheduledDate->format('N') < 6) {
                $daysAdded++;
            }
        }
    } elseif ($delaiUnite === 'heures') {
        $scheduledDate->modify("+{$delaiValeur} hours");
    } elseif ($delaiUnite === 'minutes') {
        $scheduledDate->modify("+{$delaiValeur} minutes");
    }
    
    return $scheduledDate;
}

/**
 * Get parameter value from parametres table
 * @param string $cle Parameter key
 * @return string|null Parameter value or null if not found
 */
function getParametreValue($cle) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = ?");
    $stmt->execute([$cle]);
    $result = $stmt->fetchColumn();
    
    return $result !== false ? $result : null;
}

/**
 * Convert a parameter value to boolean
 * Handles both boolean and string 'true'/'false' values for consistency
 * @param mixed $value The value to convert (can be bool, string, or null)
 * @return bool
 */
function toBooleanParam($value) {
    if (is_bool($value)) {
        return $value;
    }
    if (is_string($value)) {
        return $value === 'true' || $value === '1';
    }
    return false;
}

