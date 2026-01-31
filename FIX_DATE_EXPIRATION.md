# Fix for date_expiration SQL Error

## Problem
When clicking the "Générer le contrat et envoyer" button on the contract generation page, a SQL error occurred:

```
Fatal error: Uncaught PDOException: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'date_expiration' in 'field list'
```

## Root Cause
There was a mismatch between the database schema and the application code:
- **Database schema**: The `contrats` table had a column named `date_expiration_lien`
- **Application code**: The code was using `date_expiration` in INSERT and SELECT queries

## Solution
The column has been renamed from `date_expiration_lien` to `date_expiration` to match what the code expects.

## Changes Made

### 1. Migration File
Created `migrations/016_rename_date_expiration_lien.sql` to rename the column:
```sql
ALTER TABLE contrats 
CHANGE COLUMN date_expiration_lien date_expiration TIMESTAMP NULL;
```

### 2. Database Schema
Updated `database.sql` to use `date_expiration` for new installations.

## Deployment Instructions

To apply this fix in production:

1. Run the migration script:
   ```bash
   php run-migrations.php
   ```

2. Verify the migration was applied:
   ```sql
   SHOW COLUMNS FROM contrats LIKE 'date_expiration';
   ```
   
   You should see a column named `date_expiration` (not `date_expiration_lien`).

3. Test the contract generation by:
   - Going to `/admin-v2/generer-contrat.php?candidature_id=28`
   - Filling in the form
   - Clicking "Générer le contrat et envoyer"
   - Verify no SQL error occurs

## Files Affected
- `database.sql` - Schema updated for new installations
- `migrations/016_rename_date_expiration_lien.sql` - New migration file
- No code changes were needed as the code was already using the correct column name

## Notes
- This fix is backward compatible for existing migrations
- All existing data in the `date_expiration_lien` column will be preserved and accessible under the new name `date_expiration`
- No application code changes were required, only database schema changes
