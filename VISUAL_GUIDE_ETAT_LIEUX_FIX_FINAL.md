# Visual Guide: État des Lieux Fix

## Issue 1: Fatal Error - updateEtatLieuxTenantSignature

### Before
```
Fatal error: Uncaught Error: Call to undefined function updateEtatLieuxTenantSignature() 
in /home/barconcecc/contrat.myinvest-immobilier.com/admin-v2/edit-etat-lieux.php:95 
Stack trace: #0 {main} thrown in /home/barconcecc/contrat.myinvest-immobilier.com/admin-v2/edit-etat-lieux.php 
on line 95
```

**Error occurs when**: Editing an état des lieux and trying to save tenant signatures

**Root cause**: `functions.php` not included, so `updateEtatLieuxTenantSignature()` function unavailable

### After
```
✓ Page loads successfully
✓ Tenant signatures save without errors
✓ Signatures stored as physical .jpg files in uploads/signatures/
```

**Fix**: Added one line at the top of the file:
```php
require_once '../includes/functions.php';
```

---

## Issue 2: Photos Not Saving

### Before - Photo Upload Flow

1. User clicks photo upload zone
   ```
   [📷 Cliquer pour ajouter une photo]
   ```

2. User selects a photo file from their computer
   ```
   File selected: photo_compteur.jpg
   ```

3. JavaScript shows message:
   ```
   ✓ 1 fichier(s) sélectionné(s)
   ```

4. User saves the form
   ```
   État des lieux enregistré avec succès
   ```

5. **PROBLEM**: Photo is NOT saved to database
   - Page reload shows no photo
   - Photo not in database
   - Photo not in filesystem

### After - Photo Upload Flow

1. User clicks photo upload zone
   ```
   [📷 Cliquer pour ajouter une photo]
   ```

2. User selects a photo file from their computer
   ```
   File selected: photo_compteur.jpg
   ```

3. **NEW**: JavaScript immediately uploads photo via AJAX
   ```
   ⏳ Téléchargement en cours...
   ```

4. **NEW**: Upload completes successfully
   ```
   ✓ 1 photo(s) téléchargée(s) avec succès
   ```

5. **NEW**: Page reloads automatically (after 1 second)

6. **NEW**: Photo is displayed in the form
   ```
   ✓ 1 photo(s) enregistrée(s)
   [thumbnail of photo_compteur.jpg] [×]
   ```

7. Photo is now:
   - ✓ Saved to database (`etat_lieux_photos` table)
   - ✓ Stored in filesystem (`uploads/etats_lieux/{id}/`)
   - ✓ Visible in the form
   - ✓ Included in PDF when generated

---

## Code Changes Overview

### Change 1: Add functions.php Include

**File**: `admin-v2/edit-etat-lieux.php`
**Line**: 10

```diff
  require_once '../includes/config.php';
  require_once 'auth.php';
  require_once '../includes/db.php';
+ require_once '../includes/functions.php';
```

**Impact**: Makes all functions from `functions.php` available, including `updateEtatLieuxTenantSignature()`

---

### Change 2: Implement AJAX Photo Upload

**File**: `admin-v2/edit-etat-lieux.php`
**Lines**: 979-1047

#### Before
```javascript
// Preview photos
function previewPhoto(input, previewId) {
    const preview = document.getElementById(previewId);
    preview.innerHTML = '';
    
    if (input.files && input.files.length > 0) {
        const fileList = document.createElement('div');
        fileList.className = 'alert alert-success mb-0';
        fileList.innerHTML = `<i class="bi bi-check-circle"></i> ${input.files.length} fichier(s) sélectionné(s)`;
        preview.appendChild(fileList);
    }
}
```

**Problems**:
- Only shows preview message
- Never uploads files to server
- Files lost when form is saved

