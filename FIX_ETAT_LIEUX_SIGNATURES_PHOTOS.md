# État des Lieux - Signature and Photo Display Fix

## Summary of Changes

This fix addresses three critical issues with the état des lieux module:

### 1. Tenant Signatures Stored as Base64
**Problem:** Tenant signatures were being stored as base64 data URIs in the database, causing TCPDF errors and inconsistency with contract signatures.

**Solution:**
- Created `updateEtatLieuxTenantSignature()` function in `includes/functions.php`
- Mirrors the existing `updateTenantSignature()` function used for contract signatures
- Saves signatures as physical .jpg files in `uploads/signatures/` directory
- Stores only the relative file path in the database

**Files Modified:**
- `includes/functions.php` - Added new function (lines 269-339)
- `admin-v2/edit-etat-lieux.php` - Updated signature saving logic (lines 84-97)

### 2. Photos Not Displayed After Upload
**Problem:** Photos were being uploaded successfully but not displayed on the edit form after page reload.

**Solution:**
- Added query to load existing photos from `etat_lieux_photos` table
- Group photos by category for easy access
- Display photos in each relevant section with thumbnail previews
- Added delete button for each photo with confirmation dialog

**Files Modified:**
- `admin-v2/edit-etat-lieux.php`:
  - Lines 236-252: Load photos from database
  - Multiple sections: Display photos with delete buttons
  - Lines 993-1045: JavaScript `deletePhoto()` function

### 3. TCPDF PDF Generation Errors
**Problem:** TCPDF was failing to generate PDFs when signatures were in base64 format or when using public URLs for images.

**Solution:**
- Changed image source from public URLs to local file paths
- Used TCPDF's @ prefix syntax for local file paths
- Added error logging when signature files are not found
- Applied fix to both landlord and tenant signatures

**Files Modified:**
- `pdf/generate-etat-lieux.php`:
  - Lines 907-922: Landlord signature with local file path
  - Lines 940-966: Tenant signature with local file path

## Technical Details

### Signature File Naming Convention
```
etat_lieux_tenant_{etat_lieux_id}_{etat_lieux_locataire_id}_{timestamp}.jpg
```

Example: `etat_lieux_tenant_1_5_1707177890.jpg`

### Photo Display Structure
Photos are grouped by category:
- `compteur_electricite`
- `compteur_eau`
- `cles`
- `piece_principale`
- `cuisine`
- `salle_eau`
- `autre`

### TCPDF Image Syntax
For local files, TCPDF requires the @ prefix:
```php
<img src="@/full/path/to/image.jpg" />
```

Instead of:
```php
<img src="http://domain.com/path/to/image.jpg" />
```

## Testing Checklist

- [ ] Create new état des lieux
- [ ] Draw signature on canvas
- [ ] Submit form and verify signature is saved as .jpg file in `uploads/signatures/`
- [ ] Verify signature_data column contains file path, not base64
- [ ] Upload photos in various categories
- [ ] Reload page and verify photos are displayed
- [ ] Delete a photo and verify it's removed from both UI and filesystem
- [ ] Generate PDF and verify signatures appear correctly
- [ ] Check PDF file size is reasonable (should be much smaller without base64 signatures)

## Migration of Existing Data

If there are existing état des lieux with base64 signatures, they will continue to work but should be migrated. The code handles both formats:

1. **Base64 format** (legacy): `data:image/jpeg;base64,...`
   - Still works but will cause larger database storage
   - Will be displayed in PDF using base64 (slower)

2. **File path format** (new): `uploads/signatures/...`
   - Preferred format
   - Smaller database storage
   - Faster PDF generation

To migrate existing signatures, a migration script could be created similar to `migrate-signatures-to-files.php`.

## Security Considerations

### Authentication
- All admin pages require authentication via `auth.php`
- Photo upload endpoint validates état des lieux exists before accepting uploads
- Photo delete endpoint verifies photo exists and user is authenticated

### Input Validation
- Photo IDs are cast to integers
- Signature data is validated for correct format before processing
- File paths are validated before filesystem operations

### File Storage
- Signatures stored in `uploads/signatures/` with unique filenames
- Photos stored in `uploads/etats_lieux/{id}/` subdirectories
- File permissions set to 0755 for directories

## Known Limitations

1. **CSRF Protection**: Not implemented (consistent with rest of application)
2. **File Size Limits**: Signature files limited to 2MB, photos to 5MB
3. **Supported Formats**: JPEG, PNG for signatures; JPEG, PNG, GIF for photos

## Code Review Findings

### Addressed
- ✅ Fixed absolute paths to relative paths for better deployment
- ✅ Added URL encoding for photoId parameter
- ✅ Improved photo count update when deleting photos

### Not Addressed (Out of Scope)
- ❌ CSRF token validation (not implemented across application)
- ❌ File upload progress indicators (enhancement)
- ❌ Image optimization/compression (enhancement)

## Performance Impact

### Positive
- **Smaller database size**: File paths instead of base64 (90%+ reduction per signature)
- **Faster PDF generation**: Local files load faster than base64 decoding
- **Better caching**: Browser can cache image files

### Negative
- **Additional disk I/O**: Reading files from disk instead of database
- **Filesystem dependencies**: Need to manage file cleanup if records are deleted

## Deployment Notes

### Prerequisites
1. PHP with GD library for image processing
2. Write permissions on `uploads/signatures/` directory
3. TCPDF library installed via Composer

### Configuration
No configuration changes required. Uses existing:
- Database connection from `includes/db.php`
- Config from `includes/config.php`

### Rollback Plan
If issues occur:
1. Revert changes to `includes/functions.php`
2. Revert changes to `admin-v2/edit-etat-lieux.php`
3. Revert changes to `pdf/generate-etat-lieux.php`
4. Existing signatures will continue to work as base64

## Future Enhancements

1. **Migration Script**: Create script to convert existing base64 signatures to files
2. **Image Optimization**: Compress images to reduce storage
3. **Batch Upload**: Allow multiple photo uploads at once
4. **Photo Captions**: Add description field for photos
5. **CSRF Protection**: Implement across entire application
6. **Progress Indicators**: Show upload progress for large files
