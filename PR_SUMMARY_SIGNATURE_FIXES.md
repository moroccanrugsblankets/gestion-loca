# Summary: Tenant Signature Bug Fixes

This PR addresses TWO separate signature bugs:

## 1. Contract Signature Bug (Already Fixed)
**Issue:** Tenant 2's signature filename collision when signing within the same second as Tenant 1.

**Fix Applied:**
- Changed filename generation from `time()` to `microtime(true)` with sprintf for microsecond precision
- Added validation checks before saving signature
- Added comprehensive logging
- Files: `includes/functions.php`, `signature/step2-signature.php`

## 2. Inventory Signature Bug (Main Focus)
**Issue:** When tenant 2 signs in inventory, signature is saved for BOTH tenants because duplicate records exist in database.

**Root Cause:** 
- No UNIQUE constraint on `inventaire_locataires` table
- Same `locataire_id` can be inserted multiple times for same inventory
- Both tenants end up with same database ID

**Fix Applied:**
1. **Defensive Code** (admin-v2/edit-inventaire.php):
   - Automatic duplicate detection on page load
   - Removes duplicates, keeps oldest record
   - Enhanced logging for all tenant operations
   - Exception handling for constraint violations

2. **Database Migration** (migrations/052_add_unique_constraint_inventaire_locataires.php):
   - Cleans up existing duplicate records
   - Adds UNIQUE constraint on (inventaire_id, locataire_id)
   - Prevents future duplicates at database level

3. **Documentation** (FIX_INVENTORY_TENANT_SIGNATURE_DUPLICATION.md):
   - Step-by-step application guide
   - Troubleshooting instructions
   - Verification queries

## Installation Steps

### For Contract Signatures (Already Applied):
✅ No action needed - fixes are already in the code

### For Inventory Signatures (REQUIRED):

1. **Run the migration:**
   ```bash
   php migrations/052_add_unique_constraint_inventaire_locataires.php
   ```

2. **Verify success:**
   ```sql
   -- Should return 0 rows
   SELECT inventaire_id, locataire_id, COUNT(*) 
   FROM inventaire_locataires 
   WHERE locataire_id IS NOT NULL
   GROUP BY inventaire_id, locataire_id 
   HAVING COUNT(*) > 1;
   
   -- Should show UNIQUE constraint
   SHOW CREATE TABLE inventaire_locataires;
   ```

3. **Test with inventory:**
   - Open inventory with 2 tenants
   - Sign as tenant 1, save
   - Sign as tenant 2, save
   - Verify each signature saved correctly

## What Was Changed

### Contract Signatures:
- `includes/functions.php` - updateTenantSignature() function
- `signature/step2-signature.php` - Validation and logging

### Inventory Signatures:
- `admin-v2/edit-inventaire.php` - Duplicate detection and cleanup
- `migrations/052_add_unique_constraint_inventaire_locataires.php` - New migration
- `FIX_INVENTORY_TENANT_SIGNATURE_DUPLICATION.md` - Documentation

## Expected Behavior After Fix

### Contract Signatures:
✅ Each tenant gets unique filename with microsecond timestamp  
✅ No filename collisions even if signing simultaneously  
✅ Validation ensures correct tenant ID before saving  
✅ Comprehensive logging tracks all signature operations  

### Inventory Signatures:
✅ No duplicate tenant records in database  
✅ Each tenant gets own signature canvas  
✅ Signatures saved to correct tenant records  
✅ Database constraint prevents future duplicates  
✅ Automatic cleanup on page load  
✅ Better error messages and logging  

## Monitoring

After deployment, check logs for:
- "DUPLICATE TENANT DETECTED" - should NOT appear after migration
- "Successfully inserted inventaire_locataires record" - normal operation
- "Duplicate key prevented insertion" - constraint is working (investigate why duplicate was attempted)

## Rollback Plan

If issues occur:

### Contract Signatures:
Revert commits related to `updateTenantSignature()` and step2-signature.php

### Inventory Signatures:
```sql
-- Remove the UNIQUE constraint if needed
ALTER TABLE inventaire_locataires 
DROP INDEX unique_inventaire_locataire;
```

Then revert code changes to edit-inventaire.php

## Support

For issues or questions:
1. Check `FIX_INVENTORY_TENANT_SIGNATURE_DUPLICATION.md` for troubleshooting
2. Review error logs for specific error messages
3. Run verification SQL queries to check database state
4. Contact development team with specific error details

## Files Modified

1. includes/functions.php
2. signature/step2-signature.php
3. admin-v2/edit-inventaire.php
4. migrations/052_add_unique_constraint_inventaire_locataires.php
5. FIX_INVENTORY_TENANT_SIGNATURE_DUPLICATION.md (new)
6. PR_SUMMARY_SIGNATURE_FIXES.md (this file)
