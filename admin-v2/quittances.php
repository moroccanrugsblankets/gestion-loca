<?php
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

// Get filters
$contrat_id_filter = isset($_GET['contrat_id']) ? (int)$_GET['contrat_id'] : 0;
$mois_filter = isset($_GET['mois']) ? (int)$_GET['mois'] : 0;
$annee_filter = isset($_GET['annee']) ? (int)$_GET['annee'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get contract info if filtering by contract
$contrat_info = null;
if ($contrat_id_filter > 0) {
    $stmt = $pdo->prepare("
        SELECT c.reference_unique, 
               GROUP_CONCAT(CONCAT(l.prenom, ' ', l.nom) SEPARATOR ', ') as locataires_noms
        FROM contrats c
        LEFT JOIN locataires l ON l.contrat_id = c.id
        WHERE c.id = ?
        GROUP BY c.id, c.reference_unique
    ");
    $stmt->execute([$contrat_id_filter]);
    $contrat_info = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Build query
$sql = "
    SELECT q.*, 
           c.reference_unique as contrat_ref,
           l.reference as logement_ref,
           l.adresse as logement_adresse,
           (SELECT GROUP_CONCAT(CONCAT(prenom, ' ', nom) SEPARATOR ', ') 
            FROM locataires 
            WHERE contrat_id = q.contrat_id) as locataires_noms
    FROM quittances q
    INNER JOIN contrats c ON q.contrat_id = c.id
    LEFT JOIN logements l ON c.logement_id = l.id
    WHERE q.deleted_at IS NULL
";
$params = [];

if ($contrat_id_filter > 0) {
    $sql .= " AND q.contrat_id = ?";
    $params[] = $contrat_id_filter;
}

if ($mois_filter > 0) {
    $sql .= " AND q.mois = ?";
    $params[] = $mois_filter;
}

if ($annee_filter > 0) {
    $sql .= " AND q.annee = ?";
    $params[] = $annee_filter;
}

if ($search) {
    $sql .= " AND (q.reference_unique LIKE ? OR l.reference LIKE ? OR l.adresse LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$sql .= " ORDER BY q.annee DESC, q.mois DESC, q.date_generation DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$quittances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM quittances WHERE deleted_at IS NULL")->fetchColumn(),
    'envoyees' => $pdo->query("SELECT COUNT(*) FROM quittances WHERE email_envoye = 1 AND deleted_at IS NULL")->fetchColumn(),
    'non_envoyees' => $pdo->query("SELECT COUNT(*) FROM quittances WHERE email_envoye = 0 AND deleted_at IS NULL")->fetchColumn()
];

// Month names for display
$nomsMois = [
    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Quittances - Administration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <?php require_once __DIR__ . '/includes/sidebar-styles.php'; ?>
    <style>
        .stats-card {
            border-radius: 15px;
            padding: 20px;
            color: white;
            margin-bottom: 20px;
        }
        .stats-card.primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stats-card.success { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
        .stats-card.warning { background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); }
        .table-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .badge-sent { background-color: #28a745; }
        .badge-not-sent { background-color: #ffc107; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/menu.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1><i class="bi bi-receipt"></i> Gestion des Quittances</h1>
                <?php if ($contrat_info): ?>
                    <p class="mb-0 text-muted">
                        <strong>Contrat :</strong> <?php echo htmlspecialchars($contrat_info['reference_unique']); ?>
                    </p>
                    <p class="mb-0 text-muted">
                        <strong>Locataire(s) :</strong> <?php echo htmlspecialchars($contrat_info['locataires_noms'] ?? 'N/A'); ?>
                    </p>
                <?php endif; ?>
            </div>
            <div>
                <?php if ($contrat_id_filter > 0): ?>
                    <a href="generer-quittances.php?id=<?php echo $contrat_id_filter; ?>" class="btn btn-success me-2">
                        <i class="bi bi-plus-circle"></i> Ajouter une Quittance
                    </a>
                <?php endif; ?>
                <a href="contrats.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Retour aux Contrats
                </a>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['success']; 
                unset($_SESSION['success']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['error']; 
                unset($_SESSION['error']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card primary">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p class="mb-0">Total Quittances</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card success">
                    <h3><?php echo $stats['envoyees']; ?></h3>
                    <p class="mb-0">Envoyées</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card warning">
                    <h3><?php echo $stats['non_envoyees']; ?></h3>
                    <p class="mb-0">Non Envoyées</p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="table-card mb-4">
            <form method="GET" action="quittances.php" class="row g-3">
                <?php if ($contrat_id_filter > 0): ?>
                    <input type="hidden" name="contrat_id" value="<?php echo $contrat_id_filter; ?>">
                <?php endif; ?>
                <div class="col-md-4">
                    <label class="form-label">Recherche</label>
                    <input type="text" name="search" class="form-control" placeholder="Référence, adresse..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Mois</label>
                    <select name="mois" class="form-select">
                        <option value="">Tous</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo ($mois_filter == $m) ? 'selected' : ''; ?>>
                                <?php echo $nomsMois[$m]; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Année</label>
                    <select name="annee" class="form-select">
                        <option value="">Toutes</option>
                        <?php 
                        $currentYear = date('Y');
                        for ($y = $currentYear; $y >= $currentYear - 5; $y--): 
                        ?>
                            <option value="<?php echo $y; ?>" <?php echo ($annee_filter == $y) ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Filtrer
                    </button>
                </div>
            </form>
            <div class="row mt-2">
                <div class="col-12">
                    <a href="quittances.php<?php echo $contrat_id_filter > 0 ? '?contrat_id=' . (int)$contrat_id_filter : ''; ?>" class="btn btn-secondary btn-sm">
                        <i class="bi bi-x-circle"></i> Réinitialiser
                    </a>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Référence</th>
                            <th>Contrat</th>
                            <th>Logement</th>
                            <th>Locataires</th>
                            <th>Période</th>
                            <th>Montant</th>
                            <th>Date Génération</th>
                            <th>Email</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quittances as $quittance): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($quittance['reference_unique']); ?></strong></td>
                            <td>
                                <a href="contrat-detail.php?id=<?php echo $quittance['contrat_id']; ?>">
                                    <?php echo htmlspecialchars($quittance['contrat_ref']); ?>
                                </a>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($quittance['logement_ref']); ?></strong>
                                <br><small class="text-muted"><?php echo htmlspecialchars($quittance['logement_adresse']); ?></small>
                            </td>
                            <td>
                                <small><?php echo htmlspecialchars($quittance['locataires_noms'] ?? 'N/A'); ?></small>
                            </td>
                            <td>
                                <strong><?php echo $nomsMois[$quittance['mois']] . ' ' . $quittance['annee']; ?></strong>
                            </td>
                            <td>
                                <strong><?php echo number_format($quittance['montant_total'], 2, ',', ' '); ?> €</strong>
                                <br><small class="text-muted">
                                    Loyer: <?php echo number_format($quittance['montant_loyer'], 2, ',', ' '); ?> €<br>
                                    Charges: <?php echo number_format($quittance['montant_charges'], 2, ',', ' '); ?> €
                                </small>
                            </td>
                            <td>
                                <small><?php echo date('d/m/Y H:i', strtotime($quittance['date_generation'])); ?></small>
                            </td>
                            <td>
                                <?php if ($quittance['email_envoye']): ?>
                                    <span class="badge badge-sent">
                                        <i class="bi bi-check-circle"></i> Envoyé
                                    </span>
                                    <?php if ($quittance['date_envoi_email']): ?>
                                        <br><small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($quittance['date_envoi_email'])); ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge badge-not-sent">
                                        <i class="bi bi-exclamation-circle"></i> Non envoyé
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <?php if ($quittance['fichier_pdf'] && file_exists($quittance['fichier_pdf'])): ?>
                                        <a href="<?php echo htmlspecialchars($quittance['fichier_pdf']); ?>" class="btn btn-outline-success" title="Télécharger PDF" target="_blank">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="edit-quittance.php?id=<?php echo $quittance['id']; ?>" class="btn btn-outline-primary" title="Modifier">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button class="btn btn-outline-info" title="Renvoyer email" onclick="resendEmail(<?php echo $quittance['id']; ?>, '<?php echo htmlspecialchars($quittance['reference_unique'], ENT_QUOTES); ?>')">
                                        <i class="bi bi-envelope"></i>
                                    </button>
                                    <button class="btn btn-outline-danger" title="Supprimer" onclick="confirmDelete(<?php echo $quittance['id']; ?>, '<?php echo htmlspecialchars($quittance['reference_unique'], ENT_QUOTES); ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($quittances)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox" style="font-size: 48px;"></i>
                                <p class="mt-3">Aucune quittance trouvée</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel">Confirmer la suppression</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer la quittance <strong id="quittanceRef"></strong> ?</p>
                    <p class="text-danger mb-0">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Cette action est irréversible et supprimera :
                    </p>
                    <ul class="text-danger">
                        <li>La quittance de la base de données</li>
                        <li>Le fichier PDF associé</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <form method="POST" action="delete-quittance.php" id="deleteForm">
                        <input type="hidden" name="quittance_id" id="quittanceId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-danger">Supprimer</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    </div><!-- End Main Content -->

    <!-- Resend Email Confirmation Modal -->
    <div class="modal fade" id="resendEmailModal" tabindex="-1" aria-labelledby="resendEmailModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="resendEmailModalLabel">Confirmer le renvoi</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Voulez-vous renvoyer l'email de la quittance <strong id="quittanceRefResend"></strong> ?</p>
                    <p class="mb-0">
                        <i class="bi bi-info-circle-fill me-2"></i>L'email sera envoyé au(x) locataire(s) avec une copie aux administrateurs.
                    </p>
                </div>
                <div class="modal-footer">
                    <form method="POST" action="resend-quittance-email.php" id="resendEmailForm">
                        <input type="hidden" name="quittance_id" id="quittanceIdResend">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-info">Renvoyer</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(quittanceId, quittanceRef) {
            document.getElementById('quittanceId').value = quittanceId;
            document.getElementById('quittanceRef').textContent = quittanceRef;
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }

        function resendEmail(quittanceId, quittanceRef) {
            document.getElementById('quittanceIdResend').value = quittanceId;
            document.getElementById('quittanceRefResend').textContent = quittanceRef;
            var resendEmailModal = new bootstrap.Modal(document.getElementById('resendEmailModal'));
            resendEmailModal.show();
        }
    </script>
</body>
</html>
