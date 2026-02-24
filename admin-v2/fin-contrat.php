<?php
/**
 * Action : Mettre fin au contrat (remise des clés)
 * Passe le statut du contrat à "fin"
 */
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: contrats.php');
    exit;
}

$contrat_id = isset($_POST['contrat_id']) ? (int)$_POST['contrat_id'] : 0;

if (!$contrat_id) {
    $_SESSION['error'] = "ID de contrat invalide";
    header('Location: contrats.php');
    exit;
}

// Get contract details
$stmt = $pdo->prepare("
    SELECT c.*, l.reference as logement_ref
    FROM contrats c
    LEFT JOIN logements l ON c.logement_id = l.id
    WHERE c.id = ? AND c.deleted_at IS NULL
");
$stmt->execute([$contrat_id]);
$contrat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contrat) {
    $_SESSION['error'] = "Contrat introuvable";
    header('Location: contrats.php');
    exit;
}

// Update the contract status to 'fin'
$stmt = $pdo->prepare("UPDATE contrats SET statut = 'fin', updated_at = NOW() WHERE id = ?");
$stmt->execute([$contrat_id]);

// Mark the logement as available again
if ($contrat['logement_id']) {
    $pdo->prepare("UPDATE logements SET statut = 'disponible' WHERE id = ?")->execute([$contrat['logement_id']]);
}

// Log the action
$stmt = $pdo->prepare("
    INSERT INTO logs (type_entite, entite_id, action, details, ip_address, created_at)
    VALUES ('contrat', ?, 'fin_contrat', ?, ?, NOW())
");
$stmt->execute([
    $contrat_id,
    "Contrat {$contrat['reference_unique']} clôturé suite à la remise des clés",
    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
]);

$_SESSION['success'] = "Contrat {$contrat['reference_unique']} clôturé avec succès (remise des clés confirmée). Le logement est de nouveau disponible.";

// Redirect back to the referring page
$source = isset($_POST['source']) ? $_POST['source'] : '';
if ($source === 'detail') {
    header('Location: contrat-detail.php?id=' . $contrat_id);
} else {
    header('Location: contrats.php');
}
exit;
