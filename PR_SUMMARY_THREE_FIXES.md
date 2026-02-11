# Summary of Three Fixes - 2026-02-11

## Overview
This document summarizes the three fixes implemented in this PR.

---

## Fix 1: Remove email_admin Parameter

### Problem
The `email_admin` parameter was accessible in the database parameters table but should only be configured in `includes/config.php` for centralized configuration management.

### Solution
1. **Updated `includes/functions.php`**:
   - Simplified `getAdminEmail()` function to only use config value
   - Removed database parameter lookup logic

2. **Updated `admin-v2/parametres.php`**:
   - Added `email_admin` to the `$obsoleteParams` array
   - Parameter will no longer appear in the admin UI

3. **Created Migration 044**:
   - `migrations/044_remove_email_admin_parameter.sql`
   - Removes the `email_admin` parameter from the database

### Before
```php
function getAdminEmail() {
    global $config;
    
    // Try to get from parameter first
    $emailFromParam = getParameter('email_admin', null);
    if ($emailFromParam && filter_var($emailFromParam, FILTER_VALIDATE_EMAIL)) {
        return $emailFromParam;
    }
    
    // Fallback to config
    return $config['ADMIN_EMAIL'] ?? 'location@myinvest-immobilier.com';
}
```

### After
```php
function getAdminEmail() {
    global $config;
    
    // Use only config value
    return $config['ADMIN_EMAIL'] ?? 'location@myinvest-immobilier.com';
}
```

---

## Fix 2: Fix locataires_info Table in PDF Contract

### Problem
The `{{locataires_info}}` variable in PDF contracts displayed tenant information in a table with:
- Borders (`border: 1px solid #ddd`)
- Background colors (`background-color: #f8f9fa`)
- Labels "Locataire 1" and "Locataire 2" without colons

### Solution
Updated `pdf/generate-contrat-pdf.php` to generate a clean table:
- Removed all borders
- Removed background colors
- Changed "Locataire 1" to "Locataire 1:" (added colon)
- Changed "Locataire 2" to "Locataire 2:" (added colon)
- Updated format for 3+ tenants to match

### Before
```php
$locatairesInfoHtml = '<table style="width: 100%; border-collapse: collapse; border: 1px solid #ddd;">';
$locatairesInfoHtml .= '<tr style="background-color: #f8f9fa;">';
$locatairesInfoHtml .= '<th style="border: 1px solid #ddd; padding: 8px; text-align: left; width: 50%;">Locataire 1</th>';
```

### After
```php
$locatairesInfoHtml = '<table style="width: 100%; border-collapse: collapse;">';
$locatairesInfoHtml .= '<tr>';
$locatairesInfoHtml .= '<th style="padding: 8px; text-align: left; width: 50%;">Locataire 1:</th>';
```

### Visual Comparison

**Before:**
```
┌──────────────────────────────┬──────────────────────────────┐
│ Locataire 1                  │ Locataire 2                  │  <- Gray background
├──────────────────────────────┼──────────────────────────────┤
│ Jean DUPONT                  │ Marie MARTIN                 │
│ Né(e) le 01/01/1990          │ Né(e) le 15/05/1992          │
│ Email : jean@example.com     │ Email : marie@example.com    │
└──────────────────────────────┴──────────────────────────────┘
```

**After:**
```
Locataire 1:                    Locataire 2:
Jean DUPONT                     Marie MARTIN
Né(e) le 01/01/1990            Né(e) le 15/05/1992
Email : jean@example.com        Email : marie@example.com
```
(No borders, no background colors, cleaner presentation)

---

## Fix 3: Fix IBAN Link in Email Template

### Problem
In the `demande_justificatif_paiement` email template, the IBAN "FR76 1027 8021 6000 0206 1834 585" was being auto-linked by some email clients, creating an unwanted clickable link.

### Solution
1. **Updated `init-email-templates.php`**:
   - Moved `white-space: nowrap` from the parent div to the span element wrapping the IBAN
   - This prevents email clients from parsing the IBAN as a potential link

2. **Created Migration 045**:
   - `migrations/045_fix_iban_link_in_email_template.sql`
   - Updates the existing template in the database

### Before
```html
<div style="margin: 10px 0; white-space: nowrap;">
    <strong>IBAN :</strong> 
    <span style="font-family: monospace; letter-spacing: 1px;">
        FR76&nbsp;1027&nbsp;8021&nbsp;6000&nbsp;0206&nbsp;1834&nbsp;585
    </span>
</div>
```

### After
```html
<div style="margin: 10px 0;">
    <strong>IBAN :</strong> 
    <span style="font-family: monospace; letter-spacing: 1px; white-space: nowrap;">
        FR76&nbsp;1027&nbsp;8021&nbsp;6000&nbsp;0206&nbsp;1834&nbsp;585
    </span>
</div>
```

---

## Testing

### Tests Created
- `test-locataires-info-standalone.php`: Validates that the table has no borders, no background colors, and proper colons

### Test Results
```
=== Test des changements locataires_info ===

Vérifications:
- Pas de bordures (border: 1px solid): ✅ PASS
- Pas de couleur de fond (background-color): ✅ PASS
- 'Locataire 1:' avec deux-points: ✅ PASS
- 'Locataire 2:' avec deux-points: ✅ PASS

Test pour 3+ locataires:
- 'Locataire 3:' avec deux-points: ✅ PASS
- Pas le format ancien 'Locataire 3 : ': ✅ PASS

✅ Tous les tests passent!
```

### Code Quality Checks
- ✅ PHP syntax validation passed for all modified files
- ✅ Code review: No issues found
- ✅ CodeQL security check: No vulnerabilities detected

---

## Files Modified

1. `includes/functions.php` - Simplified getAdminEmail()
2. `admin-v2/parametres.php` - Hide email_admin parameter
3. `pdf/generate-contrat-pdf.php` - Fix locataires_info table
4. `init-email-templates.php` - Fix IBAN link in email template

## Files Created

1. `migrations/044_remove_email_admin_parameter.sql` - Remove email_admin from database
2. `migrations/045_fix_iban_link_in_email_template.sql` - Update email template in database
3. `test-locataires-info-standalone.php` - Test script for validation

---

## Deployment Instructions

1. Deploy the code changes
2. Run migrations:
   ```bash
   php run-migrations.php
   ```
3. Verify the changes:
   - Check that email_admin parameter no longer appears in admin UI
   - Generate a PDF contract and verify the table has no borders/colors
   - Send a test email using the demande_justificatif_paiement template and verify IBAN is not linked

---

## Security Summary

No security vulnerabilities were introduced or discovered. All changes are:
- Cosmetic (PDF table styling)
- Configuration-related (email_admin parameter removal)
- Email rendering fixes (IBAN link prevention)

All changes maintain existing security practices and do not introduce new attack vectors.
