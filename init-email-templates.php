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
    ],
    [
        'identifiant' => 'contrat_signature',
        'nom' => 'Invitation à signer le contrat de bail',
        'sujet' => 'Contrat de bail à signer – Action immédiate requise',
        'corps_html' => '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px 20px; }
        .alert-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .info-box { background: #f8f9fa; border-left: 4px solid #667eea; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .btn { display: inline-block; padding: 15px 30px; background: #667eea; color: #ffffff !important; text-decoration: none; border-radius: 4px; margin: 20px 0; font-weight: bold; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📝 Contrat de Bail à Signer</h1>
        </div>
        <div class="content">
            <p>Bonjour,</p>
            
            <p>Merci de prendre connaissance de la procédure ci-dessous.</p>
            
            <div class="alert-box">
                <strong>⏰ Action immédiate requise</strong><br>
                Délai de 24 heures à compter de la réception de ce message
            </div>
            
            <h3>📋 Procédure de signature du bail</h3>
            <p>Merci de compléter l\'ensemble de la procédure dans un délai de 24 heures, incluant :</p>
            <ol>
                <li><strong>La signature du contrat de bail en ligne</strong></li>
                <li><strong>La transmission d\'une pièce d\'identité</strong> en cours de validité (CNI ou passeport)</li>
                <li><strong>Le règlement du dépôt de garantie</strong> (2 mois de loyer) par virement bancaire instantané</li>
            </ol>
            
            <div class="info-box">
                <p style="margin: 0;"><strong>Important :</strong></p>
                <ul style="margin: 10px 0 0 0;">
                    <li>La prise d\'effet du bail et la remise des clés interviendront uniquement après réception complète de l\'ensemble des éléments</li>
                    <li>À défaut de réception complète du dossier dans le délai indiqué, la réservation du logement pourra être remise en disponibilité sans autre formalité</li>
                </ul>
            </div>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{{lien_signature}}" class="btn">🖊️ Accéder au Contrat de Bail</a>
            </div>
            
            <p>Nous restons à votre disposition en cas de question.</p>
            
            {{signature}}
        </div>
        <div class="footer">
            <p>MY Invest Immobilier - Gestion locative professionnelle</p>
        </div>
    </div>
</body>
</html>',
        'variables_disponibles' => '["nom", "prenom", "email", "adresse", "lien_signature"]',
        'description' => 'Email envoyé au locataire pour l\'inviter à signer le contrat de bail en ligne'
    ],
    [
        'identifiant' => 'contrat_finalisation_client',
        'nom' => 'Contrat de bail - Finalisation Client',
        'sujet' => 'Contrat de bail – Finalisation',
        'corps_html' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
        .info-box { background: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .bank-info { background: #fff; border: 2px solid #3498db; padding: 20px; margin: 20px 0; border-radius: 8px; }
        .bank-info h3 { color: #2c3e50; margin-top: 0; }
        .bank-detail { margin: 10px 0; }
        .bank-detail strong { display: inline-block; min-width: 120px; color: #555; }
        .highlight { color: #e74c3c; font-weight: bold; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0;">✅ Contrat de Bail Finalisé</h1>
        </div>
        <div class="content">
            <h2>Bonjour {{prenom}} {{nom}},</h2>
            
            <p>Nous vous remercions pour votre confiance.</p>
            
            <p>Veuillez trouver ci-joint une copie du <strong>contrat de bail dûment complété</strong>.</p>
            
            <div class="info-box">
                <strong>📋 Référence du contrat :</strong> {{reference}}
            </div>
            
            <h3>Informations importantes</h3>
            
            <p>La prise d\'effet du bail intervient après le <span class="highlight">règlement immédiat du dépôt de garantie</span>, correspondant à deux mois de loyer (<strong>{{depot_garantie}}</strong>), par virement bancaire instantané sur le compte suivant :</p>
            
            <div class="bank-info">
                <h3>Coordonnées Bancaires</h3>
                <div class="bank-detail">
                    <strong>Bénéficiaire :</strong> MY Invest Immobilier
                </div>
                <div class="bank-detail">
                    <strong>IBAN :</strong> FR76 1027 8021 6000 0206 1834 585
                </div>
                <div class="bank-detail">
                    <strong>BIC :</strong> CMCIFRA
                </div>
            </div>
            
            <p><strong>Prochaines étapes :</strong></p>
            <ol>
                <li>Effectuer le virement du dépôt de garantie ({{depot_garantie}})</li>
                <li>Attendre la confirmation de réception du règlement</li>
                <li>Recevoir les modalités de remise des clés</li>
            </ol>
            
            <p>Dès réception du règlement, nous vous confirmerons la prise d\'effet du bail ainsi que les modalités de remise des clés.</p>
            
            <p>Nous restons à votre disposition pour toute question.</p>
            
            {{signature}}
        </div>
        <div class="footer">
            <p>MY Invest Immobilier - Gestion locative professionnelle<br>
            © 2026 MY Invest Immobilier - Tous droits réservés</p>
        </div>
    </div>
</body>
</html>',
        'variables_disponibles' => '["nom", "prenom", "reference", "depot_garantie"]',
        'description' => 'Email HTML envoyé au client lors de la finalisation du contrat avec le PDF joint'
    ],
    [
        'identifiant' => 'contrat_finalisation_admin',
        'nom' => 'Notification Admin - Contrat Finalisé',
        'sujet' => '[ADMIN] Contrat signé - {{reference}}',
        'corps_html' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 30px 20px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
        .alert-box { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .info-table { width: 100%; border-collapse: collapse; margin: 20px 0; background: #fff; }
        .info-table td { padding: 12px; border-bottom: 1px solid #ddd; }
        .info-table td:first-child { font-weight: bold; width: 40%; background: #f8f9fa; }
        .button { display: inline-block; padding: 12px 30px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0;">📝 Contrat Signé - Notification Admin</h1>
        </div>
        <div class="content">
            <div class="alert-box">
                <strong>✅ Nouveau contrat signé !</strong> Un contrat de bail a été finalisé et signé par le(s) locataire(s).
            </div>
            
            <h2>Détails du contrat</h2>
            
            <table class="info-table">
                <tr>
                    <td>Référence</td>
                    <td><strong>{{reference}}</strong></td>
                </tr>
                <tr>
                    <td>Logement</td>
                    <td>{{logement}}</td>
                </tr>
                <tr>
                    <td>Locataire(s)</td>
                    <td>{{locataires}}</td>
                </tr>
                <tr>
                    <td>Dépôt de garantie</td>
                    <td>{{depot_garantie}}</td>
                </tr>
                <tr>
                    <td>Date de finalisation</td>
                    <td>{{date_finalisation}}</td>
                </tr>
            </table>
            
            <h3>Actions à effectuer :</h3>
            <ol>
                <li>Vérifier la réception du dépôt de garantie</li>
                <li>Confirmer la prise d\'effet du bail</li>
                <li>Organiser la remise des clés</li>
                <li>Planifier l\'état des lieux d\'entrée</li>
            </ol>
            
            <p style="text-align: center;">
                <a href="{{lien_admin}}" class="button">Voir le Contrat dans l\'Admin</a>
            </p>
            
            <p><strong>Note :</strong> Le contrat PDF signé est joint à cet email.</p>
            
            {{signature}}
        </div>
        <div class="footer">
            <p>MY Invest Immobilier - Système de gestion des contrats<br>
            © 2026 MY Invest Immobilier</p>
        </div>
    </div>
</body>
</html>',
        'variables_disponibles' => '["reference", "logement", "locataires", "depot_garantie", "date_finalisation", "lien_admin"]',
        'description' => 'Email HTML envoyé aux administrateurs quand un contrat est finalisé et signé'
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
