# Testing Guide: Tenant Canvas ID Fix

## Overview

This document provides step-by-step testing instructions to verify the tenant signature canvas ID duplication fix.

## Prerequisites

- Access to admin panel `/admin-v2/`
- An inventaire with 2 or more tenants
- Browser with JavaScript console (F12)

## Test Scenario 1: Visual Verification

### Steps:
1. Navigate to `/admin-v2/inventaires.php`
2. Find inventaire ID 3 (or any with multiple tenants)
3. Click "Modifier" to open edit page
4. View page source (Ctrl+U or right-click → View Page Source)

### Expected Results:

**✅ Canvas IDs Should Be:**
```html
<!-- Tenant 1 -->
<canvas id="tenantCanvas_0" ...></canvas>

<!-- Tenant 2 -->
<canvas id="tenantCanvas_1" ...></canvas>
```

**❌ Should NOT Be (old bug):**
```html
<!-- Tenant 1 -->
<canvas id="tenantCanvas_4" ...></canvas>

<!-- Tenant 2 -->
<canvas id="tenantCanvas_4" ...></canvas>  <!-- DUPLICATE! -->
```

### Verification:
Search in page source for "tenantCanvas_" and verify:
- [ ] Each canvas has a unique ID (0, 1, 2, ...)
- [ ] No duplicate canvas IDs exist
- [ ] Each tenant has a hidden `db_id` field

## Test Scenario 2: Console Output

### Steps:
1. Open edit inventaire page
2. Open browser console (F12)
3. Check console output during page load

### Expected Results:

**✅ Success Output:**
```
=== INVENTAIRE TENANT SIGNATURE INITIALIZATION ===
Total tenants to initialize: 2
Initializing Tenant 1: Index=0, DB_ID=4, Name=Tabout Salah, Canvas=tenantCanvas_0
Initializing Tenant 2: Index=1, DB_ID=5, Name=James Dupont, Canvas=tenantCanvas_1
Initialized canvas indices: [0, 1]
=== INITIALIZATION COMPLETE ===
```

**❌ Error Output (should NOT appear):**
```
⚠️  CRITICAL: Duplicate canvas ID detected! Tenant ID 4 already initialized.
```

### Verification:
- [ ] No duplicate canvas ID errors
- [ ] Each tenant shows unique index (0, 1, ...)
- [ ] DB_ID values match database records
- [ ] All canvases initialize successfully

## Test Scenario 3: Independent Signing

### Steps:
1. Open edit inventaire page
2. Sign in Tenant 1's signature canvas:
   - Click and drag in first canvas to draw
   - Verify signature appears
3. Sign in Tenant 2's signature canvas:
   - Click and drag in second canvas to draw
   - Verify signature appears
4. Click "Enregistrer le brouillon"
5. Reload page

### Expected Results:

**✅ Both Signatures Should:**
- Be drawable without interference
- Appear in their respective canvases
- Persist after page reload
- Show "Signé le [date]" badge

**❌ Should NOT:**
- Tenant 2 canvas unresponsive
- Signatures overwriting each other
- Data loss on reload
- Error messages appearing

### Verification:
- [ ] Tenant 1 can sign independently
- [ ] Tenant 2 can sign independently
- [ ] Both signatures save correctly
- [ ] Both signatures reload correctly
- [ ] No console errors

## Test Scenario 4: Form Submission

### Steps:
1. Open edit inventaire page with 2 tenants
2. Sign both canvases
3. Check both "Certifié exact" checkboxes
4. Click "Finaliser et envoyer"
5. Check database records

### Expected Results:

**✅ Database Should Show:**
```sql
SELECT id, nom, prenom, signature, certifie_exact 
FROM inventaire_locataires 
WHERE inventaire_id = 3;

| id | nom    | prenom | signature               | certifie_exact |
|----|--------|--------|-------------------------|----------------|
| 4  | Tabout | Salah  | uploads/signatures/...  | 1              |
| 5  | Dupont | James  | uploads/signatures/...  | 1              |
```

**❌ Should NOT Show:**
- Only one signature saved
- NULL signatures after submission
- certifie_exact = 0 for any tenant
- Wrong tenant data

### Verification:
- [ ] Both tenants have signature files
- [ ] Both have certifie_exact = 1
- [ ] Signature file paths are unique
- [ ] Files exist on disk
- [ ] No database errors

## Test Scenario 5: Validation

### Steps:
1. Open edit inventaire page
2. Sign only Tenant 1 (leave Tenant 2 unsigned)
3. Check "Certifié exact" for Tenant 1 only
4. Try to click "Finaliser et envoyer"

### Expected Results:

