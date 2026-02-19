<?php
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: administrateurs.php');
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

try {
    switch ($action) {
        case 'add':
            // Validate inputs
            $nom = trim($_POST['nom']);
            $prenom = trim($_POST['prenom']);
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $role = $_POST['role'];
            $actif = isset($_POST['actif']) ? 1 : 0;
            
            if (empty($nom) || empty($prenom) || empty($username) || empty($email) || empty($password)) {
                throw new Exception("Tous les champs obligatoires doivent être remplis");
            }
            
            // Check if username already exists
            $stmt = $pdo->prepare("SELECT id FROM administrateurs WHERE username = ? AND deleted_at IS NULL");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                throw new Exception("Ce nom d'utilisateur existe déjà");
            }
            
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM administrateurs WHERE email = ? AND deleted_at IS NULL");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new Exception("Cette adresse email est déjà utilisée");
            }
            
            // Hash password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new admin
            $stmt = $pdo->prepare("
                INSERT INTO administrateurs (nom, prenom, username, email, password_hash, role, actif, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$nom, $prenom, $username, $email, $password_hash, $role, $actif]);
            
            $_SESSION['success'] = "Administrateur '$username' ajouté avec succès";
            break;
            
        case 'edit':
            // Validate inputs
            $id = (int)$_POST['id'];
            $nom = trim($_POST['nom']);
            $prenom = trim($_POST['prenom']);
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $password = isset($_POST['password']) ? $_POST['password'] : '';
            $role = $_POST['role'];
            $actif = isset($_POST['actif']) ? 1 : 0;
            
            if (!$id || empty($nom) || empty($prenom) || empty($username) || empty($email)) {
                throw new Exception("Tous les champs obligatoires doivent être remplis");
            }
            
            // Check if username already exists (excluding current user)
            $stmt = $pdo->prepare("SELECT id FROM administrateurs WHERE username = ? AND id != ? AND deleted_at IS NULL");
            $stmt->execute([$username, $id]);
            if ($stmt->fetch()) {
                throw new Exception("Ce nom d'utilisateur existe déjà");
            }
            
            // Check if email already exists (excluding current user)
            $stmt = $pdo->prepare("SELECT id FROM administrateurs WHERE email = ? AND id != ? AND deleted_at IS NULL");
            $stmt->execute([$email, $id]);
            if ($stmt->fetch()) {
                throw new Exception("Cette adresse email est déjà utilisée");
            }
            
            // Update admin
            if (!empty($password)) {
                // Update with new password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE administrateurs 
                    SET nom = ?, prenom = ?, username = ?, email = ?, password_hash = ?, role = ?, actif = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nom, $prenom, $username, $email, $password_hash, $role, $actif, $id]);
            } else {
                // Update without changing password
                $stmt = $pdo->prepare("
                    UPDATE administrateurs 
                    SET nom = ?, prenom = ?, username = ?, email = ?, role = ?, actif = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nom, $prenom, $username, $email, $role, $actif, $id]);
            }
            
            $_SESSION['success'] = "Administrateur '$username' modifié avec succès";
            break;
            
        case 'delete':
            $id = (int)$_POST['id'];
            
            if (!$id) {
                throw new Exception("ID invalide");
            }
            
            // Get admin info before soft deletion
            $stmt = $pdo->prepare("SELECT username FROM administrateurs WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$id]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$admin) {
                throw new Exception("Administrateur non trouvé");
            }
            
            // Prevent deleting the last admin
            $stmt = $pdo->query("SELECT COUNT(*) FROM administrateurs WHERE role = 'admin' AND actif = TRUE AND deleted_at IS NULL");
            $adminCount = $stmt->fetchColumn();
            
            if ($adminCount <= 1) {
                $stmt = $pdo->prepare("SELECT role, actif FROM administrateurs WHERE id = ? AND deleted_at IS NULL");
                $stmt->execute([$id]);
                $adminToDelete = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($adminToDelete && $adminToDelete['role'] === 'admin' && $adminToDelete['actif']) {
                    throw new Exception("Impossible de supprimer le dernier administrateur actif");
                }
            }
            
            // Soft delete admin (set deleted_at timestamp instead of DELETE)
            $stmt = $pdo->prepare("UPDATE administrateurs SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$id]);
            
            $_SESSION['success'] = "Administrateur '{$admin['username']}' supprimé avec succès";
            break;
            
        default:
            throw new Exception("Action invalide");
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}

header('Location: administrateurs.php');
exit;
