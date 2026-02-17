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
    'valide' => $pdo->query("SELECT COUNT(*) FROM contrats WHERE statut = 'valide'")->fetchColumn(),
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
        .status-valide { background: #d1ecf1; color: #0c5460; }
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
                <div class="d-flex gap-2">
                    <a href="quittances.php" class="btn btn-info">
                        <i class="bi bi-receipt"></i> Quittances
                    </a>
                    <a href="generer-contrat.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Générer un contrat
                    </a>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['warning'])): ?>
            <div class="alert alert-warning alert-dismissible fade show">
                <?php echo $_SESSION['warning']; unset($_SESSION['warning']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row mb-4">
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
                            <div class="text-muted small">Validés</div>
                            <div class="number text-info"><?php echo $stats['valide']; ?></div>
                        </div>
                        <i class="bi bi-patch-check" style="font-size: 2rem; color: #17a2b8;"></i>
                    </div>
                </div>
            </div>
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
                        <option value="valide" <?php echo $statut_filter === 'valide' ? 'selected' : ''; ?>>Validé</option>
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
                                        'valide' => 'Validé',
                                        'expire' => 'Expiré',
                                        'annule' => 'Annulé'
                                    ];
                                    echo $statut_labels[$contrat['statut']] ?? $contrat['statut'];
                                    ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="contrat-detail.php?id=<?php echo $contrat['id']; ?>" class="btn btn-outline-primary" title="Voir détails">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($contrat['statut'] === 'signe' || $contrat['statut'] === 'valide'): ?>
                                        <a href="../pdf/download.php?contrat_id=<?php echo $contrat['id']; ?>" class="btn btn-outline-success" title="Télécharger PDF">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        <a href="edit-bilan-logement.php?contrat_id=<?php echo $contrat['id']; ?>" class="btn btn-outline-info" title="Bilan de logement">
                                            <i class="bi bi-clipboard-check"></i>
                                        </a>
                                        <a href="quittances.php?contrat_id=<?php echo $contrat['id']; ?>" class="btn btn-outline-secondary" title="Quittances">
                                            <i class="bi bi-receipt"></i>
                                        </a>
                                        <a href="gestion-loyers.php?contrat_id=<?php echo $contrat['id']; ?>" class="btn btn-outline-warning" title="Gestion du loyer">
                                            <i class="bi bi-cash-stack"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($contrat['statut'] === 'en_attente'): ?>
                                        <button class="btn btn-outline-warning" title="Renvoyer le lien" onclick="resendLink(<?php echo $contrat['id']; ?>)">
                                            <i class="bi bi-envelope"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-outline-danger" title="Supprimer" onclick="deleteContract(<?php echo $contrat['id']; ?>, '<?php echo htmlspecialchars($contrat['reference_unique'], ENT_QUOTES); ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
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
            if (confirm('Voulez-vous renvoyer le lien de signature ?\n\nUn email sera envoyé au client et aux administrateurs.')) {
                // Send AJAX request to resend the link
                fetch('renvoyer-lien-signature.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ contrat_id: contractId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✓ ' + data.message);
                        location.reload();
                    } else {
                        alert('Erreur: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Erreur lors de l\'envoi de la requête');
                });
            }
        }
        
        function deleteContract(contractId, reference) {
            document.getElementById('contractId').value = contractId;
            document.getElementById('contractRef').textContent = reference;
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
    </script>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel">Confirmer la suppression</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer le contrat <strong id="contractRef"></strong> ?</p>
                    <p class="text-danger mb-0">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Cette action est irréversible et supprimera :
                    </p>
                    <ul class="text-danger">
                        <li>Le contrat de la base de données</li>
                        <li>Les fichiers PDF associés</li>
                        <li>Les documents des locataires</li>
                    </ul>
                    <p class="text-muted">Le logement sera remis en disponibilité.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" action="supprimer-contrat.php" id="deleteForm">
                        <input type="hidden" name="contrat_id" id="contractId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-danger">Supprimer</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
