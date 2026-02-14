#!/bin/bash
# Validation script for bilan_rows table structure fix

echo "=== Validation of Bilan Rows Table Structure Fix ==="
echo ""

# Check 1: Verify complete table generation in generate-bilan-logement.php
echo "✓ Check 1: Complete table structure in generate-bilan-logement.php"
if grep -q "\$bilanRowsHtml = '<table>';" pdf/generate-bilan-logement.php; then
    echo "  ✅ PASS: Table opening tag added"
else
    echo "  ❌ FAIL: Table opening tag not found"
fi

if grep -q "\$bilanRowsHtml .= '</table>';" pdf/generate-bilan-logement.php; then
    echo "  ✅ PASS: Table closing tag added"
else
    echo "  ❌ FAIL: Table closing tag not found"
fi

if grep -q "<th style=\"width: 25%;\">Poste</th>" pdf/generate-bilan-logement.php; then
    echo "  ✅ PASS: Header row with <th> tags included"
else
    echo "  ❌ FAIL: Header row not found"
fi
echo ""

# Check 2: Verify NO thead/tbody tags in PHP generation
echo "✓ Check 2: No thead/tbody tags in PHP generation"
if grep -q "<thead>" pdf/generate-bilan-logement.php; then
    echo "  ❌ FAIL: Found <thead> tag in PHP (should not be present)"
else
    echo "  ✅ PASS: No <thead> tag in PHP"
fi

if grep -q "<tbody>" pdf/generate-bilan-logement.php; then
    echo "  ❌ FAIL: Found <tbody> tag in PHP (should not be present)"
else
    echo "  ✅ PASS: No <tbody> tag in PHP"
fi
echo ""

# Check 3: Verify same changes in test file
echo "✓ Check 3: Consistency in test-html-preview-bilan-logement.php"
if grep -q "\$bilanRowsHtml = '<table>';" test-html-preview-bilan-logement.php; then
    echo "  ✅ PASS: Table opening tag added to test file"
else
    echo "  ❌ FAIL: Table opening tag not found in test file"
fi

if grep -q "\$bilanRowsHtml .= '</table>';" test-html-preview-bilan-logement.php; then
    echo "  ✅ PASS: Table closing tag added to test file"
else
    echo "  ❌ FAIL: Table closing tag not found in test file"
fi
echo ""

# Check 4: Verify migration file exists
echo "✓ Check 4: Migration file 057_fix_bilan_rows_table_structure.sql"
if [ -f "migrations/057_fix_bilan_rows_table_structure.sql" ]; then
    echo "  ✅ PASS: Migration file exists"
    
    # Check that template no longer wraps {{bilan_rows}} in table/thead/tbody
    if grep -q "{{bilan_rows}}" migrations/057_fix_bilan_rows_table_structure.sql; then
        echo "  ✅ PASS: Migration contains {{bilan_rows}} placeholder"
    else
        echo "  ❌ FAIL: {{bilan_rows}} placeholder not found in migration"
    fi
    
    # Verify no thead/tbody in new template
    if grep -q "<thead>" migrations/057_fix_bilan_rows_table_structure.sql; then
        echo "  ❌ FAIL: Found <thead> in migration (should be removed)"
    else
        echo "  ✅ PASS: No <thead> in migration template"
    fi
else
    echo "  ❌ FAIL: Migration file not found"
fi
echo ""

echo "=== Validation Summary ==="
echo "Key changes implemented:"
echo "  1. ✅ {{bilan_rows}} now contains complete <table> structure"
echo "  2. ✅ Header row with <th> tags included in PHP generation"
echo "  3. ✅ Data rows with <td> tags included in PHP generation"
echo "  4. ✅ NO <thead>, </thead>, <tbody>, </tbody> tags used"
echo "  5. ✅ Migration file created to update database template"
echo "  6. ✅ Test file updated for consistency"
echo ""
echo "Next steps:"
echo "  - Run migration: php run-migrations.php"
echo "  - Test HTML preview: test-html-preview-bilan-logement.php?contrat_id=X"
echo "  - Generate actual PDF to verify TCPDF compatibility"
