<?php
/**
 * Verification script for TCPDF table fixes
 * Checks that all tables have proper cellspacing and cellpadding attributes
 */

echo "=== Verification: TCPDF Table Fixes ===\n\n";

$files = [
    'includes/etat-lieux-template.php' => 'État des lieux template',
    'pdf/generate-etat-lieux.php' => 'État des lieux PDF generator'
];

$allGood = true;

foreach ($files as $file => $description) {
    echo "Checking $description ($file)...\n";
    
    if (!file_exists($file)) {
        echo "  ❌ File not found\n";
        $allGood = false;
        continue;
    }
    
    $content = file_get_contents($file);
    
    // Find all <table tags
    preg_match_all('/<table[^>]*>/', $content, $matches);
    
    if (empty($matches[0])) {
        echo "  ✓ No tables found (nothing to check)\n";
        continue;
    }
    
    $totalTables = count($matches[0]);
    $tablesWithCellspacing = 0;
    $tablesWithCellpadding = 0;
    $issuesFound = [];
    
    foreach ($matches[0] as $tableTag) {
        $hasCellspacing = preg_match('/cellspacing\s*=\s*["\']?\d+["\']?/', $tableTag);
        $hasCellpadding = preg_match('/cellpadding\s*=\s*["\']?\d+["\']?/', $tableTag);
        
        if ($hasCellspacing) $tablesWithCellspacing++;
        if ($hasCellpadding) $tablesWithCellpadding++;
        
        if (!$hasCellspacing || !$hasCellpadding) {
            $issuesFound[] = $tableTag;
        }
    }
    
    echo "  Total tables: $totalTables\n";
    echo "  Tables with cellspacing: $tablesWithCellspacing\n";
    echo "  Tables with cellpadding: $tablesWithCellpadding\n";
    
    if ($tablesWithCellspacing === $totalTables && $tablesWithCellpadding === $totalTables) {
        echo "  ✓ All tables have proper TCPDF attributes\n";
    } else {
        echo "  ❌ Some tables missing cellspacing/cellpadding attributes:\n";
        foreach ($issuesFound as $issue) {
            echo "     - " . substr($issue, 0, 80) . "...\n";
        }
        $allGood = false;
    }
    
    echo "\n";
}

// Check createDefaultEtatLieux function for required fields
echo "Checking createDefaultEtatLieux for required fields...\n";
$content = file_get_contents('pdf/generate-etat-lieux.php');

$requiredFields = [
    'compteur_electricite',
    'compteur_eau_froide',
    'cles_appartement',
    'cles_boite_lettres',
    'cles_autre',
    'cles_total'
];

foreach ($requiredFields as $field) {
    $escapedField = preg_quote($field, '/');
    // Check if field is in INSERT statement (between INSERT INTO and VALUES)
    if (preg_match('/INSERT\s+INTO\s+etats_lieux\s*\([^)]*\b' . $escapedField . '\b[^)]*\)/is', $content)) {
        echo "  ✓ Field '$field' included in INSERT\n";
    } else {
        echo "  ❌ Field '$field' missing from INSERT\n";
        $allGood = false;
    }
}

echo "\n=== Verification Result ===\n";
if ($allGood) {
    echo "✅ All checks passed! TCPDF table fixes are complete.\n";
    exit(0);
} else {
    echo "❌ Some issues found. Please review the output above.\n";
    exit(1);
}
