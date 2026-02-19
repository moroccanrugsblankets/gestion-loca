# Soft Delete & Cron Job Implementation Guide

## Overview

This implementation addresses two critical issues in the gestion-loca application:

1. **Automatic Cron Job Execution**: The rappel-loyers.php cron job was not registered in the system
2. **Soft Delete Pattern**: The application now uses soft deletes instead of permanent DELETE queries
3. **File Preservation**: Generated PDF files and documents are preserved, not physically deleted

## Problem Statement

**Original Issues**:
- The cron job defined in `/admin-v2/configuration-rappels-loyers.php` did not execute automatically
- Application used DELETE queries throughout, causing permanent data loss
- PDF files were physically deleted with `unlink()`, removing audit trail

## Solution Implemented

### 1. Cron Job Registration (Migration 059)

**File**: `migrations/059_add_rappel_loyers_cron_job.sql`

**Changes**:
- Adds rappel-loyers cron job to `cron_jobs` table
- Creates required configuration parameters in `parametres` table
- Sets default execution time to 09:00 daily

**Parameters Added**:
- `rappel_loyers_dates_envoi`: [7, 9, 15] - Days of month to send reminders
- `rappel_loyers_destinataires`: [] - Admin emails to receive reminders
- `rappel_loyers_actif`: true - Enable/disable automatic reminders
- `rappel_loyers_inclure_bouton`: true - Include button in emails
- `rappel_loyers_heure_execution`: "09:00" - Time of day to execute

### 2. Soft Delete Implementation (Migration 060)

**File**: `migrations/060_add_soft_delete_columns.sql`

**Changes**:
- Adds `deleted_at TIMESTAMP` column to 13 tables
- Creates unique indexes (e.g., `idx_candidatures_deleted_at`)
- NULL value = active record
- NOT NULL value = soft-deleted record

**Tables Modified**:
1. candidatures
2. contrats
3. logements
4. inventaires
5. etats_lieux
6. quittances
7. administrateurs
8. inventaire_categories
9. inventaire_sous_categories
10. inventaire_equipements
11. etat_lieux_photos
12. candidature_documents
13. inventaire_locataires

### 3. Delete Operations Updated (11 Files)

All DELETE operations now use UPDATE queries:

**Before**:
```php
DELETE FROM candidatures WHERE id = ?
```

**After**:
```php
UPDATE candidatures SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL
```

**Files Updated**:
1. `admin-v2/delete-candidature.php`
2. `admin-v2/delete-quittance.php`
3. `admin-v2/delete-etat-lieux.php`
4. `admin-v2/delete-inventaire.php`
5. `admin-v2/supprimer-contrat.php`
6. `admin-v2/delete-etat-lieux-photo.php`
7. `admin-v2/delete-bilan-justificatif.php`
8. `admin-v2/administrateurs-actions.php`
9. `admin-v2/logements.php`
10. `admin-v2/manage-categories.php`
11. `admin-v2/edit-inventaire.php`
12. `admin-v2/manage-inventory-equipements.php`

### 4. File Preservation

**PDF Files**: No longer deleted with `unlink()`
- Contract PDFs preserved
- Quittance PDFs preserved
- État des lieux PDFs preserved
- Inventaire PDFs preserved

**Documents**: Identity documents and photos preserved
- Locataire identity documents (recto/verso)
- État des lieux photos
- Candidature documents
- Bilan justificatifs

**Exception**: Temporary files in `/tmp/` are still cleaned up (safe to delete)

### 5. List Pages Updated (8 Files)

All SELECT queries now filter soft-deleted records:

**Files Updated**:
1. `admin-v2/candidatures.php`
2. `admin-v2/contrats.php`
3. `admin-v2/logements.php`
4. `admin-v2/quittances.php`
5. `admin-v2/inventaires.php`
6. `admin-v2/etats-lieux.php`
7. `admin-v2/administrateurs.php`

**Query Pattern**:
```php
// Main listing query
WHERE table.deleted_at IS NULL

// Statistics queries
SELECT COUNT(*) FROM table WHERE deleted_at IS NULL
```

## Installation

### Step 1: Run Migrations

```bash
# Option 1: Use test script
php test-soft-delete-migrations.php

# Option 2: Manual SQL execution
mysql -u root -p bail_signature < migrations/059_add_rappel_loyers_cron_job.sql
mysql -u root -p bail_signature < migrations/060_add_soft_delete_columns.sql
```

### Step 2: Verify Database Changes

```sql
-- Check cron job
SELECT * FROM cron_jobs WHERE fichier = 'cron/rappel-loyers.php';

-- Check deleted_at columns
SHOW COLUMNS FROM candidatures LIKE 'deleted_at';
SHOW COLUMNS FROM contrats LIKE 'deleted_at';
-- etc.
```

### Step 3: Test Soft Delete

1. Navigate to any admin list page (candidatures, contrats, etc.)
2. Delete a record
3. Verify record disappears from list
4. Check database: `SELECT * FROM candidatures WHERE deleted_at IS NOT NULL`
5. Confirm PDF files still exist on filesystem

### Step 4: Test Cron Job

1. Navigate to `/admin-v2/cron-jobs.php`
2. Find "Rappel Loyers" job
3. Click "Exécuter maintenant"
4. Check logs for successful execution

## Benefits

### Data Integrity
- ✅ Records can be recovered if deleted by mistake
- ✅ Complete audit trail maintained
- ✅ Historical data preserved for compliance

### Audit Trail
- ✅ Who deleted what and when (via logs table)
- ✅ PDF files preserved for legal/audit purposes
- ✅ Document history maintained

### Data Recovery
- ✅ Simple to restore: `UPDATE table SET deleted_at = NULL WHERE id = ?`
- ✅ Files remain accessible
- ✅ No data loss

## Testing Checklist

- [ ] Migration 059 applied successfully
- [ ] Migration 060 applied successfully
- [ ] All 13 tables have deleted_at column
- [ ] Cron job appears in /admin-v2/cron-jobs.php
- [ ] Manual cron execution works
- [ ] Soft delete works on all entities
- [ ] List pages exclude soft-deleted records
- [ ] Statistics exclude soft-deleted records
- [ ] PDF files preserved after deletion
- [ ] Photos preserved after deletion
- [ ] Documents preserved after deletion

## Rollback (If Needed)

```sql
-- Remove deleted_at columns
ALTER TABLE candidatures DROP COLUMN deleted_at;
ALTER TABLE contrats DROP COLUMN deleted_at;
-- etc. for all 13 tables

-- Remove cron job
DELETE FROM cron_jobs WHERE fichier = 'cron/rappel-loyers.php';

-- Remove parameters
DELETE FROM parametres WHERE cle LIKE 'rappel_loyers%';
```

**Note**: Rollback will restore hard delete behavior. Not recommended after data has been soft-deleted.

## Future Enhancements

1. **Permanent Delete**: Add scheduled job to permanently delete records after X days
2. **Restore Interface**: Create admin UI to restore soft-deleted records
3. **Audit Log**: Enhanced logging of delete operations
4. **Cascade Rules**: Define cascade behavior for related tables

## Security Considerations

- ✅ No SQL injection vulnerabilities (all queries use prepared statements)
- ✅ No file path traversal issues
- ✅ Soft deletes prevent accidental data loss
- ✅ File preservation maintains evidence for disputes
- ✅ CodeQL security scan: No vulnerabilities detected

## Support

For issues or questions:
1. Check application logs in `/admin-v2/`
2. Review cron job logs at `/cron/rappel-loyers-log.txt`
3. Verify database schema matches expected structure
4. Contact system administrator if problems persist
