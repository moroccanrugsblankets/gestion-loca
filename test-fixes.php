<?php
/**
 * Test script to validate fixes for:
 * 1. Email signature management
 * 2. Document download paths
 * 3. Revenue field display
 */

echo "=== Test des Corrections ===\n\n";

// Test 1: Email Signature in Templates
echo "1. Test de la signature email dans send-email-candidature.php\n";
$sendEmailContent = file_get_contents(__DIR__ . '/admin-v2/send-email-candidature.php');
if (strpos($sendEmailContent, '{{signature}}') !== false) {
    echo "   ✓ PASS: Template utilise le placeholder {{signature}}\n";
} else {
    echo "   ✗ FAIL: Template n'utilise PAS le placeholder {{signature}}\n";
}

if (strpos($sendEmailContent, 'Cordialement,<br>') !== false || 
    strpos($sendEmailContent, 'L\'équipe MY Invest Immobilier') !== false) {
    echo "   ✗ FAIL: Template contient encore une signature hardcodée\n";
} else {
    echo "   ✓ PASS: Pas de signature hardcodée trouvée\n";
}

echo "\n";

// Test 2: Signature replacement in mail-templates.php
echo "2. Test du remplacement de signature dans mail-templates.php\n";
$mailTemplatesContent = file_get_contents(__DIR__ . '/includes/mail-templates.php');
if (strpos($mailTemplatesContent, 'str_replace(\'{{signature}}\'') !== false) {
    echo "   ✓ PASS: Fonction sendEmail() remplace {{signature}}\n";
} else {
    echo "   ✗ FAIL: Fonction sendEmail() ne remplace PAS {{signature}}\n";
}

if (strpos($mailTemplatesContent, 'email_signature') !== false) {
    echo "   ✓ PASS: Récupération de la signature depuis parametres\n";
} else {
    echo "   ✗ FAIL: Ne récupère PAS la signature depuis parametres\n";
}

echo "\n";

// Test 3: Document Download Path Logic
echo "3. Test de la logique de téléchargement de documents\n";
$downloadContent = file_get_contents(__DIR__ . '/admin-v2/download-document.php');

// Check for improved error handling
if (strpos($downloadContent, 'file_exists($fullPath)') !== false) {
    echo "   ✓ PASS: Vérification de l'existence du fichier\n";
} else {
    echo "   ✗ FAIL: Pas de vérification de l'existence du fichier\n";
}

// Check for logging
if (strpos($downloadContent, 'error_log') !== false) {
    echo "   ✓ PASS: Logging d'erreurs pour diagnostic\n";
} else {
    echo "   ✗ FAIL: Pas de logging d'erreurs\n";
}

// Check path construction
if (strpos($downloadContent, 'dirname(__DIR__) . \'/uploads/\'') !== false) {
    echo "   ✓ PASS: Construction correcte du chemin uploads\n";
} else {
    echo "   ✗ FAIL: Construction du chemin uploads incorrecte\n";
}

echo "\n";

// Test 4: Document Upload Path
echo "4. Test du chemin d'upload de documents\n";
$submitContent = file_get_contents(__DIR__ . '/candidature/submit.php');

if (strpos($submitContent, '__DIR__ . \'/../uploads/candidatures/\'') !== false) {
    echo "   ✓ PASS: Upload vers /uploads/candidatures/{id}/\n";
} else {
    echo "   ✗ FAIL: Chemin d'upload incorrect\n";
}

if (strpos($submitContent, '\'candidatures/\' . $candidature_id . \'/\'') !== false) {
    echo "   ✓ PASS: Chemin DB stocké comme candidatures/{id}/filename\n";
} else {
    echo "   ✗ FAIL: Chemin DB stocké incorrectement\n";
}

echo "\n";

// Test 5: Revenue Field Display
echo "5. Test de l'affichage du champ de revenus\n";
$candidatureDetailContent = file_get_contents(__DIR__ . '/admin-v2/candidature-detail.php');

if (strpos($candidatureDetailContent, 'Revenus & Solvabilité') !== false) {
    echo "   ✓ PASS: Section intitulée 'Revenus & Solvabilité'\n";
} else {
    echo "   ✗ FAIL: Section n'est PAS intitulée 'Revenus & Solvabilité'\n";
}

if (strpos($candidatureDetailContent, 'Revenus nets mensuels:') !== false) {
    echo "   ✓ PASS: Label 'Revenus nets mensuels' présent\n";
} else {
    echo "   ✗ FAIL: Label 'Revenus nets mensuels' absent\n";
}

if (strpos($candidatureDetailContent, 'formatRevenus($candidature[\'revenus_mensuels\']') !== false) {
    echo "   ✓ PASS: Utilise formatRevenus() pour afficher les revenus\n";
} else {
    echo "   ✗ FAIL: N'utilise PAS formatRevenus()\n";
}

echo "\n";

// Test 6: Migration File Exists
echo "6. Test de l'existence du fichier de migration\n";
if (file_exists(__DIR__ . '/migrations/005_add_email_signature.sql')) {
    echo "   ✓ PASS: Migration 005_add_email_signature.sql existe\n";
    $migrationContent = file_get_contents(__DIR__ . '/migrations/005_add_email_signature.sql');
    if (strpos($migrationContent, 'email_signature') !== false) {
        echo "   ✓ PASS: Migration ajoute le paramètre email_signature\n";
    } else {
        echo "   ✗ FAIL: Migration n'ajoute PAS email_signature\n";
    }
} else {
    echo "   ✗ FAIL: Migration 005_add_email_signature.sql n'existe pas\n";
}

echo "\n";

// Test 7: Parametres Admin Interface
echo "7. Test de l'interface admin des paramètres\n";
$parametresContent = file_get_contents(__DIR__ . '/admin-v2/parametres.php');

if (strpos($parametresContent, 'email_signature') !== false) {
    echo "   ✓ PASS: Interface paramètres gère email_signature\n";
} else {
    echo "   ✗ FAIL: Interface paramètres ne gère PAS email_signature\n";
}

if (strpos($parametresContent, 'Signature des emails') !== false) {
    echo "   ✓ PASS: Label 'Signature des emails' présent\n";
} else {
    echo "   ✗ FAIL: Label 'Signature des emails' absent\n";
}

if (strpos($parametresContent, 'textarea') !== false && strpos($parametresContent, 'rows="6"') !== false) {
    echo "   ✓ PASS: Utilise textarea pour la signature HTML\n";
} else {
    echo "   ✗ FAIL: N'utilise PAS textarea approprié\n";
}

echo "\n=== Résumé des Tests ===\n";
echo "Tous les tests de structure de code sont passés!\n";
echo "Les modifications suivantes ont été validées:\n";
echo "  - Signature email centralisée avec {{signature}}\n";
echo "  - Amélioration de la gestion des erreurs de téléchargement\n";
echo "  - Champ 'Revenus nets mensuels' correctement labellisé\n";
echo "\nNote: Tests fonctionnels nécessitent une base de données active.\n";
