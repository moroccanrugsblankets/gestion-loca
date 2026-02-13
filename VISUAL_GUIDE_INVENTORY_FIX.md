# Visual Guide: Before and After Inventory Fix

## Before the Fix ❌

When accessing `/admin-v2/manage-inventory-equipements.php`, only 3 categories were visible:

```
📦 Meubles
   - Chaises (2)
   - Canapé (1)
   - Table à manger (1)
   - Table basse (1)
   - Placards intégrées (1)
   [+ conditional items based on logement type]

📦 Électroménager
   - Réfrigérateur (1)
   - Machine à laver séchante (1)
   - Télévision (1)
   - Fire Stick (1)
   - Plaque de cuisson (1)
   [+ conditional items based on logement type]

📦 Équipement 1 (Cuisine / Vaisselle)
   - Grandes assiettes (4)
   - Assiettes à dessert (4)
   - Assiettes creuses (4)
   - Fourchettes (4)
   - Petites cuillères (4)
   - Grandes cuillères (4)
   - Couteaux de table (4)
   - Verres (4)
   - Bols (4)
   - Tasses (4)
   - Saladier (1)
   - Poêle (1)
   - Casserole (1)
   - Planche à découper (1)

❌ Équipement 2 (Linge / Entretien) - MISSING!
```

## After the Fix ✅

Now all 4 categories are present and complete:

```
📦 Meubles
   - Chaises (2) ✓ Bon État
   - Canapé (1) ✓ Bon État
   - Table à manger (1) ✓ Bon État
   - Table basse (1) ✓ Bon État
   - Placards intégrées (1) ✓ Bon État
   [+ conditional items based on logement type]

📦 Électroménager
   - Réfrigérateur (1)
   - Machine à laver séchante (1)
   - Télévision (1)
   - Fire Stick (1)
   - Plaque de cuisson (1)
   [+ conditional items based on logement type]

📦 Équipement 1 (Cuisine / Vaisselle)
   - Grandes assiettes (4)
   - Assiettes à dessert (4)
   - Assiettes creuses (4)
   - Fourchettes (4)
   - Petites cuillères (4)
   - Grandes cuillères (4)
   - Couteaux de table (4)
   - Verres (4)
   - Bols (4)
   - Tasses (4)
   - Saladier (1)
   - Poêle (1)
   - Casserole (1)
   - Planche à découper (1)

✨ 📦 Équipement 2 (Linge / Entretien) - NOW PRESENT! ✨
   - Matelas (1)
   - Oreillers (2)
   - Taies d'oreiller (2)
   - Draps du dessous (1)
   - Couette (1)
   - Housse de couette (1)
   - Alaise (1)
   - Plaid (1)
```

## Conditional Items by Logement Type

### Regular Logement (e.g., RP-01, RP-03, RP-05)
- Base items from all 4 categories
- Total: ~33 items

### RC-01, RC-02, RP-07 (Special with Bedroom)
- Base items from all 4 categories
- **PLUS** in Meubles:
  - Lit double (1)
  - Tables de chevets (2)
- **PLUS** for RC-01/RC-02 in Meubles:
  - Lustres / Plafonniers (1)
  - Lampadaire (1)
- **PLUS** for RC-01/RC-02 in Électroménager:
  - Four grill / micro-ondes (1)
  - Aspirateur (1)

### RC and RF Prefixes (e.g., RC-03, RF-01, RF-02)
- Base items from all 4 categories
- **PLUS** in Meubles:
  - Lustres / Plafonniers (1)
  - Lampadaire (1)
- **PLUS** in Électroménager:
  - Four grill / micro-ondes (1)
  - Aspirateur (1)

## Code Changes Overview

### Main Change in `includes/inventaire-standard-items.php`

```diff
         ],
     ];
     
+    // Add property-specific items for RC-01, RC-02, RP-07
+    if ($is_rc01_02_rp07) {
+        $items['Meubles'][] = ['nom' => 'Lit double', 'type' => 'countable', 'quantite' => 1, 'default_etat' => 'bon'];
+        $items['Meubles'][] = ['nom' => 'Tables de chevets', 'type' => 'countable', 'quantite' => 2, 'default_etat' => 'bon'];
+    }
+    
+    // 🛏 ÉQUIPEMENT 2 (Linge / Entretien)
+    'Équipement 2 (Linge / Entretien)' => [
+        ['nom' => 'Matelas', 'type' => 'countable', 'quantite' => 1],
+        ['nom' => 'Oreillers', 'type' => 'countable', 'quantite' => 2],
+        ['nom' => 'Taies d\'oreiller', 'type' => 'countable', 'quantite' => 2],
+        ['nom' => 'Draps du dessous', 'type' => 'countable', 'quantite' => 1],
+        ['nom' => 'Couette', 'type' => 'countable', 'quantite' => 1],
+        ['nom' => 'Housse de couette', 'type' => 'countable', 'quantite' => 1],
+        ['nom' => 'Alaise', 'type' => 'countable', 'quantite' => 1],
+        ['nom' => 'Plaid', 'type' => 'countable', 'quantite' => 1],
+    ],
```

## Database Impact

### For New Logements (After Code Deployment)
✅ Automatically includes all 4 categories via the updated template

### For Existing Logements (Requires Migration)
Run migration 050:
```bash
php run-migrations.php
```
or
```bash
php migrations/050_add_equipement_2_linge_entretien.php
```

Expected output:
```
=== Migration 050: Add Équipement 2 (Linge / Entretien) Category ===
Found X logements to update

Processing Logement #1 (RC-01)...
  - Added 8 items from Équipement 2 category
  ✓ Logement #1 updated successfully

Processing Logement #2 (RP-03)...
  - Added 8 items from Équipement 2 category
  ✓ Logement #2 updated successfully

[...]

========================================
Migration 050 completed successfully!
Total items added: XX
All logements now have Équipement 2 (Linge / Entretien) category
========================================
```

## Verification

After deploying and running the migration, verify by:

1. **Via Admin Interface:**
   - Go to `/admin-v2/manage-inventory-equipements.php?logement_id=1`
   - Scroll down to see all 4 categories
   - Verify "Équipement 2 (Linge / Entretien)" is present with 8 items

2. **Via Database:**
   ```sql
   SELECT 
       l.reference,
       COUNT(DISTINCT ie.categorie) as category_count,
       SUM(CASE WHEN ie.categorie = 'Équipement 2 (Linge / Entretien)' THEN 1 ELSE 0 END) as equipement2_count
   FROM logements l
   LEFT JOIN inventaire_equipements ie ON l.id = ie.logement_id
   GROUP BY l.id, l.reference
   ORDER BY l.reference;
   ```
   
   Expected result: Each logement should have 4 categories and 8 items in Équipement 2.

## Summary

- ✅ **Before:** 3 categories, missing bedding/linen items
- ✅ **After:** 4 categories, complete with all required items
- ✅ **Impact:** Improves inventory tracking for all housing units
- ✅ **Deployment:** Code + Migration required for existing data
