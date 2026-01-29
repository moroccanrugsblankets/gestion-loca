<?php
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

// Get all etats des lieux
$stmt = $pdo->query("
    SELECT edl.*, c.reference_unique as contrat_ref, 
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
    <?php require_once __DIR__ . '/includes/sidebar-styles.php'; ?>
    <style>
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
    <?php require_once __DIR__ . '/includes/menu.php'; ?>

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
                                $stmt = $pdo->query("SELECT id, reference_unique FROM contrats WHERE statut = 'signe' ORDER BY reference_unique");
                                while ($contrat = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value='{$contrat['id']}'>{$contrat['reference_unique']}</option>";
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
