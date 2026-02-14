<?php
/**
 * Verify Inventaire Email Templates
 * 
 * This script checks if the required email templates for inventaire exist
 * and provides instructions if they're missing.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║              VERIFY INVENTAIRE EMAIL TEMPLATES                               ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════════╝\n\n";

try {
    // Check if email_templates table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'email_templates'");
    if (!$stmt->fetch()) {
        echo "❌ ERROR: Table 'email_templates' does not exist!\n\n";
        echo "ACTION REQUIRED:\n";
        echo "  Run database migrations:\n";
        echo "  $ php run-migrations.php\n\n";
        exit(1);
    }
    echo "✓ Table 'email_templates' exists\n\n";
    
    // Check for required inventaire templates
    $requiredTemplates = [
        'inventaire_entree_envoye' => 'Entry Inventory Email Template',
        'inventaire_sortie_envoye' => 'Exit Inventory Email Template'
    ];
    
    $missingTemplates = [];
    
    echo "Checking required templates:\n";
    echo str_repeat("-", 80) . "\n";
    
    foreach ($requiredTemplates as $templateId => $description) {
        $stmt = $pdo->prepare("SELECT identifiant, nom, actif FROM email_templates WHERE identifiant = ?");
        $stmt->execute([$templateId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($template) {
            $status = $template['actif'] ? '✓ Active' : '⚠ Inactive';
            echo sprintf("  %-30s : %s - %s\n", $templateId, $status, $template['nom']);
        } else {
            echo sprintf("  %-30s : ❌ NOT FOUND\n", $templateId);
            $missingTemplates[] = $templateId;
        }
    }
    
    echo str_repeat("-", 80) . "\n\n";
    
    // List all inventaire-related templates
    $stmt = $pdo->query("SELECT identifiant, nom, actif FROM email_templates WHERE identifiant LIKE 'inventaire%' ORDER BY identifiant");
    $allTemplates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($allTemplates)) {
        echo "All inventaire-related templates in database:\n";
        echo str_repeat("-", 80) . "\n";
        foreach ($allTemplates as $t) {
            $status = $t['actif'] ? 'Active' : 'Inactive';
            echo sprintf("  %-30s : %-8s - %s\n", $t['identifiant'], $status, $t['nom']);
        }
        echo str_repeat("-", 80) . "\n\n";
    }
    
    // Final status
    if (empty($missingTemplates)) {
        echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
        echo "║  ✓ SUCCESS: All required email templates are present!                       ║\n";
        echo "╚══════════════════════════════════════════════════════════════════════════════╝\n\n";
        echo "The inventory finalization email feature should work correctly.\n\n";
        exit(0);
    } else {
        echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
        echo "║  ❌ ERROR: Missing required email templates                                  ║\n";
        echo "╚══════════════════════════════════════════════════════════════════════════════╝\n\n";
        echo "Missing templates:\n";
        foreach ($missingTemplates as $templateId) {
            echo "  - $templateId\n";
        }
        echo "\nACTION REQUIRED:\n";
        echo "  Run the email template migration:\n";
        echo "  $ mysql -u [username] -p [database] < migrations/035_add_inventaire_email_templates.sql\n\n";
        echo "  Or run all migrations:\n";
        echo "  $ php run-migrations.php\n\n";
        exit(1);
    }
    
} catch (PDOException $e) {
    echo "❌ DATABASE ERROR: " . $e->getMessage() . "\n\n";
    echo "Please check your database connection in includes/config.local.php\n\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n\n";
    exit(1);
}
