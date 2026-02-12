# Implementation: Import Exit Inventory Data to Bilan Logement

## Problem Statement
On the page `/edit-bilan-logement.php`, we needed to retrieve the lines added in "état de lieux de sortie" (exit inventory) from `/admin-v2/edit-etat-lieux.php` and populate the degradation table with equipment/comments.

## Solution Overview

### What Was Changed

#### 1. File Modified: `admin-v2/edit-bilan-logement.php`

### Changes Made

#### A. PHP Backend (Lines 98-103)
**Added:** Retrieval of `bilan_sections_data` from the database

```php
// Get bilan_sections_data for import functionality
$bilanSectionsData = [];
if (!empty($etat['bilan_sections_data'])) {
    $bilanSectionsData = json_decode($etat['bilan_sections_data'], true) ?: [];
}
```

**Purpose:** Loads the exit inventory data that was previously saved in `edit-etat-lieux.php`

#### B. UI - Import Button (Lines 214-221)
**Added:** Import button next to the "Add Row" button

```php
<?php if (!empty($bilanSectionsData)): ?>
<button type="button" class="btn btn-sm btn-success me-2" 
        onclick="importFromExitInventory()" id="importBilanBtn">
    <i class="bi bi-download"></i> Importer depuis l'état de sortie
</button>
<?php endif; ?>
```

**Purpose:** 
- Only shows when there is exit inventory data available
- Provides a clear action to import the data
- Uses Bootstrap styling for consistency

#### C. JavaScript - Data Import Logic (Lines 397-515)

**Added 3 new JavaScript functions:**

1. **`importFromExitInventory()`** (Lines 400-442)
   - Validates that data exists
   - Shows confirmation dialog
   - Loops through all sections (compteurs, cles, piece_principale, cuisine, salle_eau)
   - Imports equipment and comments as new rows
   - Disables import button after use to prevent duplicates
   - Shows success message with count

2. **`addBilanRowWithData(equipement, commentaire)`** (Lines 445-496)
   - Similar to existing `addBilanRow()` but accepts data
   - Pre-populates the equipment (poste) and comment fields
   - Leaves valeur and montant_du empty for manual entry
   - Maintains all validation and counter logic

3. **`escapeHtml(text)`** (Lines 499-508)
   - Security function to prevent XSS attacks
   - Escapes HTML special characters in imported data
   - Used when inserting data into HTML

**Added constant:**
```javascript
const BILAN_SECTIONS_DATA = <?php echo json_encode($bilanSectionsData); ?>;
```

## How It Works

### Data Flow

```
┌─────────────────────────────────────┐
│  edit-etat-lieux.php                │
│  (Exit Inventory)                   │
├─────────────────────────────────────┤
│  User fills in sections:            │
│  - Compteurs (Meters)               │
│  - Clés (Keys)                      │
│  - Pièce principale (Main room)     │
│  - Cuisine (Kitchen)                │
│  - Salle d'eau (Bathroom)           │
│                                     │
│  For each section:                  │
│  - Équipement: Item name            │
│  - Commentaire: Observations        │
└────────────┬────────────────────────┘
             │ Saves to DB as JSON
             │ Column: bilan_sections_data
             ▼
┌─────────────────────────────────────┐
│  Database: etats_lieux table        │
│  bilan_sections_data (JSON):        │
│  {                                  │
│    "cles": [                        │
│      {                              │
│        "equipement": "Clé porte",   │
│        "commentaire": "Manquante"   │
│      }                              │
│    ],                               │
│    "cuisine": [...]                 │
│  }                                  │
└────────────┬────────────────────────┘
             │ Fetched when loading
             │ edit-bilan-logement.php
             ▼
┌─────────────────────────────────────┐
│  edit-bilan-logement.php            │
│  (Financial Assessment)             │
├─────────────────────────────────────┤
│  1. Page loads with import button   │
│  2. User clicks import button       │
│  3. JavaScript processes data:      │
│     - Loops through all sections    │
│     - Creates new table rows        │
│     - Populates:                    │
│       * Poste = equipement          │
│       * Commentaires = commentaire  │
│       * Valeur = empty (manual)     │
│       * Montant dû = empty (manual) │
│  4. User fills in financial amounts │
│  5. Saves to bilan_logement_data    │
└─────────────────────────────────────┘
```

