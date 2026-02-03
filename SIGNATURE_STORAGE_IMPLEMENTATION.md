# Signature Storage Implementation - Summary

## Overview
This PR implements physical file storage for company signatures to match the existing implementation for client signatures.

## Problem Statement
The original issue stated (in French):
> "dans /admin-v2/contrat-configuration.php la signature n'est pas stocker physiquement !
> il faut aussi pour la signature client qu'elle soit physique"

Translation:
- In `/admin-v2/contrat-configuration.php`, the company signature is not stored physically
- Client signatures should also be stored physically

## Analysis Results
- ✅ **Client signatures** were already being stored as physical files via the `updateTenantSignature()` function in `includes/functions.php`
- ❌ **Company signature** was being stored as a base64 data URI in the `parametres` table

## Solution Implemented

### 1. Company Signature Physical Storage
Modified the signature upload flow to save files physically:

**Before:**
```php
// Stored base64 data URI in database
$base64Image = base64_encode($imageData);
$dataUri = "data:$mimeType;base64,$base64Image";
// Save $dataUri to database
```

**After:**
```php
// Save physical file
$filename = "company_signature_" . time() . ".png";
$filepath = $uploadsDir . '/' . $filename;
file_put_contents($filepath, $imageData);

// Save file path to database
$relativePath = 'uploads/signatures/' . $filename';
// Save $relativePath to database
```

### 2. Backward Compatibility
The PDF generation code now supports both formats:
- **File paths**: `uploads/signatures/company_signature_123456.png`
- **Data URIs**: `data:image/png;base64,...` (for legacy data)

This ensures existing installations continue to work during migration.

### 3. File Detection Logic
Improved file path detection to avoid false positives:
```php
// Check that it doesn't start with 'data:' AND contains the expected path
$isFilePath = (strpos($signatureImage, 'data:') !== 0 && 
               strpos($signatureImage, 'uploads/signatures/') !== false);
```

## Files Modified

### `/admin-v2/contrat-configuration.php`
- ✅ Upload handler now saves physical files
- ✅ Stores file path instead of base64
- ✅ Deletes old file when uploading new signature
- ✅ Deletes physical file when removing signature

### `/pdf/generate-contrat-pdf.php`
- ✅ Handles both file paths and data URIs
- ✅ Uses physical file directly when available
- ✅ Falls back to data URI conversion for legacy data

### `/pdf/generate-bail.php`
- ✅ Handles both file paths and data URIs
- ✅ Uses physical file directly when available
- ✅ Falls back to data URI conversion for legacy data

### `/migrate-company-signature-to-file.php` (NEW)
- ✅ One-time migration script
- ✅ Converts existing base64 signatures to physical files
- ✅ Updates database with file path
- ✅ Uses correct file extension based on image format

## Benefits

1. **Reduced Database Size**: File paths (~50 bytes) vs base64 data (~500KB)
2. **Better Performance**: No encoding/decoding overhead
3. **Easier Management**: Files can be backed up, moved, or replaced independently
4. **Consistency**: Both client and company signatures now use the same storage mechanism
5. **Backward Compatible**: Existing installations continue to work

## Migration Path

For existing installations with base64 company signatures:

1. Deploy the code changes
2. Run the migration script:
   ```bash
   php migrate-company-signature-to-file.php
   ```
3. Verify the migration was successful
4. (Optional) Clean up the old base64 data from backups after confirming everything works

## Security Considerations

- ✅ Files are saved in `/uploads/signatures/` with proper permissions (0755)
- ✅ `.htaccess` file restricts file types (only PNG, JPG, PDF allowed)
- ✅ No PHP execution in uploads directory
- ✅ File paths are validated before deletion
- ✅ File existence checked before operations

## Testing Recommendations

1. **New Installation Test**:
   - Upload a company signature
   - Verify file is created in `/uploads/signatures/`
   - Verify database contains file path, not base64
   - Generate a PDF contract and verify signature appears

2. **Migration Test**:
   - Start with base64 signature in database
   - Run migration script
   - Verify file is created
   - Verify database is updated
   - Generate PDF and verify signature appears

3. **Delete Test**:
   - Delete signature via admin interface
   - Verify physical file is removed
   - Verify database is cleared

## Notes

- Client signatures were already stored physically, so no changes were needed for that part
- The implementation maintains the same security and validation as the existing client signature system
- All error conditions are logged for debugging
- The migration script is idempotent and can be run multiple times safely
