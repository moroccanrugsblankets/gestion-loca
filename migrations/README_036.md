# Migration 036: Populate Inventaire Templates

## Purpose
This migration populates the inventory templates (`inventaire_template_html` and `inventaire_sortie_template_html`) in the `parametres` table with default HTML content.

## Problem Addressed
Issue: `/admin-v2/inventaire-configuration.php` - "tjrs aucune template editable"

The inventory configuration page was showing empty templates because the database records existed but had NULL or empty values. This migration populates them with professional, ready-to-use HTML templates.

## What This Migration Does

1. **Updates existing records** with template content if they exist
2. **Inserts new records** if they don't exist yet

The migration populates two templates:

### 1. `inventaire_template_html` (Entry Inventory Template)
- Used when creating entry inventories (tenant moving in)
- 5,088 characters of professional HTML
- Blue color scheme (#3498db)
- Variables: `{{reference}}`, `{{date}}`, `{{adresse}}`, `{{appartement}}`, `{{locataire_nom}}`, `{{equipements}}`, `{{observations}}`

### 2. `inventaire_sortie_template_html` (Exit Inventory Template)
- Used when creating exit inventories (tenant moving out)
- 6,205 characters of professional HTML
- Red color scheme (#e74c3c)
- Additional comparison section for entry/exit differences
- Variables: Same as entry + `{{comparaison}}`

## How to Run

### Option 1: Direct MySQL execution
```bash
mysql -u [username] -p [database_name] < migrations/036_populate_inventaire_templates.sql
```

### Option 2: Using the migration runner
```bash
php run-migrations.php
```

### Option 3: Using phpMyAdmin
1. Open phpMyAdmin
2. Select the database
3. Go to "Import" tab
4. Choose the file `migrations/036_populate_inventaire_templates.sql`
5. Click "Go"

## Verification

After running the migration, verify the templates are populated:

```sql
SELECT cle, LENGTH(valeur) as template_length 
FROM parametres 
WHERE cle IN ('inventaire_template_html', 'inventaire_sortie_template_html');
```

Expected results:
- `inventaire_template_html`: ~5088 characters
- `inventaire_sortie_template_html`: ~6205 characters

You can also verify by visiting:
- `/admin-v2/inventaire-configuration.php`

The TinyMCE editors should now display the HTML templates instead of being empty.

## Notes

- This migration is idempotent (safe to run multiple times)
- Existing custom templates will be overwritten by the UPDATE statements
- If you've already customized templates, back them up before running this migration
- The templates use the same design patterns as État des Lieux templates for consistency

## Generation Script

This SQL file was generated using:
```bash
php generate-inventaire-templates-sql.php
```

The source templates are defined in:
- `includes/inventaire-template.php`
  - `getDefaultInventaireTemplate()` - Entry template
  - `getDefaultInventaireSortieTemplate()` - Exit template

## Related Files
- Migration SQL: `migrations/036_populate_inventaire_templates.sql`
- Template definitions: `includes/inventaire-template.php`
- Configuration page: `admin-v2/inventaire-configuration.php`
- Table creation: `migrations/034_create_inventaire_tables.php`
