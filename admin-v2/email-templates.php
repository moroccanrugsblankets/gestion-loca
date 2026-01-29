<?php
require_once '../includes/config.php';
require_once 'auth.php';
require_once '../includes/db.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update') {
        $id = (int)$_POST['template_id'];
        $stmt = $pdo->prepare("UPDATE email_templates SET nom = ?, sujet = ?, corps_html = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([
            $_POST['nom'],
            $_POST['sujet'],
            $_POST['corps_html'],
            $id
        ]);
        $_SESSION['success'] = "Template mis à jour avec succès";
        header('Location: email-templates.php');
        exit;
    }
}

// Get template to edit if ID provided
$template = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get all templates
$stmt = $pdo->query("SELECT * FROM email_templates ORDER BY identifiant");
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Templates d'Email - My Invest Immobilier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
        }
        .sidebar {
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            min-height: 100vh;
            padding: 0;
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            color: white;
        }
        .sidebar .logo {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar .logo h4 {
            margin: 10px 0 5px 0;
            font-size: 18px;
            font-weight: 600;
        }
        .sidebar .logo small {
            color: #bdc3c7;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        .sidebar .nav-link:hover {
            background-color: rgba(255,255,255,0.1);
            color: white;
            border-left-color: #3498db;
        }
        .sidebar .nav-link.active {
            background-color: rgba(52, 152, 219, 0.2);
            color: white;
            border-left-color: #3498db;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
        }
        .main-content {
            margin-left: 250px;
            padding: 30px;
        }
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .template-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .template-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .template-card h5 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .template-card .badge {
            font-size: 0.75rem;
        }
        .variables-info {
            background: #e8f4f8;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .variables-info h6 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .variable-tag {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            margin: 2px;
            font-family: 'Courier New', monospace;
        }
        .logout-btn {
            position: absolute;
            bottom: 20px;
            left: 20px;
            right: 20px;
        }
        .code-editor {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            min-height: 400px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <i class="bi bi-building" style="font-size: 2rem;"></i>
            <h4>MY Invest</h4>
            <small>Immobilier</small>
        </div>
        <ul class="nav flex-column mt-4">
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
                    <i class="bi bi-house-door"></i> Logements
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="contrats.php">
                    <i class="bi bi-file-earmark-check"></i> Contrats
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="parametres.php">
                    <i class="bi bi-gear"></i> Paramètres
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="email-templates.php">
                    <i class="bi bi-envelope"></i> Templates d'Email
                </a>
            </li>
        </ul>
        <a href="logout.php" class="btn btn-outline-light logout-btn">
            <i class="bi bi-box-arrow-right"></i> Déconnexion
        </a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4>Templates d'Email</h4>
                    <p class="text-muted mb-0">Gérer les modèles d'emails automatiques</p>
                </div>
                <?php if ($template): ?>
                <a href="email-templates.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Retour à la liste
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($template): ?>
            <!-- Edit Template Form -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="bi bi-pencil"></i> Modifier le template: <?php echo htmlspecialchars($template['nom']); ?>
                    </h5>
                    
                    <div class="variables-info mt-3">
                        <h6><i class="bi bi-info-circle"></i> Variables disponibles</h6>
                        <p class="mb-2 small">Utilisez ces variables dans le sujet et le corps de l'email. Elles seront automatiquement remplacées par les vraies valeurs.</p>
                        <?php 
                        $variables = json_decode($template['variables_disponibles'], true);
                        if ($variables):
                            foreach ($variables as $var):
                        ?>
                            <span class="variable-tag">{{<?php echo $var; ?>}}</span>
                        <?php 
                            endforeach;
                        endif;
                        ?>
                    </div>

                    <form method="POST" action="email-templates.php">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Nom du template</label>
                            <input type="text" name="nom" class="form-control" value="<?php echo htmlspecialchars($template['nom']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Sujet de l'email</label>
                            <input type="text" name="sujet" class="form-control" value="<?php echo htmlspecialchars($template['sujet']); ?>" required>
                            <small class="text-muted">Vous pouvez utiliser les variables comme {{nom}}, {{prenom}}, etc.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Corps HTML de l'email</label>
                            <textarea name="corps_html" class="form-control code-editor" required><?php echo htmlspecialchars($template['corps_html']); ?></textarea>
                            <small class="text-muted">Code HTML complet avec les styles inline. Les variables seront remplacées lors de l'envoi.</small>
                        </div>
                        
                        <div class="text-end">
                            <a href="email-templates.php" class="btn btn-secondary">Annuler</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Enregistrer les modifications
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <!-- List Templates -->
            <div class="row">
                <?php foreach ($templates as $tpl): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="template-card">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5><?php echo htmlspecialchars($tpl['nom']); ?></h5>
                            <?php if ($tpl['actif']): ?>
                                <span class="badge bg-success">Actif</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactif</span>
                            <?php endif; ?>
                        </div>
                        
                        <p class="text-muted small mb-2">
                            <strong>ID:</strong> <code><?php echo htmlspecialchars($tpl['identifiant']); ?></code>
                        </p>
                        
                        <?php if ($tpl['description']): ?>
                        <p class="small mb-3"><?php echo htmlspecialchars($tpl['description']); ?></p>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <strong class="small">Sujet:</strong>
                            <p class="small text-muted mb-0"><?php echo htmlspecialchars($tpl['sujet']); ?></p>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="bi bi-clock"></i> <?php echo date('d/m/Y', strtotime($tpl['updated_at'])); ?>
                            </small>
                            <a href="email-templates.php?edit=<?php echo $tpl['id']; ?>" class="btn btn-sm btn-primary">
                                <i class="bi bi-pencil"></i> Modifier
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
