<?php
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

// Get filters
$statut_filter = isset($_GET['statut']) ? $_GET['statut'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$sql = "
    SELECT c.*, 
           l.reference as logement_ref, 
           l.adresse as logement_adresse,
           l.type as logement_type,
           (SELECT COUNT(*) FROM locataires WHERE contrat_id = c.id) as nb_locataires_signed
    FROM contrats c
    LEFT JOIN logements l ON c.logement_id = l.id
    WHERE 1=1
";
$params = [];

if ($statut_filter) {
    $sql .= " AND c.statut = ?";
    $params[] = $statut_filter;
}

if ($search) {
    $sql .= " AND (c.reference_unique LIKE ? OR l.reference LIKE ? OR l.adresse LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$sql .= " ORDER BY c.date_creation DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contrats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM contrats")->fetchColumn(),
    'en_attente' => $pdo->query("SELECT COUNT(*) FROM contrats WHERE statut = 'en_attente'")->fetchColumn(),
    'signe' => $pdo->query("SELECT COUNT(*) FROM contrats WHERE statut = 'signe'")->fetchColumn(),
    'expire' => $pdo->query("SELECT COUNT(*) FROM contrats WHERE statut = 'expire'")->fetchColumn()
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Contrats - Admin MyInvest</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <?php require_once __DIR__ . '/includes/sidebar-styles.php'; ?>
    <style>
        .header {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stats-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .stats-card .number {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
        }
        .table-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .status-en_attente { background: #fff3cd; color: #856404; }
        .status-signe { background: #d4edda; color: #155724; }
        .status-expire { background: #f8d7da; color: #721c24; }
        .status-annule { background: #e2e3e5; color: #383d41; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/menu.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div class="d-flex justify-content-between align-items-center">
                <h4>Gestion des Contrats</h4>
                <a href="generer-contrat.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Générer un contrat
                </a>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">Total Contrats</div>
                            <div class="number"><?php echo $stats['total']; ?></div>
                        </div>
                        <i class="bi bi-file-earmark-check" style="font-size: 2rem; color: #007bff;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">En Attente</div>
                            <div class="number text-warning"><?php echo $stats['en_attente']; ?></div>
                        </div>
                        <i class="bi bi-clock" style="font-size: 2rem; color: #ffc107;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">Signés</div>
                            <div class="number text-success"><?php echo $stats['signe']; ?></div>
                        </div>
                        <i class="bi bi-check-circle" style="font-size: 2rem; color: #28a745;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">Expirés</div>
                            <div class="number text-danger"><?php echo $stats['expire']; ?></div>
                        </div>
                        <i class="bi bi-exclamation-triangle" style="font-size: 2rem; color: #dc3545;"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="table-card mb-3">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Rechercher (référence, logement...)" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select name="statut" class="form-select">
                        <option value="">Tous les statuts</option>
                        <option value="en_attente" <?php echo $statut_filter === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                        <option value="signe" <?php echo $statut_filter === 'signe' ? 'selected' : ''; ?>>Signé</option>
                        <option value="expire" <?php echo $statut_filter === 'expire' ? 'selected' : ''; ?>>Expiré</option>
                        <option value="annule" <?php echo $statut_filter === 'annule' ? 'selected' : ''; ?>>Annulé</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Filtrer
                    </button>
                </div>
                <div class="col-md-3">
                    <a href="contrats.php" class="btn btn-secondary w-100">
                        <i class="bi bi-x-circle"></i> Réinitialiser
                    </a>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Référence</th>
                            <th>Logement</th>
                            <th>Locataires</th>
                            <th>Date Création</th>
                            <th>Date Expiration</th>
                            <th>Date Signature</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contrats as $contrat): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($contrat['reference_unique']); ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars($contrat['logement_ref']); ?></strong>
                                <br><small class="text-muted"><?php echo htmlspecialchars($contrat['logement_adresse']); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-info">
                                    <?php echo $contrat['nb_locataires']; ?> locataire(s)
                                </span>
                                <br><small class="text-muted"><?php echo $contrat['nb_locataires_signed']; ?> signé(s)</small>
                            </td>
                            <td><small><?php echo date('d/m/Y', strtotime($contrat['date_creation'])); ?></small></td>
                            <td>
                                <?php if ($contrat['date_expiration']): ?>
                                    <small><?php echo date('d/m/Y', strtotime($contrat['date_expiration'])); ?></small>
                                <?php else: ?>
                                    <small class="text-muted">-</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($contrat['date_signature']): ?>
                                    <small><?php echo date('d/m/Y H:i', strtotime($contrat['date_signature'])); ?></small>
                                <?php else: ?>
                                    <small class="text-muted">Non signé</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $contrat['statut']; ?>">
                                    <?php
                                    $statut_labels = [
                                        'en_attente' => 'En attente',
                                        'signe' => 'Signé',
                                        'expire' => 'Expiré',
                                        'annule' => 'Annulé'
                                    ];
                                    echo $statut_labels[$contrat['statut']] ?? $contrat['statut'];
                                    ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="../admin/contract-details.php?id=<?php echo $contrat['id']; ?>" class="btn btn-outline-primary" title="Voir détails">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($contrat['statut'] === 'signe'): ?>
                                        <a href="../pdf/download.php?contract_id=<?php echo $contrat['id']; ?>" class="btn btn-outline-success" title="Télécharger PDF">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($contrat['statut'] === 'en_attente'): ?>
                                        <button class="btn btn-outline-warning" title="Renvoyer le lien" onclick="resendLink(<?php echo $contrat['id']; ?>)">
                                            <i class="bi bi-envelope"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($contrats)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox" style="font-size: 48px;"></i>
                                <p class="mt-3">Aucun contrat trouvé</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function resendLink(contractId) {
            if (confirm('Voulez-vous renvoyer le lien de signature ?')) {
                // TODO: Implement resend link functionality
                alert('Fonctionnalité à implémenter: renvoyer le lien');
            }
        }
    </script>
</body>
</html>
