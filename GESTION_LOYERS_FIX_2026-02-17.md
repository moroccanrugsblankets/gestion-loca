# Fix: Gestion Loyers - Display Months from Contract Start Date

## Problem Statement (French)
/admin-v2/gestion-loyers.php le tableau doit afficher tous les mois qui commence de la date du champs de "date_prise_effet" jusqu'à le mois en cours
aussi il faut régler le problème de design, lorsqu'on passe la souris sur la case des mois pour changer le satus, le tableau bouge !

## Translation
- The table should display all months starting from the "date_prise_effet" (effective date) field until the current month
- Fix the design problem where hovering over month cells to change status causes the table to move

## Changes Made

### 1. Dynamic Month Range Based on Contract Effective Date

**Before:**
- Displayed a fixed 12-month period (last 12 months)
- Used a simple loop counting backwards from current month

**After:**
- Queries the `date_prise_effet` field from all active contracts
- Finds the earliest effective date among all contracts
- Generates months from that earliest date to the current month
- Includes a fallback to 12 months if no effective date is found

**Code Changes:**
```php
// BEFORE: Fixed 12 months
for ($i = 11; $i >= 0; $i--) {
    $date = new DateTime();
    $date->modify("-$i months");
    // ... generate month
}

// AFTER: Dynamic from contract start date
// Find earliest date_prise_effet
$earliestDate = null;
foreach ($logements as $logement) {
    if (!empty($logement['date_prise_effet'])) {
        $dateEffet = new DateTime($logement['date_prise_effet']);
        if ($earliestDate === null || $dateEffet < $earliestDate) {
            $earliestDate = $dateEffet;
        }
    }
}

// Generate months from earliest to current
$iterDate = clone $earliestDate;
while ($iterDate <= $currentDate) {
    // ... generate month
    $iterDate->modify('+1 month');
}
```

### 2. Fixed Table Jump on Hover

**Before:**
```css
.payment-cell {
    cursor: pointer;
    transition: all 0.2s;
    min-width: 80px;
    position: relative;
}

.payment-cell:hover {
    opacity: 0.8;
    transform: scale(1.05);  /* ❌ This causes layout shift */
}
```

**After:**
```css
.payment-cell {
    cursor: pointer;
    transition: opacity 0.2s;  /* ✅ Only transition opacity */
    min-width: 80px;
    position: relative;
}

.payment-cell:hover {
    opacity: 0.8;  /* ✅ No transform, no layout shift */
}
```

## Benefits

1. **More Complete Data View**
   - Users can now see payment history from when contracts actually started
   - No arbitrary 12-month limit that might cut off relevant data
   - Particularly useful for long-running contracts

2. **Better User Experience**
   - Table no longer "jumps" when hovering over cells
   - Smoother, more professional interface
   - Easier to click on cells without visual distraction

3. **Backward Compatible**
   - Falls back to 12 months if no contract effective dates are found
   - Existing functionality preserved for edge cases

## Testing

Created test-gestion-loyers-logic.php to verify:
- ✅ Month generation from specific date to current month
- ✅ Fallback to 12 months when no date_prise_effet
- ✅ Finding earliest date among multiple contracts

All tests passed successfully.

## Files Modified
- `/admin-v2/gestion-loyers.php` - Main file with both fixes
