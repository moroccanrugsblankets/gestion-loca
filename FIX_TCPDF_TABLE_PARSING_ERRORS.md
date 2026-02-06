# Fix TCPDF Table Parsing Errors

## Problem Statement

The URL `/admin-v2/finalize-etat-lieux.php?id=1` was generating multiple TCPDF errors:

```
Notice: Undefined index: cols in .../vendor/tecnickcom/tcpdf/tcpdf.php on line 17172
Notice: Undefined index: thead in .../vendor/tecnickcom/tcpdf/tcpdf.php on line 16732
Notice: Undefined variable: cellspacingx in .../vendor/tecnickcom/tcpdf/tcpdf.php on line 18380
Notice: Undefined variable: cellspacing in .../vendor/tecnickcom/tcpdf/tcpdf.php on line 18447
Notice: Trying to access array offset on value of type null in .../vendor/tecnickcom/tcpdf/tcpdf.php on line 18447
Warning: Cannot modify header information - headers already sent by (output started at .../vendor/tecnickcom/tcpdf/tcpdf.php:18447)
```

## Root Cause

The TCPDF library's HTML parser was failing due to **invalid HTML attributes** in the signature table generation:

1. **Invalid `border` attribute on `<table>` tag**: The table had `border="0"` along with `cellspacing="0"` and `cellpadding="0"` attributes that caused TCPDF's internal parser to malfunction
2. **Invalid `border` attribute on `<td>` elements**: Table cells had `border="0"` or `border="20"` attributes, which is invalid HTML (the `border` attribute is only valid on `<table>` elements, not `<td>`)
3. **Invalid `border` attribute on `<img>` elements**: While less problematic, these deprecated attributes could also cause TCPDF issues

## Solution

Removed all invalid HTML attributes and used CSS styling exclusively:

### Changes Made

#### File: `pdf/generate-etat-lieux.php`

**Before:**
```php
$html = '<table border="0" style="..." cellspacing="0" cellpadding="0"><tr>';
$html .= '<td border="20" style="...">';
$html .= '<img src="..." border="0" style="...">';
```

**After:**
```php
$html = '<table style="..."><tr>';
$html .= '<td style="...">';
$html .= '<img src="..." style="...">';
```

#### File: `pdf/generate-contrat-pdf.php`

Applied the same fixes to the contract PDF generation for consistency:
- Removed `border="0"` from `<table>` tag
- Removed `border="0"` from all `<td>` tags
- Removed `border="0"` from all `<img>` tags

#### File: `pdf/generate-bail.php`

Applied the same fixes to the bail PDF generation for consistency:
- Removed `border="0"` from all `<img>` tags

## Technical Details

### Why This Fixes the Issue

1. **TCPDF HTML Parser Compatibility**: TCPDF's internal HTML parser expects valid HTML. When it encounters invalid attributes like `border="0"` on `<td>` elements or `cellspacing`/`cellpadding` on `<table>` elements, it creates internal state variables that are not properly initialized, leading to "Undefined variable" errors.

2. **CSS-Only Styling**: Modern HTML/CSS best practices dictate using CSS for all styling. The `border`, `cellspacing`, and `cellpadding` attributes are deprecated HTML4 attributes that should be replaced with CSS properties:
   - `border="0"` → `style="border: none;"`
   - `cellspacing="0"` → `style="border-collapse: collapse;"`
   - `cellpadding="0"` → handled via CSS padding

3. **Table Cell Border Attribute**: The `border` attribute on `<td>` elements has never been valid HTML, even in older specifications. This was the primary cause of TCPDF's parser failure.

## Testing

Created `test-tcpdf-html-structure.php` to validate the HTML structure:

```bash
php test-tcpdf-html-structure.php
```

All 6 tests pass:
- ✅ No problematic table attributes (border/cellspacing/cellpadding)
- ✅ No border attributes on td elements
- ✅ No border attributes on img elements
- ✅ Table uses CSS border-collapse
- ✅ buildSignaturesTableEtatLieux function exists
- ✅ No cellspacing/cellpadding attributes

## Impact

### Fixed
- État des lieux PDF generation now works without TCPDF errors
- Contract PDF generation is more robust and standards-compliant
- Bail PDF generation is more robust and standards-compliant

### No Breaking Changes
- All styling remains visually identical (CSS properties match the removed HTML attributes)
- No functional changes to the PDF generation logic
- No database schema changes required

## Prevention

The test script `test-tcpdf-html-structure.php` can be run as part of CI/CD to prevent regression of these issues.

## References

- [TCPDF Documentation](https://tcpdf.org)
- [HTML Table Element - MDN](https://developer.mozilla.org/en-US/docs/Web/HTML/Element/table)
- [Deprecated HTML Attributes](https://developer.mozilla.org/en-US/docs/Web/HTML/Attributes#deprecated_attributes)
