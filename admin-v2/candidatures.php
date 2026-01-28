<?php
require_once 'auth.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// Handle filters
$filter_statut = $_GET['statut'] ?? '';
$filter_search = $_GET['search'] ?? '';

// Build query
$where = [];
$params = [];

if ($filter_statut) {
    $where[] = "c.statut = ?";
    $params[] = $filter_statut;
}

if ($filter_search) {
    $where[] = "(c.nom LIKE ? OR c.prenom LIKE ? OR c.email LIKE ? OR c.reference_candidature LIKE ?)";
    $searchTerm = "%$filter_search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

$query = "SELECT c.*, l.reference as logement_ref, l.adresse 
          FROM candidatures c 
          LEFT JOIN logements l ON c.logement_id = l.id 
          $whereClause
          ORDER BY c.date_soumission DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$candidatures = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Candidatures - MY Invest Immobilier</title>
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
            <a class="nav-link" href="index.php">
                <i class="bi bi-speedometer2"></i> Tableau de bord
            </a>
            <a class="nav-link active" href="candidatures.php">
                <i class="bi bi-file-earmark-text"></i> Candidatures
            </a>
            <a class="nav-link" href="logements.php">
                <i class="bi bi-building"></i> Logements
            </a>
            <a class="nav-link" href="contrats.php">
                <i class="bi bi-file-earmark-check"></i> Contrats
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
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2>Gestion des Candidatures</h2>
                    <p class="text-muted mb-0"><?php echo count($candidatures); ?> candidature(s) trouvée(s)</p>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Rechercher</label>
                        <input type="text" name="search" class="form-control" placeholder="Nom, email, référence..." value="<?php echo htmlspecialchars($filter_search); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Statut</label>
                        <select name="statut" class="form-select">
                            <option value="">Tous</option>
                            <option value="En cours" <?php echo $filter_statut === 'En cours' ? 'selected' : ''; ?>>En cours</option>
                            <option value="Accepté" <?php echo $filter_statut === 'Accepté' ? 'selected' : ''; ?>>Accepté</option>
                            <option value="Refusé" <?php echo $filter_statut === 'Refusé' ? 'selected' : ''; ?>>Refusé</option>
                            <option value="Visite planifiée" <?php echo $filter_statut === 'Visite planifiée' ? 'selected' : ''; ?>>Visite planifiée</option>
                            <option value="Contrat envoyé" <?php echo $filter_statut === 'Contrat envoyé' ? 'selected' : ''; ?>>Contrat envoyé</option>
                            <option value="Contrat signé" <?php echo $filter_statut === 'Contrat signé' ? 'selected' : ''; ?>>Contrat signé</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filtrer</button>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <a href="candidatures.php" class="btn btn-outline-secondary w-100">Réinitialiser</a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Applications Table -->
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Référence</th>
                                <th>Candidat</th>
                                <th>Contact</th>
                                <th>Situation</th>
                                <th>Revenus</th>
                                <th>Logement</th>
                                <th>Date</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($candidatures as $cand): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($cand['reference_candidature']); ?></code></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($cand['prenom'] . ' ' . $cand['nom']); ?></strong>
                                </td>
                                <td>
                                    <small>
                                        <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($cand['email']); ?><br>
                                        <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($cand['telephone']); ?>
                                    </small>
                                </td>
                                <td><small><?php echo htmlspecialchars($cand['statut_professionnel']); ?></small></td>
                                <td><small><?php echo htmlspecialchars($cand['revenus_nets_mensuels']); ?></small></td>
                                <td>
                                    <?php if ($cand['logement_ref']): ?>
                                        <small><?php echo htmlspecialchars($cand['logement_ref']); ?></small>
                                    <?php else: ?>
                                        <small class="text-muted">-</small>
                                    <?php endif; ?>
                                </td>
                                <td><small><?php echo date('d/m/Y', strtotime($cand['date_soumission'])); ?></small></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '_', $cand['statut'])); ?>">
                                        <?php echo htmlspecialchars($cand['statut']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="candidature-detail.php?id=<?php echo $cand['id']; ?>" class="btn btn-outline-primary" title="Voir détails">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="candidature-actions.php?id=<?php echo $cand['id']; ?>" class="btn btn-outline-success" title="Actions">
                                            <i class="bi bi-gear"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($candidatures)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox" style="font-size: 48px;"></i>
                                    <p class="mt-3">Aucune candidature trouvée</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
