# Fix: Tenant Signature Canvas ID Duplication

## Problem Statement

On `/admin-v2/edit-inventaire.php`, both tenants were rendered with the same canvas ID (tenantCanvas_4), causing Tenant 2 to be unable to sign independently.

### Error Message
```
Erreur Critique: ID de locataire en double détecté (ID: 4).
Les signatures pourraient ne pas fonctionner correctement.
Veuillez contacter l'administrateur.
```

### Database Reference
Table: `inventaire_locataires` for Inventaire ID = 3:
- Tenant 1: DB ID = 4, locataire_id = 63, Name = "Tabout Salah"
- Tenant 2: DB ID = 5, locataire_id = 64, Name = "James Dupont"

### Expected Behavior
- Tenant 1 → `tenantCanvas_4` (based on DB ID 4)
- Tenant 2 → `tenantCanvas_5` (based on DB ID 5)

### Actual Behavior
- Both tenants → `tenantCanvas_4` (duplicate canvas ID)

## Root Cause

The code was using the database ID (`inventaire_locataires.id`) to generate HTML element IDs. While this should work in theory, it made the code vulnerable to:

1. **Database integrity issues**: If duplicate records exist or queries return unexpected results
2. **PHP reference bugs**: Using `foreach ($array as &$item)` without proper cleanup can cause data corruption
3. **Race conditions**: Multiple processes accessing the same data simultaneously

## Solution Implemented

### Approach
Use the **loop index** instead of the database ID for all HTML element IDs and form field names, while preserving the database ID in a hidden field for backend processing.

### Benefits
1. **Guaranteed Uniqueness**: Array indices are always unique (0, 1, 2, ...)
2. **No Database Dependencies**: Works correctly regardless of database state
3. **Robust Against Bugs**: Immune to reference bugs and data corruption
4. **Clear Separation**: UI identifiers (index) vs. database identifiers (db_id field)

## Changes Made

### 1. HTML Elements (Lines 814-897)

#### Before:
```php
<canvas id="tenantCanvas_<?php echo $tenant['id']; ?>">
<input type="hidden" name="tenants[<?php echo $tenant['id']; ?>][signature]" 
       id="tenantSignature_<?php echo $tenant['id']; ?>">
```

#### After:
```php
<canvas id="tenantCanvas_<?php echo $index; ?>">
<input type="hidden" name="tenants[<?php echo $index; ?>][signature]" 
       id="tenantSignature_<?php echo $index; ?>">
<input type="hidden" name="tenants[<?php echo $index; ?>][db_id]" 
       value="<?php echo $tenant['id']; ?>">
```

**Key Changes:**
- Canvas ID: `tenantCanvas_{index}` instead of `tenantCanvas_{db_id}`
- Hidden field IDs: Use index instead of DB ID
- Added new hidden field: `db_id` to preserve database relationship
- Checkbox ID: `certifie_exact_{index}` instead of `certifie_exact_{db_id}`

### 2. JavaScript Initialization (Lines 926-970)

#### Before:
```javascript
const tenantId = <?php echo $tenant['id']; ?>;
initTenantSignature(tenantId);
```

#### After:
```javascript
const tenantIndex = <?php echo $index; ?>;
const tenantDbId = <?php echo $tenant['id']; ?>;
initTenantSignature(tenantIndex);
```

**Key Changes:**
- Use `tenantIndex` for canvas operations
- Track `tenantDbId` separately for logging
- Updated error messages to reference index instead of ID

### 3. Form Submission (Lines 1180-1185)

#### Before:
```javascript
<?php foreach ($existing_tenants as $tenant): ?>
    saveTenantSignature(<?php echo $tenant['id']; ?>);
<?php endforeach; ?>
```

#### After:
```javascript
<?php foreach ($existing_tenants as $index => $tenant): ?>
    saveTenantSignature(<?php echo $index; ?>);
<?php endforeach; ?>
```

### 4. Validation Array (Lines 1237-1247)

#### Before:
```javascript
{
    tenantId: <?php echo $tenant['id']; ?>,
    signatureId: 'tenantSignature_<?php echo $tenant['id']; ?>',
    certifieId: 'certifie_exact_<?php echo $tenant['id']; ?>'
}
```

