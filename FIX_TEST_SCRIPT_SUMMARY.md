# Fix Test Script - test-signature-tcpdf-fixes.php

## Problem Statement

The test script `test-signature-tcpdf-fixes.php` was failing with errors:
```
❌ ERREURS (2):
❌ Erreur de syntaxe dans includes/functions.php: sh: php: command not found
❌ Erreur de syntaxe dans migrations/028_add_cles_autre_field.php: sh: php: command not found
```

Despite 13 tests passing, 2 PHP syntax validation tests were failing due to PATH issues.

## Root Cause Analysis

### Issue 1: PHP Command Not Found
The test script was using:
```php
exec("php -l $fullPath 2>&1", $output, $returnCode);
```

When `exec()` runs, it uses a shell environment that may not have the same PATH as the PHP process. This caused the `php` command to not be found even though PHP was available at `/usr/bin/php`.

### Issue 2: Outdated TCPDF Validation
The test was checking for the old implementation:
```php
// Check for image path handling
if (strpos($pdfContent, '@') !== false && strpos($pdfContent, 'TCPDF requires @ prefix') !== false) {
    $success[] = "✓ Les chemins d'image TCPDF utilisent le préfixe @";
}
```

This was checking for the OLD problematic method that was already fixed in a previous session.

## Solution Applied

### Fix 1: Use PHP_BINARY Constant

**Before**:
```php
exec("php -l $fullPath 2>&1", $output, $returnCode);
```

**After**:
```php
$phpBinary = PHP_BINARY;
exec(escapeshellarg($phpBinary) . " -l " . escapeshellarg($fullPath) . " 2>&1", $output, $returnCode);
```

**Benefits**:
- ✅ `PHP_BINARY` always contains the full path to the current PHP executable
- ✅ No dependency on shell PATH environment
- ✅ Added `escapeshellarg()` for security (prevents command injection)
- ✅ Works consistently across different environments

### Fix 2: Update TCPDF Validation

**Before** (checking for old method):
```php
// Check for image path handling
if (strpos($pdfContent, '@') !== false && strpos($pdfContent, 'TCPDF requires @ prefix') !== false) {
    $success[] = "✓ Les chemins d'image TCPDF utilisent le préfixe @";
}
```

**After** (checking for correct method):
```php
// Check for correct image path handling (should use public URLs, not @ prefix)
if (strpos($pdfContent, "SITE_URL") !== false && strpos($pdfContent, "publicUrl") !== false) {
    $success[] = "✓ Les images utilisent des URLs publiques (fix TCPDF appliqué)";
} else {
    $warnings[] = "⚠ Les images pourraient ne pas utiliser des URLs publiques";
}

// Check that @ prefix is NOT used with local paths (old problematic method)
if (preg_match('/@.*fullPath|@.*dirname\(__DIR__\)/', $pdfContent)) {
    $errors[] = "❌ Préfixe @ trouvé avec chemins locaux - cela cause des erreurs TCPDF";
} else {
    $success[] = "✓ Pas de préfixe @ avec chemins locaux (fix TCPDF correct)";
}
```

**Benefits**:
- ✅ Validates the CORRECT implementation (public URLs)
- ✅ Detects if the problematic @ prefix method is accidentally reintroduced
- ✅ More comprehensive validation (2 checks instead of 1)

## Test Results

### Before Fix
```
✅ SUCCÈS (13)
❌ ERREURS (2):
   ❌ Erreur de syntaxe dans includes/functions.php: sh: php: command not found
   ❌ Erreur de syntaxe dans migrations/028_add_cles_autre_field.php: sh: php: command not found
```

### After Fix
```
✅ SUCCÈS (16):
   ✓ global $pdo trouvé dans updateEtatLieuxTenantSignature
   ✓ global $pdo est déclaré au début de la fonction
   ✓ Migration 028_add_cles_autre_field.php existe
   ✓ Migration contient l'ajout de la colonne cles_autre
   ✓ cles_autre est défini comme INT DEFAULT 0
   ✓ cles_autre est référencé dans edit-etat-lieux.php
   ✓ cles_autre est dans la requête UPDATE
   ✓ cles_autre est utilisé dans la génération PDF
   ✓ TCPDF writeHTML est utilisé
   ✓ writeHTML est dans un bloc try-catch
   ✓ Les images utilisent des URLs publiques (fix TCPDF appliqué) ⬅ NEW
   ✓ Pas de préfixe @ avec chemins locaux (fix TCPDF correct) ⬅ NEW
   ✓ Les signatures sont stockées dans uploads/signatures
   ✓ Les signatures sont enregistrées comme fichiers physiques
   ✓ Syntaxe PHP valide pour includes/functions.php ⬅ FIXED
   ✓ Syntaxe PHP valide pour migrations/028_add_cles_autre_field.php ⬅ FIXED

❌ ERREURS (0)
```

## Files Changed

### test-signature-tcpdf-fixes.php
- Lines 143-161: Updated PHP syntax checking to use `PHP_BINARY`
- Lines 109-136: Updated TCPDF validation to check for correct implementation

## Validation

Run the test:
```bash
php test-signature-tcpdf-fixes.php
```

Expected output:
```
✅ TOUS LES TESTS SONT PASSÉS
```

## Summary

**Problem**: Test script failed to find PHP command and validated outdated TCPDF implementation

**Solution**: 
1. Use `PHP_BINARY` constant for reliable PHP path
2. Update validation to check for correct TCPDF implementation (public URLs)

**Result**: All 16 tests now pass ✅

**Impact**: 
- More reliable testing across different environments
- Validates the correct TCPDF fix is in place
- Better security with `escapeshellarg()`
