<?php
/**
 * Edit Bilan du Logement - État de sortie uniquement
 * My Invest Immobilier
 */

require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Get contract ID
$contratId = isset($_GET['contrat_id']) ? (int)$_GET['contrat_id'] : 0;

if ($contratId < 1) {
    $_SESSION['error'] = "ID de contrat invalide";
    header('Location: contrats.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    try {
        $pdo->beginTransaction();
        
        // Check if we should mark the bilan as sent
        $sendBilan = isset($_POST['send_bilan']) && $_POST['send_bilan'] === '1';
        
        // Prepare bilan_logement_data if provided
        $bilanData = null;
        if (isset($_POST['bilan_rows']) && is_array($_POST['bilan_rows'])) {
            $bilanData = json_encode($_POST['bilan_rows']);
        }
        
        // Get the exit etat_lieux ID for this contract
        $stmt = $pdo->prepare("
            SELECT id FROM etats_lieux 
            WHERE contrat_id = ? AND type = 'sortie' 
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$contratId]);
        $etatLieuxId = $stmt->fetchColumn();
        
        if (!$etatLieuxId) {
            // Create a new état des lieux de sortie if it doesn't exist
            $stmt = $pdo->prepare("
                INSERT INTO etats_lieux (
                    contrat_id, type, date_etat, locataire_present, 
                    bilan_logement_data, bilan_logement_commentaire, bilan_sent,
                    created_at, updated_at
                ) VALUES (?, 'sortie', CURDATE(), TRUE, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $contratId,
                $bilanData,
                $_POST['bilan_logement_commentaire'] ?? '',
                $sendBilan ? 1 : 0
            ]);
            $etatLieuxId = $pdo->lastInsertId();
        } else {
            // Update existing état des lieux bilan data
            $stmt = $pdo->prepare("
                UPDATE etats_lieux SET
                    bilan_logement_data = ?,
                    bilan_logement_commentaire = ?,
                    bilan_sent = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $bilanData,
                $_POST['bilan_logement_commentaire'] ?? '',
                $sendBilan ? 1 : 0,
                $etatLieuxId
            ]);
        }
        
        // If sending the bilan, mark it for sending to tenant(s)
        if ($sendBilan) {
            // TODO: Implement email sending to tenant(s)
            // For now, just mark it as ready to be sent
            $_SESSION['success'] = "Bilan du logement enregistré et marqué comme prêt à envoyer";
        } else {
            $_SESSION['success'] = "Bilan du logement mis à jour avec succès";
        }
        
        $pdo->commit();
        header('Location: edit-bilan-logement.php?contrat_id=' . $contratId);
        exit;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = "Erreur lors de la mise à jour: " . $e->getMessage();
    }
}

// Get contract details
$stmt = $pdo->prepare("
    SELECT c.*, 
           c.id as contrat_id,
           c.reference_unique as contrat_ref,
           l.id as logement_id,
           l.adresse as logement_adresse
    FROM contrats c
    LEFT JOIN logements l ON c.logement_id = l.id
    WHERE c.id = ?
");
$stmt->execute([$contratId]);
$contrat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contrat) {
    $_SESSION['error'] = "Contrat non trouvé";
    header('Location: contrats.php');
    exit;
}

