<?php
/**
 * Traitement de soumission de candidature locative
 * Enregistre la candidature et les documents, puis marque le statut "En cours"
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mail-templates.php';

header('Content-Type: application/json');

// Fonction de logging pour debug
function logDebug($message, $data = null) {
    $logMessage = "[CANDIDATURE DEBUG] " . $message;
    if ($data !== null) {
        $logMessage .= " | Data: " . json_encode($data);
    }
    error_log($logMessage);
}

try {
    logDebug("Début du traitement de la candidature");
    
    // Vérification du token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        logDebug("Erreur: Token CSRF invalide", ['session' => $_SESSION['csrf_token'] ?? 'N/A', 'post' => $_POST['csrf_token'] ?? 'N/A']);
        throw new Exception('Token CSRF invalide');
    }
    
    logDebug("Token CSRF validé");
    
    // Validation des champs obligatoires
    $required_fields = [
        'nom', 'prenom', 'email', 'telephone', 'logement_id',
        'statut_professionnel', 'periode_essai', 'revenus_mensuels', 'type_revenus',
        'situation_logement', 'preavis_donne', 'nb_occupants', 'garantie_visale'
    ];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            logDebug("Champ manquant: $field");
            throw new Exception("Le champ '$field' est obligatoire");
        }
    }
    
    logDebug("Tous les champs obligatoires sont présents");
    
    // Vérifier les 5 types de documents obligatoires
    $required_doc_types = [
        'piece_identite' => 'Pièce d\'identité ou passeport',
        'bulletins_salaire' => '3 derniers bulletins de salaire',
        'contrat_travail' => 'Contrat de travail',
        'avis_imposition' => 'Dernier avis d\'imposition',
        'quittances_loyer' => '3 dernières quittances de loyer'
    ];
    
    $missing_docs = [];
    foreach ($required_doc_types as $doc_type => $doc_label) {
        if (empty($_FILES[$doc_type]['name'][0])) {
            $missing_docs[] = $doc_label;
        }
    }
    
    if (!empty($missing_docs)) {
        logDebug("Documents manquants", $missing_docs);
        throw new Exception('Documents manquants : ' . implode(', ', $missing_docs));
    }
    
    logDebug("Tous les documents obligatoires sont présents");
    
    // Vérifier l'acceptation des conditions
    if (!isset($_POST['accepte_conditions'])) {
        logDebug("Conditions non acceptées");
        throw new Exception('Vous devez accepter les conditions de traitement des données');
    }
    
    // Nettoyer et sécuriser les données
    $nom = htmlspecialchars(trim($_POST['nom']), ENT_QUOTES, 'UTF-8');
    $prenom = htmlspecialchars(trim($_POST['prenom']), ENT_QUOTES, 'UTF-8');
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $telephone = htmlspecialchars(trim($_POST['telephone']), ENT_QUOTES, 'UTF-8');
    $logement_id = (int)$_POST['logement_id'];
    
    if (!$email) {
        logDebug("Email invalide", ['email' => $_POST['email']]);
        throw new Exception('Email invalide');
    }
    
    logDebug("Données validées", ['nom' => $nom, 'prenom' => $prenom, 'email' => $email, 'logement_id' => $logement_id]);
    
    // Vérifier que le logement existe et est disponible
    $stmt = $pdo->prepare("SELECT * FROM logements WHERE id = ? AND statut = 'disponible'");
    $stmt->execute([$logement_id]);
    $logement = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$logement) {
        logDebug("Logement non disponible", ['logement_id' => $logement_id]);
        throw new Exception('Logement non disponible');
    }
    
    logDebug("Logement trouvé et disponible", ['logement_ref' => $logement['reference']]);
    
    // Début de la transaction
    $pdo->beginTransaction();
    logDebug("Transaction démarrée");
    
    // Générer une référence unique pour la candidature
    $reference_unique = 'CAND-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    
    // Insérer la candidature
    $stmt = $pdo->prepare("
        INSERT INTO candidatures (
            reference_unique, logement_id, nom, prenom, email, telephone,
            statut_professionnel, periode_essai,
            revenus_mensuels, type_revenus,
            situation_logement, preavis_donne,
            nb_occupants, garantie_visale,
            statut, date_soumission
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'En cours', NOW())
    ");
    
    $stmt->execute([
        $reference_unique,
        $logement_id,
        $nom,
        $prenom,
        $email,
        $telephone,
        $_POST['statut_professionnel'],
        $_POST['periode_essai'],
        $_POST['revenus_mensuels'],
        $_POST['type_revenus'],
        $_POST['situation_logement'],
        $_POST['preavis_donne'],
        $_POST['nb_occupants'],
        $_POST['garantie_visale']
    ]);
    
    $candidature_id = $pdo->lastInsertId();
    logDebug("Candidature insérée", ['id' => $candidature_id, 'reference' => $reference_unique]);
    
    // Créer le dossier uploads pour cette candidature
    $upload_dir = __DIR__ . '/../uploads/candidatures/' . $candidature_id;
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
        logDebug("Dossier créé", ['path' => $upload_dir]);
    }
    
    // Traiter les documents uploadés pour chaque type
    $total_uploaded = 0;
    $upload_summary = [];
    
    foreach ($required_doc_types as $doc_type => $doc_label) {
        $documents = $_FILES[$doc_type];
        $type_count = 0;
        
        if (!isset($documents['name'])) continue;
        
        for ($i = 0; $i < count($documents['name']); $i++) {
            if ($documents['error'][$i] !== UPLOAD_ERR_OK) {
                logDebug("Erreur upload pour $doc_type", ['error_code' => $documents['error'][$i], 'file' => $documents['name'][$i]]);
                continue;
            }
            
            // Vérifier le type MIME réel
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $documents['tmp_name'][$i]);
            finfo_close($finfo);
            
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
            if (!in_array($mime_type, $allowed_types)) {
                logDebug("Type MIME non autorisé", ['mime' => $mime_type, 'file' => $documents['name'][$i]]);
                continue;
            }
            
            // Vérifier la taille (max 5 Mo)
            if ($documents['size'][$i] > 5 * 1024 * 1024) {
                logDebug("Fichier trop volumineux", ['size' => $documents['size'][$i], 'file' => $documents['name'][$i]]);
                continue;
            }
            
            // Générer un nom de fichier sécurisé
            $extension = '';
            if ($mime_type === 'application/pdf') {
                $extension = '.pdf';
            } elseif ($mime_type === 'image/jpeg') {
                $extension = '.jpg';
            } elseif ($mime_type === 'image/png') {
                $extension = '.png';
            }
            
            $filename = $doc_type . '_' . $type_count . '_' . bin2hex(random_bytes(8)) . $extension;
            $filepath = $upload_dir . '/' . $filename;
            
            // Déplacer le fichier
            if (move_uploaded_file($documents['tmp_name'][$i], $filepath)) {
                // Enregistrer dans la base de données
                $stmt = $pdo->prepare("
                    INSERT INTO candidature_documents (candidature_id, type_document, nom_fichier, nom_original, chemin_fichier, taille_fichier, mime_type)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $candidature_id,
                    $doc_type,
                    $filename,
                    $documents['name'][$i],
                    'candidatures/' . $candidature_id . '/' . $filename,
                    $documents['size'][$i],
                    $mime_type
                ]);
                
                $type_count++;
                $total_uploaded++;
                logDebug("Fichier uploadé", ['type' => $doc_type, 'filename' => $filename, 'original' => $documents['name'][$i]]);
            } else {
                logDebug("Échec du déplacement du fichier", ['from' => $documents['tmp_name'][$i], 'to' => $filepath]);
            }
        }
        
        $upload_summary[$doc_type] = $type_count;
    }
    
    logDebug("Résumé upload", $upload_summary);
    
    if ($total_uploaded === 0) {
        throw new Exception('Aucun document n\'a pu être uploadé');
    }
    
    // Logger l'action
    $logSql = "INSERT INTO logs (candidature_id, action, details, ip_address) VALUES (?, ?, ?, ?)";
    executeQuery($logSql, [$candidature_id, 'Candidature soumise', "Candidature #$candidature_id - $nom $prenom - $total_uploaded documents", getClientIp()]);
    
    // Valider la transaction
    $pdo->commit();
    logDebug("Transaction validée");
    
    // Envoyer un email de confirmation au candidat
    $subject = 'Candidature locative reçue - MY Invest Immobilier';
    $htmlBody = getCandidatureRecueEmailHTML($prenom, $nom, $logement, $total_uploaded);
    
    // Envoyer l'email avec PHPMailer (format HTML)
    $emailSent = sendEmail($email, $subject, $htmlBody, null, true);
    
    if (!$emailSent) {
        logDebug("Avertissement: Email de confirmation non envoyé", ['email' => $email]);
    } else {
        logDebug("Email de confirmation envoyé", ['email' => $email]);
    }
    
    // Retourner le succès
    logDebug("Candidature traitée avec succès", ['id' => $candidature_id, 'documents' => $total_uploaded]);
    echo json_encode([
        'success' => true,
        'candidature_id' => $candidature_id,
        'message' => 'Candidature enregistrée avec succès',
        'documents_uploaded' => $total_uploaded
    ]);
    
} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        logDebug("Transaction annulée");
    }
    
    logDebug("ERREUR", ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug_info' => 'Consultez les logs du serveur pour plus de détails'
    ]);
}
