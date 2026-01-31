<?php
/**
 * Initialize Email Templates
 * This script creates default email templates if they don't exist
 * Run this script if email templates are missing from the database
 * 
 * Usage:
 *   php init-email-templates.php          - Create missing templates
 *   php init-email-templates.php --reset  - Reset all templates to defaults
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// Check for reset flag
$reset = in_array('--reset', $argv ?? []);

echo "=== Initialisation des templates d'email ===\n";
if ($reset) {
    echo "MODE: Réinitialisation complète des templates\n";
} else {
    echo "MODE: Création des templates manquants uniquement\n";
}
echo "\n";

// Check if email_templates table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'email_templates'");
    if (!$stmt->fetch()) {
        echo "❌ Table 'email_templates' n'existe pas.\n";
        echo "Veuillez exécuter les migrations d'abord: php run-migrations.php\n";
        exit(1);
    }
    echo "✓ Table 'email_templates' existe\n\n";
} catch (PDOException $e) {
    echo "❌ Erreur lors de la vérification de la table: " . $e->getMessage() . "\n";
    exit(1);
}

// Define default templates
$templates = [
    [
        'identifiant' => 'candidature_recue',
        'nom' => 'Accusé de réception de candidature',
        'sujet' => 'Votre candidature a bien été reçue - MY Invest Immobilier',
        'corps_html' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
        .content { background: #f8f9fa; padding: 30px; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>MY Invest Immobilier</h1>
        </div>
        <div class="content">
            <h2>Bonjour {{prenom}} {{nom}},</h2>
            
            <p>Nous vous confirmons la bonne réception de votre candidature pour le logement <strong>{{logement}}</strong>.</p>
            
            <p><strong>Référence de votre candidature :</strong> {{reference}}</p>
            
            <p>Votre dossier est en cours d\'étude. Nous reviendrons vers vous dans les meilleurs délais.</p>
            
            <p>Nous restons à votre disposition pour toute question.</p>
            
            {{signature}}
        </div>
        <div class="footer">
            <p>Date de soumission : {{date}}</p>
        </div>
    </div>
</body>
</html>',
        'variables_disponibles' => '["nom", "prenom", "email", "logement", "reference", "date"]',
        'description' => 'Email envoyé au candidat dès la soumission de sa candidature'
    ],
    [
        'identifiant' => 'candidature_acceptee',
        'nom' => 'Candidature acceptée',
        'sujet' => 'Suite à votre candidature',
        'corps_html' => '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 28px; }
        .content { padding: 30px; }
        .content p { margin: 15px 0; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #e9ecef; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>MY Invest Immobilier</h1>
        </div>
        <div class="content">
            <p>Bonjour,</p>
            
            <p>Nous vous remercions pour l\'intérêt que vous portez à notre logement et pour votre candidature.</p>
            
            <p>Après une première analyse de votre dossier, nous avons le plaisir de vous informer qu\'il a été retenu pour la suite du processus.<br>
            Nous reviendrons vers vous prochainement afin de convenir ensemble d\'une date de visite.</p>
            
            <p>Nous vous remercions encore pour votre démarche et restons à votre disposition pour toute information complémentaire.</p>
            
            {{signature}}
        </div>
        <div class="footer">
            <p>MY Invest Immobilier - Gestion locative professionnelle</p>
        </div>
    </div>
</body>
</html>',
        'variables_disponibles' => '["nom", "prenom", "email", "logement", "reference", "date", "lien_confirmation"]',
        'description' => 'Email envoyé au candidat si sa candidature est acceptée après le délai'
    ],
    [
        'identifiant' => 'candidature_refusee',
        'nom' => 'Candidature non retenue',
        'sujet' => 'Réponse à votre candidature',
        'corps_html' => '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 28px; }
        .content { padding: 30px; }
        .content p { margin: 15px 0; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #e9ecef; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>MY Invest Immobilier</h1>
        </div>
        <div class="content">
            <p>Bonjour,</p>
            
            <p>Nous vous remercions pour l\'intérêt que vous portez à notre logement et pour le temps consacré à votre candidature.</p>
            
            <p>Après étude de l\'ensemble des dossiers reçus, nous vous informons que nous ne donnerons pas suite à votre demande pour ce logement.</p>
            
            <p>Nous vous remercions pour votre démarche et vous souhaitons pleine réussite dans vos recherches.</p>
            
            {{signature}}
        </div>
        <div class="footer">
            <p>MY Invest Immobilier - Gestion locative professionnelle</p>
        </div>
    </div>
</body>
</html>',
        'variables_disponibles' => '["nom", "prenom", "email"]',
        'description' => 'Email envoyé au candidat si sa candidature est refusée automatiquement'
    ],
    [
        'identifiant' => 'admin_nouvelle_candidature',
        'nom' => 'Notification admin - Nouvelle candidature',
        'sujet' => 'Nouvelle candidature reçue - {{reference}}',
        'corps_html' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
        .content { background: #f8f9fa; padding: 30px; }
        .info-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .info-table td { padding: 8px; border-bottom: 1px solid #ddd; }
        .info-table td:first-child { font-weight: bold; width: 40%; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Nouvelle candidature</h1>
        </div>
        <div class="content">
            <h2>Candidature reçue</h2>
            
            <table class="info-table">
                <tr>
                    <td>Référence</td>
                    <td>{{reference}}</td>
                </tr>
                <tr>
                    <td>Candidat</td>
                    <td>{{prenom}} {{nom}}</td>
                </tr>
                <tr>
                    <td>Email</td>
                    <td>{{email}}</td>
                </tr>
                <tr>
                    <td>Téléphone</td>
                    <td>{{telephone}}</td>
                </tr>
                <tr>
                    <td>Logement</td>
                    <td>{{logement}}</td>
                </tr>
                <tr>
                    <td>Revenus mensuels</td>
                    <td>{{revenus}}</td>
                </tr>
                <tr>
                    <td>Statut professionnel</td>
                    <td>{{statut_pro}}</td>
                </tr>
                <tr>
                    <td>Date de soumission</td>
                    <td>{{date}}</td>
                </tr>
            </table>
            
            <p><a href="{{lien_admin}}">Voir la candidature dans l\'admin</a></p>
            
            {{signature}}
        </div>
        <div class="footer">
            <p>MY Invest Immobilier - Système de gestion des candidatures</p>
        </div>
    </div>
</body>
</html>',
        'variables_disponibles' => '["nom", "prenom", "email", "telephone", "logement", "reference", "date", "revenus", "statut_pro", "lien_admin"]',
        'description' => 'Email envoyé aux administrateurs lors d\'une nouvelle candidature'
    ]
];

$created = 0;
$updated = 0;
$skipped = 0;

foreach ($templates as $template) {
    try {
        // Check if template exists
        $stmt = $pdo->prepare("SELECT id FROM email_templates WHERE identifiant = ?");
        $stmt->execute([$template['identifiant']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            if ($reset) {
                // Update existing template
                $stmt = $pdo->prepare("
                    UPDATE email_templates 
                    SET nom = ?, sujet = ?, corps_html = ?, variables_disponibles = ?, 
                        description = ?, actif = 1, updated_at = NOW()
                    WHERE identifiant = ?
                ");
                $stmt->execute([
                    $template['nom'],
                    $template['sujet'],
                    $template['corps_html'],
                    $template['variables_disponibles'],
                    $template['description'],
                    $template['identifiant']
                ]);
                echo "↻ Template '{$template['identifiant']}' réinitialisé (ID: {$existing['id']})\n";
                $updated++;
            } else {
                echo "⊘ Template '{$template['identifiant']}' existe déjà (ID: {$existing['id']})\n";
                $skipped++;
            }
        } else {
            // Insert new template
            $stmt = $pdo->prepare("
                INSERT INTO email_templates 
                (identifiant, nom, sujet, corps_html, variables_disponibles, description, actif, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
            ");
            $stmt->execute([
                $template['identifiant'],
                $template['nom'],
                $template['sujet'],
                $template['corps_html'],
                $template['variables_disponibles'],
                $template['description']
            ]);
            echo "✓ Template '{$template['identifiant']}' créé avec succès\n";
            $created++;
        }
    } catch (PDOException $e) {
        echo "❌ Erreur lors du traitement du template '{$template['identifiant']}': " . $e->getMessage() . "\n";
    }
}

echo "\n=== Résumé ===\n";
echo "Templates créés: $created\n";
if ($reset) {
    echo "Templates réinitialisés: $updated\n";
}
echo "Templates existants (ignorés): $skipped\n";

if ($created > 0 || $updated > 0) {
    echo "\n✓ Templates d'email initialisés avec succès!\n";
    echo "Vous pouvez maintenant les voir et les modifier dans /admin-v2/email-templates.php\n";
} else {
    echo "\nℹ Tous les templates existent déjà.\n";
    if (!$reset) {
        echo "Pour réinitialiser les templates aux valeurs par défaut, utilisez:\n";
        echo "  php init-email-templates.php --reset\n";
    }
}

echo "\n=== Test des templates ===\n";
// Verify templates are accessible
$stmt = $pdo->query("SELECT identifiant, nom, actif FROM email_templates ORDER BY identifiant");
$allTemplates = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($allTemplates)) {
    echo "❌ Aucun template trouvé dans la base de données!\n";
} else {
    echo "✓ Templates disponibles:\n";
    foreach ($allTemplates as $t) {
        $status = $t['actif'] ? '✓ Actif' : '✗ Inactif';
        echo "  - {$t['identifiant']}: {$t['nom']} ($status)\n";
    }
}

echo "\n=== Fin ===\n";
