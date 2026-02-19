<?php
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: quittances.php');
    exit;
}

// Get quittance ID
$quittance_id = isset($_POST['quittance_id']) ? (int)$_POST['quittance_id'] : 0;

if (!$quittance_id) {
    $_SESSION['error'] = "ID de quittance invalide";
    header('Location: quittances.php');
    exit;
}

// Get quittance details before soft deletion
$stmt = $pdo->prepare("
    SELECT q.*, c.reference_unique as contrat_ref
    FROM quittances q
    INNER JOIN contrats c ON q.contrat_id = c.id
    WHERE q.id = ? AND q.deleted_at IS NULL
");
$stmt->execute([$quittance_id]);
$quittance = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quittance) {
    $_SESSION['error'] = "Quittance non trouvée";
    header('Location: quittances.php');
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Soft delete quittance (set deleted_at timestamp instead of DELETE)
    // PDF file is preserved (not deleted) as per requirements
    $stmt = $pdo->prepare("UPDATE quittances SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$quittance_id]);
    
    // Log the soft deletion
    if (isset($_SESSION['admin_id'])) {
        $stmt = $pdo->prepare("
            INSERT INTO logs (admin_id, action, details, date_action)
            VALUES (?, 'suppression_quittance', ?, NOW())
        ");
        $stmt->execute([
            $_SESSION['admin_id'],
            "Suppression (soft delete) de la quittance " . $quittance['reference_unique'] . " (Contrat: " . $quittance['contrat_ref'] . ")"
        ]);
    }
    
    // Commit transaction
    $pdo->commit();
    
    // NOTE: PDF file is preserved (not deleted) to maintain audit trail
    // File path: $quittance['fichier_pdf']
    
    $_SESSION['success'] = "Quittance " . $quittance['reference_unique'] . " supprimée avec succès";
    header('Location: quittances.php');
    exit;
    
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    $_SESSION['error'] = "Erreur lors de la suppression de la quittance : " . $e->getMessage();
    error_log("Erreur suppression quittance: " . $e->getMessage());
    header('Location: quittances.php');
    exit;
}
