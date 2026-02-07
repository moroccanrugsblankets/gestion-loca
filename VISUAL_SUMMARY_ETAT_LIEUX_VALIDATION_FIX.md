# Visual Summary: État des Lieux Fix

## Problem
When trying to create a new "état des lieux" (inventory of fixtures), users encountered the error:
> "Aucun contrat signé trouvé pour ce logement"

This was incorrect because the system should check for **validated contracts** (statut = 'valide'), not just **signed contracts** (statut = 'signe').

## Before the Fix

### create-etat-lieux.php
```php
WHERE c.logement_id = ? AND c.statut = 'signe'  // ❌ Wrong: checking for signed contracts
```

Error message:
```php
$_SESSION['error'] = "Aucun contrat signé trouvé pour ce logement";  // ❌ Wrong message
```

### etats-lieux.php
Logement dropdown showed:
```
Reference (Type)
```
Example: `LOG001 (Appartement)`

Help text:
```
Tous les logements sont disponibles pour la création d'un état des lieux
```

## After the Fix

### create-etat-lieux.php
```php
WHERE c.logement_id = ? AND c.statut = 'valide'  // ✅ Correct: checking for validated contracts
```

Error message:
```php
$_SESSION['error'] = "Aucun contrat validé trouvé pour ce logement";  // ✅ Accurate message
```

### etats-lieux.php
Logement dropdown now shows:
```
Reference - Type (REF_CONTRAT_VALIDE)
```
Example: `LOG001 - Appartement (CONT-2024-001)`

When no validated contract exists:
```
Reference - Type
```
Example: `LOG002 - Studio`

Help text:
```
Un contrat validé est requis pour créer un état des lieux. 
Les logements avec contrat validé affichent la référence entre parenthèses.
```

## Visual Comparison

### Dropdown Display

**BEFORE:**
```
┌─────────────────────────────────────────┐
│ -- Sélectionner un logement --          │
│ LOG001 (Appartement)                    │
│ LOG002 (Studio)                         │
│ LOG003 (T2)                             │
└─────────────────────────────────────────┘
Tous les logements sont disponibles...
```

**AFTER:**
```
┌─────────────────────────────────────────┐
│ -- Sélectionner un logement --          │
│ LOG001 - Appartement (CONT-2024-001)    │  ← Has validated contract ✅
│ LOG002 - Studio                         │  ← No validated contract
│ LOG003 - T2 (CONT-2024-003)             │  ← Has validated contract ✅
└─────────────────────────────────────────┘
Un contrat validé est requis pour créer un état des lieux.
Les logements avec contrat validé affichent la référence entre parenthèses.
```

## Contract Status Flow

```
┌──────────────────────────────────────────────────────────┐
│                  Contract Status Flow                     │
└──────────────────────────────────────────────────────────┘

1. en_attente     →  Contract created, awaiting signature
2. signe          →  Contract signed by tenant(s)
3. valide         →  Contract validated by admin  ⭐ REQUIRED FOR ÉTAT DES LIEUX
4. actif/termine  →  Contract lifecycle states
```

## Technical Changes Summary

### Files Modified
1. ✅ `admin-v2/create-etat-lieux.php` (2 changes)
   - Line 44: Changed `statut = 'signe'` to `statut = 'valide'`
   - Line 52: Updated error message

2. ✅ `admin-v2/etats-lieux.php` (1 section modified)
   - Lines 362-393: Complete dropdown query rewrite
   - Added JOIN to fetch last validated contract reference
   - Updated display format
   - Improved help text

### SQL Query Improvements

**New Query for Logements Dropdown:**
```sql
SELECT l.id, l.reference, l.type, l.adresse,
       c.reference_unique as contrat_ref
FROM logements l
LEFT JOIN (
    SELECT c1.logement_id, c1.reference_unique
    FROM contrats c1
    INNER JOIN (
        SELECT logement_id, MAX(date_creation) as max_date
        FROM contrats
        WHERE statut = 'valide'
        GROUP BY logement_id
    ) c2 ON c1.logement_id = c2.logement_id 
        AND c1.date_creation = c2.max_date
    WHERE c1.statut = 'valide'
) c ON l.id = c.logement_id
ORDER BY l.reference
```

This query:
- Shows ALL logements
- Includes the reference of the LAST VALIDATED contract (if exists)
- Uses LEFT JOIN so logements without validated contracts still appear
- Allows users to see which logements have validated contracts

## Testing Results

✅ All syntax checks passed
✅ SQL queries validated
✅ Code review passed (no issues)
✅ CodeQL security scan passed
✅ Custom validation tests passed

## User Impact

### Before
❌ Confusing error messages about "signed contracts"
❌ No visibility into which logements have contracts
❌ Unclear which contracts are eligible for état des lieux

### After
✅ Clear error messages about "validated contracts"
✅ Contract references shown in dropdown
✅ Easy to identify which logements have validated contracts
✅ Better user experience when creating états des lieux

## Security Summary

No security vulnerabilities were introduced or identified:
- ✅ Proper parameterized queries (PDO prepared statements)
- ✅ Proper HTML escaping (htmlspecialchars with ENT_QUOTES)
- ✅ Input validation maintained
- ✅ No SQL injection risks
- ✅ No XSS risks

---

**Implementation Date:** February 7, 2026
**Status:** ✅ Complete and Tested
