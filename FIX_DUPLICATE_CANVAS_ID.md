# Fix: Duplicate Canvas ID Issue for Tenant Signatures

## Problem Description

### Symptom
- Tenant 2 cannot sign in the inventory form
- Browser console shows duplicate canvas ID error:
  ```
  ⚠️  DUPLICATE CANVAS ID DETECTED: Canvas ID 2 was already initialized!
  This will cause Tenant 2 signature to not work properly.
  Root cause: Multiple tenant records have the same database ID.
  ```

### Console Output
```
=== Initializing tenant signatures ===
Total tenants: 2
Initializing tenant 1: ID=2, locataire_id=63, name=Salah Tabout
Signature canvas initialized successfully for tenant ID: 2 (Tenant 1)
Initializing tenant 2: ID=2, locataire_id=63, name=Salah Tabout
⚠️  DUPLICATE CANVAS ID DETECTED: Canvas ID 2 was already initialized!
```

### Root Cause
The code was using the database `id` field from the `inventaire_locataires` table to generate HTML element IDs:
- Canvas: `<canvas id="tenantCanvas_<?php echo $tenant['id']; ?>">`
- When multiple tenant records have the same ID (due to database issues), this creates duplicate IDs
- Duplicate IDs break JavaScript event handling and prevent the second tenant from signing

## Solution Implemented

### Approach
Use the **loop index** instead of the database ID for all HTML element IDs and form field names, while preserving the database ID in a hidden field for backend processing.

### Before (Using Database ID)
```php
<!-- HTML -->
<canvas id="tenantCanvas_<?php echo $tenant['id']; ?>">
<input type="hidden" name="tenants[<?php echo $tenant['id']; ?>][signature]" 
       id="tenantSignature_<?php echo $tenant['id']; ?>">

<!-- JavaScript -->
<?php foreach ($existing_tenants as $tenant): ?>
    initTenantSignature(<?php echo $tenant['id']; ?>);
<?php endforeach; ?>

<!-- Backend -->
foreach ($_POST['tenants'] as $tenantId => $tenantInfo) {
    // $tenantId is the database ID from array key
}
```

**Problem**: If two tenants have `id=2`, both canvas elements get `id="tenantCanvas_2"`, causing conflicts.

### After (Using Array Index)
```php
<!-- HTML -->
<?php foreach ($existing_tenants as $index => $tenant): ?>
    <canvas id="tenantCanvas_<?php echo $index; ?>">
    <input type="hidden" name="tenants[<?php echo $index; ?>][signature]" 
           id="tenantSignature_<?php echo $index; ?>">
    <input type="hidden" name="tenants[<?php echo $index; ?>][db_id]" 
           value="<?php echo $tenant['id']; ?>">
<?php endforeach; ?>

<!-- JavaScript -->
<?php foreach ($existing_tenants as $index => $tenant): ?>
    initTenantSignature(<?php echo $index; ?>);
<?php endforeach; ?>

<!-- Backend -->
foreach ($_POST['tenants'] as $tenantIndex => $tenantInfo) {
    $tenantId = (int)$tenantInfo['db_id']; // Get database ID from hidden field
    // Use $tenantId for database updates
}
```

**Solution**: Each tenant gets a unique index (0, 1, 2, etc.), ensuring unique IDs regardless of database duplicates.

## Changes Made

### 1. HTML Element IDs
| Element | Before | After |
|---------|--------|-------|
| Canvas | `tenantCanvas_{db_id}` | `tenantCanvas_{index}` |
| Signature Input | `tenantSignature_{db_id}` | `tenantSignature_{index}` |
| Checkbox | `certifie_exact_{db_id}` | `certifie_exact_{index}` |

### 2. Form Field Names
| Field | Before | After |
|-------|--------|-------|
| Signature | `tenants[{db_id}][signature]` | `tenants[{index}][signature]` |
| Locataire ID | `tenants[{db_id}][locataire_id]` | `tenants[{index}][locataire_id]` |
| Name | `tenants[{db_id}][nom]` | `tenants[{index}][nom]` |
| First Name | `tenants[{db_id}][prenom]` | `tenants[{index}][prenom]` |
| Email | `tenants[{db_id}][email]` | `tenants[{index}][email]` |
| Certifié Exact | `tenants[{db_id}][certifie_exact]` | `tenants[{index}][certifie_exact]` |
| **NEW** DB ID | N/A | `tenants[{index}][db_id]` |