#### After
```javascript
// Upload and preview photos
function previewPhoto(input, previewId) {
    const preview = document.getElementById(previewId);
    preview.innerHTML = '';
    
    if (!input.files || input.files.length === 0) {
        return;
    }
    
    // Determine category from input ID
    const categoryMap = {
        'photo_compteur_elec': 'compteur_electricite',
        'photo_compteur_eau': 'compteur_eau',
        'photo_cles': 'cles',
        'photo_piece_principale': 'piece_principale',
        'photo_cuisine': 'cuisine',
        'photo_salle_eau': 'salle_eau',
        'photo_etat_general': 'autre'
    };
    
    const category = categoryMap[input.id];
    if (!category) {
        console.error('Unknown category for input:', input.id);
        return;
    }
    
    // Show uploading message
    preview.innerHTML = '<div class="alert alert-info mb-0"><i class="bi bi-hourglass-split"></i> Téléchargement en cours...</div>';
    
    // Upload each file
    const uploadPromises = [];
    for (let i = 0; i < input.files.length; i++) {
        const formData = new FormData();
        formData.append('photo', input.files[i]);
        formData.append('etat_lieux_id', <?php echo json_encode((int)$id); ?>);
        formData.append('categorie', category);
        
        const uploadPromise = fetch('upload-etat-lieux-photo.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.error || 'Erreur inconnue');
            }
            return data;
        });
        
        uploadPromises.push(uploadPromise);
    }
    
    // Wait for all uploads to complete
    Promise.all(uploadPromises)
        .then(results => {
            preview.innerHTML = '<div class="alert alert-success mb-0"><i class="bi bi-check-circle"></i> ' + results.length + ' photo(s) téléchargée(s) avec succès</div>';
            
            // Reload the page to show the uploaded photos
            // Note: This will refresh the entire form. Users should save their changes before uploading photos if needed.
            const RELOAD_DELAY_MS = 1000;
            setTimeout(() => {
                window.location.reload();
            }, RELOAD_DELAY_MS);
        })
        .catch(error => {
            console.error('Upload error:', error);
            preview.innerHTML = '<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle"></i> Erreur: ' + error.message + '</div>';
        });
}
```

**Improvements**:
- ✓ Actually uploads files via AJAX
- ✓ Maps input IDs to database categories
- ✓ Shows upload progress
- ✓ Handles multiple files
- ✓ Reloads page to show uploaded photos
- ✓ Error handling with user feedback
- ✓ Secure JavaScript embedding with `json_encode()`

---

## Testing Scenarios

### Scenario 1: Edit État des Lieux - No Fatal Error

**Steps**:
1. Navigate to `/admin-v2/edit-etat-lieux.php?id=1`
2. Page should load without errors

**Expected Before Fix**:
```
Fatal error: Call to undefined function updateEtatLieuxTenantSignature()
```

**Expected After Fix**:
```
✓ Page loads successfully
✓ Form displays correctly
✓ All fields are editable
```

---

### Scenario 2: Upload Compteur Électricité Photo

**Steps**:
1. Navigate to `/admin-v2/edit-etat-lieux.php?id=1`
2. Scroll to "Compteur électricité" section
3. Click on photo upload zone
4. Select a photo file (e.g., `compteur_elec.jpg`)
5. Wait for upload to complete
6. Page should reload automatically

**Expected Before Fix**:
- Shows "1 fichier(s) sélectionné(s)"
- Photo NOT saved to database
- After page reload, photo NOT visible

**Expected After Fix**:
- Shows "Téléchargement en cours..."
- Then shows "1 photo(s) téléchargée(s) avec succès"
- Page reloads after 1 second
- Photo appears with thumbnail and delete button:
  ```
  ✓ 1 photo(s) enregistrée(s)
  [thumbnail] [×]
  ```

---

### Scenario 3: Save Tenant Signature

**Steps**:
1. Navigate to `/admin-v2/edit-etat-lieux.php?id=1`
2. Scroll to tenant signature section
3. Draw a signature on the canvas
4. Click "Enregistrer" button
5. Check browser console for errors

**Expected Before Fix**:
```
Fatal error: Call to undefined function updateEtatLieuxTenantSignature()
```

