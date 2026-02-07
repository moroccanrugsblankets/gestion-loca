# PR Summary: Migration for État des Lieux de Sortie HTML Template

## 📋 Overview

This PR adds a database migration to store the HTML template for **"État des Lieux de Sortie"** (Move-Out Inventory) in the `parametres` table, making it available for dynamic PDF generation.

## ✨ Changes Made

### 1. Migration File: `migrations/033_add_etat_lieux_sortie_template.php`

**Purpose**: Store the exit inventory HTML template in the database

**Key Features**:
- ✅ Extracts template from existing `getDefaultExitEtatLieuxTemplate()` function
- ✅ Stores in `parametres` table with key `etat_lieux_sortie_template_html`
- ✅ Idempotent design (can be run multiple times safely)
- ✅ Proper transaction handling with rollback on errors
- ✅ Comprehensive error messages and verification
- ✅ Updates existing template if already present

**Template Details**:
- **Size**: 7,332 characters
- **Type**: HTML5 with embedded CSS
- **Group**: templates
- **Description**: Template HTML pour l'état des lieux de sortie (exit inspection)

### 2. Documentation: `MIGRATION_033_INSTRUCTIONS.md`

**Complete guide including**:
- Execution instructions (direct PHP execution)
- Verification steps with SQL queries
- Dependencies and prerequisites
- Rollback procedure
- Usage examples
- Troubleshooting tips
- Changelog

### 3. Test Script: `test-migration-033.php` (ignored by git)

**Comprehensive validation**:
- ✅ Template file existence
- ✅ Function availability
- ✅ Template retrieval (7,332 chars)
- ✅ All 8 required placeholders present
- ✅ Valid HTML structure
- ✅ CSS styles included
- ✅ Migration file syntax
- ✅ Migration logic verification
- ✅ Transaction usage

## 🎯 Template Features

The exit template includes all fields specific to move-out inspections:

### Exit-Specific Sections
1. **Deposit Guarantee Section** (`{{depot_garantie_section}}`)
   - Restitution status (total/partial/withheld)
   - Amount withheld
   - Reason for withholding

2. **Property Assessment** (`{{bilan_logement_section}}`)
   - Dynamic table of degradations/issues
   - Columns: Item/Equipment, Comments, Value (€), Amount Due (€)
   - Automatic totals
   - General comments field

3. **Conformity Badges**
   - `{{cles_conformite}}` - Keys conformity badge
   - `{{etat_general_conforme}}` - General state conformity badge

4. **Dynamic Section Numbering**
   - `{{signatures_section_number}}` - Adapts based on included sections (7, 8, or 9)

### Conditional Sections
- `{{cles_observations_section}}` - Keys observations (if any)
- `{{degradations_section}}` - Detailed degradations (if any)
- `{{observations_section}}` - General observations (if any)

### Preserved Common Fields
All standard fields from entry template:
- Reference, date, address
- Meter readings (electricity, water)
- Keys count (apartment, mailbox, other, total)
- Room descriptions (main room, kitchen, bathroom/WC)
- Signatures (agency, owner, tenant)

## 🔄 How It Works

### Before Migration
```
- Template exists in: includes/etat-lieux-template.php (code only)
- Not stored in database
```

### After Migration
```
- Template stored in: parametres table
- Key: etat_lieux_sortie_template_html
- Type: string
- Group: templates
- Length: 7,332 characters
- Accessible for dynamic PDF generation
```

### Usage Flow
```php
// PDF generation automatically loads template from database
$pdfPath = generateEtatDesLieuxPDF($contratId, 'sortie');

// System:
// 1. Detects type = 'sortie'
// 2. Loads template from parametres table
// 3. Replaces placeholders with actual data
// 4. Generates PDF with TCPDF
```

## 📊 Testing Results

All tests passed successfully:

