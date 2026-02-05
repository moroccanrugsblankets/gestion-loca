<?php
/**
 * Validation script for État des lieux changes
 * Verifies the changes are correctly implemented
 */

echo "=== Validation des modifications État des lieux ===\n\n";

$allValid = true;

// 1. Check create-etat-lieux.php
echo "1. Vérification de create-etat-lieux.php:\n";
$createContent = file_get_contents(__DIR__ . '/admin-v2/create-etat-lieux.php');

if (strpos($createContent, 'FROM locataires WHERE contrat_id = ?') !== false) {
    echo "   ✓ Utilise la table locataires (correct)\n";
} else {
    echo "   ✗ N'utilise pas la table locataires\n";
    $allValid = false;
}

if (strpos($createContent, 'candidatures cand') === false || strpos($createContent, 'cand.email as locataire_email') === false) {
    echo "   ✓ Ne dépend plus de la table candidatures\n";
} else {
    echo "   ✗ Dépend encore de candidatures\n";
    $allValid = false;
}

// 2. Check edit-etat-lieux.php
echo "\n2. Vérification de edit-etat-lieux.php:\n";
$editContent = file_get_contents(__DIR__ . '/admin-v2/edit-etat-lieux.php');

if (strpos($editContent, 'readonly') !== false && strpos($editContent, 'locataire_nom_complet') !== false) {
    echo "   ✓ Champ locataire en lecture seule\n";
} else {
    echo "   ✗ Champ locataire pas en lecture seule\n";
    $allValid = false;
}

if (strpos($editContent, 'width="200" height="80"') !== false) {
    echo "   ✓ Taille signature réduite (200x80)\n";
} else {
    echo "   ✗ Taille signature non réduite\n";
    $allValid = false;
}

if (strpos($editContent, 'foreach ($existing_tenants as') !== false) {
    echo "   ✓ Boucle dynamique pour les locataires\n";
} else {
    echo "   ✗ Pas de boucle dynamique pour les locataires\n";
    $allValid = false;
}

if (strpos($editContent, 'signature_bailleur') === false && strpos($editContent, 'initSignatureBailleur') === false) {
    echo "   ✓ Champ signature bailleur manuel supprimé\n";
} else {
    echo "   ✗ Champ signature bailleur manuel encore présent\n";
    $allValid = false;
}

// 3. Check PDF generation
echo "\n3. Vérification de generate-etat-lieux.php:\n";
$pdfContent = file_get_contents(__DIR__ . '/pdf/generate-etat-lieux.php');

if (strpos($pdfContent, "SELECT valeur FROM parametres WHERE cle = 'signature_societe_image'") !== false) {
    echo "   ✓ Utilise la signature de l'entreprise depuis parametres\n";
} else {
    echo "   ✗ N'utilise pas la signature depuis parametres\n";
    $allValid = false;
}

if (strpos($pdfContent, 'FROM etat_lieux_locataires') !== false) {
    echo "   ✓ Récupère les locataires depuis etat_lieux_locataires\n";
} else {
    echo "   ✗ Ne récupère pas depuis etat_lieux_locataires\n";
    $allValid = false;
}

if (strpos($pdfContent, 'max-width:120px; max-height:50px') !== false) {
    echo "   ✓ Taille signature PDF réduite (120x50)\n";
} else {
    echo "   ✗ Taille signature PDF non réduite\n";
    $allValid = false;
}

if (strpos($pdfContent, 'nl2br') !== false && strpos($pdfContent, "observations") !== false) {
    echo "   ✓ Observations avec retours à la ligne (nl2br)\n";
} else {
    echo "   ✗ Observations sans nl2br\n";
    $allValid = false;
}

// 4. Check syntax
echo "\n4. Vérification syntaxe PHP:\n";
$files = [
    'admin-v2/create-etat-lieux.php',
    'admin-v2/edit-etat-lieux.php',
    'pdf/generate-etat-lieux.php'
];

foreach ($files as $file) {
    exec("php -l " . __DIR__ . "/$file 2>&1", $output, $code);
    if ($code === 0) {
        echo "   ✓ $file: syntaxe valide\n";
    } else {
        echo "   ✗ $file: erreur de syntaxe\n";
        $allValid = false;
    }
}

// Summary
echo "\n" . str_repeat("=", 60) . "\n";
if ($allValid) {
    echo "✅ Toutes les vérifications sont passées!\n";
    echo "\nChangements implémentés:\n";
    echo "• Auto-population des données locataires depuis le contrat\n";
    echo "• Champs locataire en lecture seule\n";
    echo "• Signature bailleur automatique depuis parametres\n";
    echo "• Signatures locataires dynamiques (1-2 maximum)\n";
    echo "• Taille des signatures réduite\n";
    echo "• Observations avec retours à la ligne\n";
    exit(0);
} else {
    echo "❌ Certaines vérifications ont échoué\n";
    exit(1);
}
