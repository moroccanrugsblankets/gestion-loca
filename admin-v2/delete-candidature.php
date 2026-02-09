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

// Get candidature details before deletion
$stmt = $pdo->prepare("
    SELECT c.*, l.reference as logement_ref
    FROM candidatures c
    LEFT JOIN logements l ON c.logement_id = l.id
    WHERE c.id = ?
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
    
    // Get candidature documents before deletion
    $stmt = $pdo->prepare("SELECT chemin_fichier FROM candidature_documents WHERE candidature_id = ?");
    $stmt->execute([$candidature_id]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Delete associated documents from candidature_documents table
    $stmt = $pdo->prepare("DELETE FROM candidature_documents WHERE candidature_id = ?");
    $stmt->execute([$candidature_id]);
    
    // Delete candidature
    $stmt = $pdo->prepare("DELETE FROM candidatures WHERE id = ?");
    $stmt->execute([$candidature_id]);
    
    // Delete document files if they exist
    foreach ($documents as $document) {
        if ($document['chemin_fichier'] && file_exists($document['chemin_fichier'])) {
            unlink($document['chemin_fichier']);
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
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
