<?php
/**
 * Diagnostic Script for Email Template System
 * This script checks the status of the email template system
 * and provides recommendations for fixing any issues
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

echo "=== Diagnostic du Système d'Email ===\n\n";

$issues = [];
$warnings = [];
$success = [];

// 1. Check database connection
try {
    $pdo->query("SELECT 1");
    $success[] = "Connexion à la base de données OK";
} catch (PDOException $e) {
    $issues[] = "Impossible de se connecter à la base de données: " . $e->getMessage();
    echo "❌ ERREUR CRITIQUE: Connexion base de données échouée\n\n";
    echo "Veuillez vérifier votre configuration dans includes/config.php ou includes/config.local.php\n";
    exit(1);
}

// 2. Check if email_templates table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'email_templates'");
    if ($stmt->fetch()) {
        $success[] = "Table 'email_templates' existe";
    } else {
        $issues[] = "Table 'email_templates' n'existe pas";
    }
} catch (PDOException $e) {
    $issues[] = "Erreur lors de la vérification de la table: " . $e->getMessage();
}

// 3. Check if templates exist
$requiredTemplates = [
    'candidature_recue',
    'candidature_acceptee',
    'candidature_refusee',
    'admin_nouvelle_candidature',
    'contrat_signature'
];

$missingTemplates = [];
$inactiveTemplates = [];
$foundTemplates = [];

foreach ($requiredTemplates as $templateId) {
    try {
        $stmt = $pdo->prepare("SELECT id, actif FROM email_templates WHERE identifiant = ?");
        $stmt->execute([$templateId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$template) {
            $missingTemplates[] = $templateId;
        } else {
            $foundTemplates[] = $templateId;
            if (!$template['actif']) {
                $inactiveTemplates[] = $templateId;
            }
        }
    } catch (PDOException $e) {
        $issues[] = "Erreur lors de la vérification du template '$templateId': " . $e->getMessage();
    }
}

if (empty($missingTemplates)) {
    $success[] = "Tous les templates requis sont présents (" . count($foundTemplates) . "/5)";
} else {
    $issues[] = "Templates manquants: " . implode(', ', $missingTemplates);
}

if (!empty($inactiveTemplates)) {
    $warnings[] = "Templates inactifs: " . implode(', ', $inactiveTemplates);
}

// 4. Check email signature parameter
try {
    $signature = getParameter('email_signature', '');
    if (!empty($signature)) {
        $success[] = "Paramètre 'email_signature' est défini";
        
        // Check if signature contains expected elements
        if (strpos($signature, 'MY INVEST') !== false || strpos($signature, 'logo') !== false) {
            $success[] = "Signature contient les éléments attendus";
        } else {
            $warnings[] = "La signature ne contient pas les éléments attendus (logo, MY INVEST)";
        }
    } else {
        $warnings[] = "Paramètre 'email_signature' est vide";
    }
} catch (Exception $e) {
    $warnings[] = "Impossible de récupérer le paramètre 'email_signature': " . $e->getMessage();
}

// 5. Check SMTP configuration
$smtpConfigured = true;
if (empty($config['SMTP_HOST'])) {
    $issues[] = "SMTP_HOST n'est pas configuré";
    $smtpConfigured = false;
}
if (empty($config['SMTP_USERNAME'])) {
    $warnings[] = "SMTP_USERNAME n'est pas configuré";
}
if (empty($config['SMTP_PASSWORD'])) {
    $issues[] = "SMTP_PASSWORD n'est pas configuré (obligatoire pour l'envoi d'emails)";
    $smtpConfigured = false;
}

if ($smtpConfigured && !empty($config['SMTP_USERNAME']) && !empty($config['SMTP_PASSWORD'])) {
    $success[] = "Configuration SMTP complète";
}

// 6. Check parametres table
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM parametres");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result['count'] > 0) {
        $success[] = "Table 'parametres' contient {$result['count']} paramètre(s)";
    } else {
        $warnings[] = "Table 'parametres' est vide";
    }
} catch (PDOException $e) {
    $warnings[] = "Erreur lors de la vérification de la table 'parametres': " . $e->getMessage();
}

// Display results
echo "=== Résultats du Diagnostic ===\n\n";

if (!empty($success)) {
    echo "✓ SUCCÈS:\n";
    foreach ($success as $msg) {
        echo "  ✓ $msg\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "⚠ AVERTISSEMENTS:\n";
    foreach ($warnings as $msg) {
        echo "  ⚠ $msg\n";
    }
    echo "\n";
}

if (!empty($issues)) {
    echo "❌ PROBLÈMES:\n";
    foreach ($issues as $msg) {
        echo "  ❌ $msg\n";
    }
    echo "\n";
}

// Recommendations
echo "=== Recommandations ===\n\n";

if (!empty($missingTemplates)) {
    echo "🔧 Templates manquants détectés!\n";
    echo "   Pour les créer, exécutez:\n";
    echo "   php init-email-templates.php\n\n";
}

if (!empty($inactiveTemplates)) {
    echo "🔧 Templates inactifs détectés!\n";
    echo "   Activez-les dans /admin-v2/email-templates.php\n\n";
}

if (empty($config['SMTP_PASSWORD'])) {
    echo "🔧 SMTP_PASSWORD manquant!\n";
    echo "   Configurez-le dans includes/config.local.php\n";
    echo "   Voir le fichier includes/config.local.php.template pour un exemple\n\n";
}

if (empty($missingTemplates) && empty($issues)) {
    echo "✓ Aucun problème majeur détecté!\n";
    echo "  Le système d'email devrait fonctionner correctement.\n\n";
    
    if (!empty($warnings)) {
        echo "  Note: Il y a quelques avertissements ci-dessus,\n";
        echo "  mais ils ne devraient pas empêcher l'envoi d'emails.\n\n";
    }
} else {
    echo "⚠ Des problèmes ont été détectés.\n";
    echo "  Veuillez suivre les recommandations ci-dessus pour les corriger.\n\n";
}

echo "=== Détails des Templates ===\n\n";
try {
    $stmt = $pdo->query("SELECT identifiant, nom, actif, updated_at FROM email_templates ORDER BY identifiant");
    $allTemplates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($allTemplates)) {
        echo "❌ Aucun template trouvé dans la base de données\n";
    } else {
        echo "Templates disponibles:\n";
        foreach ($allTemplates as $t) {
            $status = $t['actif'] ? '✓ Actif' : '✗ Inactif';
            $lastUpdate = date('d/m/Y H:i', strtotime($t['updated_at']));
            echo "  • {$t['identifiant']}\n";
            echo "    Nom: {$t['nom']}\n";
            echo "    Statut: $status\n";
            echo "    Dernière mise à jour: $lastUpdate\n\n";
        }
    }
} catch (PDOException $e) {
    echo "❌ Erreur lors de la récupération des templates: " . $e->getMessage() . "\n";
}

echo "=== Fin du Diagnostic ===\n";
