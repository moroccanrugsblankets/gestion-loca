<?php
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

$locataireId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$locataireId) {
    $_SESSION['error'] = "Locataire invalide.";
    header('Location: contrats.php');
    exit;
}

// Fetch locataire with contract info
$stmt = $pdo->prepare("
    SELECT l.*, c.id as contrat_id
    FROM locataires l
    INNER JOIN contrats c ON l.contrat_id = c.id
    WHERE l.id = ?
");
$stmt->execute([$locataireId]);
$locataire = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$locataire) {
    $_SESSION['error'] = "Locataire introuvable.";
    header('Location: contrats.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Token CSRF invalide.';
    } else {
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $dateNaissance = trim($_POST['date_naissance'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');

        if (empty($nom) || empty($prenom) || empty($dateNaissance) || empty($email)) {
            $error = 'Les champs Nom, Prénom, Date de naissance et Email sont obligatoires.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Adresse email invalide.';
        } else {
            $stmt = $pdo->prepare("
                UPDATE locataires SET nom = ?, prenom = ?, date_naissance = ?, email = ?, telephone = ?
                WHERE id = ?
            ");
            $stmt->execute([$nom, $prenom, $dateNaissance, $email, $telephone ?: null, $locataireId]);

            // Log the action
            $stmt2 = $pdo->prepare("
                INSERT INTO logs (type_entite, entite_id, action, details, ip_address, created_at)
                VALUES ('locataire', ?, 'modification_locataire', ?, ?, NOW())
            ");
            $stmt2->execute([
                $locataireId,
                "Données modifiées par l'administrateur",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            $_SESSION['success'] = "Données du locataire mises à jour avec succès.";
            header('Location: contrat-detail.php?id=' . $locataire['contrat_id']);
            exit;
        }
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier le locataire - Admin MyInvest</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <?php require_once __DIR__ . '/includes/sidebar-styles.php'; ?>
</head>
<body>
    <?php require_once __DIR__ . '/includes/menu.php'; ?>
    <div class="main-content">
        <div class="container mt-4">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="contrats.php">Contrats</a></li>
                    <li class="breadcrumb-item"><a href="contrat-detail.php?id=<?= $locataire['contrat_id'] ?>">Contrat</a></li>
                    <li class="breadcrumb-item active">Modifier locataire <?= $locataire['ordre'] ?></li>
                </ol>
            </nav>

            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-person-gear"></i> Modifier les informations du locataire <?= $locataire['ordre'] ?></h5>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nom" class="form-label">Nom *</label>
                                <input type="text" class="form-control" id="nom" name="nom"
                                       value="<?= htmlspecialchars($_POST['nom'] ?? $locataire['nom']) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="prenom" class="form-label">Prénom *</label>
                                <input type="text" class="form-control" id="prenom" name="prenom"
                                       value="<?= htmlspecialchars($_POST['prenom'] ?? $locataire['prenom']) ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="date_naissance" class="form-label">Date de naissance *</label>
                                <input type="date" class="form-control" id="date_naissance" name="date_naissance"
                                       value="<?= htmlspecialchars($_POST['date_naissance'] ?? $locataire['date_naissance']) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?= htmlspecialchars($_POST['email'] ?? $locataire['email']) ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="telephone" class="form-label">N° de téléphone</label>
                                <input type="tel" class="form-control" id="telephone" name="telephone"
                                       value="<?= htmlspecialchars($_POST['telephone'] ?? $locataire['telephone'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg"></i> Enregistrer
                            </button>
                            <a href="contrat-detail.php?id=<?= $locataire['contrat_id'] ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-x-lg"></i> Annuler
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
