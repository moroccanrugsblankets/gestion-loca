# Fix: Inventory Tenant Signature Duplication

## Problem
When tenant 2 signs in an inventory, their signature is saved for both tenants. The root cause is duplicate tenant records in the `inventaire_locataires` table with the same `locataire_id`.

## Solution Summary
1. **Immediate Fix**: Automatic duplicate detection and removal on page load
2. **Long-term Fix**: Database UNIQUE constraint to prevent future duplicates
3. **Enhanced Logging**: Track all tenant insertions and duplicates

## How to Apply the Fix

### Step 1: Run the Migration (REQUIRED)

Run migration 052 to clean up existing duplicates and add the UNIQUE constraint:

```bash
cd /path/to/gestion-loca
php migrations/052_add_unique_constraint_inventaire_locataires.php
```

**What this does:**
- Finds all duplicate tenant records (same inventaire_id + locataire_id)
- Keeps the oldest record, deletes duplicates
- Adds UNIQUE constraint to prevent future duplicates

### Step 2: Verify the Fix

1. **Check for remaining duplicates:**
   ```sql
   SELECT inventaire_id, locataire_id, COUNT(*) as count
   FROM inventaire_locataires
   WHERE locataire_id IS NOT NULL
   GROUP BY inventaire_id, locataire_id
   HAVING count > 1;
   ```
   This should return **0 rows** after migration.

2. **Verify UNIQUE constraint exists:**
   ```sql
   SHOW CREATE TABLE inventaire_locataires;
   ```
   You should see `UNIQUE KEY unique_inventaire_locataire (inventaire_id, locataire_id)`

### Step 3: Test with Inventory

1. Open an inventory with 2 tenants in edit mode
2. Check browser console for logs - you should see:
   - No "DUPLICATE TENANT DETECTED" warnings
   - Each tenant with a unique ID
3. Sign as tenant 1, save
4. Sign as tenant 2, save
5. Verify each tenant's signature is saved correctly

## How the Fix Works

### Defensive Code (Automatic)
Every time `edit-inventaire.php` loads, it:
1. Fetches all tenant records for the inventory
2. Checks for duplicates (same locataire_id)
3. Automatically removes duplicates, keeping the oldest
4. Logs all actions for debugging

### Database Constraint (Permanent)
The UNIQUE constraint on `(inventaire_id, locataire_id)` prevents:
- Accidental duplicate insertions
- Race conditions
- Manual data corruption

### Enhanced Logging
All tenant operations now log:
- When tenants are inserted
- When duplicates are detected
- When constraint violations occur

## Troubleshooting

### Issue: Migration fails with "duplicate key" error
**Solution:** Duplicates exist but weren't cleaned up. Run this SQL manually:
```sql
-- Find duplicates
SELECT inventaire_id, locataire_id, GROUP_CONCAT(id) as ids
FROM inventaire_locataires
WHERE locataire_id IS NOT NULL
GROUP BY inventaire_id, locataire_id
HAVING COUNT(*) > 1;

-- For each group, delete all but the first ID
-- (Replace X,Y with the duplicate IDs, keeping the smallest one)
DELETE FROM inventaire_locataires WHERE id IN (Y, Z, ...);
```

### Issue: Still seeing duplicate signatures after fix
**Possible causes:**
1. Migration not run yet → Run migration 052
2. Browser cache → Clear cache and refresh
3. Old signature data → Delete existing signatures and re-sign

### Issue: "Cannot insert duplicate key" error
**This is GOOD!** It means the constraint is working. Check:
- Why is the same tenant being added twice?
- Is there a bug in the contract tenant list?
- Manual data entry error?

## What Changed

### Files Modified:
1. **admin-v2/edit-inventaire.php**
   - Lines 286-316: Duplicate detection and removal
   - Lines 339-363: Enhanced insertion logging and error handling

2. **migrations/052_add_unique_constraint_inventaire_locataires.php**
   - New migration file for cleanup and constraint

### Database Schema Change:
```sql
ALTER TABLE inventaire_locataires
ADD CONSTRAINT unique_inventaire_locataire
UNIQUE KEY (inventaire_id, locataire_id);
```

## Benefits

✅ **No more duplicate signatures** - Each tenant gets their own record  
✅ **Automatic cleanup** - Existing duplicates removed on page load  
✅ **Database protection** - Constraint prevents future duplicates  
✅ **Better debugging** - Comprehensive logging tracks all operations  
✅ **No data loss** - Oldest record kept when removing duplicates  

## Notes

- The fix is **backward compatible** - existing inventories will work
- Duplicate detection runs **automatically** - no manual intervention needed
- The UNIQUE constraint is **permanent** - protects database integrity
- Logging helps **diagnose** any future issues