**✅ Validation Should:**
- Block form submission
- Show error message:
  ```
  Erreurs de validation :
  • La signature de James Dupont est obligatoire
  • La case "Certifié exact" doit être cochée pour James Dupont
  ```
- Scroll to top to show errors
- Keep Tenant 1's signature intact

**❌ Should NOT:**
- Submit incomplete data
- Clear existing signatures
- Allow finalization without all signatures

### Verification:
- [ ] Form blocks submission when incomplete
- [ ] Error messages are clear and specific
- [ ] Existing signatures are preserved
- [ ] Can complete and submit after fixing

## Test Scenario 6: Draft Save

### Steps:
1. Open edit inventaire page
2. Sign Tenant 1 only
3. Click "Enregistrer le brouillon" (not Finaliser)
4. Reload page

### Expected Results:

**✅ Draft Save Should:**
- Save Tenant 1's signature
- Not require all signatures
- Not require "Certifié exact" checked
- Show success message
- Preserve incomplete state

### Verification:
- [ ] Draft saves with partial signatures
- [ ] Tenant 1 signature persists
- [ ] Tenant 2 remains unsigned
- [ ] No validation errors on draft save

## Test Scenario 7: Error Recovery

### Steps:
1. Open edit inventaire page
2. Simulate network error (throttle connection in DevTools)
3. Try to save with signatures
4. Observe error handling

### Expected Results:

**✅ Error Handling Should:**
- Show appropriate error message
- Not lose signature data
- Allow retry after fixing
- Maintain form state

### Verification:
- [ ] Graceful error messages
- [ ] Data not lost on error
- [ ] Can retry successfully
- [ ] Transaction rollback works

## Test Scenario 8: Multiple Tenants (3+)

### Steps:
1. Find or create inventaire with 3+ tenants
2. Open edit page
3. Verify all canvases work independently

### Expected Results:

**✅ All Tenants Should:**
- Have unique canvas IDs (0, 1, 2, ...)
- Sign independently
- Save correctly
- Validate properly

### Verification:
- [ ] Canvas IDs: tenantCanvas_0, 1, 2, ...
- [ ] All signatures work
- [ ] All save correctly
- [ ] Validation works for all

## Database Verification Commands

```bash
# Check PHP syntax
php -l admin-v2/edit-inventaire.php

# Run verification script (if database configured)
php verify-inventaire-tenant-signatures.php 3

# Check database records
mysql -u user -p database_name <<EOF
SELECT 
    il.id, 
    il.inventaire_id,
    il.locataire_id,
    il.nom,
    il.prenom,
    il.signature,
    il.certifie_exact
FROM inventaire_locataires il
WHERE inventaire_id = 3
ORDER BY il.id;
EOF
```

## Regression Testing

Verify related functionality still works:

### Equipment Section
- [ ] Equipment fields work correctly
- [ ] Entry/Exit data saves properly
- [ ] Duplicate button functions

### Other Fields
- [ ] Observations générales saves
- [ ] Lieu de signature saves
- [ ] Date inventaire displays correctly

## Browser Compatibility

Test in multiple browsers:
- [ ] Chrome/Edge (Chromium)
- [ ] Firefox
- [ ] Safari (if available)

Verify:
- Canvas drawing works
- Touch events work (mobile/tablet)
- Form submission succeeds
- No JavaScript errors

## Performance Testing

### Large Inventories
1. Test with 5+ tenants
2. Verify:
   - [ ] Page loads quickly
   - [ ] No memory issues
   - [ ] Signatures responsive
   - [ ] Save completes in reasonable time

## Success Criteria

**All tests MUST pass:**
- ✅ No duplicate canvas IDs
- ✅ No console errors
- ✅ All tenants can sign independently
- ✅ All signatures save correctly
- ✅ All signatures persist on reload
- ✅ Validation works properly
- ✅ Draft save works
- ✅ Error handling works

## Failure Scenarios to Test

### What Could Still Go Wrong?

1. **Database query returns no tenants**
   - Expected: Show empty state
   - [ ] Verify no JavaScript errors

2. **One tenant already signed**
   - Expected: Show existing signature, allow editing
   - [ ] Verify signature displays correctly

3. **Signature too large**
   - Expected: Show error, don't save oversized data
   - [ ] Verify size limit enforcement

4. **Session expires during edit**
   - Expected: Redirect to login, preserve data if possible
   - [ ] Verify graceful handling

## Conclusion

If all tests pass:
- ✅ Fix is working correctly
- ✅ No regressions introduced
- ✅ Ready for production

If any test fails:
- ❌ Review failure details
- ❌ Check console for errors
- ❌ Verify database state
- ❌ Report issue for investigation