#### After:
```javascript
{
    tenantIndex: <?php echo $index; ?>,
    tenantDbId: <?php echo $tenant['id']; ?>,
    signatureId: 'tenantSignature_<?php echo $index; ?>',
    certifieId: 'certifie_exact_<?php echo $index; ?>'
}
```

### 5. Backend Processing (Lines 88-143)

#### Before:
```php
foreach ($_POST['tenants'] as $tenantId => $tenantInfo) {
    $tenantId = (int)$tenantId; // Array key is DB ID
    // ... use $tenantId for database operations
}
```

#### After:
```php
// Validate all tenants have db_id field
foreach ($_POST['tenants'] as $tenantIndex => $tenantInfo) {
    if (!isset($tenantInfo['db_id']) || empty($tenantInfo['db_id'])) {
        throw new Exception("Données de locataire incomplètes");
    }
}

foreach ($_POST['tenants'] as $tenantIndex => $tenantInfo) {
    $tenantId = (int)$tenantInfo['db_id']; // Extract DB ID from hidden field
    // ... use $tenantId for database operations
}
```

**Key Changes:**
- Loop variable is now `$tenantIndex` (not database ID)
- Extract database ID from `$tenantInfo['db_id']` hidden field
- Added validation to ensure `db_id` field exists
- Updated error logging to include both index and DB ID

### 6. PHP Best Practice Fix (Line 375)

#### Before:
```php
foreach ($existing_tenants as &$tenant) {
    $tenant['signature_data'] = $tenant['signature'] ?? '';
}
// No cleanup - reference persists!
```

#### After:
```php
foreach ($existing_tenants as &$tenant) {
    $tenant['signature_data'] = $tenant['signature'] ?? '';
}
unset($tenant); // Clean up reference to prevent accidental modifications
```

## Testing

### Expected Results

1. **Unique Canvas IDs**: Each tenant gets a unique canvas ID
   - Tenant 1: `tenantCanvas_0`
   - Tenant 2: `tenantCanvas_1`

2. **No Duplicate Warnings**: Console should show:
   ```
   Initializing Tenant 1: Index=0, DB_ID=4, Name=Tabout Salah
   Initializing Tenant 2: Index=1, DB_ID=5, Name=James Dupont
   ```

3. **Independent Signatures**: Each tenant can sign independently without conflicts

4. **Correct Data Persistence**: Signatures save to correct database records using DB ID

### Verification Commands

```bash
# Check PHP syntax
php -l admin-v2/edit-inventaire.php

# Run verification script
php verify-inventaire-tenant-signatures.php 3
```

## Comparison with Previous Approach

| Aspect | DB ID Approach | Index Approach |
|--------|---------------|----------------|
| HTML Element IDs | `tenantCanvas_{db_id}` | `tenantCanvas_{index}` |
| Uniqueness Guarantee | Depends on database | Always unique |
| Robustness | Vulnerable to DB issues | Immune to DB issues |
| Code Complexity | Simple but fragile | Slightly more complex but robust |
| Database Mapping | Direct (array key) | Indirect (hidden field) |

## Files Modified

- `admin-v2/edit-inventaire.php`
  - Lines 375: Added `unset($tenant)` after reference loop
  - Lines 814-897: Updated HTML elements to use index
  - Lines 926-970: Updated JavaScript initialization
  - Lines 1180-1185: Updated form submission handler
  - Lines 1237-1247: Updated validation array
  - Lines 88-143: Updated backend processing with validation

## Security Considerations

1. **Input Validation**: Added validation to ensure `db_id` field exists before processing
2. **SQL Injection Prevention**: Continued use of prepared statements with parameterized queries
3. **XSS Prevention**: Maintained proper `htmlspecialchars()` escaping
4. **Error Handling**: Proper exception handling with user-friendly messages

## Related Issues

This fix resolves:
- Duplicate canvas ID error preventing Tenant 2 from signing
- Potential data corruption from uncleaned PHP references
- Fragility related to database state dependencies

## Future Improvements

Consider applying this pattern to other similar forms:
- `edit-etat-lieux.php` (uses same DB ID approach but may have better data integrity)
- Any other multi-tenant signature forms
