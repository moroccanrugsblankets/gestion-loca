<?php
/**
 * Traitement de soumission de candidature locative
 * Enregistre la candidature et les documents, puis marque le statut "En cours"
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mail-templates.php';

header('Content-Type: application/json');

try {
    // Vérification du token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        throw new Exception('Token CSRF invalide');
    }
    
    // Validation des champs obligatoires
    $required_fields = [
        'nom', 'prenom', 'email', 'telephone', 'logement_id',
        'statut_professionnel', 'periode_essai', 'revenus_mensuels', 'type_revenus',
        'situation_logement', 'preavis_donne', 'nb_occupants', 'garantie_visale'
    ];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Le champ '$field' est obligatoire");
        }
    }
    
    // Vérifier qu'il y a au moins un document
    if (empty($_FILES['documents']['name'][0])) {
        throw new Exception('Au moins un document justificatif est requis');
    }
    
    // Vérifier l'acceptation des conditions
    if (!isset($_POST['accepte_conditions'])) {
        throw new Exception('Vous devez accepter les conditions de traitement des données');
    }
    
    // Nettoyer et sécuriser les données
    $nom = htmlspecialchars(trim($_POST['nom']), ENT_QUOTES, 'UTF-8');
    $prenom = htmlspecialchars(trim($_POST['prenom']), ENT_QUOTES, 'UTF-8');
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $telephone = htmlspecialchars(trim($_POST['telephone']), ENT_QUOTES, 'UTF-8');
    $logement_id = (int)$_POST['logement_id'];
    
    if (!$email) {
        throw new Exception('Email invalide');
    }
    
    // Vérifier que le logement existe et est disponible
    $stmt = $pdo->prepare("SELECT * FROM logements WHERE id = ? AND statut = 'disponible'");
    $stmt->execute([$logement_id]);
    $logement = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$logement) {
        throw new Exception('Logement non disponible');
    }
    
    // Début de la transaction
    $pdo->beginTransaction();
    
    // Insérer la candidature
    $stmt = $pdo->prepare("
        INSERT INTO candidatures (
            logement_id, nom, prenom, email, telephone,
            statut_professionnel, periode_essai,
            revenus_mensuels, type_revenus,
            situation_logement, preavis_donne,
            nb_occupants, garantie_visale,
            statut, date_soumission
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'En cours', NOW())
    ");
    
    $stmt->execute([
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
    
    // Créer le dossier uploads pour cette candidature
    $upload_dir = __DIR__ . '/../uploads/candidatures/' . $candidature_id;
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Traiter les documents uploadés
    $documents = $_FILES['documents'];
    $uploaded_count = 0;
    
    for ($i = 0; $i < count($documents['name']); $i++) {
        if ($documents['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }
        
        // Vérifier le type MIME réel
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $documents['tmp_name'][$i]);
        finfo_close($finfo);
        
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
        if (!in_array($mime_type, $allowed_types)) {
            continue;
        }
        
        // Vérifier la taille (max 5 Mo)
        if ($documents['size'][$i] > 5 * 1024 * 1024) {
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
        
        $filename = 'doc_' . $uploaded_count . '_' . bin2hex(random_bytes(8)) . $extension;
        $filepath = $upload_dir . '/' . $filename';
        
        // Déplacer le fichier
        if (move_uploaded_file($documents['tmp_name'][$i], $filepath)) {
            // Enregistrer dans la base de données
            $stmt = $pdo->prepare("
                INSERT INTO candidature_documents (candidature_id, type_document, nom_fichier, chemin_fichier)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $candidature_id,
                'Pièce justificative',
                $documents['name'][$i],
                'candidatures/' . $candidature_id . '/' . $filename
            ]);
            
            $uploaded_count++;
        }
    }
    
    if ($uploaded_count === 0) {
        throw new Exception('Aucun document n\'a pu être uploadé');
    }
    
    // Logger l'action
    // Note: logAction from functions.php takes (contratId, action, details)
    // For candidature logging, we'll use executeQuery directly
    $logSql = "INSERT INTO logs (candidature_id, action, details, ip_address) VALUES (?, ?, ?, ?)";
    executeQuery($logSql, [$candidature_id, 'Candidature soumise', "Candidature #$candidature_id - $nom $prenom", $_SERVER['REMOTE_ADDR']]);
    
    // Valider la transaction
    $pdo->commit();
    
    // Envoyer un email de confirmation au candidat
    $subject = 'Candidature locative reçue - MY Invest Immobilier';
    $htmlBody = getCandidatureRecueEmailHTML($prenom, $nom, $logement, $uploaded_count);
    
    // Envoyer l'email avec PHPMailer (format HTML)
    $emailSent = sendEmail($email, $subject, $htmlBody, null, true);
    
    if (!$emailSent) {
        error_log("Avertissement: Email de confirmation non envoyé à $email pour candidature #$candidature_id");
    }
    
    // Retourner le succès
    echo json_encode([
        'success' => true,
        'candidature_id' => $candidature_id,
        'message' => 'Candidature enregistrée avec succès'
    ]);
    
} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log('Erreur soumission candidature: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Une erreur est survenue lors de l\'enregistrement de votre candidature. Merci de réessayer.'
    ]);
}
