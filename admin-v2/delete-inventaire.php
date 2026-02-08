<?php
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

if (!isset($_POST['inventaire_id'])) {
    $_SESSION['error'] = "Inventaire non spécifié";
    header('Location: inventaires.php');
    exit;
}

$inventaire_id = (int)$_POST['inventaire_id'];

try {
    $pdo->beginTransaction();
    
    // Get inventaire to delete photos
    $stmt = $pdo->prepare("SELECT * FROM inventaires WHERE id = ?");
    $stmt->execute([$inventaire_id]);
    $inventaire = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$inventaire) {
        $_SESSION['error'] = "Inventaire introuvable";
        header('Location: inventaires.php');
        exit;
    }
    
    // Delete photos from filesystem
    $stmt = $pdo->prepare("SELECT fichier FROM inventaire_photos WHERE inventaire_id = ?");
    $stmt->execute([$inventaire_id]);
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($photos as $photo) {
        $filepath = __DIR__ . '/../uploads/inventaires/' . basename($photo['fichier']);
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }
    
    // Delete from database (cascade will handle inventaire_photos and inventaire_locataires)
    $stmt = $pdo->prepare("DELETE FROM inventaires WHERE id = ?");
    $stmt->execute([$inventaire_id]);
    
    $pdo->commit();
    
    $_SESSION['success'] = "Inventaire supprimé avec succès";
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Erreur lors de la suppression de l'inventaire";
    error_log("Erreur suppression inventaire: " . $e->getMessage());
}

header('Location: inventaires.php');
exit;
