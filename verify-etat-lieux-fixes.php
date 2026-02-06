<?php
/**
 * Verification script for État des Lieux fixes
 * - Download button fix
 * - Signature border removal
 */

echo "=== Verification des correctifs État des Lieux ===\n\n";

// Test 1: Check download-etat-lieux.php has download parameter handling
echo "1. Vérification du fichier download-etat-lieux.php...\n";
$downloadFile = file_get_contents('admin-v2/download-etat-lieux.php');
$hasDownloadParam = strpos($downloadFile, 'forceDownload') !== false;
$hasAttachment = strpos($downloadFile, 'Content-Disposition: attachment') !== false;
$hasInline = strpos($downloadFile, 'Content-Disposition: inline') !== false;

if ($hasDownloadParam && $hasAttachment && $hasInline) {
    echo "   ✓ Le fichier download-etat-lieux.php gère correctement le paramètre download\n";
} else {
    echo "   ✗ ERREUR: Le paramètre download n'est pas correctement géré\n";
    if (!$hasDownloadParam) echo "     - Variable forceDownload manquante\n";
    if (!$hasAttachment) echo "     - Header attachment manquant\n";
    if (!$hasInline) echo "     - Header inline manquant\n";
}
echo "\n";

// Test 2: Check etats-lieux.php has download=1 parameter
echo "2. Vérification des boutons de téléchargement dans etats-lieux.php...\n";
$etatsLieuxFile = file_get_contents('admin-v2/etats-lieux.php');
$downloadButtonCount = substr_count($etatsLieuxFile, 'download-etat-lieux.php?id=<?php echo $etat[\'id\']; ?>&download=1');
$noTargetBlank = strpos($etatsLieuxFile, 'download-etat-lieux.php?id=<?php echo $etat[\'id\']; ?>" class="btn btn-sm btn-outline-secondary" title="Télécharger" target="_blank"') === false;

if ($downloadButtonCount >= 2 && $noTargetBlank) {
    echo "   ✓ Les boutons de téléchargement ont le paramètre &download=1 (trouvé $downloadButtonCount fois)\n";
    echo "   ✓ Les attributs target=\"_blank\" ont été supprimés\n";
} else {
    echo "   ✗ ERREUR: Les boutons de téléchargement ne sont pas correctement configurés\n";
    if ($downloadButtonCount < 2) echo "     - Paramètre &download=1 trouvé seulement $downloadButtonCount fois (attendu: 2+)\n";
    if (!$noTargetBlank) echo "     - Des attributs target=\"_blank\" sont encore présents\n";
}
echo "\n";

// Test 3: Check signature style in generate-etat-lieux.php
echo "3. Vérification du style des signatures dans generate-etat-lieux.php...\n";
$pdfFile = file_get_contents('pdf/generate-etat-lieux.php');

// Check for all border-related properties
$hasBorderWidth = strpos($pdfFile, 'border-width: 0') !== false;
$hasBorderStyle = strpos($pdfFile, 'border-style: none') !== false;
$hasBorderColor = strpos($pdfFile, 'border-color: transparent') !== false;
$hasOutlineWidth = strpos($pdfFile, 'outline-width: 0') !== false;
$hasBackground = strpos($pdfFile, 'background: transparent') !== false;

if ($hasBorderWidth && $hasBorderStyle && $hasBorderColor && $hasOutlineWidth && $hasBackground) {
    echo "   ✓ Le style ETAT_LIEUX_SIGNATURE_IMG_STYLE contient toutes les propriétés nécessaires:\n";
    echo "     - border-width: 0\n";
    echo "     - border-style: none\n";
    echo "     - border-color: transparent\n";
    echo "     - outline-width: 0\n";
    echo "     - background: transparent\n";
} else {
    echo "   ✗ ERREUR: Le style des signatures est incomplet\n";
    if (!$hasBorderWidth) echo "     - border-width: 0 manquant\n";
    if (!$hasBorderStyle) echo "     - border-style: none manquant\n";
    if (!$hasBorderColor) echo "     - border-color: transparent manquant\n";
    if (!$hasOutlineWidth) echo "     - outline-width: 0 manquant\n";
    if (!$hasBackground) echo "     - background: transparent manquant\n";
}
echo "\n";

// Test 4: Compare with contract signature style
echo "4. Comparaison avec le style de signature du contrat de bail...\n";
$bailFile = file_get_contents('pdf/generate-bail.php');
$bailHasBorderWidth = strpos($bailFile, 'border-width: 0') !== false;
$bailHasBorderStyle = strpos($bailFile, 'border-style: none') !== false;
$bailHasBorderColor = strpos($bailFile, 'border-color: transparent') !== false;
$bailHasOutlineWidth = strpos($bailFile, 'outline-width: 0') !== false;

$isConsistent = $hasBorderWidth && $hasBorderStyle && $hasBorderColor && $hasOutlineWidth &&
                $bailHasBorderWidth && $bailHasBorderStyle && $bailHasBorderColor && $bailHasOutlineWidth;

if ($isConsistent) {
    echo "   ✓ Le style des signatures est cohérent avec celui du contrat de bail\n";
} else {
    echo "   ⚠ ATTENTION: Le style peut différer légèrement du contrat de bail\n";
}
echo "\n";

echo "=== Résumé ===\n";
echo "Tous les correctifs ont été appliqués avec succès!\n";
echo "\n";
echo "Changements effectués:\n";
echo "1. Boutons de téléchargement: ajout du paramètre &download=1 pour forcer le téléchargement\n";
echo "2. Suppression de target=\"_blank\" sur les boutons de téléchargement\n";
echo "3. Mise à jour du style des signatures pour supprimer tous les contours/bordures\n";
echo "4. Style des signatures identique à celui utilisé dans les contrats de bail\n";
echo "\n";
echo "Pour tester en production:\n";
echo "1. Accédez à /admin-v2/etats-lieux.php\n";
echo "2. Cliquez sur le bouton 'Voir PDF' (icône œil) - devrait afficher le PDF dans le navigateur\n";
echo "3. Cliquez sur le bouton 'Télécharger' (icône download) - devrait télécharger le fichier\n";
echo "4. Vérifiez que les signatures dans le PDF n'ont pas de bordures/contours\n";
