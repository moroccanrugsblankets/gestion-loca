<?php
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

// Get candidature ID if provided
$candidature_id = isset($_GET['candidature_id']) ? (int)$_GET['candidature_id'] : 0;
$candidature = null;

if ($candidature_id) {
    $stmt = $pdo->prepare("SELECT * FROM candidatures WHERE id = ?");
    $stmt->execute([$candidature_id]);
    $candidature = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get all available properties
$stmt = $pdo->query("SELECT * FROM logements WHERE statut IN ('Disponible', 'Réservé') ORDER BY reference");
$logements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get applications that can have contracts
$stmt = $pdo->query("
    SELECT id, reference, nom, prenom, email, statut
    FROM candidatures 
    WHERE statut IN ('Accepté', 'Visite planifiée')
    ORDER BY created_at DESC
");
$candidatures = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $logement_id = (int)$_POST['logement_id'];
    $candidature_id = (int)$_POST['candidature_id'];
    $nb_locataires = (int)$_POST['nb_locataires'];
    $date_prise_effet = $_POST['date_prise_effet'];
    
    // Generate unique reference
    $reference_unique = 'BAIL-' . strtoupper(uniqid());
    
    // Calculate expiration date (24h from now)
    $date_expiration = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Create contract
    $stmt = $pdo->prepare("
        INSERT INTO contrats (reference_unique, logement_id, statut, date_creation, date_expiration, date_prise_effet, nb_locataires)
        VALUES (?, ?, 'en_attente', NOW(), ?, ?, ?)
    ");
    $stmt->execute([$reference_unique, $logement_id, $date_expiration, $date_prise_effet, $nb_locataires]);
    $contrat_id = $pdo->lastInsertId();
    
    // Update property status
    $stmt = $pdo->prepare("UPDATE logements SET statut = 'Réservé' WHERE id = ?");
    $stmt->execute([$logement_id]);
    
    // Update candidature status
    $stmt = $pdo->prepare("UPDATE candidatures SET statut = 'Contrat envoyé', logement_id = ? WHERE id = ?");
    $stmt->execute([$logement_id, $candidature_id]);
    
    // Log action
    $stmt = $pdo->prepare("
        INSERT INTO logs (candidature_id, contrat_id, action, details, ip_address, created_at)
        VALUES (?, ?, 'Contrat généré', ?, ?, NOW())
    ");
    $stmt->execute([
        $candidature_id,
        $contrat_id,
        "Contrat $reference_unique créé pour logement ID $logement_id",
        $_SERVER['REMOTE_ADDR']
    ]);
    
    // Generate signature link
    $token = bin2hex(random_bytes(32));
    
    // TODO: Store token and send signature email
    // For now, just redirect to contracts list
    
    $_SESSION['success'] = "Contrat généré avec succès. Référence: $reference_unique";
    header('Location: contrats.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Générer un Contrat - Admin MyInvest</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
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
            overflow-y: auto;
        }
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 12px 20px;
            margin: 2px 0;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: #34495e;
            color: white;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .header {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="text-center mb-4">
            <h5 class="text-white">MyInvest Admin</h5>
        </div>
        <ul class="nav flex-column">
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
                    <i class="bi bi-building"></i> Logements
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="contrats.php">
                    <i class="bi bi-file-earmark-check"></i> Contrats
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> Déconnexion
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h4>Générer un Nouveau Contrat</h4>
            <p class="text-muted mb-0">Créer un contrat de bail et envoyer le lien de signature</p>
        </div>

        <div class="form-card">
            <form action="generer-contrat.php" method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Candidature *</label>
                        <select name="candidature_id" class="form-select" required id="candidature_select">
                            <option value="">-- Sélectionner une candidature --</option>
                            <?php foreach ($candidatures as $cand): ?>
                                <option value="<?php echo $cand['id']; ?>" 
                                        <?php echo ($candidature && $candidature['id'] == $cand['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cand['reference'] . ' - ' . $cand['prenom'] . ' ' . $cand['nom']); ?>
                                    (<?php echo htmlspecialchars($cand['statut']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Seules les candidatures acceptées sont affichées</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Logement *</label>
                        <select name="logement_id" class="form-select" required id="logement_select">
                            <option value="">-- Sélectionner un logement --</option>
                            <?php foreach ($logements as $logement): ?>
                                <option value="<?php echo $logement['id']; ?>"
                                        data-loyer="<?php echo $logement['loyer']; ?>"
                                        data-charges="<?php echo $logement['charges']; ?>"
                                        data-depot="<?php echo $logement['depot_garantie']; ?>">
                                    <?php echo htmlspecialchars($logement['reference'] . ' - ' . $logement['adresse']); ?>
                                    (<?php echo htmlspecialchars($logement['type']); ?> - <?php echo $logement['loyer']; ?>€/mois)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Logements disponibles ou réservés</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Nombre de locataires *</label>
                        <select name="nb_locataires" class="form-select" required>
                            <option value="1">1 locataire</option>
                            <option value="2">2 locataires</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Date de prise d'effet *</label>
                        <input type="date" name="date_prise_effet" class="form-control" required 
                               min="<?php echo date('Y-m-d'); ?>">
                        <small class="form-text text-muted">Date d'entrée dans le logement</small>
                    </div>

                    <div class="col-12">
                        <div class="alert alert-info">
                            <h6><i class="bi bi-info-circle"></i> Informations importantes</h6>
                            <ul class="mb-0">
                                <li>Le contrat sera créé avec un statut "En attente"</li>
                                <li>Un lien de signature valide 24h sera généré</li>
                                <li>Le locataire recevra un email avec les instructions</li>
                                <li>Le statut de la candidature passera à "Contrat envoyé"</li>
                                <li>Le logement sera marqué comme "Réservé"</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Preview Card -->
                    <div class="col-12" id="preview_card" style="display: none;">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">Aperçu du contrat</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Loyer mensuel HT:</strong> <span id="preview_loyer">-</span> €
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Charges:</strong> <span id="preview_charges">-</span> €
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Total mensuel:</strong> <span id="preview_total">-</span> €
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Dépôt de garantie:</strong> <span id="preview_depot">-</span> €
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <hr>
                        <div class="d-flex justify-content-between">
                            <a href="contrats.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Annuler
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Générer le contrat et envoyer
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show preview when logement is selected
        document.getElementById('logement_select').addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            if (option.value) {
                const loyer = parseFloat(option.dataset.loyer);
                const charges = parseFloat(option.dataset.charges);
                const depot = parseFloat(option.dataset.depot);
                const total = loyer + charges;
                
                document.getElementById('preview_loyer').textContent = loyer.toFixed(2);
                document.getElementById('preview_charges').textContent = charges.toFixed(2);
                document.getElementById('preview_total').textContent = total.toFixed(2);
                document.getElementById('preview_depot').textContent = depot.toFixed(2);
                document.getElementById('preview_card').style.display = 'block';
            } else {
                document.getElementById('preview_card').style.display = 'none';
            }
        });
    </script>
</body>
</html>
