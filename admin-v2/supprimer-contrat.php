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

// Get contract details before deletion
$stmt = $pdo->prepare("
    SELECT c.*, l.reference as logement_ref
    FROM contrats c
    LEFT JOIN logements l ON c.logement_id = l.id
    WHERE c.id = ?
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
    
    // Log the deletion action before deleting
    $stmt = $pdo->prepare("
        INSERT INTO logs (type_entite, entite_id, action, details, ip_address, created_at)
        VALUES ('contrat', ?, 'Contrat supprimé', ?, ?, NOW())
    ");
    $stmt->execute([
        $contrat_id,
        "Contrat {$contrat['reference_unique']} supprimé pour logement {$contrat['logement_ref']}",
        $_SERVER['REMOTE_ADDR']
    ]);
    
    // Delete associated locataires (will cascade delete via foreign key)
    // Delete contract
    $stmt = $pdo->prepare("DELETE FROM contrats WHERE id = ?");
    $stmt->execute([$contrat_id]);
    
    // Delete PDF files if they exist
    $pdf_path = dirname(__DIR__) . '/pdf/contrats/bail-' . $contrat['reference_unique'] . '.pdf';
    if (file_exists($pdf_path)) {
        unlink($pdf_path);
    }
    
    // Delete locataire identity documents if they exist
    $stmt = $pdo->prepare("SELECT piece_identite_recto, piece_identite_verso FROM locataires WHERE contrat_id = ?");
    $stmt->execute([$contrat_id]);
    $locataires = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($locataires as $locataire) {
        if ($locataire['piece_identite_recto'] && file_exists($locataire['piece_identite_recto'])) {
            unlink($locataire['piece_identite_recto']);
        }
        if ($locataire['piece_identite_verso'] && file_exists($locataire['piece_identite_verso'])) {
            unlink($locataire['piece_identite_verso']);
        }
    }
    
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
