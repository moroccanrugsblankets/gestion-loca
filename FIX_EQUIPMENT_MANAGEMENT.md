# Fix: Equipment Management Display Issues

## Problem Statement

Two critical issues were affecting the equipment management system:

1. **No equipment displayed**: Accessing `/admin-v2/manage-inventory-equipements.php?logement_id=5` showed no equipment, even though equipment should auto-populate
2. **Invalid request error**: Accessing `/admin-v2/populate-logement-defaults.php` returned `{"success":false,"message":"Requête invalide"}`

## Root Causes

### 1. Missing Category IDs
Equipment was being inserted with only the `categorie` (string) field but not the `categorie_id` (integer) field. When the page tried to display equipment, it performed a LEFT JOIN on `categorie_id`, which was NULL, causing the category information to be lost and equipment to not be grouped properly.

### 2. Missing Database Categories
The equipment list in `inventaire-standard-items.php` used category names that didn't exist in the `inventaire_categories` table:
- "Équipement 1 (Cuisine / Vaisselle)" 
- "Équipement 2 (Linge / Entretien)"

### 3. POST-Only Endpoint
The `populate-logement-defaults.php` endpoint only accepted POST requests, but was being accessed via GET (browser navigation), causing the "Invalid request" error.

## Solutions Implemented

### 1. Database Schema Updates

**Migration 051** (`migrations/051_add_equipment_categories.php`):
- Adds the missing categories to match the standard equipment list
- Uses `ON DUPLICATE KEY UPDATE` for idempotency

**Migration 048 Update** (`migrations/048_create_categories_system.php`):
- Updated to include the missing categories for fresh installations

### 2. Code Fixes

**`admin-v2/manage-inventory-equipements.php`**:
- Auto-populate now builds a category name-to-ID mapping
- Sets both `categorie` and `categorie_id` when inserting equipment
- Added backward compatibility: if `categorie_id` is NULL, tries to match by category name

**`admin-v2/populate-logement-defaults.php`**:
- Now accepts both GET and POST requests
- Builds category mapping and sets `categorie_id` when inserting
- Better error messages

### 3. Utility Scripts

**`populate-all-logements-equipment.php`**:
```bash
php populate-all-logements-equipment.php
```
- Bulk populates equipment for all logements that don't have any
- Skips logements that already have equipment
- Shows progress and summary

**`fix-equipment-category-ids.php`**:
```bash
php fix-equipment-category-ids.php
```
- Fixes existing equipment records with NULL `categorie_id`
- Matches by category name and updates the `categorie_id`
- Shows which records were fixed

## Equipment List Specification

The standard equipment list now properly includes (per problem statement):

### 🪑 Meubles (all in Bon État)
- Chaises 2
- Canapé 1
- Table à manger 1
- Table basse 1
- Placards intégrées
- **Property-specific (RC-01, RC-02, RP-07)**:
  - Lit double 1
  - Tables de chevets 2
- **Property-specific (RC and RF)**:
  - Lustres / Plafonniers 1
  - Lampadaire 1

### 🔌 Électroménager
- Réfrigérateur 1
- Machine à laver séchante 1
- Télévision 1
- Fire Stick 1
- Plaque de cuisson 1
- **Property-specific (RC and RF)**:
  - Four grill / micro-ondes 1
  - Aspirateur 1

### 🍽 Équipement 1 (Cuisine / Vaisselle)
- Grandes assiettes 4
- Assiettes à dessert 4
- Assiettes creuses 4
- Fourchettes 4
- Petites cuillères 4
- Grandes cuillères 4
- Couteaux de table 4
- Verres 4
- Bols 4
- Tasses 4
- Saladier 1
- Poêle 1
- Casserole 1
- Planche à découper 1

### 🛏 Équipement 2 (Linge / Entretien)
- Matelas 1
- Oreillers 2
- Taies d'oreiller 2
- Draps du dessous 1
- Couette 1
- Housse de couette 1
- Alaise 1
- Plaid 1

## Deployment Instructions

### For Existing Installations

1. **Run the migration to add missing categories**:
```bash
php migrations/051_add_equipment_categories.php
```

2. **Fix existing equipment with NULL category_id** (if any):
```bash
php fix-equipment-category-ids.php
```

3. **Populate equipment for logements without equipment**:
```bash
php populate-all-logements-equipment.php
```

### For Fresh Installations

Migration 048 now includes all necessary categories, so no additional steps are needed.

## Testing

After deployment:

1. **Test equipment display**:
   - Visit `/admin-v2/manage-inventory-equipements.php?logement_id=5`
   - Equipment should auto-populate if empty
   - All categories should be visible with proper icons

2. **Test populate endpoint**:
   - Visit `/admin-v2/populate-logement-defaults.php?logement_id=5`
   - Should return success JSON (not "Invalid request")

3. **Verify property-specific equipment**:
   - RC-01, RC-02, RP-07: Should have bedroom furniture
   - RC and RF: Should have additional lighting and appliances

## Files Changed

- `migrations/048_create_categories_system.php` - Added missing categories
- `migrations/051_add_equipment_categories.php` - New migration for existing installations
- `admin-v2/manage-inventory-equipements.php` - Fixed auto-populate and added backward compatibility
- `admin-v2/populate-logement-defaults.php` - Added GET support and category_id handling
- `populate-all-logements-equipment.php` - New utility script
- `fix-equipment-category-ids.php` - New utility script

## Security Summary

- All database queries use prepared statements with parameter binding
- Input validation ensures logement_id is an integer
- Authentication is required (via `auth.php`)
- No SQL injection vulnerabilities
- No XSS vulnerabilities in display code (uses `htmlspecialchars()`)
- Transaction handling ensures data integrity

## Backward Compatibility

The fix maintains full backward compatibility:
- Existing equipment with `categorie` but NULL `categorie_id` will still display
- The grouping logic tries to match by category name if ID is not available
- Utility script can update existing records to use category IDs
