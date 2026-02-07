<?php
/**
 * Comprehensive verification script for TCPDF table fixes
 * This script checks all PHP files for tables without cellspacing/cellpadding attributes
 * 
 * Usage: php verify-tcpdf-table-fixes.php
 */

echo "=== Comprehensive TCPDF Table Fixes Verification ===\n\n";

$errors = [];
$warnings = [];
$checks = 0;

// Check état des lieux template
echo "1. Checking État des lieux template (includes/etat-lieux-template.php)...\n";
if (!file_exists(__DIR__ . '/includes/etat-lieux-template.php')) {
    $errors[] = "File not found: includes/etat-lieux-template.php";
} else {
    $content = file_get_contents(__DIR__ . '/includes/etat-lieux-template.php');
    preg_match_all('/<table[^>]*>/i', $content, $matches);
    $totalTables = count($matches[0]);
    $tablesWithCellspacing = 0;
    $tablesWithCellpadding = 0;
    
    foreach ($matches[0] as $table) {
        $checks++;
        if (stripos($table, 'cellspacing') !== false) {
            $tablesWithCellspacing++;
        }
        if (stripos($table, 'cellpadding') !== false) {
            $tablesWithCellpadding++;
        }
    }
    
    echo "   Total tables: $totalTables\n";
    echo "   Tables with cellspacing: $tablesWithCellspacing\n";
    echo "   Tables with cellpadding: $tablesWithCellpadding\n";
    
    if ($totalTables === $tablesWithCellspacing && $totalTables === $tablesWithCellpadding) {
        echo "   ✓ All tables have proper TCPDF attributes\n";
    } else {
        $errors[] = "État des lieux template has tables without cellspacing/cellpadding";
    }
}

// Check état des lieux PDF generator
echo "\n2. Checking État des lieux PDF generator (pdf/generate-etat-lieux.php)...\n";
if (!file_exists(__DIR__ . '/pdf/generate-etat-lieux.php')) {
    $errors[] = "File not found: pdf/generate-etat-lieux.php";
} else {
    $content = file_get_contents(__DIR__ . '/pdf/generate-etat-lieux.php');
    
    // Check buildSignaturesTableEtatLieux function
    if (preg_match('/function buildSignaturesTableEtatLieux.*?\{(.*?)(?=\nfunction|\nclass|\z)/s', $content, $funcMatch)) {
        $funcContent = $funcMatch[1];
        preg_match_all('/<table[^>]*>/i', $funcContent, $matches);
        $totalTables = count($matches[0]);
        $tablesWithCellspacing = 0;
        $tablesWithCellpadding = 0;
        
        foreach ($matches[0] as $table) {
            $checks++;
            if (stripos($table, 'cellspacing') !== false) {
                $tablesWithCellspacing++;
            }
            if (stripos($table, 'cellpadding') !== false) {
                $tablesWithCellpadding++;
            }
        }
        
        echo "   buildSignaturesTableEtatLieux tables: $totalTables\n";
        echo "   Tables with cellspacing: $tablesWithCellspacing\n";
        echo "   Tables with cellpadding: $tablesWithCellpadding\n";
        
        if ($totalTables === $tablesWithCellspacing && $totalTables === $tablesWithCellpadding) {
            echo "   ✓ All signature tables have proper TCPDF attributes\n";
        } else {
            $errors[] = "buildSignaturesTableEtatLieux has tables without cellspacing/cellpadding";
        }
    }
    
    // Check createDefaultEtatLieux for required fields
    echo "\n   Checking createDefaultEtatLieux for required fields...\n";
    $requiredFields = [
        'compteur_electricite',
        'compteur_eau_froide',
        'cles_appartement',
        'cles_boite_lettres',
        'cles_autre',
        'cles_total'
    ];
    
    foreach ($requiredFields as $field) {
        $checks++;
        if (preg_match('/INSERT INTO etats_lieux[^;]*\b' . $field . '\b/is', $content)) {
            echo "   ✓ Field '$field' included in INSERT\n";
        } else {
            $errors[] = "Field '$field' missing from createDefaultEtatLieux INSERT statement";
        }
    }
}

// Check contrat PDF generator
echo "\n3. Checking Contrat PDF generator (pdf/generate-contrat-pdf.php)...\n";
if (!file_exists(__DIR__ . '/pdf/generate-contrat-pdf.php')) {
    $warnings[] = "File not found: pdf/generate-contrat-pdf.php (optional check)";
} else {
    $content = file_get_contents(__DIR__ . '/pdf/generate-contrat-pdf.php');
    
    // Check buildSignaturesTable function
    if (preg_match('/function buildSignaturesTable.*?\{(.*?)(?=\nfunction|\nclass|\z)/s', $content, $funcMatch)) {
        $funcContent = $funcMatch[1];
        preg_match_all('/<table[^>]*>/i', $funcContent, $matches);
        $totalTables = count($matches[0]);
        $tablesWithCellspacing = 0;
        $tablesWithCellpadding = 0;
        
        foreach ($matches[0] as $table) {
            $checks++;
            if (stripos($table, 'cellspacing') !== false) {
                $tablesWithCellspacing++;
            }
            if (stripos($table, 'cellpadding') !== false) {
                $tablesWithCellpadding++;
            }
        }
        
        echo "   buildSignaturesTable tables: $totalTables\n";
        echo "   Tables with cellspacing: $tablesWithCellspacing\n";
        echo "   Tables with cellpadding: $tablesWithCellpadding\n";
        
        if ($totalTables === $tablesWithCellspacing && $totalTables === $tablesWithCellpadding) {
            echo "   ✓ All signature tables have proper TCPDF attributes\n";
        } else {
            $warnings[] = "buildSignaturesTable has tables without cellspacing/cellpadding (may cause contract PDF errors)";
        }
    }
}

// Check if test files exist
echo "\n4. Checking test files...\n";
if (file_exists(__DIR__ . '/test-etat-lieux.php')) {
    echo "   ✓ test-etat-lieux.php exists\n";
} else {
    $warnings[] = "test-etat-lieux.php not found (recommended for testing)";
}

if (file_exists(__DIR__ . '/verify-tcpdf-table-fix.php')) {
    echo "   ✓ verify-tcpdf-table-fix.php exists\n";
} else {
    $warnings[] = "verify-tcpdf-table-fix.php not found";
}

// Summary
echo "\n" . str_repeat("=", 70) . "\n";
echo "VERIFICATION SUMMARY\n";
echo str_repeat("=", 70) . "\n";
echo "Total checks performed: $checks\n";
echo "Errors found: " . count($errors) . "\n";
echo "Warnings: " . count($warnings) . "\n";
echo "\n";

if (!empty($errors)) {
    echo "❌ ERRORS:\n";
    foreach ($errors as $i => $error) {
        echo "   " . ($i + 1) . ". $error\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "⚠ WARNINGS:\n";
    foreach ($warnings as $i => $warning) {
        echo "   " . ($i + 1) . ". $warning\n";
    }
    echo "\n";
}

if (empty($errors)) {
    echo "✅ ALL CRITICAL CHECKS PASSED!\n";
    echo "\nThe TCPDF table fixes have been properly implemented.\n";
    echo "État des lieux PDFs should generate without errors.\n";
    
    if (empty($warnings)) {
        echo "\n🎉 Perfect! No warnings either.\n";
        exit(0);
    } else {
        echo "\nNote: There are some warnings but they are not critical.\n";
        exit(0);
    }
} else {
    echo "❌ VERIFICATION FAILED!\n";
    echo "\nPlease review and fix the errors above before deploying.\n";
    exit(1);
}