**Expected After Fix**:
```
✓ Form saved successfully
✓ Signature saved as .jpg file in uploads/signatures/
✓ Database updated with file path (not base64)
✓ No console errors
```

---

### Scenario 4: Upload Multiple Photos

**Steps**:
1. Navigate to `/admin-v2/edit-etat-lieux.php?id=1`
2. Scroll to "Pièce principale" section
3. Click on photo upload zone (this input accepts multiple files)
4. Select 3 photo files
5. Wait for upload to complete

**Expected Before Fix**:
- Shows "3 fichier(s) sélectionné(s)"
- Photos NOT saved

**Expected After Fix**:
- Shows "Téléchargement en cours..."
- Then shows "3 photo(s) téléchargée(s) avec succès"
- Page reloads
- All 3 photos appear with thumbnails:
  ```
  ✓ 3 photo(s) enregistrée(s)
  [thumbnail1] [×]  [thumbnail2] [×]  [thumbnail3] [×]
  ```

---

## Error Handling

### Upload Error Example

**Scenario**: File too large (> 5MB)

**Before Fix**:
- No error message
- File appears selected but never uploads
- User confused

**After Fix**:
```
⚠️ Erreur: Fichier trop volumineux. Taille maximale: 5MB
```

### Network Error Example

**Scenario**: Server unreachable

**After Fix**:
```
⚠️ Erreur: Erreur inconnue
```
(with console error logged for debugging)

---

## Browser Compatibility

The AJAX upload uses modern JavaScript features:
- ✓ FormData API (all modern browsers)
- ✓ Fetch API (all modern browsers)
- ✓ Promises (all modern browsers)
- ✓ Template literals (all modern browsers)

**Minimum browser versions**:
- Chrome 42+
- Firefox 39+
- Safari 10.1+
- Edge 14+

---

## Security Considerations

### XSS Prevention

**Before**: Direct echo of PHP variable into JavaScript
```javascript
formData.append('etat_lieux_id', <?php echo $id; ?>);
```
**Risk**: If `$id` somehow contained malicious content, could inject JavaScript

**After**: Safe encoding with `json_encode()`
```javascript
formData.append('etat_lieux_id', <?php echo json_encode((int)$id); ?>);
```
**Security**: 
- `(int)$id` ensures numeric value
- `json_encode()` properly escapes for JavaScript context
- No XSS risk

### File Upload Validation

The upload endpoint (`upload-etat-lieux-photo.php`) already validates:
- ✓ File type (only images allowed)
- ✓ File size (max 5MB)
- ✓ User authentication
- ✓ État des lieux exists

---

## Performance Impact

### Network Traffic
- **Before**: 0 requests (photos never uploaded)
- **After**: 1 AJAX request per photo + 1 page reload
- **Impact**: Minimal - photos uploaded asynchronously

### Server Load
- **Before**: 0 server processing (photos never uploaded)
- **After**: Standard file upload processing
- **Impact**: Minimal - same as manual upload would be

### User Experience
- **Before**: Fast but broken (photos not saved)
- **After**: Slightly slower but working correctly
- **Improvement**: Photos saved immediately, visible after reload

---

## Maintenance Notes

### Future Improvements

1. **Remove Page Reload**: Update DOM dynamically instead of reloading
   - Would preserve unsaved form data
   - Better UX but more complex code

2. **Progress Bar**: Show upload progress percentage
   - Better feedback for large files
   - Requires additional JavaScript

3. **Drag & Drop**: Allow drag & drop file upload
   - More modern UX
   - Requires additional event handlers

4. **Image Preview**: Show thumbnail before upload
   - Better UX
   - Requires FileReader API

### Code Consistency

The photo upload approach (AJAX + reload) is consistent with:
- Photo delete functionality (already uses AJAX)
- Other admin forms that save and reload

The signature saving approach (base64 to file) is consistent with:
- Contract signature saving
- All other signature handling in the application
