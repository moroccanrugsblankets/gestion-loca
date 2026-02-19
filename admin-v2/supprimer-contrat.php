<?php
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: contrats.php');
    exit;
}

// Get contract ID
$contrat_id = isset($_POST['contrat_id']) ? (int)$_POST['contrat_id'] : 0;

if (!$contrat_id) {
    $_SESSION['error'] = "ID de contrat invalide";
    header('Location: contrats.php');
    exit;
}

// Get contract details before soft deletion
$stmt = $pdo->prepare("
    SELECT c.*, l.reference as logement_ref
    FROM contrats c
    LEFT JOIN logements l ON c.logement_id = l.id
    WHERE c.id = ? AND c.deleted_at IS NULL
");
$stmt->execute([$contrat_id]);
$contrat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contrat) {
    $_SESSION['error'] = "Contrat non trouvé";
    header('Location: contrats.php');
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Log the soft deletion action
    $stmt = $pdo->prepare("
        INSERT INTO logs (type_entite, entite_id, action, details, ip_address, created_at)
        VALUES ('contrat', ?, 'Contrat supprimé (soft delete)', ?, ?, NOW())
    ");
    $stmt->execute([
        $contrat_id,
        "Contrat {$contrat['reference_unique']} soft deleted pour logement {$contrat['logement_ref']}",
        $_SERVER['REMOTE_ADDR']
    ]);
    
    // Soft delete contract (set deleted_at timestamp instead of DELETE)
    // Associated locataires are preserved with the contract
    // PDF files and identity documents are preserved for audit trail
    $stmt = $pdo->prepare("UPDATE contrats SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$contrat_id]);
    
    // NOTE: PDF files and identity documents are preserved (not deleted) as per requirements
    // This maintains data integrity and allows for audit trails and potential recovery
    
    // If contract was linked to a candidature, reset its status
    if ($contrat['candidature_id']) {
        $stmt = $pdo->prepare("UPDATE candidatures SET statut = 'accepte' WHERE id = ?");
        $stmt->execute([$contrat['candidature_id']]);
    }
    
    // If logement was reserved/occupied, make it available again
    if ($contrat['logement_id']) {
        $stmt = $pdo->prepare("UPDATE logements SET statut = 'disponible' WHERE id = ?");
        $stmt->execute([$contrat['logement_id']]);
    }
    
    // Commit transaction
    $pdo->commit();
    
    $_SESSION['success'] = "Contrat {$contrat['reference_unique']} supprimé avec succès";
    
} catch (Exception $e) {
    // Rollback on error
    $pdo->rollBack();
    error_log("Erreur lors de la suppression du contrat: " . $e->getMessage());
    $_SESSION['error'] = "Erreur lors de la suppression du contrat: " . $e->getMessage();
}

header('Location: contrats.php');
exit;
