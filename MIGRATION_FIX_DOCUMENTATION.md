# Migration Fix Documentation

## Problem Description

The migration system was experiencing an inconsistency where:

1. The migration runner (`run-migrations.php`) reported migrations as already executed:
   ```
   ⊘ Skipping (already executed): 002_create_parametres_table.sql
   ```

2. However, accessing `parametres.php` resulted in an error:
   ```
   Fatal error: SQLSTATE[42S02]: Base table or view not found: 1146 
   Table 'barconcecccontra.parametres' doesn't exist
   ```

3. Additionally, documents in `candidature-detail.php` were not grouped by type, making it difficult to organize and view documents.

## Root Cause

This issue occurs when:
- A migration is marked as executed in the `migrations` tracking table
- But the actual migration failed or was rolled back
- The tracking record was not removed during the rollback
- This leaves the database in an inconsistent state

Common causes:
1. Transaction rollback that didn't clear the tracking record
2. Manual table deletion after migration
3. Database errors during migration execution
4. Partial migration execution

## Solution

### 1. Migration Fix Script

A new script `fix-migrations.php` has been created to:

1. **Detect Inconsistencies**: Check which migrations are tracked but their tables don't exist
2. **Remove Invalid Records**: Delete incorrect migration tracking entries
3. **Re-run Migrations**: Execute the migrations again to create missing tables
4. **Verify Results**: Ensure tables are created and properly tracked

### Usage

To fix the migration issues, run:

```bash
php fix-migrations.php
```

The script will:
- ✓ Check the migrations tracking table
- ✓ Verify each tracked migration has its corresponding table
- ✓ Identify discrepancies (tracked but table missing)
- ✓ Remove incorrect tracking records
- ✓ Re-execute failed migrations
- ✓ Add new tracking records
- ✓ Report success or errors

### Expected Output

```
=== Migration Fix Tool ===

✓ Migration tracking table ready

Found 4 tracked migration(s)

✗ ISSUE: Migration '002_create_parametres_table.sql' is tracked but table 'parametres' doesn't exist
  Will remove tracking record and re-run migration

=== Fixing Issues ===

Fixing: 002_create_parametres_table.sql
  ✓ Removed incorrect tracking record
  ✓ Re-executed migration SQL
  ✓ Added new tracking record
  ✓ Migration fixed successfully

=== Fix Complete ===
You should now be able to access the parametres.php page
```

### 2. Document Grouping Enhancement

The `candidature-detail.php` page has been updated to:

1. **Fetch Documents with Type Information**: Query includes `type_document` field
2. **Group Documents by Type**: Documents are organized into categories
3. **Display with Clear Headers**: Each document type has a visual header
4. **Maintain User-Friendly Labels**: French labels for each document type

#### Document Types Supported

- **Pièce d'identité** (piece_identite)
- **Bulletins de salaire** (bulletins_salaire)
- **Contrat de travail** (contrat_travail)
- **Avis d'imposition** (avis_imposition)
- **Quittances de loyer** (quittances_loyer)
- **Justificatif de revenus** (justificatif_revenus)
- **Justificatif de domicile** (justificatif_domicile)
- **Autre document** (autre)

#### Visual Improvements

- Document type headers with blue color and left border
- Folder icon for each type section
- Clear separation between document types
- Responsive design maintained

## Files Modified

### New Files
- `fix-migrations.php` - Migration fix utility script

### Modified Files
- `admin-v2/candidature-detail.php` - Updated to group documents by type

## Changes to candidature-detail.php

### Before
```php
// Documents were fetched as concatenated strings
SELECT c.*, 
       GROUP_CONCAT(cd.nom_fichier SEPARATOR '|||') as documents,
       GROUP_CONCAT(cd.chemin_fichier SEPARATOR '|||') as documents_paths

// Displayed as a flat list without type information
```

### After
```php
// Documents fetched with type information preserved
SELECT type_document, nom_fichier, chemin_fichier, uploaded_at
FROM candidature_documents
WHERE candidature_id = ?
ORDER BY type_document, uploaded_at

// Grouped by type with proper labels and styling
```

## Testing

### Manual Testing Required

Since we don't have access to the production database, the following tests should be performed:

1. **Migration Fix**:
   ```bash
   # Run the fix script
   php fix-migrations.php
   
   # Verify tables exist
   mysql -u [user] -p [database] -e "SHOW TABLES LIKE 'parametres'"
   
   # Access the parametres page
   # Navigate to: https://contrat.myinvest-immobilier.com/admin-v2/parametres.php
   ```

2. **Document Grouping**:
   ```bash
   # Access a candidature detail page with documents
   # Navigate to: https://contrat.myinvest-immobilier.com/admin-v2/candidature-detail.php?id=8
   
   # Verify:
   # - Documents are grouped by type
   # - Each type has a clear header
   # - Documents can still be downloaded
   ```

## Prevention

To prevent this issue in the future:

1. **Monitor Migration Execution**: Always check logs after running migrations
2. **Verify Table Creation**: After migrations, verify tables exist
3. **Use fix-migrations.php**: Run periodically to check consistency
4. **Database Backups**: Maintain regular backups before migrations
5. **Test Migrations**: Test on staging environment first

## Rollback

If needed, you can rollback by:

1. The fix script uses transactions - errors are automatically rolled back
2. If a migration needs to be undone, remove the tracking record:
   ```sql
   DELETE FROM migrations WHERE migration_file = '002_create_parametres_table.sql';
   ```
3. Drop the table if needed:
   ```sql
   DROP TABLE IF EXISTS parametres;
   ```

## Support

For issues or questions:
1. Check the error logs in `/error.log`
2. Review the migration script output
3. Verify database connection settings in `includes/config.php`
4. Check MySQL error logs

## Future Improvements

Potential enhancements:
1. Add automatic consistency checking to `run-migrations.php`
2. Create migration rollback functionality
3. Add email notifications for migration failures
4. Implement migration dry-run mode
5. Add migration versioning
