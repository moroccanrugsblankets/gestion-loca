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
    $note = trim($_POST['note']);
    
    // Validate input
    if (empty($note)) {
        $_SESSION['error'] = "La note ne peut pas être vide";
        header('Location: candidature-detail.php?id=' . $candidature_id);
        exit;
    }
    
    // Limit note length
    if (strlen($note) > 5000) {
        $_SESSION['error'] = "La note est trop longue (maximum 5000 caractères)";
        header('Location: candidature-detail.php?id=' . $candidature_id);
        exit;
    }
    
    // Verify candidature exists
    $stmt = $pdo->prepare("SELECT id FROM candidatures WHERE id = ?");
    $stmt->execute([$candidature_id]);
    $candidature = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$candidature) {
        $_SESSION['error'] = "Candidature non trouvée";
        header('Location: candidatures.php');
        exit;
    }
    
    // Add note to logs
    try {
        logCandidatureAction($pdo, $candidature_id, 'Note ajoutée', $note);
        $_SESSION['success'] = "Note ajoutée avec succès";
    } catch (Exception $e) {
        error_log("Error adding note: " . $e->getMessage());
        $_SESSION['error'] = "Erreur lors de l'ajout de la note";
    }
    
    header('Location: candidature-detail.php?id=' . $candidature_id);
    exit;
}

header('Location: candidatures.php');
exit;
