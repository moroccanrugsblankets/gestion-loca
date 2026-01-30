# Fix: Auto-Refused Candidatures Status Synchronization

## Problem Statement

**French:** "nouvelle candidature marquée comme refusée mais j'ai toujours le message : Aucune candidature en attente de réponse automatique."

**English Translation:** "New application marked as refused but I still have the message: No application pending automatic response."

## Root Cause

When a new candidature was submitted and automatically refused (e.g., salary < 3000€, missing Visale guarantee, etc.), the system would:

1. Evaluate the candidature using `evaluateCandidature()` function
2. Set `statut = 'refuse'` if criteria were not met
3. Insert the candidature into the database
4. **BUT** the `reponse_automatique` field would default to `'en_attente'`

This created a mismatch:
- `statut = 'refuse'` (correctly marked as refused)
- `reponse_automatique = 'en_attente'` (incorrectly marked as pending)

### Impact

On the Cron Jobs page (`admin-v2/cron-jobs.php`), the query to show pending automatic responses is:

```sql
SELECT ... FROM candidatures c
WHERE c.statut = 'en_cours' 
AND c.reponse_automatique = 'en_attente'
```

Since automatically refused candidatures have:
- `statut = 'refuse'` (not 'en_cours')
- `reponse_automatique = 'en_attente'`

They don't match the query, so they correctly don't appear in the pending list. However, this creates confusion because:
1. The candidature exists and is marked as refused
2. The admin might expect to see it somewhere in the system
3. The `reponse_automatique = 'en_attente'` suggests it's still waiting for processing

## Solution

### Code Changes

Modified `candidature/submit.php` to properly set `reponse_automatique` field when creating a new candidature:

**Before:**
```php
// Evaluate candidature immediately to determine initial status
$evaluation = evaluateCandidature($candidatureData);
$initialStatut = $evaluation['statut'];
$motifRefus = $evaluation['motif'];

// Insert candidature
$stmt = $pdo->prepare("
    INSERT INTO candidatures (
        ..., statut, motif_refus, date_soumission
    ) VALUES (..., ?, ?, NOW())
");
$stmt->execute([..., $initialStatut, $motifRefus ?: null]);
```

**After:**
```php
// Evaluate candidature immediately to determine initial status
$evaluation = evaluateCandidature($candidatureData);
$initialStatut = $evaluation['statut'];
$motifRefus = $evaluation['motif'];

// Set reponse_automatique based on evaluation result
// If automatically refused, mark as processed; otherwise, pending automatic response
$reponseAutomatique = ($initialStatut === 'refuse') ? 'refuse' : 'en_attente';

// Insert candidature with proper reponse_automatique value
$stmt = $pdo->prepare("
    INSERT INTO candidatures (
        ..., statut, motif_refus, reponse_automatique, date_soumission
    ) VALUES (..., ?, ?, ?, NOW())
");
$stmt->execute([..., $initialStatut, $motifRefus ?: null, $reponseAutomatique]);
```

### Logic Explanation

The fix ensures:

1. **For candidatures that pass initial evaluation:**
   - `statut = 'en_cours'`
   - `reponse_automatique = 'en_attente'`
   - These will appear in the pending automatic responses list
   - They will be processed by the cron job after the configured delay

2. **For candidatures that fail initial evaluation:**
   - `statut = 'refuse'`
   - `reponse_automatique = 'refuse'`
   - These will NOT appear in the pending list (correctly)
   - They are already processed and don't need further action

### Migration Script

For existing candidatures that were created before this fix, a migration script is provided:

**File:** `migrations/fix_auto_refused_candidatures.php`

**Usage:**
```bash
php migrations/fix_auto_refused_candidatures.php
```

This script will:
1. Find all candidatures with `statut = 'refuse'` and `reponse_automatique = 'en_attente'`
2. Display them for review
3. Ask for confirmation
4. Update `reponse_automatique = 'refuse'` for these candidatures
5. Set `date_reponse_auto = created_at` to indicate when they were processed

## Files Modified

1. **candidature/submit.php**
   - Added logic to set `reponse_automatique` based on evaluation result
   - Updated INSERT statement to include `reponse_automatique` field
   - Updated execute() parameters to include the new value

2. **migrations/fix_auto_refused_candidatures.php** (NEW)
   - Migration script to fix existing data

## Testing

### Manual Testing Steps

1. **Create a candidature that meets all criteria:**
   - Salary >= 3000€
   - CDI or CDD
   - Visale guarantee: Yes
   - Verify in database: `statut = 'en_cours'`, `reponse_automatique = 'en_attente'`

2. **Create a candidature with salary < 3000€:**
   - Verify in database: `statut = 'refuse'`, `reponse_automatique = 'refuse'`

3. **Check Cron Jobs page:**
   - Should only show candidatures with `statut = 'en_cours'` AND `reponse_automatique = 'en_attente'`
   - Should NOT show the refused candidature from step 2

### Database Query for Verification

```sql
-- Check for mismatched candidatures (should return 0 after fix)
SELECT id, reference_unique, statut, reponse_automatique, motif_refus, created_at
FROM candidatures
WHERE statut = 'refuse'
AND reponse_automatique = 'en_attente';

-- Check correctly processed candidatures
SELECT id, reference_unique, statut, reponse_automatique, created_at
FROM candidatures
WHERE statut = 'refuse'
AND reponse_automatique = 'refuse'
ORDER BY created_at DESC
LIMIT 10;
```

## Related Issues

This fix complements the existing fix in `CANDIDATURE_STATUS_SYNC_FIX.md` which handles:
- Manual status changes via admin interface
- Email token responses

Together, these fixes ensure that `statut` and `reponse_automatique` fields are always synchronized, regardless of how the candidature status is set:
1. **At creation** (this fix)
2. **Manual changes** (existing fix)
3. **Automatic processing** (already correct in cron job)

## Date

2026-01-30
