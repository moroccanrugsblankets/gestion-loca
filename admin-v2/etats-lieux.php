<?php
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

// Get all etats des lieux
$stmt = $pdo->query("
    SELECT edl.*, c.reference as contrat_ref, 
           CONCAT(cand.prenom, ' ', cand.nom) as locataire,
           l.adresse
    FROM etats_lieux edl
    LEFT JOIN contrats c ON edl.contrat_id = c.id
    LEFT JOIN candidatures cand ON c.candidature_id = cand.id
    LEFT JOIN logements l ON c.logement_id = l.id
    ORDER BY edl.date_etat DESC
");
$etats_lieux = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>États des lieux - My Invest Immobilier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
        }
        .sidebar {
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            min-height: 100vh;
            padding: 0;
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            color: white;
        }
        .sidebar .logo {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar .logo h4 {
            margin: 10px 0 5px 0;
            font-size: 18px;
            font-weight: 600;
        }
        .sidebar .logo small {
            color: #bdc3c7;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        .sidebar .nav-link:hover {
            background-color: rgba(255,255,255,0.1);
            color: white;
            border-left-color: #3498db;
        }
        .sidebar .nav-link.active {
            background-color: rgba(52, 152, 219, 0.2);
            color: white;
            border-left-color: #3498db;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
        }
        .main-content {
            margin-left: 250px;
            padding: 30px;
        }
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .logout-btn {
            position: absolute;
            bottom: 20px;
            left: 20px;
            right: 20px;
        }
        .etat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .etat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <i class="bi bi-building" style="font-size: 2rem;"></i>
            <h4>MY Invest</h4>
            <small>Immobilier</small>
        </div>
        <ul class="nav flex-column mt-4">
            <li class="nav-item">
                <a class="nav-link" href="index.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="candidatures.php">
                    <i class="bi bi-file-earmark-text"></i> Candidatures
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logements.php">
                    <i class="bi bi-house-door"></i> Logements
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="contrats.php">
                    <i class="bi bi-file-earmark-check"></i> Contrats
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="parametres.php">
                    <i class="bi bi-gear"></i> Paramètres
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="email-templates.php">
                    <i class="bi bi-envelope"></i> Templates d'Email
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="etats-lieux.php">
                    <i class="bi bi-clipboard-check"></i> États des lieux
                </a>
            </li>
        </ul>
        <a href="logout.php" class="btn btn-outline-light logout-btn">
            <i class="bi bi-box-arrow-right"></i> Déconnexion
        </a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4>États des lieux</h4>
                    <p class="text-muted mb-0">Gestion des états des lieux d'entrée et de sortie</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEtatModal">
                    <i class="bi bi-plus-circle"></i> Nouvel état des lieux
                </button>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($etats_lieux)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-clipboard-check" style="font-size: 4rem; color: #dee2e6;"></i>
                    <h5 class="mt-3 text-muted">Aucun état des lieux enregistré</h5>
                    <p class="text-muted">Créez votre premier état des lieux pour commencer</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEtatModal">
                        <i class="bi bi-plus-circle"></i> Créer un état des lieux
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($etats_lieux as $etat): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="etat-card">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h5 class="mb-0">
                                <?php if ($etat['type'] === 'entree'): ?>
                                    <i class="bi bi-box-arrow-in-right text-success"></i>
                                <?php else: ?>
                                    <i class="bi bi-box-arrow-right text-danger"></i>
                                <?php endif; ?>
                                État des lieux <?php echo htmlspecialchars($etat['type']); ?>
                            </h5>
                            <span class="badge bg-<?php echo $etat['type'] === 'entree' ? 'success' : 'danger'; ?>">
                                <?php echo ucfirst($etat['type']); ?>
                            </span>
                        </div>
                        
                        <p class="text-muted small mb-2">
                            <strong>Contrat:</strong> <?php echo htmlspecialchars($etat['contrat_ref'] ?? 'N/A'); ?>
                        </p>
                        
                        <p class="text-muted small mb-2">
                            <strong>Locataire:</strong> <?php echo htmlspecialchars($etat['locataire'] ?? 'N/A'); ?>
                        </p>
                        
                        <p class="text-muted small mb-3">
                            <strong>Adresse:</strong> <?php echo htmlspecialchars($etat['adresse'] ?? 'N/A'); ?>
                        </p>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="bi bi-calendar"></i> <?php echo date('d/m/Y', strtotime($etat['date_etat'])); ?>
                            </small>
                            <div>
                                <a href="#" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="#" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-download"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add État Modal -->
    <div class="modal fade" id="addEtatModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nouvel état des lieux</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="create-etat-lieux.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Type:</label>
                            <select name="type" class="form-select" required>
                                <option value="">-- Sélectionner --</option>
                                <option value="entree">État des lieux d'entrée</option>
                                <option value="sortie">État des lieux de sortie</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contrat:</label>
                            <select name="contrat_id" class="form-select" required>
                                <option value="">-- Sélectionner un contrat --</option>
                                <?php
                                $stmt = $pdo->query("SELECT id, reference FROM contrats WHERE statut = 'signe' ORDER BY reference");
                                while ($contrat = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value='{$contrat['id']}'>{$contrat['reference']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date:</label>
                            <input type="date" name="date_etat" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Créer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
