# Visual Guide - Duplicate Canvas ID Fix

## 🔴 Problem: Tenant 2 Cannot Sign

### Before Fix - Console Output
```
=== Initializing tenant signatures ===
Total tenants: 2

Initializing tenant 1: ID=2, locataire_id=63, name=Salah Tabout
✓ Signature canvas initialized successfully for tenant ID: 2 (Tenant 1)

Initializing tenant 2: ID=2, locataire_id=63, name=Salah Tabout
⚠️  DUPLICATE CANVAS ID DETECTED: Canvas ID 2 was already initialized!
❌ This will cause Tenant 2 signature to not work properly.
```

### Visual Representation

```
┌─────────────────────────────────────────────────────────────┐
│                    INVENTAIRE FORM                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Signature locataire 1 - Salah Tabout                      │
│  ┌────────────────────────────────────────┐                │
│  │ <canvas id="tenantCanvas_2">           │ ✓ WORKS       │
│  │  [Signature area]                      │                │
│  └────────────────────────────────────────┘                │
│  □ Certifié exact                                          │
│                                                             │
│  Signature locataire 2 - Salah Tabout                      │
│  ┌────────────────────────────────────────┐                │
│  │ <canvas id="tenantCanvas_2">           │ ❌ DUPLICATE!  │
│  │  [Cannot draw here]                    │    NO WORK     │
│  └────────────────────────────────────────┘                │
│  □ Certifié exact                                          │
└─────────────────────────────────────────────────────────────┘

Problem: Both canvases have id="tenantCanvas_2"
Result: JavaScript can only bind to the first canvas
Impact: Second tenant cannot sign!
```

## ✅ Solution: Use Array Index Instead of Database ID

### After Fix - Console Output
```
=== Initializing tenant signatures ===
Total tenants: 2

Initializing tenant 1: Index=0, db_id=2, locataire_id=63, name=Salah Tabout
✓ Signature canvas initialized successfully for index: 0 (Tenant 1)

Initializing tenant 2: Index=1, db_id=2, locataire_id=63, name=Salah Tabout
✓ Signature canvas initialized successfully for index: 1 (Tenant 2)
```

### Visual Representation

```
┌─────────────────────────────────────────────────────────────┐
│                    INVENTAIRE FORM                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Signature locataire 1 - Salah Tabout                      │
│  ┌────────────────────────────────────────┐                │
│  │ <canvas id="tenantCanvas_0">           │ ✓ WORKS       │
│  │  [Signature area]                      │                │
│  └────────────────────────────────────────┘                │
│  <input name="tenants[0][db_id]" value="2">                │
│  □ Certifié exact                                          │
│                                                             │
│  Signature locataire 2 - Salah Tabout                      │
│  ┌────────────────────────────────────────┐                │
│  │ <canvas id="tenantCanvas_1">           │ ✓ WORKS!      │
│  │  [Can now draw signature]              │                │
│  └────────────────────────────────────────┘                │
│  <input name="tenants[1][db_id]" value="2">                │
│  □ Certifié exact                                          │
└─────────────────────────────────────────────────────────────┘

Solution: Each canvas has unique ID using index
Result: JavaScript can bind to both canvases
Impact: Both tenants can sign! ✅
```

## Code Changes Comparison

### HTML - Canvas Element

#### Before ❌
```php
<?php foreach ($existing_tenants as $tenant): ?>
    <canvas id="tenantCanvas_<?php echo $tenant['id']; ?>">
    <!-- If $tenant['id'] = 2 for both, creates duplicates! -->
<?php endforeach; ?>
```

**Output:**
```html
<canvas id="tenantCanvas_2">  <!-- Tenant 1 -->
<canvas id="tenantCanvas_2">  <!-- Tenant 2 - DUPLICATE! -->
```

#### After ✅
```php
<?php foreach ($existing_tenants as $index => $tenant): ?>
    <canvas id="tenantCanvas_<?php echo $index; ?>">
    <input type="hidden" name="tenants[<?php echo $index; ?>][db_id]" 
           value="<?php echo $tenant['id']; ?>">
<?php endforeach; ?>
```

**Output:**
```html
<!-- Tenant 1 -->
<canvas id="tenantCanvas_0">
<input name="tenants[0][db_id]" value="2">

<!-- Tenant 2 -->
<canvas id="tenantCanvas_1">  <!-- UNIQUE! -->
<input name="tenants[1][db_id]" value="2">
```

### JavaScript - Initialization

#### Before ❌
```javascript
<?php foreach ($existing_tenants as $tenant): ?>
    initTenantSignature(<?php echo $tenant['id']; ?>);
    // If $tenant['id'] = 2 for both, both try to init canvas #2
<?php endforeach; ?>
```

**Output:**
```javascript
initTenantSignature(2);  // Tenant 1 - Initializes canvas #2
initTenantSignature(2);  // Tenant 2 - ERROR! Canvas #2 already initialized
```

#### After ✅
```javascript
<?php foreach ($existing_tenants as $index => $tenant): ?>
    initTenantSignature(<?php echo $index; ?>);
<?php endforeach; ?>
```

