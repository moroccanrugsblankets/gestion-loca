# Fix: Auto-Refused Candidatures Display Enhancement

## Date
2026-01-30

## Problem Statement

After running the migration script `php migrations/fix_auto_refused_candidatures.php`, users saw the message:
> "J'ai executé php migrations/fix_auto_refused_candidatures.php mais toujours aucune Réponses Automatiques Programmées : Aucune candidature en attente de réponse automatique."

**Translation:** "I executed the migration script but still no Scheduled Automatic Responses: No candidature waiting for automatic response."

## Root Cause

The issue was not a bug, but a **misunderstanding of expected behavior**:

1. The migration script successfully fixed 3 candidatures that were automatically refused at creation
2. These candidatures had `statut='refuse'` and were correctly updated to `reponse_automatique='refuse'`
3. The "Réponses Automatiques Programmées" section only shows candidatures with `statut='en_cours'` AND `reponse_automatique='en_attente'`
4. **Therefore, the auto-refused candidatures correctly do NOT appear in this section**

### Why Users Were Confused

1. The migration script message didn't explain that auto-refused candidatures won't appear in the pending list
2. There was no visual display showing these auto-refused candidatures exist
3. Users expected to see ALL candidatures somewhere in the cron-jobs interface

## Solution Implemented

### 1. Enhanced Migration Script Messaging

**File:** `migrations/fix_auto_refused_candidatures.php`

Added clarification after successful migration:

```
IMPORTANT:
- These candidatures will NOT appear in 'Réponses Automatiques Programmées' on the cron-jobs page.
- This is correct behavior: they were automatically refused at creation and are already processed.
- They appear in the candidatures list with statut='refuse'.
- Only candidatures with statut='en_cours' appear in 'Réponses Automatiques Programmées'.
- A new section 'Candidatures Auto-Refusées Récemment' has been added to show recent auto-refused candidatures.
```

### 2. New Display Section in Cron Jobs Page

**File:** `admin-v2/cron-jobs.php`

Added a new section **"Candidatures Auto-Refusées Récemment"** that displays:
- Candidatures automatically refused at submission (last 7 days)
- Reference, candidate name, email, property, submission date
- Refusal reason
- Link to view details

**Query:**
```php
SELECT 
    c.id, c.reference_unique, c.nom, c.prenom, c.email,
    c.created_at, c.statut, c.reponse_automatique, c.motif_refus,
    l.reference as logement_reference
FROM candidatures c
LEFT JOIN logements l ON c.logement_id = l.id
WHERE c.statut = 'refuse' 
AND c.reponse_automatique = 'refuse'
AND c.motif_refus IS NOT NULL
AND c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY c.created_at DESC
LIMIT 50
```

### 3. Test Script

**File:** `test-auto-refused-display.php`

Created a test script that verifies:
1. ✓ Query for pending automatic responses works
2. ✓ Query for recently auto-refused candidatures works
3. ✓ No mismatched candidatures exist (after migration)
4. ✓ Delay parameters are configured

## Visual Changes

### Before Fix

```
admin-v2/cron-jobs.php
├── Réponses Automatiques Programmées
│   └── Shows only candidatures with statut='en_cours'
└── Tâches Planifiées Configurées
    └── (Other cron jobs)

Problem: Auto-refused candidatures are invisible!
```

### After Fix

```
admin-v2/cron-jobs.php
├── Réponses Automatiques Programmées
│   └── Shows candidatures with statut='en_cours' (pending processing)
├── Candidatures Auto-Refusées Récemment (NEW!)
│   ├── Shows candidatures automatically refused at creation
│   ├── Displays refusal reason
│   ├── Shows last 7 days
│   └── Link to view each candidature
└── Tâches Planifiées Configurées
    └── (Other cron jobs)

Solution: Auto-refused candidatures are now visible!
```

## How the System Works

### Two-Stage Candidature Processing

#### Stage 1: Immediate Evaluation (at submission)

**File:** `candidature/submit.php`

When a candidature is submitted:
1. Evaluate against 6 criteria (income, employment, Visale guarantee, etc.)
2. If criteria NOT met:
   - Set `statut = 'refuse'`
   - Set `reponse_automatique = 'refuse'`
   - Send immediate rejection email
3. If criteria met:
   - Set `statut = 'en_cours'`
   - Set `reponse_automatique = 'en_attente'`
   - Wait for automatic processing

#### Stage 2: Delayed Acceptance (after configured delay)

**File:** `cron/process-candidatures.php`

After configured delay (e.g., 4 business days):
1. Process only candidatures with `statut='en_cours'` AND `reponse_automatique='en_attente'`
2. Send acceptance email
3. Update to `statut='accepte'` and `reponse_automatique='accepte'`

## Testing

### Manual Test Steps

1. **Run the test script:**
   ```bash
   php test-auto-refused-display.php
   ```

2. **Expected output:**
   ```
   Test 1: Query for pending automatic responses...
     ✓ Found X candidatures pending automatic response

   Test 2: Query for recently auto-refused candidatures...
     ✓ Found Y auto-refused candidatures in last 7 days

   Test 3: Check for mismatched candidatures...
     ✓ No mismatched candidatures found (correct!)

   Test 4: Check delay parameters...
     ✓ Delay configured: 4 jours

   === Test Summary ===
   ✓ All tests passed!
   ```

3. **View the cron-jobs page:**
   - Navigate to `/admin-v2/cron-jobs.php`
   - Should see "Réponses Automatiques Programmées" section with pending candidatures
   - Should see "Candidatures Auto-Refusées Récemment" section with auto-refused ones

### Database Verification

```sql
-- Should return 0 after migration
SELECT COUNT(*) FROM candidatures
WHERE statut = 'refuse' AND reponse_automatique = 'en_attente';

-- Should show auto-refused candidatures
SELECT id, reference_unique, motif_refus, created_at 
FROM candidatures
WHERE statut = 'refuse' 
AND reponse_automatique = 'refuse'
AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY);
```

## Files Modified

1. **migrations/fix_auto_refused_candidatures.php**
   - Enhanced success message with explanation
   - Clarified that auto-refused candidatures won't appear in pending list

2. **admin-v2/cron-jobs.php**
   - Added query for recently auto-refused candidatures
   - Added new UI section "Candidatures Auto-Refusées Récemment"
   - Added table displaying auto-refused candidatures with details

3. **test-auto-refused-display.php** (NEW)
   - Test script to verify queries work correctly
   - Validates data consistency
   - Provides summary of candidature states

## Benefits

1. ✅ **Clear Communication:** Migration script now explains expected behavior
2. ✅ **Visibility:** Auto-refused candidatures are now visible in admin interface
3. ✅ **Understanding:** Users can see why candidatures were refused
4. ✅ **Transparency:** Complete view of all candidature states
5. ✅ **Debugging:** Easy to verify system is working correctly

## Related Documentation

- `AUTO_REFUSED_CANDIDATURES_FIX.md` - Original fix for data inconsistency
- `AUTOMATIC_EMAILS_FIX.md` - Automatic email template integration
- `CANDIDATURE_STATUS_SYNC_FIX.md` - Status synchronization fix

## Notes

- Auto-refused candidatures are kept for 7 days in the display (configurable)
- They remain in the database permanently for record-keeping
- The query uses `DATE_SUB(NOW(), INTERVAL 7 DAY)` for performance
- Limit of 50 candidatures prevents page overload
