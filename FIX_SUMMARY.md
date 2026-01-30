# Fix Summary: Auto-Refused Candidatures Status Synchronization

## ✅ Problem Resolved

**Issue:** "nouvelle candidature marquée comme refusée mais j'ai toujours le message : Aucune candidature en attente de réponse automatique."

The system was creating new candidatures with `statut = 'refuse'` (automatically rejected) but leaving `reponse_automatique = 'en_attente'`, causing a status mismatch.

## ✅ Solution Applied

### Changes Made

1. **candidature/submit.php** (10 lines modified)
   - Added logic to set `reponse_automatique` based on evaluation result
   - Automatically refused candidatures now get `reponse_automatique = 'refuse'`
   - Candidatures that pass evaluation get `reponse_automatique = 'en_attente'`

2. **migrations/fix_auto_refused_candidatures.php** (NEW)
   - Migration script to fix existing mismatched records in the database

3. **AUTO_REFUSED_CANDIDATURES_FIX.md** (NEW)
   - Comprehensive documentation of the problem and solution

## 🔧 Deployment Steps

### 1. Deploy the Code
Merge this PR to deploy the fix to production.

### 2. Run the Migration Script
After deployment, run the migration to fix existing data:

```bash
# Navigate to the project directory
cd /path/to/contrat-de-bail

# Run the migration script
php migrations/fix_auto_refused_candidatures.php
```

The script will:
- Show you all mismatched candidatures
- Ask for confirmation
- Fix the records by setting `reponse_automatique = 'refuse'`

### 3. Verify the Fix

**Database Verification:**
```sql
-- This should return 0 rows after the fix
SELECT id, reference_unique, statut, reponse_automatique
FROM candidatures
WHERE statut = 'refuse'
AND reponse_automatique = 'en_attente';
```

**Manual Testing:**
1. Create a test candidature with salary < 3000€
2. Check the database: should have `statut = 'refuse'` AND `reponse_automatique = 'refuse'`
3. Go to the Cron Jobs page (`admin-v2/cron-jobs.php`)
4. Verify the candidature does NOT appear in "Réponses Automatiques Programmées"

## 📋 What This Fix Does

### Before the Fix
```
New Candidature Created (salary < 3000€)
├─ statut: 'refuse' ✓
└─ reponse_automatique: 'en_attente' ✗ (WRONG)
```

### After the Fix
```
New Candidature Created (salary < 3000€)
├─ statut: 'refuse' ✓
└─ reponse_automatique: 'refuse' ✓ (CORRECT)
```

## 🔍 How It Works

The fix ensures that when a candidature is created:

1. **If automatically refused** (doesn't meet criteria):
   - `statut = 'refuse'`
   - `reponse_automatique = 'refuse'` ← NEW
   - Won't appear in pending automatic responses list
   - Correctly marked as already processed

2. **If passes evaluation** (meets all criteria):
   - `statut = 'en_cours'`
   - `reponse_automatique = 'en_attente'`
   - WILL appear in pending automatic responses list
   - Will be processed by cron job after configured delay

## 📊 Impact

- ✅ No more status mismatches for new candidatures
- ✅ Cron Jobs page shows accurate information
- ✅ Existing data can be fixed with migration script
- ✅ Minimal code changes (surgical fix)
- ✅ No breaking changes
- ✅ Backward compatible

## 🔗 Related Fixes

This fix complements existing fixes:
- **CANDIDATURE_STATUS_SYNC_FIX.md**: Handles manual status changes via admin
- **candidature/reponse-candidature.php**: Handles email token responses

Together, these ensure `statut` and `reponse_automatique` stay synchronized across all scenarios.

## 📝 Files Modified

```
candidature/submit.php                       (modified, +10 lines)
migrations/fix_auto_refused_candidatures.php (new, 96 lines)
AUTO_REFUSED_CANDIDATURES_FIX.md            (new, 175 lines)
```

## ✅ Quality Checks Completed

- [x] Code review (no issues found)
- [x] PHP syntax validation
- [x] CodeQL security check
- [x] Documentation created
- [x] Migration script created

## 🎯 Next Steps

1. Review and merge this PR
2. Deploy to production
3. Run the migration script: `php migrations/fix_auto_refused_candidatures.php`
4. Verify with test candidatures
5. Monitor the Cron Jobs page to ensure it shows correct data

---

**Date:** 2026-01-30
**Fixed in PR:** #[PR_NUMBER]
