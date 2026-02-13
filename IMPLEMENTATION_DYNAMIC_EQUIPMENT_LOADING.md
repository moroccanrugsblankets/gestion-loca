# Dynamic Equipment Loading Implementation

## Overview
Modified `/admin-v2/edit-inventaire.php` to dynamically load equipment from the database instead of using hardcoded standard items.

## Problem Statement
- The page `/admin-v2/edit-inventaire.php?id=2` was using hardcoded equipment from `inventaire-standard-items.php`
- Equipment specific to each logement was defined via `manage-inventory-equipements.php` but not used by the inventory edit page
- Requirement: Load equipment dynamically from the `inventaire_equipements` database table

## Solution

### Changes Made

#### 1. Database Query
Added a query to fetch equipment from the `inventaire_equipements` table based on the inventaire's `logement_id`:

```php
$stmt = $pdo->prepare("
    SELECT e.*, 
           c.nom as categorie_nom, 
           c.icone as categorie_icone,
           sc.nom as sous_categorie_nom
    FROM inventaire_equipements e
    LEFT JOIN inventaire_categories c ON e.categorie_id = c.id
    LEFT JOIN inventaire_sous_categories sc ON e.sous_categorie_id = sc.id
    WHERE e.logement_id = ? 
    ORDER BY COALESCE(c.ordre, 999), e.ordre, e.nom
");
```

#### 2. Data Transformation
Transform database equipment into the structure expected by the view:
- Categories with subcategories: `$standardItems[$categoryName][$subcategoryName][] = $item`
- Categories without subcategories: `$standardItems[$categoryName][] = $item`

#### 3. Fallback Mechanism
If no equipment is defined for a logement, the system falls back to standard items:
```php
if (!empty($logement_equipements)) {
    // Use equipment from database
    // ... transformation logic ...
} else {
    // Fallback to standard items
    $standardItems = getStandardInventaireItems();
}
```

#### 4. Initial Data Generation
Created `generateInventoryDataFromEquipment()` function to generate initial inventory data structure from either database equipment or standard items.

### Database Schema

The implementation works with the following tables:
- `inventaire_equipements`: Stores equipment definitions for each logement
- `inventaire_categories`: Equipment categories with icons and ordering
- `inventaire_sous_categories`: Subcategories for organizing equipment
- `inventaires`: Main inventory records

### Backward Compatibility

The implementation maintains backward compatibility:
- The original `categorie` VARCHAR field is used as a fallback
- The new `categorie_id` and `sous_categorie_id` fields are preferred
- Equipment without category associations still works via the text field

### Flow

1. User opens `/admin-v2/edit-inventaire.php?id=X`
2. System loads the inventaire record
3. System queries `inventaire_equipements` for the logement
4. If equipment found: Use database equipment
5. If no equipment: Fall back to standard items
6. Transform data into view structure
7. Render inventory form with equipment

### Benefits

1. **Dynamic Equipment**: Each logement can have its own specific equipment list
2. **Centralized Management**: Equipment is managed via `manage-inventory-equipements.php`
3. **Flexibility**: Easy to add, edit, or remove equipment per logement
4. **No Code Changes**: Equipment changes don't require code modifications
5. **Backward Compatible**: Existing inventories continue to work

### Testing Recommendations

1. Test with a logement that has defined equipment
2. Test with a logement without defined equipment (should use standard items)
3. Test inventory creation and editing
4. Test equipment with categories and subcategories
5. Verify sorting by category order

### Security

- Uses prepared statements for SQL queries (prevents SQL injection)
- Requires authentication (auth.php)
- Data properly escaped in the view (htmlspecialchars)
- Logement_id comes from validated inventaire record

## Files Modified

- `/admin-v2/edit-inventaire.php`: Main implementation file

## Related Files

- `/admin-v2/manage-inventory-equipements.php`: Equipment management interface
- `/includes/inventaire-standard-items.php`: Fallback standard items
- `/migrations/034_create_inventaire_tables.php`: Initial table structure
- `/migrations/048_create_categories_system.php`: Categories system

## Date
2026-02-13
