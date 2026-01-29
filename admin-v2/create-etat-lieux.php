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
    
    // Verify contract exists
    $stmt = $pdo->prepare("SELECT id FROM contrats WHERE id = ?");
    $stmt->execute([$contrat_id]);
    if (!$stmt->fetch()) {
        $_SESSION['error'] = "Contrat non trouvé";
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
        $_SESSION['error'] = "Erreur lors de la création: " . $e->getMessage();
    }
    
    header('Location: etats-lieux.php');
    exit;
}

header('Location: etats-lieux.php');
exit;
