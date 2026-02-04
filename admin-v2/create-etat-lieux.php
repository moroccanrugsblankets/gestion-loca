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
    
    // Verify contract exists and get details
    $stmt = $pdo->prepare("
        SELECT c.*, 
               l.adresse, l.appartement,
               CONCAT(cand.prenom, ' ', cand.nom) as locataire_nom,
               cand.email as locataire_email
        FROM contrats c
        LEFT JOIN logements l ON c.logement_id = l.id
        LEFT JOIN candidatures cand ON c.candidature_id = cand.id
        WHERE c.id = ?
    ");
    $stmt->execute([$contrat_id]);
    $contrat = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$contrat) {
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
    
    // Generate unique reference
    $reference = 'EDL-' . strtoupper($type[0]) . '-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Insert new état des lieux with initial data
    try {
        $stmt = $pdo->prepare("
            INSERT INTO etats_lieux (
                contrat_id, type, date_etat, reference_unique,
                adresse, appartement, 
                bailleur_nom, locataire_nom_complet, locataire_email,
                statut, created_at, created_by
            ) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'brouillon', NOW(), ?)
        ");
        $stmt->execute([
            $contrat_id, 
            $type, 
            $date_etat, 
            $reference,
            $contrat['adresse'],
            $contrat['appartement'],
            'SCI My Invest Immobilier, représentée par Maxime ALEXANDRE',
            $contrat['locataire_nom'],
            $contrat['locataire_email'],
            $_SESSION['username'] ?? 'admin'
        ]);
        
        $etat_lieux_id = $pdo->lastInsertId();
        
        // Redirect to comprehensive form
        header("Location: edit-etat-lieux.php?id=$etat_lieux_id");
        exit;
        
    } catch (PDOException $e) {
        error_log("Error creating état des lieux: " . $e->getMessage());
        $_SESSION['error'] = "Erreur lors de la création de l'état des lieux";
        header('Location: etats-lieux.php');
        exit;
    }
}

header('Location: etats-lieux.php');
exit;
