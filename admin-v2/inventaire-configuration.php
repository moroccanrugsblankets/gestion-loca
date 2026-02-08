<?php
/**
 * Inventaire Configuration - Template Management
 * My Invest Immobilier
 */
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Update inventory templates
        if (isset($_POST['inventaire_template_html'])) {
            $stmt = $pdo->prepare("
                UPDATE parametres SET valeur = ? WHERE cle = 'inventaire_template_html'
            ");
            $stmt->execute([$_POST['inventaire_template_html']]);
        }
        
        if (isset($_POST['inventaire_sortie_template_html'])) {
            $stmt = $pdo->prepare("
                UPDATE parametres SET valeur = ? WHERE cle = 'inventaire_sortie_template_html'
            ");
            $stmt->execute([$_POST['inventaire_sortie_template_html']]);
        }
        
        $_SESSION['success'] = "Configuration mise à jour avec succès";
        header('Location: inventaire-configuration.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur lors de la mise à jour: " . $e->getMessage();
        error_log("Erreur config inventaire: " . $e->getMessage());
    }
}

// Get current templates
$stmt = $pdo->query("SELECT cle, valeur FROM parametres WHERE cle IN ('inventaire_template_html', 'inventaire_sortie_template_html')");
$templates = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $templates[$row['cle']] = $row['valeur'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration Inventaire - My Invest Immobilier</title>
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
        .config-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .template-editor {
            font-family: monospace;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/menu.php'; ?>

    <div class="main-content">
        <div class="header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4><i class="bi bi-gear"></i> Configuration de l'Inventaire</h4>
                    <p class="text-muted mb-0">Personnalisation des templates PDF d'inventaire</p>
                </div>
                <a href="inventaires.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Retour aux inventaires
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

        <form method="POST">
            <div class="config-card">
                <h5 class="mb-4">
                    <i class="bi bi-file-earmark-text"></i> Template d'inventaire d'entrée
                </h5>
                <div class="alert alert-info">
                    <strong>Note:</strong> Laissez vide pour utiliser le template par défaut. Le template actuel est généré automatiquement en HTML.
                </div>
                <div class="mb-3">
                    <label class="form-label">Template HTML personnalisé (optionnel)</label>
                    <textarea name="inventaire_template_html" class="form-control template-editor" rows="10" 
                              placeholder="Template HTML pour l'inventaire d'entrée..."><?php echo htmlspecialchars($templates['inventaire_template_html'] ?? ''); ?></textarea>
                    <small class="text-muted">
                        Variables disponibles: {{reference}}, {{date}}, {{adresse}}, {{locataire_nom}}, {{equipements}}
                    </small>
                </div>
            </div>

            <div class="config-card">
                <h5 class="mb-4">
                    <i class="bi bi-file-earmark-text"></i> Template d'inventaire de sortie
                </h5>
                <div class="alert alert-info">
                    <strong>Note:</strong> Laissez vide pour utiliser le template par défaut.
                </div>
                <div class="mb-3">
                    <label class="form-label">Template HTML personnalisé (optionnel)</label>
                    <textarea name="inventaire_sortie_template_html" class="form-control template-editor" rows="10" 
                              placeholder="Template HTML pour l'inventaire de sortie..."><?php echo htmlspecialchars($templates['inventaire_sortie_template_html'] ?? ''); ?></textarea>
                    <small class="text-muted">
                        Variables disponibles: {{reference}}, {{date}}, {{adresse}}, {{locataire_nom}}, {{equipements}}, {{comparaison}}
                    </small>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <a href="inventaires.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Annuler
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Enregistrer la configuration
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
