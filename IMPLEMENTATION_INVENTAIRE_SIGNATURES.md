# Implementation Guide: Inventaire Client Signature Functionality

## Overview
This document describes the implementation of tenant signature functionality in the `edit-inventaire.php` page, mirroring the implementation from `edit-etat-lieux.php`.

## Changes Summary

### Files Modified
1. **admin-v2/edit-inventaire.php** - Main implementation file
2. **includes/functions.php** - Helper function already existed

### Database Requirements
The following tables and columns are required (already present from migration 034):

#### inventaires table
- `lieu_signature` VARCHAR(255) - Common signature location for all tenants

#### inventaire_locataires table
- `id` INT - Primary key
- `inventaire_id` INT - Foreign key to inventaires table
- `locataire_id` INT - Foreign key to locataires table (optional)
- `nom` VARCHAR(100) - Tenant last name
- `prenom` VARCHAR(100) - Tenant first name
- `email` VARCHAR(255) - Tenant email
- `signature` VARCHAR(500) - Relative path to signature image file
- `date_signature` TIMESTAMP - When the signature was created
- `certifie_exact` BOOLEAN - "Certified exact" checkbox status

## Implementation Details

### 1. Backend (PHP)

#### Include Required Files
```php
require_once '../includes/functions.php';
```

#### Form Submission Handler
The form submission handler was enhanced with:
- **Transaction Support**: All updates are wrapped in a database transaction for data integrity
- **lieu_signature Field**: Common signature location saved to inventaires table
- **Tenant Signature Processing**: 
  - Updates `certifie_exact` status for each tenant
  - Validates signature format (data URL with base64 image)
  - Saves signature using `updateInventaireTenantSignature()` function
  - Logs errors if signature save fails

```php
// Update tenant signatures
if (isset($_POST['tenants']) && is_array($_POST['tenants'])) {
    foreach ($_POST['tenants'] as $tenantId => $tenantInfo) {
        // Update certifie_exact status
        $certifieExact = isset($tenantInfo['certifie_exact']) ? 1 : 0;
        $stmt = $pdo->prepare("UPDATE inventaire_locataires SET certifie_exact = ? WHERE id = ?");
        $stmt->execute([$certifieExact, $tenantId]);
        
        // Update signature if provided
        if (!empty($tenantInfo['signature'])) {
            updateInventaireTenantSignature($tenantId, $tenantInfo['signature'], $inventaire_id);
        }
    }
}
```

#### Tenant Data Fetching
```php
// Get existing tenants for this inventaire
$stmt = $pdo->prepare("SELECT * FROM inventaire_locataires WHERE inventaire_id = ?");
$stmt->execute([$inventaire_id]);
$existing_tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Auto-populate from contract if no tenants exist
if (empty($existing_tenants) && !empty($inventaire['contrat_id'])) {
    // Fetch tenants from contract and insert into inventaire_locataires
}

// Transform for display
foreach ($existing_tenants as &$tenant) {
    $tenant['signature_data'] = $tenant['signature'] ?? '';
    $tenant['signature_timestamp'] = $tenant['date_signature'] ?? '';
}
```

### 2. Frontend (HTML/CSS)

#### Signature Section Structure
```html
<div class="form-card">
    <div class="section-title">
        <i class="bi bi-pen"></i> Signatures des locataires
    </div>
    
    <!-- Common signature location -->
    <div class="row mb-4">
        <div class="col-md-6">
            <label for="lieu_signature" class="form-label">Lieu de signature</label>
            <input type="text" name="lieu_signature" id="lieu_signature" class="form-control">
        </div>
    </div>
    
    <!-- Per-tenant signature canvas -->
    <div class="section-subtitle">
        Signature locataire 1 - [Name]
    </div>
    <canvas id="tenantCanvas_[ID]" width="300" height="150"></canvas>
    <input type="hidden" name="tenants[[ID]][signature]" id="tenantSignature_[ID]">
    
    <!-- Certifié exact checkbox -->
    <div class="form-check">
        <input type="checkbox" name="tenants[[ID]][certifie_exact]" value="1">
        <label>Certifié exact</label>
    </div>
</div>
```

#### CSS Styles Added
```css
.section-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: #212529;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #e9ecef;
}

.signature-container {
    border: 2px solid #000000;
    border-radius: 4px;
    display: inline-block;
    background: white;
}

.signature-container canvas {
    display: block;
    cursor: crosshair;
}
```

### 3. JavaScript Implementation

#### Configuration
```javascript
const SIGNATURE_JPEG_QUALITY = 0.95;
```

#### Canvas Initialization
```javascript
function initTenantSignature(id) {
    const canvas = document.getElementById(`tenantCanvas_${id}`);
    const ctx = canvas.getContext('2d');
    
    // Configure drawing style
    ctx.strokeStyle = '#000000';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    
    // Add mouse and touch event listeners
}
```

