<?php
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $logement_id = (int)$_POST['logement_id'];
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
    
    // Find the active contract for this logement
    $stmt = $pdo->prepare("
        SELECT c.*, 
               l.adresse, l.appartement,
               l.default_cles_appartement, l.default_cles_boite_lettres,
               l.default_etat_piece_principale, l.default_etat_cuisine, l.default_etat_salle_eau
        FROM contrats c
        LEFT JOIN logements l ON c.logement_id = l.id
        WHERE c.logement_id = ? AND c.statut = 'signe'
        ORDER BY c.date_creation DESC
        LIMIT 1
    ");
    $stmt->execute([$logement_id]);
    $contrat = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$contrat) {
        $_SESSION['error'] = "Aucun contrat signé trouvé pour ce logement";
        header('Location: etats-lieux.php');
        exit;
    }
    
    $contrat_id = $contrat['id'];
    
    // Get tenant(s) from contract
    $stmt = $pdo->prepare("SELECT * FROM locataires WHERE contrat_id = ? ORDER BY ordre ASC LIMIT 2");
    $stmt->execute([$contrat_id]);
    $locataires = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($locataires)) {
        $_SESSION['error'] = "Aucun locataire trouvé pour ce contrat";
        header('Location: etats-lieux.php');
        exit;
    }
    
    // Build locataire_nom_complet from all tenants
    $locataire_noms = array_map(function($loc) {
        return $loc['prenom'] . ' ' . $loc['nom'];
    }, $locataires);
    $locataire_nom_complet = implode(' et ', $locataire_noms);
    $locataire_email = $locataires[0]['email']; // Use first tenant's email
    
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
    
    // Prepare default values
    $default_cles_appartement = null;
    $default_cles_boite_lettres = null;
    $default_cles_total = null;
    $default_piece_principale = null;
    $default_coin_cuisine = null;
    $default_salle_eau_wc = null;
    $default_etat_general = null;
    
    // For entry: use logement defaults
    if ($type === 'entree') {
        $default_cles_appartement = (int)($contrat['default_cles_appartement'] ?? 2);
        $default_cles_boite_lettres = (int)($contrat['default_cles_boite_lettres'] ?? 1);
        $default_cles_total = $default_cles_appartement + $default_cles_boite_lettres;
        
        // Default room description template - used for main room and kitchen
        $default_room_description = "• Revêtement de sol : parquet très bon état d'usage\n• Murs : peintures très bon état\n• Plafond : peintures très bon état\n• Installations électriques et plomberie : fonctionnelles";
        
        $default_piece_principale = $contrat['default_etat_piece_principale'] ?? $default_room_description;
        
        $default_coin_cuisine = $contrat['default_etat_cuisine'] ?? $default_room_description;
        
        $default_salle_eau_wc = $contrat['default_etat_salle_eau'] ?? 
            "• Revêtement de sol : carrelage très bon état d'usage\n• Faïence : très bon état\n• Plafond : peintures très bon état\n• Installations électriques et plomberie : fonctionnelles";
        
        $default_etat_general = "Le logement a fait l'objet d'une remise en état générale avant l'entrée dans les lieux.\nIl est propre, entretenu et ne présente aucune dégradation apparente au jour de l'état des lieux.\nAucune anomalie constatée.";
    }
    // For exit: try to copy from entry state
    else if ($type === 'sortie') {
        $stmt = $pdo->prepare("SELECT * FROM etats_lieux WHERE contrat_id = ? AND type = 'entree' ORDER BY date_etat DESC LIMIT 1");
        $stmt->execute([$contrat_id]);
        $etat_entree = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($etat_entree) {
            $default_cles_appartement = $etat_entree['cles_appartement'];
            $default_cles_boite_lettres = $etat_entree['cles_boite_lettres'];
            $default_cles_total = $etat_entree['cles_total'];
            $default_piece_principale = $etat_entree['piece_principale'];
            $default_coin_cuisine = $etat_entree['coin_cuisine'];
            $default_salle_eau_wc = $etat_entree['salle_eau_wc'];
            $default_etat_general = "À compléter lors de l'état des lieux de sortie (anomalies constatées, traces d'usage, dégradations éventuelles).";
        }
    }
    
    // Insert new état des lieux with initial data
    try {
        $stmt = $pdo->prepare("
            INSERT INTO etats_lieux (
                contrat_id, type, date_etat, reference_unique,
                adresse, appartement, 
                bailleur_nom, locataire_nom_complet, locataire_email,
                cles_appartement, cles_boite_lettres, cles_total,
                piece_principale, coin_cuisine, salle_eau_wc, etat_general,
                statut, created_at, created_by
            ) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'brouillon', NOW(), ?)
        ");
        $stmt->execute([
            $contrat_id, 
            $type, 
            $date_etat, 
            $reference,
            $contrat['adresse'],
            $contrat['appartement'],
            'SCI My Invest Immobilier, représentée par Maxime ALEXANDRE',
            $locataire_nom_complet,
            $locataire_email,
            $default_cles_appartement,
            $default_cles_boite_lettres,
            $default_cles_total,
            $default_piece_principale,
            $default_coin_cuisine,
            $default_salle_eau_wc,
            $default_etat_general,
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
