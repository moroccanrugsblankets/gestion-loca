# Visual Guide: Dynamic Equipment Loading

## Before vs After

### BEFORE (Hardcoded)
```
┌─────────────────────────────────────────────────────┐
│ edit-inventaire.php?id=2                            │
├─────────────────────────────────────────────────────┤
│                                                     │
│  1. Load inventaire record                          │
│  2. Get standard items from PHP file ❌             │
│     ↓                                               │
│     inventaire-standard-items.php (HARDCODED)       │
│     - État des pièces                               │
│     - Meubles                                       │
│     - Électroménager                                │
│     - etc. (ALL PROPERTIES GET SAME LIST)           │
│                                                     │
│  3. Render form with hardcoded equipment            │
│                                                     │
└─────────────────────────────────────────────────────┘
```

### AFTER (Dynamic)
```
┌─────────────────────────────────────────────────────┐
│ edit-inventaire.php?id=2                            │
├─────────────────────────────────────────────────────┤
│                                                     │
│  1. Load inventaire record                          │
│  2. Get logement_id from inventaire                 │
│  3. Query database for equipment ✅                 │
│     ↓                                               │
│     ┌─────────────────────────────────────┐        │
│     │ SELECT FROM inventaire_equipements  │        │
│     │ WHERE logement_id = X               │        │
│     └─────────────────────────────────────┘        │
│     ↓                                               │
│  4. Found equipment?                                │
│     ├── YES → Use logement-specific equipment       │
│     │         (from manage-inventory-equipements)   │
│     │                                               │
│     └── NO  → Fallback to standard items            │
│               (from inventaire-standard-items.php)  │
│                                                     │
│  5. Render form with equipment                      │
│                                                     │
└─────────────────────────────────────────────────────┘
```

## Data Flow

### Equipment Management Flow
```
┌──────────────────────────────────────────────────────────┐
│                                                          │
│  Admin navigates to:                                     │
│  manage-inventory-equipements.php?logement_id=5          │
│                                                          │
│  ┌────────────────────────────────────────────┐         │
│  │ Add/Edit/Delete Equipment for Logement #5  │         │
│  │                                             │         │
│  │ - Réfrigérateur (Électroménager)           │         │
│  │ - Table de salon (Mobilier)                │         │
│  │ - Canapé 3 places (Mobilier)               │         │
│  │ - etc.                                      │         │
│  └────────────────────────────────────────────┘         │
│                      ↓                                   │
│            Saves to database                             │
│                      ↓                                   │
│  ┌─────────────────────────────────────────────┐        │
│  │ inventaire_equipements table                │        │
│  │                                              │        │
│  │ logement_id | categorie_id | nom            │        │
│  │ ─────────── | ─────────── | ────────────    │        │
│  │     5       |      1      | Réfrigérateur   │        │
│  │     5       |      2      | Table de salon  │        │
│  │     5       |      2      | Canapé 3 places │        │
│  └─────────────────────────────────────────────┘        │
│                                                          │
└──────────────────────────────────────────────────────────┘
```

### Inventory Creation Flow
```
┌──────────────────────────────────────────────────────────┐
│                                                          │
│  User opens: edit-inventaire.php?id=2                    │
│                                                          │
│  ┌────────────────────────────────────────────┐         │
│  │ 1. Load inventaire #2                       │         │
│  │    - type: entree                           │         │
│  │    - logement_id: 5                         │         │
│  │    - contrat_id: 12                         │         │
│  └────────────────────────────────────────────┘         │
│                      ↓                                   │
│  ┌────────────────────────────────────────────┐         │
│  │ 2. Query equipment for logement_id=5        │         │
│  │    SELECT * FROM inventaire_equipements     │         │
│  │    WHERE logement_id = 5                    │         │
│  └────────────────────────────────────────────┘         │
│                      ↓                                   │
│  ┌────────────────────────────────────────────┐         │
│  │ 3. Equipment found:                         │         │
│  │    - Réfrigérateur                          │         │
│  │    - Table de salon                         │         │
│  │    - Canapé 3 places                        │         │
│  └────────────────────────────────────────────┘         │
│                      ↓                                   │
│  ┌────────────────────────────────────────────┐         │
│  │ 4. Transform to view structure:             │         │
│  │                                             │         │
│  │ Électroménager:                             │         │
│  │   - Réfrigérateur                           │         │
│  │                                             │         │
│  │ Mobilier:                                   │         │
│  │   - Table de salon                          │         │
│  │   - Canapé 3 places                         │         │
│  └────────────────────────────────────────────┘         │
│                      ↓                                   │
│  ┌────────────────────────────────────────────┐         │
│  │ 5. Render inventory form with equipment     │         │
│  │                                             │         │
│  │ [Entry columns] [Exit columns]              │         │
│  │ Réfrigérateur    □ □ □       □ □ □          │         │
│  │ Table de salon   □ □ □       □ □ □          │         │
│  │ Canapé 3 places  □ □ □       □ □ □          │         │
│  └────────────────────────────────────────────┘         │
│                                                          │
└──────────────────────────────────────────────────────────┘
```

## Database Schema

```
┌────────────────────────────────────────┐
│ inventaire_equipements                 │
├────────────────────────────────────────┤
│ id (PK)                                │
│ logement_id (FK → logements.id)        │
│ categorie_id (FK → categories.id)      │
│ sous_categorie_id (FK → subcats.id)    │
│ categorie (VARCHAR) - legacy field     │
│ nom                                    │
│ description                            │
│ quantite                               │
│ valeur_estimee                         │
│ ordre                                  │
└────────────────────────────────────────┘
         ↓
┌────────────────────────────────────────┐
│ inventaire_categories                  │
├────────────────────────────────────────┤
│ id (PK)                                │
│ nom (Électroménager, Mobilier, etc.)   │
│ icone (bi-plugin, bi-house-door, etc.) │
│ ordre (for sorting)                    │
│ actif                                  │
└────────────────────────────────────────┘
         ↓
┌────────────────────────────────────────┐
│ inventaire_sous_categories             │
├────────────────────────────────────────┤
│ id (PK)                                │
│ categorie_id (FK)                      │
│ nom (Entrée, Cuisine, Chambre 1, etc.) │
│ ordre                                  │
│ actif                                  │
└────────────────────────────────────────┘
```

## Benefits Visualization

### Flexibility
```
BEFORE: All logements → Same equipment list
AFTER:  Each logement → Custom equipment list

Logement A (Studio)          Logement B (Maison)
  - Lit simple                 - Lit double
  - Table                      - Table salle à manger
  - Chaise                     - 6 Chaises
  - Réfrigérateur             - Réfrigérateur
                               - Lave-vaisselle
                               - Lave-linge
                               - Sèche-linge
                               - TV
                               - Canapé
```

### Maintainability
```
BEFORE: To change equipment
  1. Edit PHP code
  2. Deploy code
  3. Risk breaking system

AFTER: To change equipment
  1. Use admin interface
  2. Done! ✅
```

## Summary

✅ **Dynamic**: Equipment loaded from database per logement
✅ **Flexible**: Each logement can have different equipment
✅ **Maintainable**: No code changes needed
✅ **User-Friendly**: Managed via web interface
✅ **Backward Compatible**: Falls back to standard items
✅ **Secure**: Prepared statements, authentication

## Date
2026-02-13
