<?php
/**
 * Gestionnaire de réponse aux candidatures via token email
 * Permet aux administrateurs d'accepter ou refuser une candidature depuis un lien email
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mail-templates.php';

// Récupérer les paramètres
$token = $_GET['token'] ?? '';
$action = $_GET['action'] ?? '';

// Valider les paramètres
if (empty($token) || !in_array($action, ['positive', 'negative'])) {
    http_response_code(400);
    die('Paramètres invalides');
}

try {
    // Rechercher la candidature par token
    $stmt = $pdo->prepare("SELECT * FROM candidatures WHERE response_token = ?");
    $stmt->execute([$token]);
    $candidature = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$candidature) {
        http_response_code(404);
        die('Candidature introuvable ou token invalide');
    }
    
    // Vérifier si la candidature a déjà été traitée
    if (in_array($candidature['statut'], ['accepte', 'refuse'])) {
        $message = "Cette candidature a déjà été traitée (statut: " . $candidature['statut'] . ")";
        $alreadyProcessed = true;
    } else {
        $alreadyProcessed = false;
        
        // Déterminer le nouveau statut
        $newStatus = ($action === 'positive') ? 'accepte' : 'refuse';
        
        // Mettre à jour le statut
        $stmt = $pdo->prepare("UPDATE candidatures SET statut = ?, date_reponse_envoyee = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $candidature['id']]);
        
        // Logger l'action
        $actionLog = ($action === 'positive') ? 'Candidature acceptée via email' : 'Candidature refusée via email';
        $logSql = "INSERT INTO logs (candidature_id, action, details, ip_address) VALUES (?, ?, ?, ?)";
        executeQuery($logSql, [
            $candidature['id'], 
            $actionLog, 
            "Réponse via token email - IP: " . getClientIp(),
            getClientIp()
        ]);
        
        // Envoyer un email de notification au candidat
        $nomComplet = $candidature['prenom'] . ' ' . $candidature['nom'];
        $emailSubject = ($action === 'positive') 
            ? 'Candidature acceptée - MY Invest Immobilier'
            : 'Suite à votre candidature - MY Invest Immobilier';
        
        $emailBody = getStatusChangeEmailHTML($nomComplet, ucfirst($newStatus), '');
        
        $emailSent = sendEmail($candidature['email'], $emailSubject, $emailBody, null, true);
        
        if (!$emailSent) {
            error_log("Avertissement: Email de notification non envoyé au candidat " . $candidature['email']);
        }
        
        $message = "La candidature a été " . ($action === 'positive' ? 'acceptée' : 'refusée') . " avec succès.";
    }
    
} catch (Exception $e) {
    error_log("Erreur lors du traitement de la réponse candidature: " . $e->getMessage());
    http_response_code(500);
    die('Erreur lors du traitement de votre demande');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réponse Candidature - MY Invest Immobilier</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            max-width: 600px;
            text-align: center;
        }
        .success-icon {
            font-size: 64px;
            color: #28a745;
            margin-bottom: 20px;
        }
        .warning-icon {
            font-size: 64px;
            color: #ffc107;
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        p {
            color: #666;
            line-height: 1.6;
        }
        .candidature-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 4px;
            margin: 20px 0;
            text-align: left;
        }
        .candidature-info strong {
            color: #333;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($alreadyProcessed): ?>
            <div class="warning-icon">⚠️</div>
            <h1>Candidature déjà traitée</h1>
        <?php else: ?>
            <div class="success-icon">✓</div>
            <h1><?php echo $action === 'positive' ? 'Candidature Acceptée' : 'Candidature Refusée'; ?></h1>
        <?php endif; ?>
        
        <p><?php echo htmlspecialchars($message); ?></p>
        
        <div class="candidature-info">
            <p><strong>Référence :</strong> <?php echo htmlspecialchars($candidature['reference_unique']); ?></p>
            <p><strong>Candidat :</strong> <?php echo htmlspecialchars($candidature['prenom'] . ' ' . $candidature['nom']); ?></p>
            <p><strong>Email :</strong> <?php echo htmlspecialchars($candidature['email']); ?></p>
            <p><strong>Statut actuel :</strong> <?php echo htmlspecialchars($candidature['statut']); ?></p>
        </div>
        
        <?php if (!$alreadyProcessed): ?>
            <p style="color: #28a745; font-weight: bold;">
                Un email de notification a été envoyé au candidat.
            </p>
        <?php endif; ?>
        
        <a href="<?php echo $config['SITE_URL']; ?>/admin-v2/candidature-detail.php?id=<?php echo $candidature['id']; ?>" class="btn">
            Voir la candidature
        </a>
    </div>
</body>
</html>
