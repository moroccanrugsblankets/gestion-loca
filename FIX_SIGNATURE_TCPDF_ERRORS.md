# Fix État des Lieux Signature and TCPDF Issues

## Issues Fixed

This PR addresses two critical issues reported in the État des Lieux module:

### 1. Tenant Signature Not Saving (`/admin-v2/edit-etat-lieux.php?id=1`)
**Root Cause**: Missing `global $pdo;` declaration in `updateEtatLieuxTenantSignature()` function in `includes/functions.php`.

**Impact**: When tenants tried to sign the État des Lieux document, the signature data was not being saved to the database because the function couldn't access the PDO database connection.

**Fix**: Added `global $pdo;` declaration at the beginning of the `updateEtatLieuxTenantSignature()` function.

### 2. TCPDF ERROR (`/admin-v2/finalize-etat-lieux.php?id=1`)
**Root Cause**: Missing `cles_autre` field in the `etats_lieux` database table.

**Impact**: When updating the État des Lieux record, the UPDATE query was failing because it tried to set the `cles_autre` field which didn't exist in the database. This failure cascaded into the PDF generation process, causing TCPDF errors.

**Fix**: Created migration `028_add_cles_autre_field.php` to add the missing column to the database schema.

## Changes Made

### 1. `includes/functions.php`
```php
function updateEtatLieuxTenantSignature($etatLieuxLocataireId, $signatureData, $etatLieuxId) {
    global $pdo;  // ← ADDED THIS LINE
    
    // ... rest of function
}
```

### 2. `migrations/028_add_cles_autre_field.php` (NEW FILE)
- Adds `cles_autre INT DEFAULT 0` column to `etats_lieux` table
- Positioned after `cles_boite_lettres` column
- Includes proper error handling and migration tracking

### 3. `test-signature-tcpdf-fixes.php` (NEW FILE)
- Comprehensive validation script to verify both fixes
- Checks for the presence of `global $pdo;`
- Verifies migration file exists and is correct
- Validates PHP syntax of modified files

## Deployment Instructions

### For Production (OVH)

1. **Apply the database migration**:
   ```bash
   php migrations/028_add_cles_autre_field.php
   ```

2. **Verify the fixes**:
   ```bash
   php test-signature-tcpdf-fixes.php
   ```

3. **Test the functionality**:
   - Navigate to `/admin-v2/edit-etat-lieux.php?id=1`
   - Try signing as a tenant and save the form
   - Verify signature is saved correctly
   - Navigate to `/admin-v2/finalize-etat-lieux.php?id=1`
   - Finalize and send the document
   - Verify PDF is generated without TCPDF errors

## Technical Details

### Database Schema Change
The `cles_autre` field stores the number of "other keys" in addition to apartment keys and mailbox keys. It's used in the keys section of the État des Lieux form and in the total keys calculation.

### Signature Storage
Tenant signatures are stored as physical files in `uploads/signatures/` directory with the naming pattern:
```
etat_lieux_tenant_{etat_lieux_id}_{etat_lieux_locataire_id}_{timestamp}.jpg
```

The database stores the relative path (e.g., `uploads/signatures/etat_lieux_tenant_1_1_1675890000.jpg`) rather than the base64-encoded data.

## Testing Results

All validation tests pass:
- ✓ `global $pdo;` declaration added and positioned correctly
- ✓ Migration file created with proper column definition
- ✓ `cles_autre` field is used in UPDATE queries
- ✓ `cles_autre` field is used in PDF generation
- ✓ TCPDF error handling is in place
- ✓ Signature file storage is configured correctly
- ✓ PHP syntax is valid in all modified files

## Security Considerations

- Input validation is already present for signature data (size limits, format validation)
- File paths are sanitized and stored in a dedicated directory
- Database queries use prepared statements
- No new security vulnerabilities introduced

## Related Files

- `admin-v2/edit-etat-lieux.php` - État des Lieux editing form
- `admin-v2/finalize-etat-lieux.php` - État des Lieux finalization and PDF sending
- `pdf/generate-etat-lieux.php` - PDF generation logic
- `includes/functions.php` - Utility functions including signature saving
- `migrations/026_fix_etats_lieux_schema.php` - Previous schema migration
- `migrations/027_enhance_etats_lieux_comprehensive.php` - Previous schema enhancements