// Get état des lieux de sortie for this contract
$stmt = $pdo->prepare("
    SELECT edl.* 
    FROM etats_lieux edl
    WHERE edl.contrat_id = ? AND edl.type = 'sortie'
    ORDER BY edl.created_at DESC
    LIMIT 1
");
$stmt->execute([$contratId]);
$etat = $stmt->fetch(PDO::FETCH_ASSOC);

// Get inventaires de sortie for this contract
$stmt = $pdo->prepare("
    SELECT * FROM inventaires
    WHERE contrat_id = ? AND type = 'sortie'
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->execute([$contratId]);
$inventaire = $stmt->fetch(PDO::FETCH_ASSOC);

// Prepare bilan rows from état des lieux if available
$bilanRows = [];
$bilanSent = false; // Track if bilan has been sent

// Define static lines that should always be present
$staticLines = ['Vide', 'Eau', 'Électricité'];

if ($etat && !empty($etat['bilan_logement_data'])) {
    $bilanRows = json_decode($etat['bilan_logement_data'], true) ?: [];
    // Check if bilan has been sent
    $bilanSent = isset($etat['bilan_sent']) && $etat['bilan_sent'];
}

// Auto-import logic: Only import if bilan hasn't been sent yet
if (!$bilanSent) {
    // Create hash maps for O(1) lookups
    $existingPostesMap = array_flip(array_column($bilanRows, 'poste'));
    $staticLinesMap = array_flip($staticLines);
    
    // Add static lines at the beginning if they don't exist
    foreach ($staticLines as $staticLine) {
        if (!isset($existingPostesMap[$staticLine])) {
            $bilanRows[] = [
                'poste' => $staticLine,
                'commentaires' => '',
                'valeur' => '',
                'montant_du' => ''
            ];
        }
    }
    
    // Helper function to check if data rows exist (beyond static lines)
    $hasNonStaticRows = function($rows, $staticLinesMap) {
        foreach ($rows as $row) {
            $poste = $row['poste'] ?? '';
            if (!isset($staticLinesMap[$poste])) {
                return true;
            }
        }
        return false;
    };
    
    // If no data rows exist yet (only static lines), try to auto-import from inventaire
    if (!$hasNonStaticRows($bilanRows, $staticLinesMap) && $inventaire) {
        // Auto-import from inventaire - get equipment with comments
        $equipements = json_decode($inventaire['equipements_data'], true) ?: [];
        foreach ($equipements as $item) {
            if (isset($item['commentaires']) && trim($item['commentaires']) !== '') {
                $bilanRows[] = [
                    'poste' => $item['nom'] ?? '',
                    'commentaires' => $item['commentaires'],
                    'valeur' => '',
                    'montant_du' => ''
                ];
            }
        }
    }
}

if (empty($bilanRows)) {
    // Add static lines and one empty row by default
    $bilanRows = [];
    foreach ($staticLines as $staticLine) {
        $bilanRows[] = ['poste' => $staticLine, 'commentaires' => '', 'valeur' => '', 'montant_du' => ''];
    }
    // Add one empty row for data entry
    $bilanRows[] = ['poste' => '', 'commentaires' => '', 'valeur' => '', 'montant_du' => ''];
}

// Get bilan_sections_data for import functionality from état des lieux
$bilanSectionsData = [];
if ($etat && !empty($etat['bilan_sections_data'])) {
    $bilanSectionsData = json_decode($etat['bilan_sections_data'], true) ?: [];
}

// Get justificatifs from état des lieux
$justificatifs = [];
if ($etat && !empty($etat['bilan_logement_justificatifs'])) {
    $justificatifs = json_decode($etat['bilan_logement_justificatifs'], true) ?: [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bilan du logement - <?php echo htmlspecialchars($contrat['contrat_ref']); ?></title>
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
        .form-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #212529;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e9ecef;
        }
        #bilanTable .bilan-field.is-invalid {
            border-color: #dc3545;
            background-color: #f8d7da;
        }
        #bilanTable .bilan-field.is-valid {
            border-color: #28a745;
            background-color: #d4edda;
        }
        #bilanTable thead th {
            background-color: #f8f9fa;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }
        #bilanTable tfoot td {
            font-weight: 600;
            background-color: #f8f9fa;
        }
        .bilan-row:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/menu.php'; ?>

    <div class="main-content">
        <div class="header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4>
                        <i class="bi bi-clipboard-check"></i> Bilan du logement
                    </h4>
                    <p class="text-muted mb-0">
                        Contrat - <?php echo htmlspecialchars($contrat['contrat_ref']); ?>
                    </p>
                </div>
                <a href="contrat-detail.php?id=<?php echo $contratId; ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Retour
                </a>
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

        <form method="POST" id="bilanForm">
            <input type="hidden" name="action" value="save">

            <div class="form-card">
                <div class="section-title">
                    <i class="bi bi-clipboard-check"></i> Détail des dégradations
                </div>
                
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Cette section permet de détailler les dégradations constatées, les frais associés et les justificatifs.
                </div>
                
                <!-- Dynamic Table for Degradations -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Tableau des dégradations</h6>
                        <div>
                            <?php if (!$bilanSent): ?>
                                <?php if (!empty($bilanSectionsData)): ?>
                                <button type="button" class="btn btn-sm btn-warning me-2" onclick="importFromExitState()" id="importExitStateBtn">
                                    <i class="bi bi-download"></i> Importer depuis l'état de sortie
                                </button>
                                <?php endif; ?>
                                <?php if ($inventaire): ?>
                                <button type="button" class="btn btn-sm btn-success me-2" onclick="importFromExitInventory()" id="importBilanBtn">
                                    <i class="bi bi-download"></i> Importer depuis l'inventaire de sortie
                                </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-info text-white me-2">
                                    <i class="bi bi-check-circle"></i> Bilan envoyé
                                </span>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-primary" onclick="addBilanRow()" id="addBilanRowBtn">
                                <i class="bi bi-plus-circle"></i> Ajouter une ligne
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered" id="bilanTable">
                            <thead class="table-light">
                                <tr>
                                    <th width="25%">Poste / Équipement</th>
                                    <th width="35%">Commentaires</th>
                                    <th width="15%">Valeur (€)</th>
                                    <th width="15%">Montant dû (€)</th>
                                    <th width="10%">Action</th>
                                </tr>
                            </thead>
                            <tbody id="bilanTableBody">
                                <?php foreach ($bilanRows as $index => $row): ?>
                                <tr class="bilan-row">
                                    <td>
                                        <input type="text" name="bilan_rows[<?php echo $index; ?>][poste]" 
                                               class="form-control bilan-field" 
                                               value="<?php echo htmlspecialchars($row['poste'] ?? ''); ?>" 
                                               placeholder="Ex: Peinture salon">
                                    </td>
                                    <td>
                                        <input type="text" name="bilan_rows[<?php echo $index; ?>][commentaires]" 
                                               class="form-control bilan-field" 
                                               value="<?php echo htmlspecialchars($row['commentaires'] ?? ''); ?>" 
                                               placeholder="Description détaillée"
                                               list="commentairesSuggestions">
                                    </td>
                                    <td>
                                        <input type="number" name="bilan_rows[<?php echo $index; ?>][valeur]" 
                                               class="form-control bilan-field bilan-valeur" 
                                               value="<?php echo htmlspecialchars($row['valeur'] ?? ''); ?>" 
                                               step="0.01" min="0" 
                                               placeholder="0.00"
                                               onchange="calculateBilanTotals()">
                                    </td>
                                    <td>
                                        <input type="number" name="bilan_rows[<?php echo $index; ?>][montant_du]" 
                                               class="form-control bilan-field bilan-montant-du" 
                                               value="<?php echo htmlspecialchars($row['montant_du'] ?? ''); ?>" 
                                               step="0.01" min="0" 
                                               placeholder="0.00"
                                               onchange="calculateBilanTotals()">
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-danger" onclick="removeBilanRow(this)" title="Supprimer la ligne">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="2" class="text-end"><strong>Total des frais constatés:</strong></td>
                                    <td><strong id="totalValeur">0.00 €</strong></td>
                                    <td><strong id="totalMontantDu">0.00 €</strong></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                        
                        <!-- Datalist for comment suggestions -->
                        <datalist id="commentairesSuggestions">
                            <option value="Vide">
                            <option value="Solde créditeur">
                            <option value="Solde débiteur">
                        </datalist>
                    </div>
                    
                    <div class="alert alert-warning mt-2">
                        <i class="bi bi-exclamation-triangle"></i> Maximum 20 lignes. Les champs vides sont validés avec une bordure rouge.
                    </div>
                </div>
                
                <!-- Justificatifs Upload -->
                <div class="mb-4">
                    <h6 class="mb-3">Justificatifs (Factures, devis, photos)</h6>
                    
                    <div class="mb-3">
                        <label class="form-label">Télécharger des fichiers (PDF, JPG, PNG - max 20 MB par fichier)</label>
                        <input type="file" class="form-control" id="bilanJustificatifInput" 
                               accept=".pdf,.jpg,.jpeg,.png" 
                               onchange="uploadBilanJustificatif(this)">
                        <small class="text-muted">Formats acceptés: PDF, JPG, PNG. Taille maximale: 20 MB par fichier.</small>
                    </div>
                    
                    <div id="bilanJustificatifsContainer">
                        <?php if (!empty($justificatifs)): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-file-earmark-check"></i> <strong><?php echo count($justificatifs); ?> fichier(s) téléchargé(s)</strong>
                        </div>
                        
                        <div class="row" id="justificatifsFilesList">
                            <?php foreach ($justificatifs as $file): ?>
                            <div class="col-md-4 mb-3" id="justificatif_<?php echo htmlspecialchars($file['id']); ?>">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="card-title mb-1">
                                                    <?php if ($file['type'] === 'application/pdf'): ?>
                                                    <i class="bi bi-file-pdf text-danger"></i>
                                                    <?php else: ?>
                                                    <i class="bi bi-file-image text-primary"></i>
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($file['original_name']); ?>
                                                </h6>
                                                <p class="card-text small text-muted mb-1">
                                                    <?php echo number_format($file['size'] / 1024, 2); ?> KB
                                                </p>
                                                <p class="card-text small text-muted">
                                                    <?php echo htmlspecialchars($file['uploaded_at']); ?>
                                                </p>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-danger ms-2" 
                                                    onclick="deleteBilanJustificatif('<?php echo htmlspecialchars($file['id']); ?>')" 
                                                    title="Supprimer">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                        <?php if ($file['type'] !== 'application/pdf'): ?>
                                        <a href="/<?php echo htmlspecialchars($file['path']); ?>" target="_blank">
                                            <img src="/<?php echo htmlspecialchars($file['path']); ?>" 
                                                 class="img-thumbnail mt-2" 
                                                 style="max-height: 150px; width: auto;">
                                        </a>
                                        <?php else: ?>
                                        <a href="/<?php echo htmlspecialchars($file['path']); ?>" 
                                           target="_blank" 
                                           class="btn btn-sm btn-outline-primary mt-2">
                                            <i class="bi bi-eye"></i> Voir le PDF
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-secondary" id="noJustificatifsMessage">
                            <i class="bi bi-info-circle"></i> Aucun justificatif téléchargé pour le moment.
                        </div>
                        <div class="row" id="justificatifsFilesList" style="display: none;"></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- General Comment -->
                <div class="mb-3">
                    <label class="form-label">Commentaire général</label>
                    <textarea name="bilan_logement_commentaire" class="form-control" rows="4" 
                              placeholder="Observations générales concernant le bilan du logement"><?php 
                        if ($etat && !empty($etat['bilan_logement_commentaire'])) {
                            echo htmlspecialchars($etat['bilan_logement_commentaire']);
                        } else {
                            echo 'Les dégradations listées ci-dessus ont été constatées lors de l\'état de sortie. Les montants indiqués correspondent aux frais de remise en état.';
                        }
                    ?></textarea>
                </div>
            </div>

            <div class="d-flex justify-content-between mb-5">
                <a href="contrat-detail.php?id=<?php echo $contratId; ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Annuler
                </a>
                <div>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-save"></i> Enregistrer le bilan
                    </button>
                    <?php if (!$bilanSent): ?>
                    <button type="submit" name="send_bilan" value="1" class="btn btn-success btn-lg ms-2">
                        <i class="bi bi-send"></i> Enregistrer et envoyer au(x) locataire(s)
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const CONTRAT_ID = <?php echo $contratId; ?>;
        const ETAT_LIEUX_ID = <?php echo $etat ? $etat['id'] : 0; ?>;
        let bilanRowCounter = <?php echo count($bilanRows); ?>;
        const MAX_BILAN_ROWS = 20;
        const BILAN_MAX_FILE_SIZE = <?php echo $config['BILAN_MAX_FILE_SIZE']; ?>;
        const BILAN_ALLOWED_TYPES = <?php echo json_encode($config['BILAN_ALLOWED_TYPES']); ?>;
        const BILAN_SECTIONS_DATA = <?php echo json_encode($bilanSectionsData); ?>;
        
        // Import data from exit inventory (état de sortie)
        function importFromExitInventory() {
            // Confirm before importing
            if (!confirm('Importer les équipements avec commentaires depuis l\'inventaire de sortie?\n\nSeuls les équipements ayant des commentaires seront importés.\nCela ajoutera de nouvelles lignes au tableau.')) {
                return;
            }
            
            // Disable button and show loading state
            const importBtn = document.getElementById('importBilanBtn');
            const originalContent = importBtn.innerHTML;
            importBtn.disabled = true;
            importBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Importation en cours...';
            
            // Call the new import API
            fetch('import-inventaire-to-bilan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    contrat_id: CONTRAT_ID
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.rows && data.rows.length > 0) {
                    let importedCount = 0;
                    
                    // Import each row
                    data.rows.forEach(row => {
                        // Check if we haven't reached the max rows
                        if (document.querySelectorAll('.bilan-row').length < MAX_BILAN_ROWS) {
                            addBilanRowWithData(row.poste, row.commentaires, row.valeur, row.montant_du);
                            importedCount++;
                        }
                    });
                    
                    if (importedCount > 0) {
                        alert(`✓ ${importedCount} équipement(s) avec commentaires importé(s) avec succès`);
                        // Update button to show import is complete
                        importBtn.innerHTML = '<i class="bi bi-check-circle"></i> Données importées';
                        // Keep button disabled to prevent duplicate imports
                    } else {
                        alert('Le nombre maximum de lignes est atteint');
                        importBtn.disabled = false;
                        importBtn.innerHTML = originalContent;
                    }
                } else {
                    alert(data.message || 'Aucune donnée à importer');
                    importBtn.disabled = false;
                    importBtn.innerHTML = originalContent;
                }
            })
            .catch(error => {
                console.error('Import error:', error);
                alert('Erreur lors de l\'importation: ' + error.message);
                importBtn.disabled = false;
                importBtn.innerHTML = originalContent;
            });
        }
        
        // Import data from exit state (état de sortie bilan sections)
        function importFromExitState() {
            // Confirm before importing
            if (!confirm('Importer les dégradations constatées depuis l\'état de sortie?\n\nCela ajoutera de nouvelles lignes au tableau avec les équipements manquants et endommagés.')) {
                return;
            }
            
            // Disable button and show loading state
            const importBtn = document.getElementById('importExitStateBtn');
            const originalContent = importBtn.innerHTML;
            importBtn.disabled = true;
            importBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Importation en cours...';
            
            try {
                // BILAN_SECTIONS_DATA is already available as a JS constant
                let importedCount = 0;
                
                // Import data from each section
                Object.keys(BILAN_SECTIONS_DATA).forEach(section => {
                    if (Array.isArray(BILAN_SECTIONS_DATA[section])) {
                        BILAN_SECTIONS_DATA[section].forEach(item => {
                            // Check if we haven't reached the max rows
                            if (document.querySelectorAll('.bilan-row').length < MAX_BILAN_ROWS) {
                                const equipement = item.equipement || '';
                                const commentaire = item.commentaire || '';
                                
                                // Only import if there's something to import
                                if (equipement || commentaire) {
                                    const poste = equipement;
                                    // Remove only known category labels like '[Manquant]' or '[Endommagé]' that
                                    // were previously added during import at the START of the comment.
                                    // This preserves user-added bracketed text like '[voir photo]' or '[TODO]'.
                                    // Case-insensitive pattern: /^\[(Manquant|Endommagé)\]\s*/i
                                    const comment = commentaire.replace(/^\[(Manquant|Endommagé)\]\s*/i, '');
                                    
                                    addBilanRowWithData(poste, comment, '', '');
                                    importedCount++;
                                }
                            }
                        });
                    }
                });
                
                if (importedCount > 0) {
                    alert(`✓ ${importedCount} élément(s) importé(s) avec succès depuis l'état de sortie`);
                    // Update button to show import is complete
                    importBtn.innerHTML = '<i class="bi bi-check-circle"></i> Données importées';
                    // Keep button disabled to prevent duplicate imports
                } else {
                    alert('Aucune donnée à importer ou nombre maximum de lignes atteint');
                    importBtn.disabled = false;
                    importBtn.innerHTML = originalContent;
                }
            } catch (error) {
                console.error('Import error:', error);
                alert('Erreur lors de l\'importation: ' + error.message);
                importBtn.disabled = false;
                importBtn.innerHTML = originalContent;
            }
        }
        
        // Add a new row with data (updated to accept all 4 fields)
        function addBilanRowWithData(poste, commentaires, valeur = '', montant_du = '') {
            if (document.querySelectorAll('.bilan-row').length >= MAX_BILAN_ROWS) {
                return;
            }
            
            const tbody = document.getElementById('bilanTableBody');
            const newRow = document.createElement('tr');
            newRow.className = 'bilan-row';
            newRow.innerHTML = `
                <td>
                    <input type="text" name="bilan_rows[${bilanRowCounter}][poste]" 
                           class="form-control bilan-field" 
                           value="${escapeHtml(poste || '')}"
                           placeholder="Ex: Peinture salon">
                </td>
                <td>
                    <input type="text" name="bilan_rows[${bilanRowCounter}][commentaires]" 
                           class="form-control bilan-field" 
                           value="${escapeHtml(commentaires || '')}"
                           placeholder="Description détaillée"
                           list="commentairesSuggestions">
                </td>
                <td>
                    <input type="number" name="bilan_rows[${bilanRowCounter}][valeur]" 
                           class="form-control bilan-field bilan-valeur" 
                           step="0.01" min="0" 
                           placeholder="0.00"
                           value="${valeur}"
                           onchange="calculateBilanTotals()">
                </td>
                <td>
                    <input type="number" name="bilan_rows[${bilanRowCounter}][montant_du]" 
                           class="form-control bilan-field bilan-montant-du" 
                           step="0.01" min="0" 
                           placeholder="0.00"
                           value="${montant_du}"
                           onchange="calculateBilanTotals()">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-danger" onclick="removeBilanRow(this)" title="Supprimer la ligne">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(newRow);
            bilanRowCounter++;
            
            // Add input listeners for validation
            newRow.querySelectorAll('.bilan-field').forEach(field => {
                field.addEventListener('input', validateBilanFields);
            });
            
            updateBilanRowButton();
            validateBilanFields();
            calculateBilanTotals();
        }
        
        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
        
        // Add a new row to the bilan table
        function addBilanRow() {
            if (document.querySelectorAll('.bilan-row').length >= MAX_BILAN_ROWS) {
                alert('Maximum de 20 lignes atteint');
                return;
            }
            
            const tbody = document.getElementById('bilanTableBody');
            const newRow = document.createElement('tr');
            newRow.className = 'bilan-row';
            newRow.innerHTML = `
                <td>
                    <input type="text" name="bilan_rows[${bilanRowCounter}][poste]" 
                           class="form-control bilan-field" 
                           placeholder="Ex: Peinture salon">
                </td>
                <td>
                    <input type="text" name="bilan_rows[${bilanRowCounter}][commentaires]" 
                           class="form-control bilan-field" 
                           placeholder="Description détaillée"
                           list="commentairesSuggestions">
                </td>
                <td>
                    <input type="number" name="bilan_rows[${bilanRowCounter}][valeur]" 
                           class="form-control bilan-field bilan-valeur" 
                           step="0.01" min="0" 
                           placeholder="0.00"
                           onchange="calculateBilanTotals()">
                </td>
                <td>
                    <input type="number" name="bilan_rows[${bilanRowCounter}][montant_du]" 
                           class="form-control bilan-field bilan-montant-du" 
                           step="0.01" min="0" 
                           placeholder="0.00"
                           onchange="calculateBilanTotals()">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-danger" onclick="removeBilanRow(this)" title="Supprimer la ligne">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(newRow);
            bilanRowCounter++;
            
            // Add input listeners for validation
            newRow.querySelectorAll('.bilan-field').forEach(field => {
                field.addEventListener('input', validateBilanFields);
            });
            
            updateBilanRowButton();
            validateBilanFields();
        }
        
        // Remove a row from the bilan table
        function removeBilanRow(button) {
            const row = button.closest('tr');
            row.remove();
            calculateBilanTotals();
            updateBilanRowButton();
            validateBilanFields();
        }
        
        // Calculate totals for Valeur and Montant dû
        function calculateBilanTotals() {
            let totalValeur = 0;
            let totalMontantDu = 0;
            
            document.querySelectorAll('.bilan-valeur').forEach(input => {
                const value = parseFloat(input.value) || 0;
                totalValeur += value;
            });
            
            document.querySelectorAll('.bilan-montant-du').forEach(input => {
                const value = parseFloat(input.value) || 0;
                totalMontantDu += value;
            });
            
            document.getElementById('totalValeur').textContent = totalValeur.toFixed(2) + ' €';
            document.getElementById('totalMontantDu').textContent = totalMontantDu.toFixed(2) + ' €';
        }
        
        // Validate bilan fields
        function validateBilanFields() {
            let allValid = true;
            const rows = document.querySelectorAll('.bilan-row');
            
            rows.forEach(row => {
                const fields = row.querySelectorAll('.bilan-field');
                let rowHasValue = false;
                
                // Check if any field in the row has a value
                fields.forEach(field => {
                    if (field.value.trim() !== '') {
                        rowHasValue = true;
                    }
                });
                
                // If row has any value, only Poste and Commentaires fields are required
                // Valeur and Montant dû fields are optional (empty values are treated as 0 in calculations)
                if (rowHasValue) {
                    fields.forEach(field => {
                        // Skip validation for valeur and montant_du fields (they are optional)
                        if (field.classList.contains('bilan-valeur') || field.classList.contains('bilan-montant-du')) {
                            field.classList.remove('is-invalid', 'is-valid');
                            return;
                        }
                        
                        if (field.value.trim() === '') {
                            field.classList.add('is-invalid');
                            field.classList.remove('is-valid');
                            allValid = false;
                        } else {
                            field.classList.remove('is-invalid');
                            field.classList.add('is-valid');
                        }
                    });
                } else {
                    // Empty row - remove validation classes
                    fields.forEach(field => {
                        field.classList.remove('is-invalid', 'is-valid');
                    });
                }
            });
            
            return allValid;
        }
        
        // Update the add row button state
        function updateBilanRowButton() {
            const currentRows = document.querySelectorAll('.bilan-row').length;
            const addButton = document.getElementById('addBilanRowBtn');
            
            if (currentRows >= MAX_BILAN_ROWS) {
                addButton.disabled = true;
                addButton.classList.add('disabled');
            } else {
                addButton.disabled = false;
                addButton.classList.remove('disabled');
            }
        }
        
        // Upload a justificatif file
        function uploadBilanJustificatif(input) {
            const file = input.files[0];
            if (!file) return;
            
            // Check if etat_lieux exists
            if (ETAT_LIEUX_ID === 0) {
                alert('Veuillez d\'abord enregistrer le bilan avant de télécharger des fichiers.');
                input.value = '';
                return;
            }
            
            // Validate file size
            if (file.size > BILAN_MAX_FILE_SIZE) {
                alert('Le fichier est trop volumineux. Taille maximale: ' + (BILAN_MAX_FILE_SIZE / (1024 * 1024)) + ' MB');
                input.value = '';
                return;
            }
            
            // Validate file type
            const fileType = file.type;
            if (!BILAN_ALLOWED_TYPES.includes(fileType)) {
                alert('Type de fichier non autorisé. Formats acceptés: PDF, JPG, PNG');
                input.value = '';
                return;
            }
            
            const formData = new FormData();
            // IMPORTANT: Field name must be 'justificatif' to match backend (upload-bilan-justificatif.php line 45)
            formData.append('justificatif', file);
            formData.append('etat_lieux_id', ETAT_LIEUX_ID);
            
            // Show loading indicator
            const container = document.getElementById('bilanJustificatifsContainer');
            const loadingMsg = document.createElement('div');
            loadingMsg.className = 'alert alert-info';
            loadingMsg.innerHTML = '<i class="bi bi-hourglass-split"></i> Téléchargement en cours...';
            container.insertBefore(loadingMsg, container.firstChild);
            
            fetch('upload-bilan-justificatif.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                loadingMsg.remove();
                
                if (data.success) {
                    // Hide "no files" message
                    const noFilesMsg = document.getElementById('noJustificatifsMessage');
                    if (noFilesMsg) {
                        noFilesMsg.style.display = 'none';
                    }
                    
                    // Show files list
                    const filesList = document.getElementById('justificatifsFilesList');
                    filesList.style.display = 'flex';
                    
                    // Add the new file to the list
                    const fileCard = createFileCard(data.file);
                    filesList.insertAdjacentHTML('afterbegin', fileCard);
                    
                    // Clear input
                    input.value = '';
                    
                    // Show success message
                    const successMsg = document.createElement('div');
                    successMsg.className = 'alert alert-success alert-dismissible fade show';
                    successMsg.innerHTML = `
                        <i class="bi bi-check-circle"></i> Fichier téléchargé avec succès
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    container.insertBefore(successMsg, container.firstChild);
                    
                    setTimeout(() => {
                        successMsg.remove();
                    }, 3000);
                } else {
                    alert('Erreur lors du téléchargement: ' + data.message);
                }
            })
            .catch(error => {
                loadingMsg.remove();
                console.error('Error:', error);
                alert('Erreur lors du téléchargement du fichier');
            });
        }
        
        // Create HTML for a file card
        function createFileCard(file) {
            const isPdf = file.type === 'application/pdf';
            const icon = isPdf ? 'bi-file-pdf text-danger' : 'bi-file-image text-primary';
            
            let imageOrLink = '';
            if (!isPdf) {
                imageOrLink = `
                    <a href="/${file.path}" target="_blank">
                        <img src="/${file.path}" class="img-thumbnail mt-2" style="max-height: 150px; width: auto;">
                    </a>
                `;
            } else {
                imageOrLink = `
                    <a href="/${file.path}" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                        <i class="bi bi-eye"></i> Voir le PDF
                    </a>
                `;
            }
            
            return `
                <div class="col-md-4 mb-3" id="justificatif_${file.id}">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="card-title mb-1">
                                        <i class="bi ${icon}"></i>
                                        ${file.original_name}
                                    </h6>
                                    <p class="card-text small text-muted mb-1">
                                        ${(file.size / 1024).toFixed(2)} KB
                                    </p>
                                    <p class="card-text small text-muted">
                                        ${file.uploaded_at}
                                    </p>
                                </div>
                                <button type="button" class="btn btn-sm btn-danger ms-2" 
                                        onclick="deleteBilanJustificatif('${file.id}')" 
                                        title="Supprimer">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                            ${imageOrLink}
                        </div>
                    </div>
                </div>
            `;
        }
        
        // Delete a justificatif file
        function deleteBilanJustificatif(fileId) {
            if (!confirm('Êtes-vous sûr de vouloir supprimer ce fichier ?')) {
                return;
            }
            
            fetch('delete-bilan-justificatif.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `file_id=${fileId}&etat_lieux_id=${ETAT_LIEUX_ID}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the file card
                    const fileCard = document.getElementById(`justificatif_${fileId}`);
                    if (fileCard) {
                        fileCard.remove();
                    }
                    
                    // Check if there are any files left
                    const filesList = document.getElementById('justificatifsFilesList');
                    if (filesList.children.length === 0) {
                        filesList.style.display = 'none';
                        const noFilesMsg = document.getElementById('noJustificatifsMessage');
                        if (noFilesMsg) {
                            noFilesMsg.style.display = 'block';
                        }
                    }
                } else {
                    alert('Erreur lors de la suppression: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Erreur lors de la suppression du fichier');
            });
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Add input listeners for validation
            document.querySelectorAll('.bilan-field').forEach(field => {
                field.addEventListener('input', validateBilanFields);
            });
            
            // Calculate initial totals
            calculateBilanTotals();
            
            // Update button state
            updateBilanRowButton();
            
            // Initial validation
            validateBilanFields();
        });
        
        // Handle form submission
        document.getElementById('bilanForm').addEventListener('submit', function(e) {
            if (!validateBilanFields()) {
                e.preventDefault();
                alert('Veuillez remplir tous les champs des lignes qui contiennent des données');
                return false;
            }
        });
    </script>
</body>
</html>
