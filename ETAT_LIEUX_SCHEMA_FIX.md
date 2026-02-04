# États des Lieux - Schema Fix

## Problem Summary

The application was experiencing a fatal SQL error when accessing `/admin-v2/view-etat-lieux.php?id=1`:

```
Fatal error: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'c.date_debut' in 'field list'
```

Additionally, there was a schema inconsistency issue with the `etats_lieux` table.

## Fixes Applied

### 1. SQL Column Names Fix

**File**: `admin-v2/view-etat-lieux.php` (Line 50)

**Problem**: The SQL query was attempting to select columns `c.date_debut` and `c.date_fin` from the `contrats` table, but these columns don't exist.

**Solution**: Updated the query to use the correct column names:
- `c.date_debut` → `c.date_prise_effet` (aliased as `date_debut`)
- `c.date_fin` → `c.date_fin_prevue` (aliased as `date_fin`)

### 2. Table Schema Inconsistency Fix

**Problem**: There were two conflicting schemas for états des lieux:

1. **Base schema** (`database.sql`): `etats_lieux` table with minimal fields + JSON columns
2. **Migration 021**: Attempted to create `etat_lieux` table (wrong name - singular instead of plural)

This created confusion and potential for having two different tables.

**Solution**:

#### A. Deprecated Migration 021
- Modified `migrations/021_create_etat_lieux_tables.php` to be a no-op
- Added warnings about the table name conflict
- Preserved the file for migration numbering consistency

#### B. Created Migration 026
- New file: `migrations/026_fix_etats_lieux_schema.php`
- Properly extends the existing `etats_lieux` table (correct plural name)
- Adds all required columns for detailed entry/exit inventory
- Creates related tables: `etat_lieux_locataires` and `etat_lieux_photos`
- Includes email templates configuration

## Migration Instructions

To apply these fixes to your database:

### Step 1: Run Migration 026

```bash
cd /path/to/contrat-de-bail
php migrations/026_fix_etats_lieux_schema.php
```

This will:
- Add all missing columns to `etats_lieux` table
- Create `etat_lieux_locataires` table for tenant signatures
- Create `etat_lieux_photos` table for optional photos
- Set up email templates for état des lieux notifications

### Step 2: Verify the Update

Check that the `etats_lieux` table now has all required columns:

```sql
SHOW COLUMNS FROM etats_lieux;
```

Expected new columns:
- `reference_unique` - Unique reference for each état des lieux
- `adresse`, `appartement` - Property identification
- `bailleur_nom`, `bailleur_representant` - Landlord information
- `compteur_electricite`, `compteur_eau_froide` - Meter readings
- `compteur_electricite_photo`, `compteur_eau_froide_photo` - Optional meter photos
- `cles_appartement`, `cles_boite_lettres`, `cles_total` - Key tracking
- `cles_photo`, `cles_conformite`, `cles_observations` - Key condition info
- `piece_principale`, `coin_cuisine`, `salle_eau_wc` - Detailed room descriptions
- `comparaison_entree` - Comparison with entry state (for exit inventory)
- `depot_garantie_status`, `depot_garantie_montant_retenu`, `depot_garantie_motif_retenue` - Security deposit handling
- `lieu_signature` - Signature location
- `bailleur_signature` - Landlord signature storage
- `statut` - Status tracking (brouillon/finalise/envoye)
- `email_envoye`, `date_envoi_email` - Email tracking
- `updated_at`, `created_by` - Additional metadata

### Step 3: Test the Fix

1. Navigate to `/admin-v2/etats-lieux.php` in your browser
2. Create a test état des lieux
3. View it at `/admin-v2/view-etat-lieux.php?id=X`
4. Download the PDF to verify generation works correctly

## Implementation Details

### Table: etats_lieux (Main Table)

Stores all état des lieux data including:
- Property and tenant identification
- Meter readings (electricity, water)
- Key inventory
- Detailed room-by-room descriptions
- Signatures and status
- Email tracking

### Table: etat_lieux_locataires

Stores tenant-specific data for each état des lieux:
- Tenant information snapshot
- Individual signatures
- Signature metadata (timestamp, IP)

### Table: etat_lieux_photos

Stores optional photos (internal use only - NOT sent to tenants):
- Categorized photos (meters, keys, rooms)
- File paths and descriptions
- Display ordering

## Features Enabled

After migration 026, the system supports:

### Entry Inventory (État des lieux d'entrée)
- Complete property identification
- Meter readings with optional photos
- Key delivery tracking
- Detailed room descriptions (main room, kitchen, bathroom/WC)
- General state observations
- Electronic signatures
- PDF generation
- Automatic email to tenant + copy to gestion@myinvest-immobilier.com

### Exit Inventory (État des lieux de sortie)
- All entry features plus:
- Comparison with entry state
- Key return verification
- Security deposit decision (full/partial/retained)
- Damage assessment

## Backward Compatibility

Migration 026 is designed to be non-destructive:
- Uses `ALTER TABLE ADD COLUMN IF NOT EXISTS` patterns
- Checks for existing columns before adding
- Preserves existing data
- Adds indexes only if they don't exist

## Notes

### Photos
- All photos are optional
- Photos are for internal use only (My Invest)
- Photos are NOT included in PDFs sent to tenants
- Photos are stored in `/uploads/etat_lieux_photos/`

### Email Templates
Two new parameters are added:
- `etat_lieux_email_subject` - Email subject line template
- `etat_lieux_email_template` - Email body template

Both support template variables:
- `{{type}}` - "entree" or "sortie"
- `{{type_label}}` - "d'entrée" or "de sortie"
- `{{adresse}}` - Property address
- `{{date_etat}}` - Inventory date

## Troubleshooting

### If you see "Unknown column" errors:
- Make sure migration 026 has been run
- Check that all expected columns exist in `etats_lieux` table

### If migration 021 already created "etat_lieux" table:
- You may have a duplicate table `etat_lieux` (singular)
- Migration 026 uses `etats_lieux` (plural - the correct name)
- You may need to migrate data and drop the incorrect table

### To check for duplicate tables:
```sql
SHOW TABLES LIKE '%etat%lieux%';
```

Expected result: Only `etats_lieux` should exist

## Contact

For issues or questions:
- Check application logs in error_log
- Review migration output for specific errors
- Ensure database user has ALTER TABLE permissions
