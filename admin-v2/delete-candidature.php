<?php
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: candidatures.php');
    exit;
}

// Get candidature ID
$candidature_id = isset($_POST['candidature_id']) ? (int)$_POST['candidature_id'] : 0;

if (!$candidature_id) {
    $_SESSION['error'] = "ID de candidature invalide";
    header('Location: candidatures.php');
    exit;
}

// Get candidature details before soft deletion
$stmt = $pdo->prepare("
    SELECT c.*, l.reference as logement_ref
    FROM candidatures c
    LEFT JOIN logements l ON c.logement_id = l.id
    WHERE c.id = ? AND c.deleted_at IS NULL
");
$stmt->execute([$candidature_id]);
$candidature = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$candidature) {
    $_SESSION['error'] = "Candidature non trouvée";
    header('Location: candidatures.php');
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Soft delete candidature (set deleted_at timestamp instead of DELETE)
    $stmt = $pdo->prepare("UPDATE candidatures SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$candidature_id]);
    
    // Commit transaction
    $pdo->commit();
    
    // NOTE: Document files are preserved (not deleted) as per requirements
    // This allows for data recovery and audit trail
    
    $candidature_ref = $candidature['reference_unique'] ?? "#{$candidature_id}";
    $_SESSION['success'] = "Candidature {$candidature_ref} supprimée avec succès";
    
} catch (Exception $e) {
    // Rollback on error
    $pdo->rollBack();
    error_log("Erreur lors de la suppression de la candidature: " . $e->getMessage());
    $_SESSION['error'] = "Erreur lors de la suppression de la candidature: " . $e->getMessage();
}

header('Location: candidatures.php');
exit;
