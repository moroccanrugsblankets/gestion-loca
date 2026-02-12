<?php
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../pdf/generate-quittance.php';

$contractId = (int)($_GET['id'] ?? 0);

if ($contractId === 0) {
    $_SESSION['error'] = "ID de contrat invalide.";
    header('Location: contrats.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mois_annees'])) {
    $moisAnnees = $_POST['mois_annees']; // Array of "YYYY-MM" strings
    
    if (empty($moisAnnees)) {
        $_SESSION['error'] = "Veuillez sélectionner au moins un mois.";
        header('Location: generer-quittances.php?id=' . $contractId);
        exit;
    }
    
    // Get contract and tenant information
    $contrat = fetchOne("
        SELECT c.*, 
               l.reference,
               l.adresse,
               l.loyer,
               l.charges
        FROM contrats c
        INNER JOIN logements l ON c.logement_id = l.id
        WHERE c.id = ?
    ", [$contractId]);
    
    if (!$contrat) {
        $_SESSION['error'] = "Contrat non trouvé.";
        header('Location: contrats.php');
        exit;
    }
    
    $locataires = fetchAll("
        SELECT * FROM locataires 
        WHERE contrat_id = ? 
        ORDER BY ordre
    ", [$contractId]);
    
    if (empty($locataires)) {
        $_SESSION['error'] = "Aucun locataire trouvé pour ce contrat.";
        header('Location: contrat-detail.php?id=' . $contractId);
        exit;
    }
    
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    
    // Generate quittances for each selected month
    foreach ($moisAnnees as $moisAnnee) {
        list($annee, $mois) = explode('-', $moisAnnee);
        $annee = (int)$annee;
        $mois = (int)$mois;
        
        // Generate PDF
        $result = generateQuittancePDF($contractId, $mois, $annee);
        
        if ($result === false) {
            $errorCount++;
            $errors[] = "Erreur lors de la génération pour " . date('F Y', mktime(0, 0, 0, $mois, 1, $annee));
            continue;
        }
        
        // Prepare email variables
        $nomsMois = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
        ];
        
        $periode = $nomsMois[$mois] . ' ' . $annee;
        $montantLoyer = number_format((float)$contrat['loyer'], 2, ',', ' ');
        $montantCharges = number_format((float)$contrat['charges'], 2, ',', ' ');
        $montantTotal = number_format((float)$contrat['loyer'] + (float)$contrat['charges'], 2, ',', ' ');
        
        // Send email to each tenant
        foreach ($locataires as $locataire) {
            $emailSent = sendTemplatedEmail('quittance_envoyee', $locataire['email'], [
                'locataire_nom' => $locataire['nom'],
                'locataire_prenom' => $locataire['prenom'],
                'adresse' => $contrat['adresse'],
                'periode' => $periode,
                'montant_loyer' => $montantLoyer,
                'montant_charges' => $montantCharges,
                'montant_total' => $montantTotal,
                'signature' => getParameter('email_signature', '')
            ], $result['filepath'], false, true); // false = not admin email, true = add admin BCC
            
            if (!$emailSent) {
                error_log("Erreur envoi email quittance à " . $locataire['email']);
            }
        }
        
        // Update quittance record to mark email as sent
        $stmt = $pdo->prepare("UPDATE quittances SET email_envoye = 1, date_envoi_email = NOW() WHERE id = ?");
        $stmt->execute([$result['quittance_id']]);
        
        $successCount++;
    }
    
    // Display success/error messages
    if ($successCount > 0) {
        $message = "Quittance(s) envoyée(s) avec succès : $successCount générée(s) et envoyée(s) par email.";
        $_SESSION['success'] = $message;
    }
    
    if ($errorCount > 0) {
        $message = "Erreurs lors de la génération : $errorCount échec(s). " . implode(', ', $errors);
        $_SESSION['warning'] = $message;
    }
    
    header('Location: contrat-detail.php?id=' . $contractId);
    exit;
}

// Get contract details
$contrat = fetchOne("
    SELECT c.*, 
           l.reference as logement_ref, 
           l.adresse as logement_adresse
    FROM contrats c
    LEFT JOIN logements l ON c.logement_id = l.id
    WHERE c.id = ?
", [$contractId]);

if (!$contrat) {
    $_SESSION['error'] = "Contrat non trouvé.";
    header('Location: contrats.php');
    exit;
}

// Get existing quittances for this contract
$stmt = $pdo->prepare("
    SELECT mois, annee, reference_unique, date_generation, email_envoye
    FROM quittances 
    WHERE contrat_id = ? 
    ORDER BY annee DESC, mois DESC
");
$stmt->execute([$contractId]);
$existingQuittances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create a map of existing month/year combinations
$existingMap = [];
foreach ($existingQuittances as $q) {
    $existingMap[$q['annee'] . '-' . str_pad($q['mois'], 2, '0', STR_PAD_LEFT)] = true;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Générer des Quittances - <?php echo htmlspecialchars($contrat['reference_unique']); ?></title>
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
        .form-card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .month-checkbox {
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .month-checkbox:hover {
            border-color: #667eea;
            background: #f8f9fa;
        }
        .month-checkbox input[type="checkbox"]:checked + label {
            color: #667eea;
            font-weight: bold;
        }
        .already-generated {
            background: #e8f5e9;
            border-color: #4caf50;
        }
        .already-generated .badge {
            background: #4caf50;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/menu.php'; ?>

    <div class="main-content">
        <div class="header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4><i class="bi bi-receipt"></i> Générer des Quittances de Loyer</h4>
                    <p class="mb-0 text-muted">Contrat: <strong><?php echo htmlspecialchars($contrat['reference_unique']); ?></strong></p>
                    <p class="mb-0 text-muted">Logement: <?php echo htmlspecialchars($contrat['logement_adresse']); ?></p>
                </div>
                <a href="contrat-detail.php?id=<?php echo $contractId; ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Retour au contrat
                </a>
            </div>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <h5 class="mb-4"><i class="bi bi-calendar-check"></i> Sélectionner les Mois</h5>
            <p class="text-muted">Sélectionnez un ou plusieurs mois pour générer les quittances correspondantes. 
            Une quittance sera générée et envoyée par email pour chaque mois sélectionné.</p>

            <form method="POST" action="">
                <div class="row">
                    <?php
                    // Generate options for the last 24 months and next 3 months
                    $currentYear = date('Y');
                    $currentMonth = date('n');
                    
                    for ($i = 24; $i >= -3; $i--) {
                        $timestamp = strtotime("-$i months");
                        $year = date('Y', $timestamp);
                        $month = date('n', $timestamp);
                        $monthName = date('F Y', $timestamp);
                        
                        // French month names
                        $nomsMoisFr = [
                            'January' => 'Janvier', 'February' => 'Février', 'March' => 'Mars',
                            'April' => 'Avril', 'May' => 'Mai', 'June' => 'Juin',
                            'July' => 'Juillet', 'August' => 'Août', 'September' => 'Septembre',
                            'October' => 'Octobre', 'November' => 'Novembre', 'December' => 'Décembre'
                        ];
                        
                        foreach ($nomsMoisFr as $en => $fr) {
                            $monthName = str_replace($en, $fr, $monthName);
                        }
                        
                        $value = sprintf('%04d-%02d', $year, $month);
                        $alreadyGenerated = isset($existingMap[$value]);
                        
                        $checkboxClass = $alreadyGenerated ? 'month-checkbox already-generated' : 'month-checkbox';
                        ?>
                        <div class="col-md-4">
                            <div class="<?php echo $checkboxClass; ?>">
                                <div class="form-check">
                                    <input 
                                        class="form-check-input" 
                                        type="checkbox" 
                                        name="mois_annees[]" 
                                        value="<?php echo $value; ?>" 
                                        id="mois_<?php echo $value; ?>">
                                    <label class="form-check-label w-100" for="mois_<?php echo $value; ?>">
                                        <?php echo $monthName; ?>
                                        <?php if ($alreadyGenerated): ?>
                                            <span class="badge bg-success float-end">Déjà générée</span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>

                <div class="mt-4">
                    <button type="button" class="btn btn-outline-primary" onclick="selectAll()">
                        <i class="bi bi-check-all"></i> Tout sélectionner
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="deselectAll()">
                        <i class="bi bi-x-circle"></i> Tout désélectionner
                    </button>
                </div>

                <hr class="my-4">

                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    <strong>Information:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Une quittance PDF sera générée pour chaque mois sélectionné</li>
                        <li>Les quittances seront automatiquement envoyées par email aux locataires</li>
                        <li>Une copie cachée (BCC) sera envoyée aux administrateurs</li>
                        <li>Vous pouvez re-générer une quittance déjà existante (elle sera écrasée)</li>
                    </ul>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="contrat-detail.php?id=<?php echo $contractId; ?>" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Annuler
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg" onclick="return confirm('Êtes-vous sûr de vouloir générer et envoyer les quittances pour les mois sélectionnés ?');">
                        <i class="bi bi-send"></i> Générer et Envoyer les Quittances
                    </button>
                </div>
            </form>
        </div>

        <?php if (!empty($existingQuittances)): ?>
        <div class="form-card">
            <h5 class="mb-4"><i class="bi bi-clock-history"></i> Historique des Quittances Générées</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Référence</th>
                            <th>Période</th>
                            <th>Date de Génération</th>
                            <th>Email Envoyé</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($existingQuittances as $q): ?>
                        <?php
                        $nomsMois = [
                            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
                            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
                            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
                        ];
                        $periode = $nomsMois[$q['mois']] . ' ' . $q['annee'];
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($q['reference_unique']); ?></strong></td>
                            <td><?php echo $periode; ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($q['date_generation'])); ?></td>
                            <td>
                                <?php if ($q['email_envoye']): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle"></i> Oui</span>
                                <?php else: ?>
                                    <span class="badge bg-warning"><i class="bi bi-clock"></i> Non</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectAll() {
            document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = true);
        }
        
        function deselectAll() {
            document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
        }
    </script>
</body>
</html>