### Example Import

**From Exit Inventory:**
```json
{
  "cles": [
    {
      "equipement": "Clé appartement",
      "commentaire": "Manquante - 1 clé non restituée"
    }
  ],
  "cuisine": [
    {
      "equipement": "Four",
      "commentaire": "Légères traces de brûlure"
    },
    {
      "equipement": "Réfrigérateur",
      "commentaire": "Joint de porte endommagé"
    }
  ]
}
```

**Becomes Table Rows:**
| Poste/Équipement | Commentaires | Valeur (€) | Montant dû (€) |
|------------------|--------------|------------|----------------|
| Clé appartement | Manquante - 1 clé non restituée | *[empty]* | *[empty]* |
| Four | Légères traces de brûlure | *[empty]* | *[empty]* |
| Réfrigérateur | Joint de porte endommagé | *[empty]* | *[empty]* |

The user then fills in the financial values manually.

## User Experience

### Before This Feature
1. User filled exit inventory in `edit-etat-lieux.php`
2. User had to manually re-type all equipment and comments in `edit-bilan-logement.php`
3. High risk of typos and inconsistencies
4. Time-consuming and error-prone

### After This Feature
1. User fills exit inventory in `edit-etat-lieux.php` (same as before)
2. User opens `edit-bilan-logement.php`
3. User sees green "Import" button
4. User clicks import → all equipment/comments populate automatically
5. User only needs to add financial values
6. Much faster and consistent data entry

## Security Considerations

1. **XSS Prevention:** All imported text is escaped using `escapeHtml()` function
2. **SQL Injection:** Already prevented by existing PDO prepared statements
3. **Data Validation:** Import only processes valid data (non-empty equipment or comments)
4. **Row Limit:** Respects existing MAX_BILAN_ROWS (20) limit
5. **One-time Import:** Button disables after use to prevent accidental duplicates

## Testing

### Manual Testing Steps

1. **Prerequisites:**
   - Have an état de lieux of type "sortie" with bilan_sections_data filled
   - Access to the application database

2. **Test the Import:**
   ```
   1. Navigate to /admin-v2/edit-bilan-logement.php?id=[ID]
   2. Verify the "Importer depuis l'état de sortie" button is visible
   3. Click the import button
   4. Confirm the dialog
   5. Verify rows are added with equipment and comments
   6. Verify button is disabled after import
   7. Fill in valeur and montant_du fields
   8. Click "Enregistrer le bilan"
   9. Verify data is saved correctly
   ```

3. **Test Edge Cases:**
   - No bilan_sections_data → button should not appear ✓
   - Empty sections → should not create empty rows ✓
   - 20 rows already exist → should not exceed limit ✓
   - Special characters in text → should be escaped ✓

### Using Test Script

Run the test script (requires database access):
```bash
php test-bilan-import.php
```

Or access via browser:
```
http://your-domain.com/test-bilan-import.php
```

This will show:
- Available exit inventory data
- Preview of what would be imported
- Direct link to test the UI

## Database Schema

### Tables Used

**`etats_lieux`**
- `bilan_sections_data` (JSON) - Exit inventory sections with equipment/comments
- `bilan_logement_data` (JSON) - Financial assessment with equipment/comments/costs

### Migration

Already exists: `migrations/047_add_bilan_sections_data.sql`

## Files Changed

1. **admin-v2/edit-bilan-logement.php**
   - Added PHP code to load bilan_sections_data
   - Added import button in HTML
   - Added JavaScript import functions
   - Total lines changed: ~130 lines added

2. **test-bilan-import.php** (NEW)
   - Test script to verify functionality
   - Can be removed after testing

## Compatibility

- ✓ Works with existing data structure
- ✓ No database changes required
- ✓ Backward compatible (works even if no exit inventory data exists)
- ✓ Does not affect existing save/load functionality
- ✓ Uses existing validation and calculation functions

## Conclusion

This implementation provides a seamless way to transfer exit inventory data into the financial assessment form, reducing manual work and improving data consistency. The solution is minimal, secure, and follows the existing code patterns in the application.
