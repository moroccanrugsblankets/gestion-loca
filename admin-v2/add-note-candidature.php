<?php
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $candidature_id = (int)$_POST['candidature_id'];
    $note = $_POST['note'];
    
    // Verify candidature exists
    $stmt = $pdo->prepare("SELECT id FROM candidatures WHERE id = ?");
    $stmt->execute([$candidature_id]);
    $candidature = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$candidature) {
        $_SESSION['error'] = "Candidature non trouvée";
        header('Location: candidatures.php');
        exit;
    }
    
    // Add note to logs
    try {
        $stmt = $pdo->prepare("
            INSERT INTO logs (candidature_id, action, details, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([
            $candidature_id,
            'Note ajoutée',
            $note
        ]);
        $_SESSION['success'] = "Note ajoutée avec succès";
    } catch (PDOException $e) {
        // If candidature_id column doesn't exist, try polymorphic structure
        try {
            $stmt = $pdo->prepare("
                INSERT INTO logs (type_entite, entite_id, action, details, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                'candidature',
                $candidature_id,
                'Note ajoutée',
                $note
            ]);
            $_SESSION['success'] = "Note ajoutée avec succès";
        } catch (PDOException $e2) {
            $_SESSION['error'] = "Erreur lors de l'ajout de la note: " . $e2->getMessage();
        }
    }
    
    header('Location: candidature-detail.php?id=' . $candidature_id);
    exit;
}

header('Location: candidatures.php');
exit;
