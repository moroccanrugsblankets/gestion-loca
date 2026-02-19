<?php
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

if (!isset($_POST['inventaire_id'])) {
    $_SESSION['error'] = "Inventaire non spécifié";
    header('Location: inventaires.php');
    exit;
}

$inventaire_id = (int)$_POST['inventaire_id'];

try {
    $pdo->beginTransaction();
    
    // Get inventaire details
    $stmt = $pdo->prepare("SELECT * FROM inventaires WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$inventaire_id]);
    $inventaire = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$inventaire) {
        $_SESSION['error'] = "Inventaire introuvable";
        header('Location: inventaires.php');
        exit;
    }
    
    // NOTE: Photos are preserved (not deleted from filesystem) as per requirements
    // Only marking as deleted in database for soft delete functionality
    
    // Soft delete from database (set deleted_at timestamp instead of DELETE)
    // Note: If inventaire_photos table has deleted_at, it should also be soft deleted
    $stmt = $pdo->prepare("UPDATE inventaires SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$inventaire_id]);
    
    $pdo->commit();
    
    $_SESSION['success'] = "Inventaire supprimé avec succès";
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Erreur lors de la suppression de l'inventaire";
    error_log("Erreur suppression inventaire: " . $e->getMessage());
}

header('Location: inventaires.php');
exit;
