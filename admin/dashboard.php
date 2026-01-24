<?php
/**
 * Admin - Tableau de bord
 * My Invest Immobilier
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Récupérer les filtres
$filterStatut = $_GET['statut'] ?? '';

// Récupérer les contrats
$contrats = $filterStatut ? getAllContracts($filterStatut) : getAllContracts();

// Statistiques
$statsEnAttente = count(getAllContracts('en_attente'));
$statsSignes = count(getAllContracts('signe'));
$statsExpires = count(getAllContracts('expire'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - MY Invest Immobilier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="text-center mb-4">
            <img src="../assets/images/logo.png" alt="MY Invest Immobilier" class="logo mb-3" 
                 onerror="this.style.display='none'">
            <h1 class="h2">Tableau de bord des contrats</h1>
        </div>

        <!-- Statistiques -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">En attente</h5>
                        <p class="display-4"><?= $statsEnAttente ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Signés</h5>
                        <p class="display-4"><?= $statsSignes ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Expirés</h5>
                        <p class="display-4"><?= $statsExpires ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <label for="statut" class="form-label">Filtrer par statut</label>
                        <select class="form-select" id="statut" name="statut" onchange="this.form.submit()">
                            <option value="">Tous</option>
                            <option value="en_attente" <?= $filterStatut === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                            <option value="signe" <?= $filterStatut === 'signe' ? 'selected' : '' ?>>Signé</option>
                            <option value="expire" <?= $filterStatut === 'expire' ? 'selected' : '' ?>>Expiré</option>
                            <option value="annule" <?= $filterStatut === 'annule' ? 'selected' : '' ?>>Annulé</option>
                        </select>
                    </div>
                    <div class="col-md-8 d-flex align-items-end">
                        <a href="generate-link.php" class="btn btn-primary">Générer un nouveau lien</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Liste des contrats -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Liste des contrats</h5>
                
                <?php if (empty($contrats)): ?>
                    <p class="text-muted">Aucun contrat trouvé.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Logement</th>
                                    <th>Adresse</th>
                                    <th>Statut</th>
                                    <th>Nb Locataires</th>
                                    <th>Date création</th>
                                    <th>Date expiration</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contrats as $contrat): ?>
                                    <?php
                                    $badgeClass = match($contrat['statut']) {
                                        'en_attente' => 'bg-warning',
                                        'signe' => 'bg-success',
                                        'expire' => 'bg-danger',
                                        'annule' => 'bg-secondary',
                                        default => 'bg-secondary'
                                    };
                                    ?>
                                    <tr>
                                        <td><?= $contrat['id'] ?></td>
                                        <td><strong><?= htmlspecialchars($contrat['reference']) ?></strong></td>
                                        <td><?= htmlspecialchars($contrat['adresse']) ?></td>
                                        <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($contrat['statut']) ?></span></td>
                                        <td><?= $contrat['nb_locataires'] ?></td>
                                        <td><?= formatDateFr($contrat['date_creation'], 'd/m/Y H:i') ?></td>
                                        <td><?= formatDateFr($contrat['date_expiration'], 'd/m/Y H:i') ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" 
                                                    onclick="showContractDetails(<?= $contrat['id'] ?>)">
                                                Détails
                                            </button>
                                            <?php if ($contrat['statut'] === 'signe'): ?>
                                                <a href="../pdf/download.php?contrat_id=<?= $contrat['id'] ?>" 
                                                   class="btn btn-sm btn-success">
                                                    PDF
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal pour les détails -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Détails du contrat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailsContent">
                    Chargement...
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showContractDetails(contractId) {
            const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
            const content = document.getElementById('detailsContent');
            
            content.innerHTML = 'Chargement...';
            modal.show();
            
            // Charger les détails via AJAX
            fetch('contract-details.php?id=' + contractId)
                .then(response => response.text())
                .then(html => {
                    content.innerHTML = html;
                })
                .catch(error => {
                    content.innerHTML = 'Erreur lors du chargement des détails.';
                });
        }
    </script>
</body>
</html>
