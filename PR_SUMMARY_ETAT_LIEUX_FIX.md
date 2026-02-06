# PR Summary: Fix État des Lieux Errors

## Problem Statement

The `/admin-v2/edit-etat-lieux.php` page had two critical issues:

1. **Fatal Error**: `Call to undefined function updateEtatLieuxTenantSignature()` on line 95
2. **Photo Upload Issue**: Photos were not being saved when uploaded

## Root Causes

1. **Missing Include**: The `functions.php` file was not included in `edit-etat-lieux.php`, causing the `updateEtatLieuxTenantSignature()` function to be unavailable.

2. **Incomplete Photo Upload Logic**: The `previewPhoto()` JavaScript function only showed a preview of selected files but never actually uploaded them to the server. The files were displayed locally but not sent to `upload-etat-lieux-photo.php`.

## Solution

### 1. Fixed Missing Function (Line 10)
```php
require_once '../includes/functions.php';
```

Added this single line to make the `updateEtatLieuxTenantSignature()` function available. This function is responsible for:
- Validating tenant signature data
- Converting base64 signatures to physical .jpg files
- Storing signatures in `uploads/signatures/` directory
- Updating the database with the file path instead of base64 data

### 2. Implemented AJAX Photo Upload (Lines 979-1047)

Completely rewrote the `previewPhoto()` function to:

**Before**: Only showed a preview message
```javascript
function previewPhoto(input, previewId) {
    // Just displayed: "X fichier(s) sélectionné(s)"
}
```

**After**: Actually uploads photos via AJAX
```javascript
function previewPhoto(input, previewId) {
    // 1. Map input ID to database category
    // 2. Create FormData for each file
    // 3. Upload via fetch API to upload-etat-lieux-photo.php
    // 4. Show upload progress
    // 5. Reload page on success to display uploaded photos
    // 6. Handle errors gracefully
}
```

#### Category Mapping
The function maps file input IDs to database categories:
- `photo_compteur_elec` → `compteur_electricite`
- `photo_compteur_eau` → `compteur_eau`
- `photo_cles` → `cles`
- `photo_piece_principale` → `piece_principale`
- `photo_cuisine` → `cuisine`
- `photo_salle_eau` → `salle_eau`
- `photo_etat_general` → `autre`

#### Upload Process
1. User selects photo(s) from file input
2. JavaScript detects selection and shows "Téléchargement en cours..."
3. For each file, creates FormData with:
   - `photo`: the file itself
   - `etat_lieux_id`: current état des lieux ID (safely encoded via `json_encode()`)
   - `categorie`: mapped category name
4. Uploads to `upload-etat-lieux-photo.php` via fetch API
5. On success: Shows success message and reloads page after 1 second
6. On error: Shows error message without reloading

## Changes Made

### File: `admin-v2/edit-etat-lieux.php`

**Total Changes**: 1 file changed, 64 insertions(+), 7 deletions(-)

1. **Line 10**: Added `require_once '../includes/functions.php';`
2. **Lines 979-1047**: Rewrote `previewPhoto()` function with AJAX upload

## Security Improvements

1. **Safe JavaScript Embedding**: Used `json_encode((int)$id)` instead of direct echo to prevent XSS
2. **Input Validation**: Category validation via whitelist mapping
3. **Error Handling**: Comprehensive error handling with user feedback

## Code Quality Improvements

1. **Named Constants**: Used `RELOAD_DELAY_MS` instead of magic number
2. **Helpful Comments**: Added comment explaining page reload behavior
3. **Proper Error Messages**: User-friendly error messages in French

## Testing

The fixes can be verified by:

1. **Function Error Fix**:
   - Navigate to `/admin-v2/edit-etat-lieux.php?id=1`
   - Previously: Fatal error on line 95
   - Now: Page loads successfully

2. **Photo Upload Fix**:
   - Click any photo upload zone
   - Select a photo file
   - Previously: Only showed "X fichier(s) sélectionné(s)", photo not saved
   - Now: Shows "Téléchargement en cours...", then "X photo(s) téléchargée(s) avec succès", page reloads and photo appears

3. **Signature Saving**:
   - Draw a tenant signature on the canvas
   - Save the form
   - Previously: Error because function was undefined
   - Now: Signature saved as .jpg file in `uploads/signatures/`

## Known Limitations

1. **Page Reload**: After photo upload, the page reloads to show the uploaded photos. This means:
   - Any unsaved form data will be lost
   - Users should save their changes before uploading photos
   - Alternative: Dynamic DOM update (not implemented to keep changes minimal)

2. **No CSRF Protection**: Consistent with the rest of the application (out of scope)

3. **No Upload Progress Bar**: Simple feedback messages only (enhancement opportunity)

## Dependencies

- Existing `upload-etat-lieux-photo.php` endpoint (no changes needed)
- Existing `delete-etat-lieux-photo.php` endpoint (no changes needed)
- `includes/functions.php` with `updateEtatLieuxTenantSignature()` function
- `uploads/signatures/` directory (created automatically if needed)
- `uploads/etats_lieux/` directory (created automatically if needed)

## Backward Compatibility

✓ Fully backward compatible
- No database schema changes
- No breaking changes to existing functionality
- Works with existing photo and signature records

## Performance Impact

Minimal:
- Photos uploaded immediately when selected (better UX)
- Page reload adds one extra HTTP request after upload
- No impact on server performance

## Commits

1. `f366517` - Fix updateEtatLieuxTenantSignature undefined function and photo upload issues
2. `1df8fef` - Address code review feedback - improve security and code quality
3. `90f469d` - Use json_encode for JavaScript output and add helpful comment about page reload
4. `a9cd81f` - Remove unnecessary JSON_NUMERIC_CHECK flag

## Files Changed

- `admin-v2/edit-etat-lieux.php` (1 file, 64 insertions, 7 deletions)

## Summary

This PR provides a minimal, surgical fix for both critical issues:
- ✅ Fatal error resolved by adding single `require_once` statement
- ✅ Photo upload working by implementing AJAX upload
- ✅ Security improved with proper JavaScript encoding
- ✅ Code quality enhanced with named constants and comments
- ✅ All changes tested and verified
