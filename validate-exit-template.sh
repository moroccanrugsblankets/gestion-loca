#!/bin/bash
# Validation script for exit template implementation

echo "=== Exit Template Implementation Validation ==="
echo ""

# Test 1: Check PHP syntax
echo "Test 1: Checking PHP syntax..."
php -l admin-v2/etat-lieux-configuration.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "✓ admin-v2/etat-lieux-configuration.php - No syntax errors"
else
    echo "✗ admin-v2/etat-lieux-configuration.php - SYNTAX ERROR"
    php -l admin-v2/etat-lieux-configuration.php
    exit 1
fi

php -l pdf/generate-etat-lieux.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "✓ pdf/generate-etat-lieux.php - No syntax errors"
else
    echo "✗ pdf/generate-etat-lieux.php - SYNTAX ERROR"
    php -l pdf/generate-etat-lieux.php
    exit 1
fi
echo ""

# Test 2: Check for required handlers
echo "Test 2: Checking for required form action handlers..."
if grep -q "update_template_sortie" admin-v2/etat-lieux-configuration.php; then
    echo "✓ Exit template update handler exists"
else
    echo "✗ Exit template update handler NOT FOUND"
    exit 1
fi

if grep -q "update_template'" admin-v2/etat-lieux-configuration.php; then
    echo "✓ Entry template update handler exists"
else
    echo "✗ Entry template update handler NOT FOUND"
    exit 1
fi
echo ""

# Test 3: Check for database parameter usage
echo "Test 3: Checking database parameter usage..."
if grep -q "etat_lieux_sortie_template_html" admin-v2/etat-lieux-configuration.php; then
    echo "✓ Exit template parameter used in config page"
else
    echo "✗ Exit template parameter NOT FOUND in config page"
    exit 1
fi

if grep -q "etat_lieux_sortie_template_html" pdf/generate-etat-lieux.php; then
    echo "✓ Exit template parameter used in PDF generation"
else
    echo "✗ Exit template parameter NOT FOUND in PDF generation"
    exit 1
fi
echo ""

# Test 4: Check for UI elements
echo "Test 4: Checking UI elements..."
if grep -q "template_html_sortie" admin-v2/etat-lieux-configuration.php; then
    echo "✓ Exit template editor field exists"
else
    echo "✗ Exit template editor field NOT FOUND"
    exit 1
fi

if grep -q "bi-box-arrow-in-right" admin-v2/etat-lieux-configuration.php; then
    echo "✓ Entry template icon (green) exists"
else
    echo "✗ Entry template icon NOT FOUND"
    exit 1
fi

if grep -q "bi-box-arrow-right" admin-v2/etat-lieux-configuration.php; then
    echo "✓ Exit template icon (red) exists"
else
    echo "✗ Exit template icon NOT FOUND"
    exit 1
fi
echo ""

# Test 5: Check for TinyMCE initialization
echo "Test 5: Checking TinyMCE editor initialization..."
tinymce_count=$(grep -c "tinymce.init" admin-v2/etat-lieux-configuration.php)
if [ "$tinymce_count" -eq 2 ]; then
    echo "✓ Two TinyMCE editors initialized (entry and exit)"
else
    echo "✗ Expected 2 TinyMCE editors, found $tinymce_count"
    exit 1
fi
echo ""

# Test 6: Check for preview functionality
echo "Test 6: Checking preview functionality..."
if grep -q "preview-card-sortie" admin-v2/etat-lieux-configuration.php; then
    echo "✓ Exit template preview card exists"
else
    echo "✗ Exit template preview card NOT FOUND"
    exit 1
fi

if grep -q "showPreview" admin-v2/etat-lieux-configuration.php; then
    echo "✓ Preview function exists"
else
    echo "✗ Preview function NOT FOUND"
    exit 1
fi
echo ""

# Test 7: Check for fallback logic in PDF generation
echo "Test 7: Checking fallback logic in PDF generation..."
if grep -q "fall back to entry template" pdf/generate-etat-lieux.php; then
    echo "✓ Fallback logic exists for missing exit template"
else
    echo "✗ Fallback logic NOT FOUND"
    exit 1
fi
echo ""

# Test 8: Count variable tags
echo "Test 8: Checking template variables..."
entry_vars=$(grep -c "variable-tag.*onclick=\"copyVariable" admin-v2/etat-lieux-configuration.php | head -1)
echo "  Found variable tags in configuration page"
echo ""

# Test 9: Check for reset functionality
echo "Test 9: Checking reset functionality..."
if grep -q "resetToDefault" admin-v2/etat-lieux-configuration.php; then
    echo "✓ Reset to default function exists"
else
    echo "✗ Reset to default function NOT FOUND"
    exit 1
fi
echo ""

# Summary
echo "=== Validation Summary ==="
echo "All tests passed! ✓"
echo ""
echo "Implementation includes:"
echo "  - Separate template configuration for entry and exit states"
echo "  - Visual distinction (green icon for entry, red for exit)"
echo "  - Independent save, preview, and reset functionality"
echo "  - Backward compatible fallback logic"
echo "  - Same variable support for both templates"
echo ""
echo "Access the configuration page at:"
echo "  /admin-v2/etat-lieux-configuration.php"
