<?php
/**
 * Test HTML Preview - Bilan Logement
 * This page allows testing the HTML template before TCPDF processing
 * ADMIN ACCESS ONLY
 * My Invest Immobilier
 */

require_once 'includes/config.php';
require_once 'includes/db.php';

// Security: Require authentication (uncomment when auth is available)
// session_start();
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
//     header('Location: admin-v2/login.php');
//     exit;
// }

// Get contract ID from URL parameter
$contratId = isset($_GET['contrat_id']) ? (int)$_GET['contrat_id'] : 0;

// Get all contracts for the dropdown
$stmt = $pdo->query("
    SELECT c.id, c.reference_unique, l.adresse
    FROM contrats c
    LEFT JOIN logements l ON c.logement_id = l.id
    ORDER BY c.created_at DESC
    LIMIT 100
");
$allContracts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$htmlPreview = '';
$error = '';

if ($contratId > 0) {
    try {
        // Get contract and logement details (including depot_garantie from logements table)
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   l.adresse as logement_adresse,
                   l.depot_garantie as depot_garantie,
                   c.reference_unique as contrat_ref
            FROM contrats c
            LEFT JOIN logements l ON c.logement_id = l.id
            WHERE c.id = ?
        ");
        $stmt->execute([$contratId]);
        $contrat = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$contrat) {
            $error = "Contrat non trouvé";
        } else {
            // Extract depot_garantie for later use
            $depotGarantie = floatval($contrat['depot_garantie'] ?? 0);
            
            // Get locataires
            $stmt = $pdo->prepare("
                SELECT nom, prenom, email 
                FROM locataires 
                WHERE contrat_id = ? 
                ORDER BY ordre
            ");
            $stmt->execute([$contratId]);
            $locataires = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $locataireNom = '';
            if (!empty($locataires)) {
                $locataireNom = $locataires[0]['prenom'] . ' ' . $locataires[0]['nom'];
                if (count($locataires) > 1) {
                    $locataireNom .= ' et ' . $locataires[1]['prenom'] . ' ' . $locataires[1]['nom'];
                }
            }

            // Get état des lieux de sortie with bilan data
            $stmt = $pdo->prepare("
                SELECT * FROM etats_lieux 
                WHERE contrat_id = ? AND type = 'sortie'
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$contratId]);
            $etatLieux = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$etatLieux || empty($etatLieux['bilan_logement_data'])) {
                $error = "Aucune donnée de bilan trouvée pour ce contrat. Veuillez d'abord créer un bilan.";
            } else {
                // Decode bilan data
                $bilanRows = json_decode($etatLieux['bilan_logement_data'], true) ?: [];
                
                // Get HTML template from parametres
                $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'bilan_logement_template_html'");
                $stmt->execute();
                $templateHtml = $stmt->fetchColumn();

                if (!$templateHtml) {
                    $error = "Template HTML du bilan non trouvé dans les paramètres";
                } else {
                    // Get logo if exists
                    $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'logo_societe'");
                    $stmt->execute();
                    $logoData = $stmt->fetchColumn();
                    
                    $logoHtml = '';
                    if ($logoData) {
                        if (strpos($logoData, 'data:') === 0 || strpos($logoData, 'uploads/') !== false) {
                            $logoHtml = '<img src="' . htmlspecialchars($logoData) . '" alt="Logo" style="max-width: 150px; max-height: 80px;">';
                        }
                    }

                    // Get signature if exists
                    $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = 'signature_societe_image'");
                    $stmt->execute();
                    $signatureData = $stmt->fetchColumn();
                    
                    $signatureHtml = '';
                    if ($signatureData) {
                        $signatureHtml = '<div style="margin-top: 20px;"><p><strong>Signature de l\'agence :</strong></p>';
                        $signatureHtml .= '<img src="' . htmlspecialchars($signatureData) . '" alt="Signature" style="max-width: 200px;">';
                        $signatureHtml .= '</div>';
                    }

                    // Build bilan rows HTML with complete table structure (without thead/tbody tags)
                    $bilanRowsHtml = '<table>';
                    
                    // Add header row
                    $bilanRowsHtml .= '<tr>';
                    $bilanRowsHtml .= '<th style="width: 25%;">Poste</th>';
                    $bilanRowsHtml .= '<th style="width: 30%;">Commentaires</th>';
                    $bilanRowsHtml .= '<th style="width: 15%;">Valeur</th>';
                    $bilanRowsHtml .= '<th style="width: 15%;">Débit</th>';
                    $bilanRowsHtml .= '<th style="width: 15%;">Crédit</th>';
                    $bilanRowsHtml .= '</tr>';
                    
                    $totalValeur = 0;
                    $totalSoldeDebiteur = 0;
                    $totalSoldeCrediteur = 0;
                    
                    foreach ($bilanRows as $row) {
                        $poste = htmlspecialchars($row['poste'] ?? '');
                        $commentaires = htmlspecialchars($row['commentaires'] ?? '');
                        $valeur = htmlspecialchars($row['valeur'] ?? '');
                        
                        // Handle backward compatibility: montant_du -> solde_debiteur
                        $soldeDebiteur = $row['solde_debiteur'] ?? ($row['montant_du'] ?? '');
                        $soldeCrediteur = $row['solde_crediteur'] ?? '';
                        
                        // Parse values to add to totals
                        if (!empty($valeur) && is_numeric($valeur)) {
                            $totalValeur += floatval($valeur);
                        }
                        if (!empty($soldeDebiteur) && is_numeric($soldeDebiteur)) {
                            $totalSoldeDebiteur += floatval($soldeDebiteur);
                        }
                        if (!empty($soldeCrediteur) && is_numeric($soldeCrediteur)) {
                            $totalSoldeCrediteur += floatval($soldeCrediteur);
                        }
                        
                        // Format amounts for display
                        $valeurDisplay = !empty($valeur) && is_numeric($valeur) ? number_format(floatval($valeur), 2, ',', ' ') . ' €' : htmlspecialchars($valeur);
                        $soldeDebiteurDisplay = !empty($soldeDebiteur) && is_numeric($soldeDebiteur) ? number_format(floatval($soldeDebiteur), 2, ',', ' ') . ' €' : htmlspecialchars($soldeDebiteur);
                        $soldeCrediteurDisplay = !empty($soldeCrediteur) && is_numeric($soldeCrediteur) ? number_format(floatval($soldeCrediteur), 2, ',', ' ') . ' €' : htmlspecialchars($soldeCrediteur);
                        
                        $bilanRowsHtml .= '<tr>';
                        $bilanRowsHtml .= '<td>' . $poste . '</td>';
                        $bilanRowsHtml .= '<td>' . nl2br($commentaires) . '</td>';
                        $bilanRowsHtml .= '<td>' . $valeurDisplay . '</td>';
                        $bilanRowsHtml .= '<td>' . $soldeDebiteurDisplay . '</td>';
                        $bilanRowsHtml .= '<td>' . $soldeCrediteurDisplay . '</td>';
                        $bilanRowsHtml .= '</tr>';
                    }
                    
                    $bilanRowsHtml .= '</table>';

                    // Build commentaire section
                    $commentaireHtml = '';
                    if (!empty($etatLieux['bilan_logement_commentaire'])) {
                        $commentaire = htmlspecialchars($etatLieux['bilan_logement_commentaire']);
                        $commentaireHtml = '<div class="commentaire-section">';
                        $commentaireHtml .= '<h3>Observations générales</h3>';
                        $commentaireHtml .= '<p>' . nl2br($commentaire) . '</p>';
                        $commentaireHtml .= '</div>';
                    }

                    // Calculate financial summary values
                    // Valeur estimative = total valeur from bilan rows
                    $valeurEstimative = $totalValeur;
                    
                    // Calculate: Montant à restituer = Dépôt de garantie + Solde Créditeur - Solde Débiteur (if > 0, else 0)
                    $calculResultat = $depotGarantie + $totalSoldeCrediteur - $totalSoldeDebiteur;
                    $montantARestituer = $calculResultat > 0 ? $calculResultat : 0;
                    
                    // Calculate: Reste dû = abs(Dépôt de garantie + Solde Créditeur - Solde Débiteur) (if < 0, else 0)
                    $resteDu = $calculResultat < 0 ? abs($calculResultat) : 0;

                    // Replace variables in template
                    $variables = [
                        '{{logo}}' => $logoHtml,
                        '{{locataire_nom}}' => htmlspecialchars($locataireNom),
                        '{{contrat_ref}}' => htmlspecialchars($contrat['contrat_ref']),
                        '{{adresse}}' => htmlspecialchars($contrat['logement_adresse']),
                        '{{date}}' => date('d/m/Y'),
                        '{{bilan_rows}}' => $bilanRowsHtml,
                        '{{commentaire_section}}' => $commentaireHtml,
                        '{{total_valeur}}' => number_format($totalValeur, 2, ',', ' ') . ' €',
                        '{{total_solde_debiteur}}' => number_format($totalSoldeDebiteur, 2, ',', ' ') . ' €',
                        '{{total_solde_crediteur}}' => number_format($totalSoldeCrediteur, 2, ',', ' ') . ' €',
                        '{{total_montant}}' => number_format($totalSoldeDebiteur, 2, ',', ' ') . ' €',
                        '{{signature_agence}}' => $signatureHtml,
                        // New financial summary variables
                        '{{depot_garantie}}' => number_format($depotGarantie, 2, ',', ' ') . ' €',
                        '{{valeur_estimative}}' => number_format($valeurEstimative, 2, ',', ' ') . ' €',
                        '{{montant_a_restituer}}' => number_format($montantARestituer, 2, ',', ' ') . ' €',
                        '{{reste_du}}' => number_format($resteDu, 2, ',', ' ') . ' €'
                    ];

                    $htmlPreview = str_replace(array_keys($variables), array_values($variables), $templateHtml);
                }
            }
        }
    } catch (Exception $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test HTML Preview - Bilan Logement</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .selector-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .preview-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            min-height: 500px;
        }
        .preview-frame {
            border: 2px solid #dee2e6;
            border-radius: 5px;
            padding: 20px;
            background: #f8f9fa;
        }
        .info-banner {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body style="background: #f5f5f5; padding: 20px;">
    <div class="container-fluid">
        <h1 class="mb-4">Test HTML Preview - Bilan Logement</h1>
        
        <div class="info-banner">
            <strong>ℹ️ Information:</strong> Cette page permet de prévisualiser le rendu HTML du bilan du logement avant la conversion en PDF par TCPDF.
            Sélectionnez un contrat pour afficher son bilan.
        </div>
        
        <div class="selector-container">
            <form method="get" action="">
                <div class="row">
                    <div class="col-md-8">
                        <label for="contrat_id" class="form-label">Sélectionner un contrat :</label>
                        <select name="contrat_id" id="contrat_id" class="form-select" required>
                            <option value="">-- Choisir un contrat --</option>
                            <?php foreach ($allContracts as $contract): ?>
                                <option value="<?= $contract['id'] ?>" <?= $contract['id'] == $contratId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($contract['reference_unique']) ?> - <?= htmlspecialchars($contract['adresse'] ?? 'N/A') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Prévisualiser</button>
                        <?php if ($contratId > 0): ?>
                            <a href="admin-v2/edit-bilan-logement.php?contrat_id=<?= $contratId ?>" class="btn btn-secondary ms-2">
                                Éditer le bilan
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-warning">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($htmlPreview && !$error): ?>
            <div class="preview-container">
                <h3 class="mb-3">Aperçu HTML</h3>
                <div class="alert alert-info">
                    <strong>⚠️ Note de sécurité:</strong> Cette page est destinée uniquement aux administrateurs pour tester le rendu HTML.
                    Toutes les variables utilisateur sont échappées avant l'insertion dans le template.
                </div>
                <div class="preview-frame">
                    <?= $htmlPreview ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