#### Signature Saving
```javascript
function saveTenantSignature(id) {
    const canvas = document.getElementById(`tenantCanvas_${id}`);
    
    // Create temporary canvas with white background
    const tempCanvas = document.createElement('canvas');
    tempCanvas.width = canvas.width;
    tempCanvas.height = canvas.height;
    const tempCtx = tempCanvas.getContext('2d');
    
    // Fill white background (JPEG doesn't support transparency)
    tempCtx.fillStyle = '#FFFFFF';
    tempCtx.fillRect(0, 0, tempCanvas.width, tempCanvas.height);
    
    // Draw signature on white background
    tempCtx.drawImage(canvas, 0, 0);
    
    // Convert to JPEG data URL
    const signatureData = tempCanvas.toDataURL('image/jpeg', SIGNATURE_JPEG_QUALITY);
    document.getElementById(`tenantSignature_${id}`).value = signatureData;
}
```

#### Clear Signature
```javascript
function clearTenantSignature(id) {
    const canvas = document.getElementById(`tenantCanvas_${id}`);
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    document.getElementById(`tenantSignature_${id}`).value = '';
}
```

## Security Features

### 1. Signature Validation
- Size limit: 2MB maximum
- Format validation: Only data:image/(jpeg|jpg|png);base64 allowed
- Regex validation for base64 content

### 2. Path Validation for Display
```php
// Validate data URL
if (preg_match('/^data:image\/(jpeg|jpg|png);base64,([A-Za-z0-9+\/=]+)$/', $signatureSrc)) {
    if (strlen($signatureSrc) <= 2 * 1024 * 1024) {
        $displaySrc = $signatureSrc;
    }
}
// Validate file path
elseif (preg_match('/^uploads\/signatures\/[a-zA-Z0-9_\-]+\.(jpg|jpeg|png)$/', $signatureSrc)) {
    $displaySrc = '../' . $signatureSrc;
}
```

### 3. Transaction Safety
All database operations are wrapped in transactions to ensure data consistency:
- If any operation fails, all changes are rolled back
- Prevents partial updates that could leave data in an inconsistent state

## Helper Function: updateInventaireTenantSignature()

Located in `includes/functions.php`, this function:

1. **Validates signature data**:
   - Checks size limit (2MB)
   - Validates data URL format
   - Validates base64 encoding

2. **Saves physical file**:
   - Creates `uploads/signatures/` directory if needed
   - Generates unique filename: `inventaire_tenant_{inventaireId}_{tenantId}_{timestamp}.jpg`
   - Saves decoded image data to file

3. **Updates database**:
   - Stores relative path in `inventaire_locataires.signature`
   - Sets `date_signature` to current timestamp
   - Cleans up file if database update fails

## Usage Flow

1. **User opens edit-inventaire.php**:
   - System fetches inventaire data
   - System fetches existing tenants from inventaire_locataires
   - If no tenants exist but contract is linked, auto-populates from contract

2. **User fills out form**:
   - Edits equipment quantities and states
   - Adds observations
   - Enters signature location
   - Each tenant draws their signature on canvas
   - Each tenant checks "Certifié exact" if applicable

3. **User submits form**:
   - Transaction begins
   - Equipment data saved to inventaires table
   - Lieu_signature saved to inventaires table
   - For each tenant:
     - certifie_exact status updated
     - If signature provided, saved as physical file
   - Transaction committed
   - Success message displayed

## Testing

### Manual Testing Checklist
- [ ] Can access edit-inventaire.php with valid inventaire ID
- [ ] Signature section displays when tenants exist
- [ ] Can draw signature on canvas with mouse
- [ ] Can draw signature on canvas with touch (mobile)
- [ ] Can clear signature and redraw
- [ ] Can check/uncheck "Certifié exact"
- [ ] Form submission saves signatures correctly
- [ ] Existing signatures display properly on page reload
- [ ] Transaction rollback works if error occurs
- [ ] Signature files are created in uploads/signatures/
- [ ] PHP errors are logged properly

### Test Script
A test script `test-inventaire-signatures.php` is available to verify:
- Function availability
- Database table structure
- Signature validation regex

## Troubleshooting

### Signatures Not Saving
1. Check write permissions on `uploads/signatures/` directory
2. Check error logs for validation failures
3. Verify tenant records exist in inventaire_locataires table

### Signatures Not Displaying
1. Check that signature path is in correct format
2. Verify file exists at the specified path
3. Check browser console for JavaScript errors

### Database Errors
1. Verify all required columns exist in inventaire_locataires table
2. Check that foreign keys are valid
3. Review error logs for SQL errors

## Performance Considerations

1. **File Storage**: Signatures are stored as physical files, not base64 in database, for better performance
2. **Optimized Validation**: Removed redundant base64_decode check during display
3. **Transaction Batching**: All updates in single transaction reduces database round-trips
4. **Canvas Efficiency**: Uses efficient drawing with requestAnimationFrame where applicable

## Future Enhancements

Potential improvements for future versions:
1. Add image compression before saving
2. Support multiple signature formats (SVG, etc.)
3. Add signature history/versioning
4. Email signature confirmation to tenants
5. Add digital timestamp certification
6. Support for delegate signatures

## Related Files

- `admin-v2/edit-etat-lieux.php` - Similar implementation for états des lieux
- `includes/functions.php` - Contains updateInventaireTenantSignature()
- `migrations/034_create_inventaire_tables.php` - Database schema
- `pdf/inventaire_*.php` - PDF generation with signatures

## Support

For issues or questions:
1. Check error logs in web server and PHP error log
2. Review this documentation
3. Compare with edit-etat-lieux.php implementation
4. Contact development team

---

**Last Updated**: 2024
**Version**: 1.0
**Author**: GitHub Copilot
