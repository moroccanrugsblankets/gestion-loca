<?php
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contrat_id = (int)$_POST['contrat_id'];
    $type = $_POST['type'];
    $date_etat = $_POST['date_etat'];
    
    // Validate inputs
    if (!in_array($type, ['entree', 'sortie'])) {
        $_SESSION['error'] = "Type d'état des lieux invalide";
        header('Location: etats-lieux.php');
        exit;
    }
    
    // Validate date format and reasonableness
    $date = DateTime::createFromFormat('Y-m-d', $date_etat);
    if (!$date || $date->format('Y-m-d') !== $date_etat) {
        $_SESSION['error'] = "Format de date invalide";
        header('Location: etats-lieux.php');
        exit;
    }
    
    // Check date is not too far in the past or future (within 5 years)
    $now = new DateTime();
    $diff = $now->diff($date);
    if ($diff->y > 5) {
        $_SESSION['error'] = "La date ne peut pas être à plus de 5 ans dans le passé ou le futur";
        header('Location: etats-lieux.php');
        exit;
    }
    
    // Verify contract exists
    $stmt = $pdo->prepare("SELECT id FROM contrats WHERE id = ?");
    $stmt->execute([$contrat_id]);
    if (!$stmt->fetch()) {
        $_SESSION['error'] = "Contrat non trouvé";
        header('Location: etats-lieux.php');
        exit;
    }
    
    // Check for duplicate
    $stmt = $pdo->prepare("SELECT id FROM etats_lieux WHERE contrat_id = ? AND type = ?");
    $stmt->execute([$contrat_id, $type]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = "Un état des lieux de ce type existe déjà pour ce contrat";
        header('Location: etats-lieux.php');
        exit;
    }
    
    // Insert new état des lieux
    try {
        $stmt = $pdo->prepare("
            INSERT INTO etats_lieux (contrat_id, type, date_etat, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$contrat_id, $type, $date_etat]);
        $_SESSION['success'] = "État des lieux créé avec succès";
    } catch (PDOException $e) {
        error_log("Error creating état des lieux: " . $e->getMessage());
        $_SESSION['error'] = "Erreur lors de la création de l'état des lieux";
    }
    
    header('Location: etats-lieux.php');
    exit;
}

header('Location: etats-lieux.php');
exit;
