<?php
require_once 'auth.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// Get statistics
$stats = [];

// Total applications
$stmt = $pdo->query("SELECT COUNT(*) as total FROM candidatures");
$stats['total_candidatures'] = $stmt->fetch()['total'];

// By status
$stmt = $pdo->query("SELECT statut, COUNT(*) as count FROM candidatures GROUP BY statut");
while ($row = $stmt->fetch()) {
    $stats['statut_' . strtolower(str_replace(' ', '_', $row['statut']))] = $row['count'];
}

// Properties
$stmt = $pdo->query("SELECT COUNT(*) as total FROM logements WHERE statut = 'Disponible'");
$stats['logements_disponibles'] = $stmt->fetch()['total'];

// Contracts
$stmt = $pdo->query("SELECT COUNT(*) as total FROM contrats WHERE statut = 'signe'");
$stats['contrats_signes'] = $stmt->fetch()['total'];

// Recent applications (last 10)
$stmt = $pdo->query("SELECT c.*, l.reference as logement_ref, l.adresse 
                      FROM candidatures c 
                      LEFT JOIN logements l ON c.logement_id = l.id 
                      ORDER BY c.date_soumission DESC LIMIT 10");
$recent_candidatures = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - MY Invest Immobilier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; }
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            width: 250px;
            background: #2c3e50;
            padding: 20px 0;
        }
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 12px 20px;
            border-left: 3px solid transparent;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: #34495e;
            border-left-color: #3498db;
            color: white;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .stat-card {
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            font-size: 32px;
            font-weight: bold;
            margin: 10px 0;
        }
        .stat-card .icon {
            font-size: 40px;
            opacity: 0.3;
        }
        .header-bar {
            background: white;
            padding: 15px 20px;
            margin: -20px -20px 20px -20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-en_cours { background: #ffc107; color: #000; }
        .status-accepté { background: #28a745; color: white; }
        .status-refusé { background: #dc3545; color: white; }
        .status-visite_planifiée { background: #17a2b8; color: white; }
        .status-contrat_envoyé { background: #6f42c1; color: white; }
        .status-contrat_signé { background: #007bff; color: white; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="text-center mb-4">
            <h4 class="text-white">MY Invest</h4>
            <p class="text-muted small mb-0">Administration</p>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link active" href="index.php">
                <i class="bi bi-speedometer2"></i> Tableau de bord
            </a>
            <a class="nav-link" href="candidatures.php">
                <i class="bi bi-file-earmark-text"></i> Candidatures
            </a>
            <a class="nav-link" href="logements.php">
                <i class="bi bi-building"></i> Logements
            </a>
            <a class="nav-link" href="contrats.php">
                <i class="bi bi-file-earmark-check"></i> Contrats
            </a>
            <a class="nav-link" href="parametres.php">
                <i class="bi bi-gear"></i> Paramètres
            </a>
            <a class="nav-link" href="etats-lieux.php">
                <i class="bi bi-clipboard-check"></i> États des lieux
            </a>
            <hr class="bg-secondary">
            <a class="nav-link" href="logout.php">
                <i class="bi bi-box-arrow-right"></i> Déconnexion
            </a>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="header-bar">
            <h2>Tableau de bord</h2>
            <p class="text-muted mb-0">Bienvenue, <?php echo htmlspecialchars($admin_prenom . ' ' . $admin_nom); ?></p>
        </div>
        
        <!-- Statistics -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small">Total Candidatures</div>
                            <h3><?php echo $stats['total_candidatures']; ?></h3>
                        </div>
                        <i class="bi bi-file-earmark-text icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card bg-warning text-dark">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small">En cours</div>
                            <h3><?php echo $stats['statut_en_cours'] ?? 0; ?></h3>
                        </div>
                        <i class="bi bi-hourglass-split icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card bg-success text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small">Acceptés</div>
                            <h3><?php echo $stats['statut_accepté'] ?? 0; ?></h3>
                        </div>
                        <i class="bi bi-check-circle icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card bg-info text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small">Logements disponibles</div>
                            <h3><?php echo $stats['logements_disponibles']; ?></h3>
                        </div>
                        <i class="bi bi-building icon"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Applications -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Candidatures récentes</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Référence</th>
                                <th>Candidat</th>
                                <th>Email</th>
                                <th>Téléphone</th>
                                <th>Logement</th>
                                <th>Date</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_candidatures as $cand): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($cand['reference_unique'] ?? 'N/A'); ?></code></td>
                                <td><?php echo htmlspecialchars($cand['prenom'] . ' ' . $cand['nom']); ?></td>
                                <td><?php echo htmlspecialchars($cand['email']); ?></td>
                                <td><?php echo htmlspecialchars($cand['telephone']); ?></td>
                                <td>
                                    <?php if ($cand['logement_ref']): ?>
                                        <small><?php echo htmlspecialchars($cand['logement_ref']); ?></small>
                                    <?php else: ?>
                                        <small class="text-muted">Non spécifié</small>
                                    <?php endif; ?>
                                </td>
                                <td><small><?php echo date('d/m/Y', strtotime($cand['date_soumission'])); ?></small></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '_', $cand['statut'])); ?>">
                                        <?php echo htmlspecialchars($cand['statut']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="candidature-detail.php?id=<?php echo $cand['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white">
                <a href="candidatures.php" class="btn btn-primary">Voir toutes les candidatures</a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