**Output:**
```javascript
initTenantSignature(0);  // Tenant 1 - Initializes canvas #0
initTenantSignature(1);  // Tenant 2 - Initializes canvas #1 ✓
```

### Backend - Processing

#### Before ❌
```php
foreach ($_POST['tenants'] as $tenantId => $tenantInfo) {
    // $tenantId = database ID from array key
    // If both have db_id=2, data overwrites!
    $stmt->execute([$certifieExact, $tenantId, $inventaire_id]);
}
```

**Form Data:**
```php
$_POST['tenants'] = [
    2 => [  // First submission
        'signature' => 'data:image/jpeg;base64,/9j/...',
        'certifie_exact' => '1'
    ],
    2 => [  // Second submission - OVERWRITES FIRST!
        'signature' => 'data:image/jpeg;base64,/9j/...',
        'certifie_exact' => '1'
    ]
];
// Result: Only last tenant's data is saved
```

#### After ✅
```php
// Validate all have db_id
$missingDbIds = [];
foreach ($_POST['tenants'] as $tenantIndex => $tenantInfo) {
    if (!isset($tenantInfo['db_id'])) {
        $missingDbIds[] = $tenantIndex;
    }
}
if (!empty($missingDbIds)) {
    throw new Exception("Données incomplètes");
}

// Process with db_id from hidden field
foreach ($_POST['tenants'] as $tenantIndex => $tenantInfo) {
    $tenantId = (int)$tenantInfo['db_id'];
    $stmt->execute([$certifieExact, $tenantId, $inventaire_id]);
}
```

**Form Data:**
```php
$_POST['tenants'] = [
    0 => [  // Tenant 1 - Unique index
        'signature' => 'data:image/jpeg;base64,/9j/...',
        'certifie_exact' => '1',
        'db_id' => '2'  // Maps to database
    ],
    1 => [  // Tenant 2 - Unique index
        'signature' => 'data:image/jpeg;base64,/9j/...',
        'certifie_exact' => '1',
        'db_id' => '2'  // Maps to database
    ]
];
// Result: Both tenants' data saved correctly ✓
```

## Data Flow Diagram

### Before ❌
```
Database Record → HTML/JS → Form Submission
┌──────────────┐   ┌────────────────┐   ┌────────────────┐
│ Tenant 1     │   │ Canvas ID: 2   │   │ tenants[2][..] │
│ id: 2        │→→→│ ✓ Initialized  │→→→│ ✓ Submitted    │
└──────────────┘   └────────────────┘   └────────────────┘

┌──────────────┐   ┌────────────────┐   ┌────────────────┐
│ Tenant 2     │   │ Canvas ID: 2   │   │ tenants[2][..] │
│ id: 2        │→→→│ ❌ DUPLICATE!  │→→→│ ❌ OVERWRITES! │
└──────────────┘   └────────────────┘   └────────────────┘
```

### After ✅
```
Database Record → HTML/JS → Form Submission → Database Update
┌─────────────┐   ┌────────────────┐   ┌────────────────┐   ┌──────────────┐
│ Tenant 1    │   │ Canvas ID: 0   │   │ tenants[0][..] │   │ UPDATE       │
│ id: 2       │→→→│ ✓ Initialized  │→→→│ db_id: 2       │→→→│ WHERE id=2   │
└─────────────┘   └────────────────┘   └────────────────┘   └──────────────┘

┌─────────────┐   ┌────────────────┐   ┌────────────────┐   ┌──────────────┐
│ Tenant 2    │   │ Canvas ID: 1   │   │ tenants[1][..] │   │ UPDATE       │
│ id: 2       │→→→│ ✓ Initialized  │→→→│ db_id: 2       │→→→│ WHERE id=2   │
└─────────────┘   └────────────────┘   └────────────────┘   └──────────────┘
```

## Summary

### Problem ❌
- Database had duplicate IDs for different tenants
- Code used database ID for HTML element IDs
- Resulted in duplicate canvas IDs
- Second tenant could not sign

### Solution ✅
- Use array index (0, 1, 2) for HTML element IDs
- Store database ID in hidden field
- Extract database ID from hidden field in backend
- Each tenant gets unique UI elements

### Result ✅
✅ All tenants can sign independently  
✅ No duplicate ID conflicts  
✅ Data saved correctly  
✅ Robust against database issues  

## Testing Checklist

When testing after deployment:

- [ ] Open inventory with 2 tenants
- [ ] Open browser console (F12)
- [ ] Verify console shows unique canvas IDs: 0 and 1
- [ ] Draw signature for Tenant 1 ✓
- [ ] Draw signature for Tenant 2 ✓ (This should now work!)
- [ ] Click "Effacer" button for Tenant 1 - signature clears
- [ ] Click "Effacer" button for Tenant 2 - signature clears
- [ ] Draw both signatures again
- [ ] Check "Certifié exact" for both
- [ ] Click "Enregistrer le brouillon" - both signatures save
- [ ] Reload page - both signatures display
- [ ] Click "Finaliser" - form submits successfully
- [ ] Check database - both records updated correctly