```
✅ Template file exists
✅ Function getDefaultExitEtatLieuxTemplate() available
✅ Template retrieved: 7,332 characters
✅ All 8 required placeholders found:
   - {{reference}}
   - {{date_etat}}
   - {{adresse}}
   - {{signatures_section_number}}
   - {{depot_garantie_section}}
   - {{bilan_logement_section}}
   - {{cles_conformite}}
   - {{etat_general_conforme}}
✅ Valid HTML structure (opening/closing tags)
✅ CSS styles included
✅ Migration file syntax correct
✅ Migration logic verified
✅ Transactions properly used
```

## 📋 Execution Instructions

### Run the Migration

```bash
php migrations/033_add_etat_lieux_sortie_template.php
```

### Expected Output

```
=== Migration 033: Add État des Lieux de Sortie HTML Template ===

Template loaded: 7332 characters
  - Creating new template entry...
  ✓ Template created successfully with ID: [id]

Template verification:
  - Key: etat_lieux_sortie_template_html
  - Type: string
  - Group: templates
  - Description: Template HTML pour l'état des lieux de sortie (exit inspection)
  - Length: 7332 characters

✅ Migration 033 completed successfully
État des lieux de sortie HTML template has been added to the database.
```

### Verification Query

```sql
SELECT cle, type, groupe, description, LENGTH(valeur) as template_length 
FROM parametres 
WHERE cle = 'etat_lieux_sortie_template_html';
```

## 🔐 Security

- ✅ **Code Review**: Passed - No issues found
- ✅ **CodeQL**: No code changes for analyzable languages
- ✅ **SQL Injection**: Protected via prepared statements
- ✅ **XSS**: Template data properly escaped during PDF generation
- ✅ **Transaction Safety**: Rollback on errors
- ✅ **Idempotent**: Safe to run multiple times

## 📚 Dependencies

**Required**:
- ✅ Migration 002: `002_create_parametres_table.sql` (creates `parametres` table)
- ✅ File: `includes/etat-lieux-template.php` (contains template function)

**Database**:
- Table: `parametres`
- Columns: `id`, `cle`, `valeur`, `type`, `description`, `groupe`, `created_at`, `updated_at`

## 🎯 Benefits

1. **Centralized Management**: Template stored in database, not hardcoded
2. **Easy Updates**: Modify template via database without code changes
3. **Version Control**: `updated_at` timestamp tracks changes
4. **Flexibility**: Different templates for entry vs exit inspections
5. **Maintainability**: Clear separation of concerns

## 🔄 Rollback Procedure

If needed, rollback is simple:

```sql
DELETE FROM parametres WHERE cle = 'etat_lieux_sortie_template_html';
```

## 📝 Files Changed

```
✅ migrations/033_add_etat_lieux_sortie_template.php (new)
✅ MIGRATION_033_INSTRUCTIONS.md (new)
```

**Lines of Code**:
- Migration: 109 lines
- Documentation: 102 lines
- Test script: 177 lines (not committed)
- **Total**: 211 lines committed

## ✅ Checklist

- [x] Migration file created
- [x] PHP syntax validated
- [x] Template function verified
- [x] All placeholders present
- [x] Transaction handling implemented
- [x] Error handling included
- [x] Documentation created
- [x] Test script written and passed
- [x] Code review completed (no issues)
- [x] Security check completed (no issues)
- [x] Idempotent behavior confirmed

## 🚀 Next Steps

After merging this PR:

1. Execute the migration on production:
   ```bash
   php migrations/033_add_etat_lieux_sortie_template.php
   ```

2. Verify the template is stored:
   ```sql
   SELECT COUNT(*) FROM parametres WHERE cle = 'etat_lieux_sortie_template_html';
   ```

3. Test PDF generation for exit inventories:
   ```php
   $pdfPath = generateEtatDesLieuxPDF($contratId, 'sortie');
   ```

4. Monitor logs for any issues

## 💡 Notes

- Migration is **idempotent**: Running it multiple times is safe
- Template can be updated in database without modifying code
- Fallback to hardcoded template exists if database entry missing
- Compatible with existing entry template system
- No breaking changes to existing functionality

---

**Migration 033** is ready for production deployment! 🎉
