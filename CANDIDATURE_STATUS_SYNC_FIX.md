# Fix: Candidature Status Synchronization Issue

## Problem Description

When a candidature was manually marked as "Refusée" (refused) on the admin page `admin-v2/candidatures.php`, the following issue occurred:

- The candidature was correctly displayed as "Refusée" on the candidatures list page
- However, on the Cron Jobs page (`admin-v2/cron-jobs.php`), the message "Aucune candidature en attente de réponse automatique" was shown, even though there should have been candidatures pending automatic processing

## Root Cause

The issue was caused by a **desynchronization** between two database fields:

1. **`statut`** - The main status field (values: 'en_cours', 'accepte', 'refuse', etc.)
2. **`reponse_automatique`** - The automatic response tracking field (values: 'en_attente', 'accepte', 'refuse')

### What was happening:

When an admin manually changed a candidature status to 'refuse' or 'accepte', only the `statut` field was updated. The `reponse_automatique` field remained as 'en_attente'.

The cron jobs page shows pending candidatures using this query:
```sql
SELECT ... FROM candidatures
WHERE statut = 'en_cours' 
AND reponse_automatique = 'en_attente'
```

Since manually refused candidatures had:
- `statut = 'refuse'` (not 'en_cours')
- `reponse_automatique = 'en_attente'`

They didn't match the query, leading to the confusing behavior.

## Solution

The fix ensures that when a candidature status is changed to 'accepte' or 'refuse', the `reponse_automatique` field is also updated to match. This prevents duplicate automatic processing and ensures proper tracking.

### Files Modified:

1. **`admin-v2/change-status.php`**
   - Updated the status change logic to also set `reponse_automatique` when changing to 'accepte' or 'refuse'
   - Also sets `date_reponse_auto` timestamp

2. **`candidature/reponse-candidature.php`**
   - Updated the email token response handler to also set `reponse_automatique`
   - Ensures consistency when candidates respond via email links

### Changes Made:

#### Before:
```php
$stmt = $pdo->prepare("UPDATE candidatures SET statut = ? WHERE id = ?");
$stmt->execute([$nouveau_statut, $candidature_id]);
```

#### After:
```php
if ($nouveau_statut === 'accepte' || $nouveau_statut === 'refuse') {
    $stmt = $pdo->prepare("UPDATE candidatures SET statut = ?, reponse_automatique = ?, date_reponse_auto = NOW() WHERE id = ?");
    $stmt->execute([$nouveau_statut, $nouveau_statut, $candidature_id]);
} else {
    $stmt = $pdo->prepare("UPDATE candidatures SET statut = ? WHERE id = ?");
    $stmt->execute([$nouveau_statut, $candidature_id]);
}
```

## Migration Script

A migration script has been created to fix existing mismatched records in the database:

**File:** `migrations/fix_candidature_status_mismatch.php`

### How to run:

```bash
php migrations/fix_candidature_status_mismatch.php
```

This script will:
1. Find all candidatures with `statut = 'accepte' OR 'refuse'` but `reponse_automatique = 'en_attente'`
2. Ask for confirmation
3. Update the `reponse_automatique` field to match the `statut`
4. Set the `date_reponse_auto` timestamp

## Testing

After deploying this fix:

1. **Manual Status Change Test:**
   - Go to a candidature with status 'en_cours'
   - Change status to 'refuse' via the admin interface
   - Verify that both `statut` and `reponse_automatique` are set to 'refuse' in the database

2. **Cron Page Test:**
   - Go to `admin-v2/cron-jobs.php`
   - Verify that manually refused/accepted candidatures do NOT appear in the "Réponses Automatiques Programmées" section
   - Only candidatures with `statut = 'en_cours' AND reponse_automatique = 'en_attente'` should appear

3. **Email Response Test:**
   - Send a candidature acceptance/rejection email with token
   - Click the accept/reject link
   - Verify that both fields are updated correctly

## Impact

- **Positive:** No more confusion about pending automatic responses
- **Positive:** Prevents duplicate processing of candidatures
- **Positive:** Accurate tracking of how candidatures were processed (manual vs automatic)
- **Minimal:** Only affects the specific fields related to status changes

## Backward Compatibility

This fix is backward compatible. Existing candidatures with mismatched status can be fixed using the migration script. The cron job (`cron/process-candidatures.php`) already correctly sets both fields, so no changes were needed there.

## Related Files

- `admin-v2/change-status.php` - Manual status changes
- `candidature/reponse-candidature.php` - Email token responses
- `admin-v2/cron-jobs.php` - Cron jobs monitoring page
- `cron/process-candidatures.php` - Automatic candidature processing (already correct)
- `migrations/fix_candidature_status_mismatch.php` - Migration script

## Date
2026-01-30
