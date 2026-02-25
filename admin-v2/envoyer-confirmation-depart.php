<?php
/**
 * Action : Envoyer l'email de confirmation réception courrier AR24 au locataire
 */
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: contrats.php');
    exit;
}

$contrat_id = isset($_POST['contrat_id']) ? (int)$_POST['contrat_id'] : 0;
$date_fin_prevue_input = isset($_POST['date_fin_prevue']) ? trim($_POST['date_fin_prevue']) : '';

if (!$contrat_id) {
    $_SESSION['error'] = "ID de contrat invalide";
    header('Location: contrats.php');
    exit;
}

// If a new date_fin_prevue was submitted via the modal, update it first
if (!empty($date_fin_prevue_input)) {
    $d = DateTime::createFromFormat('Y-m-d', $date_fin_prevue_input);
    if ($d && $d->format('Y-m-d') === $date_fin_prevue_input) {
        $pdo->prepare("UPDATE contrats SET date_fin_prevue = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$date_fin_prevue_input, $contrat_id]);
    }
}

// Get contract + logement details
$stmt = $pdo->prepare("
    SELECT c.*,
           l.reference as logement_ref,
           l.adresse as logement_adresse
    FROM contrats c
    INNER JOIN logements l ON c.logement_id = l.id
    WHERE c.id = ? AND c.deleted_at IS NULL
");
$stmt->execute([$contrat_id]);
$contrat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contrat) {
    $_SESSION['error'] = "Contrat introuvable";
    header('Location: contrats.php');
    exit;
}

// Fetch all tenants for this contract
$stmt = $pdo->prepare("SELECT * FROM locataires WHERE contrat_id = ? ORDER BY ordre ASC");
$stmt->execute([$contrat_id]);
$locataires = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($locataires)) {
    $_SESSION['error'] = "Aucun locataire trouvé pour ce contrat";
    $redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'contrats.php';
    header('Location: ' . $redirect);
    exit;
}

$logement = $contrat['logement_ref'] . ' - ' . $contrat['logement_adresse'];
$dateReception = date('d/m/Y');
$dateFinPrevue = !empty($contrat['date_fin_prevue']) ? date('d/m/Y', strtotime($contrat['date_fin_prevue'])) : 'Non définie';
$emailsSent = 0;
$emailsFailed = 0;

foreach ($locataires as $locataire) {
    $sent = sendTemplatedEmail(
        'confirmation_courrier_ar24',
        $locataire['email'],
        [
            'nom'            => $locataire['nom'],
            'prenom'         => $locataire['prenom'],
            'logement'       => $logement,
            'reference'      => $contrat['reference_unique'],
            'date_reception' => $dateReception,
            'date_fin_prevue' => $dateFinPrevue,
            'signature'      => getParameter('email_signature', ''),
        ],
        null,
        false,
        true, // addAdminBcc
        ['contexte' => 'contrat_id=' . $contrat_id]
    );
    if ($sent) {
        $emailsSent++;
    } else {
        $emailsFailed++;
        error_log("Erreur envoi confirmation AR24 à " . $locataire['email']);
    }
}

// Log the action
$stmt = $pdo->prepare("
    INSERT INTO logs (type_entite, entite_id, action, details, ip_address, created_at)
    VALUES ('contrat', ?, 'envoi_confirmation_ar24', ?, ?, NOW())
");
$stmt->execute([
    $contrat_id,
    "Confirmation réception courrier AR24 envoyée à $emailsSent locataire(s)",
    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
]);

if ($emailsSent > 0) {
    $_SESSION['success'] = "Email de confirmation AR24 envoyé avec succès à $emailsSent locataire(s)";
} else {
    $_SESSION['error'] = "Échec de l'envoi de l'email de confirmation AR24";
}

// Redirect back to the referring page (contrat-detail or contrats list)
$source = isset($_POST['source']) ? $_POST['source'] : '';
if ($source === 'detail') {
    header('Location: contrat-detail.php?id=' . $contrat_id);
} else {
    header('Location: contrats.php');
}
exit;