### 3. JavaScript Updates
```javascript
// Initialization
document.addEventListener('DOMContentLoaded', function() {
    // Before: initTenantSignature(<?php echo $tenant['id']; ?>);
    // After: initTenantSignature(<?php echo $index; ?>);
});

// Form submission
document.getElementById('inventaireForm').addEventListener('submit', function(e) {
    // Before: saveTenantSignature(<?php echo $tenant['id']; ?>);
    // After: saveTenantSignature(<?php echo $index; ?>);
});

// Validation
const tenantValidations = [
    {
        // Before: id: <?php echo $tenant['id']; ?>,
        // After: tenantIndex: <?php echo $index; ?>,
        signatureId: 'tenantSignature_<?php echo $index; ?>',
        certifieId: 'certifie_exact_<?php echo $index; ?>'
    }
];
```

### 4. Backend PHP Updates
```php
// Validation: Check all tenants have db_id
$missingDbIds = [];
foreach ($_POST['tenants'] as $tenantIndex => $tenantInfo) {
    if (!isset($tenantInfo['db_id']) || empty($tenantInfo['db_id'])) {
        $missingDbIds[] = $tenantIndex;
    }
}

if (!empty($missingDbIds)) {
    throw new Exception("Données de locataire incomplètes. Veuillez réessayer.");
}

// Processing: Extract db_id from form data
foreach ($_POST['tenants'] as $tenantIndex => $tenantInfo) {
    $tenantId = (int)$tenantInfo['db_id']; // Map index to database ID
    
    // Update database using $tenantId
    $stmt->execute([$certifieExact, $tenantId, $inventaire_id]);
}
```

## Benefits

1. **Unique IDs**: Each canvas element gets a unique ID (0, 1, 2, etc.)
2. **No Duplicates**: Works correctly even if database has duplicate records
3. **Proper Data Mapping**: Hidden `db_id` field maintains correct database relationship
4. **Error Prevention**: Validation ensures no silent failures
5. **Maintainable**: Clear separation between UI identifiers and database IDs

## Testing

### Expected Behavior
1. ✅ Both (or all) tenants can sign independently
2. ✅ No duplicate canvas ID warnings in console
3. ✅ Signatures save correctly for each tenant
4. ✅ Form validation works for all tenants
5. ✅ Data persists correctly to database using hidden `db_id` field

### Test Steps
1. Open an inventory with 2 tenants
2. Open browser console (F12)
3. Check for canvas initialization messages (should show unique IDs: 0, 1)
4. Sign in Tenant 1 signature canvas
5. Sign in Tenant 2 signature canvas
6. Save as draft
7. Reload page - both signatures should be preserved
8. Check "Certifié exact" for both tenants
9. Finalize - should succeed

## Comparison with edit-etat-lieux.php

The working `edit-etat-lieux.php` file uses the **same flawed approach** (database ID for canvas IDs). 

The difference is that `etat_lieux_locataires` table likely has better data integrity with:
- Proper unique constraints
- `ordre` field for explicit ordering
- Better duplicate prevention in insertion logic

This fix makes `edit-inventaire.php` more robust and immune to database ID issues.

## Files Modified

- `admin-v2/edit-inventaire.php`
  - Lines 784-813: HTML canvas and form fields (using index)
  - Lines 843-849: JavaScript initialization (using index)
  - Lines 1046-1051: Form submission handler (using index)
  - Lines 89-141: Backend processing (extract db_id, validate completeness)
  - Lines 1103-1114: Validation array (using tenantIndex property)

## Related Issues

This fix addresses the specific issue: **"IMPOSSIBLE DE SIGNER POUR LOCATAIRE 2 !!"**

The PDF styling issue mentioned ("J4AI UN BACKGROUD SUR LE BLOC DE SIGNATURE") is separate and may need additional investigation in `pdf/generate-inventaire.php`.
