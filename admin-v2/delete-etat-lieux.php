<?php
/**
 * Delete État des Lieux
 * My Invest Immobilier
 */

require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Méthode non autorisée";
    header('Location: etats-lieux.php');
    exit;
}

// Get état des lieux ID
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id < 1) {
    $_SESSION['error'] = "ID de l'état des lieux invalide";
    header('Location: etats-lieux.php');
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Get état des lieux details for logging
    $stmt = $pdo->prepare("SELECT * FROM etats_lieux WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $etat = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$etat) {
        throw new Exception("État des lieux non trouvé");
    }
    
    // Soft delete associated photos (mark as deleted, don't remove files)
    // Photos are preserved for audit trail and potential data recovery
    $stmt = $pdo->prepare("UPDATE etat_lieux_photos SET deleted_at = NOW() WHERE etat_lieux_id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    
    // Soft delete the état des lieux (set deleted_at timestamp instead of DELETE)
    $stmt = $pdo->prepare("UPDATE etats_lieux SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    
    // Commit transaction
    $pdo->commit();
    
    // Log the soft deletion
    error_log("État des lieux soft deleted - ID: $id, Type: {$etat['type']}, Contrat ID: {$etat['contrat_id']}, User: " . ($_SESSION['username'] ?? 'unknown'));
    
    $_SESSION['success'] = "État des lieux supprimé avec succès";
    
} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error deleting état des lieux ID $id: " . $e->getMessage());
    $_SESSION['error'] = "Erreur lors de la suppression: " . $e->getMessage();
}

header('Location: etats-lieux.php');
exit;
