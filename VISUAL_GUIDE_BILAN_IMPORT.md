# Visual Guide: Import Feature for Bilan Logement

## 📋 Overview

This feature allows you to import equipment and comments from the exit inventory (état de lieux de sortie) directly into the financial assessment form (bilan du logement).

## 🎯 What Problem Does This Solve?

### Before (Manual Process):
```
Step 1: Fill exit inventory
┌─────────────────────────────┐
│ Equipment: Clé appartement  │
│ Comment: Manquante          │
└─────────────────────────────┘

Step 2: Switch to bilan page
Step 3: Manually re-type EVERYTHING
┌─────────────────────────────┐
│ Poste: Clé appartement     │  ← Manual typing (copy/paste)
│ Comment: Manquante          │  ← Manual typing (copy/paste)
│ Valeur: [fill in]          │
│ Montant: [fill in]         │
└─────────────────────────────┘

❌ Time-consuming
❌ Error-prone
❌ Risk of typos
```

### After (Automated Import):
```
Step 1: Fill exit inventory
┌─────────────────────────────┐
│ Equipment: Clé appartement  │
│ Comment: Manquante          │
└─────────────────────────────┘

Step 2: Switch to bilan page
Step 3: Click "Import" button ✨
┌─────────────────────────────┐
│ Poste: Clé appartement     │  ← Auto-filled ✓
│ Comment: Manquante          │  ← Auto-filled ✓
│ Valeur: [fill in]          │
│ Montant: [fill in]         │
└─────────────────────────────┘

✓ Fast
✓ Accurate
✓ Consistent
```

## 🖥️ User Interface

### Location: `/admin-v2/edit-bilan-logement.php`

```
┌─────────────────────────────────────────────────────────┐
│                 Bilan du Logement                       │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Tableau des dégradations                               │
│                                                         │
│  [🔽 Importer depuis l'état de sortie] [➕ Ajouter]  │ ← NEW BUTTON
│                                                         │
│  ┌────────────┬─────────────┬────────┬──────────┐    │
│  │ Équipement │ Commentaire │ Valeur │ Montant  │    │
│  ├────────────┼─────────────┼────────┼──────────┤    │
│  │            │             │        │          │    │
│  └────────────┴─────────────┴────────┴──────────┘    │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

### Button Visibility

**Button appears when:**
- Exit inventory has equipment/comment data
- Shows as green button with download icon

**Button hidden when:**
- No exit inventory data exists
- Keeps UI clean when not needed

## 📊 Example Data Flow

### Input: Exit Inventory Data

```json
// Stored in database column: bilan_sections_data
{
  "cles": [
    {
      "equipement": "Clé appartement",
      "commentaire": "1 clé manquante"
    },
    {
      "equipement": "Clé boîte aux lettres",
      "commentaire": "Conforme"
    }
  ],
  "cuisine": [
    {
      "equipement": "Four",
      "commentaire": "Traces de brûlure sur la porte"
    },
    {
      "equipement": "Réfrigérateur",
      "commentaire": "Joint endommagé"
    }
  ],
  "salle_eau": [
    {
      "equipement": "Robinetterie",
      "commentaire": "Fuite légère au niveau du mitigeur"
    }
  ]
}
```

### Output: Table Rows

After clicking the import button:

| # | Poste/Équipement | Commentaires | Valeur | Montant dû |
|---|------------------|--------------|--------|------------|
| 1 | Clé appartement | 1 clé manquante | _[empty]_ | _[empty]_ |
| 2 | Clé boîte aux lettres | Conforme | _[empty]_ | _[empty]_ |
| 3 | Four | Traces de brûlure sur la porte | _[empty]_ | _[empty]_ |
| 4 | Réfrigérateur | Joint endommagé | _[empty]_ | _[empty]_ |
| 5 | Robinetterie | Fuite légère au niveau du mitigeur | _[empty]_ | _[empty]_ |

**Then the user fills in:**
- Valeur (€): Estimated cost of repair/replacement
- Montant dû (€): Amount charged to tenant

## 🔄 Step-by-Step Workflow

### Complete Process

```
┌─────────────────────────────────────────────────────┐
│ 1. Create Exit Inventory                           │
│    /admin-v2/edit-etat-lieux.php                    │
└────────────────┬────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────┐
│ 2. Fill in sections with equipment & comments       │
│    - Compteurs (meters)                             │
│    - Clés (keys)                                    │
│    - Pièce principale (main room)                   │
│    - Cuisine (kitchen)                              │
│    - Salle d'eau (bathroom)                         │
└────────────────┬────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────┐
│ 3. Save the exit inventory                         │
│    Data saved to: bilan_sections_data (JSON)        │
└────────────────┬────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────┐
│ 4. Navigate to Bilan Logement                      │
│    /admin-v2/edit-bilan-logement.php?id=X          │
└────────────────┬────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────┐
│ 5. See the import button                           │
│    [🔽 Importer depuis l'état de sortie]           │
└────────────────┬────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────┐
│ 6. Click the button                                │
│    → Confirmation dialog appears                    │
│    → Click OK                                       │
└────────────────┬────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────┐
│ 7. Data imported! ✓                                │
│    All equipment & comments now in table            │
│    Button changes to: [✓ Données importées]        │
│    (and becomes disabled)                           │
└────────────────┬────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────┐
│ 8. Fill in financial values                        │
│    - Add Valeur (€) for each item                  │
│    - Add Montant dû (€) for each item              │
└────────────────┬────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────┐
│ 9. Save the bilan                                  │
│    Click: [💾 Enregistrer le bilan]                │
│    Data saved to: bilan_logement_data (JSON)        │
└─────────────────────────────────────────────────────┘
```

## 🎬 User Interaction

### Dialog Messages

**Confirmation:**
```
╔══════════════════════════════════════════╗
║ Confirmation                             ║
╠══════════════════════════════════════════╣
║ Voulez-vous importer les équipements et ║
║ commentaires depuis l'état de sortie?   ║
║                                          ║
║ Cela ajoutera de nouvelles lignes au    ║
║ tableau.                                 ║
║                                          ║
║         [Annuler]    [OK]               ║
╚══════════════════════════════════════════╝
```

**Success:**
```
╔══════════════════════════════════════════╗
║ Succès                                   ║
╠══════════════════════════════════════════╣
║ 5 ligne(s) importée(s) avec succès     ║
║                                          ║
║              [OK]                       ║
╚══════════════════════════════════════════╝
```

**No data:**
```
╔══════════════════════════════════════════╗
║ Information                              ║
╠══════════════════════════════════════════╣
║ Aucune donnée à importer depuis         ║
║ l'état de sortie                        ║
║                                          ║
║              [OK]                       ║
╚══════════════════════════════════════════╝
```

## 🔒 Security Features

### 1. XSS Prevention
```javascript
function escapeHtml(text) {
    // Converts: <script>alert('xss')</script>
    // To: &lt;script&gt;alert('xss')&lt;/script&gt;
}
```

### 2. Row Limit
- Maximum 20 rows enforced
- Prevents table overflow
- Stops import if limit reached

### 3. One-Time Import
- Button disables after use
- Prevents accidental duplicates
- Can be re-enabled by refreshing page if needed

### 4. Confirmation Dialog
- User must confirm before import
- Prevents accidental clicks
- Clear explanation of what will happen

## 📱 Responsive Design

The button works on all screen sizes:

**Desktop:**
```
Tableau des dégradations  [🔽 Importer...] [➕ Ajouter]
```

**Mobile:**
```
Tableau des dégradations
[🔽 Importer...]
[➕ Ajouter]
```

## 🧪 Testing Checklist

To verify the feature works:

- [ ] Create an état de sortie with equipment/comments
- [ ] Navigate to edit-bilan-logement.php
- [ ] Verify import button is visible and green
- [ ] Click the import button
- [ ] Confirm the dialog
- [ ] Verify all items appear in table
- [ ] Verify equipment → Poste column
- [ ] Verify comments → Commentaires column
- [ ] Verify Valeur and Montant are empty
- [ ] Verify button changes to "Données importées"
- [ ] Verify button is disabled
- [ ] Fill in financial values
- [ ] Save and verify data persists
- [ ] Refresh page - verify import button is still disabled
- [ ] Test with empty exit inventory - button should not appear
- [ ] Test with 15+ items - verify 20 row limit

## 🚀 Benefits

### Time Savings
- **Before:** ~5-10 minutes manual data entry
- **After:** ~30 seconds click and fill

### Accuracy
- **Before:** Typos, inconsistencies, missing items
- **After:** Exact copy from source, no errors

### User Experience
- **Before:** Frustrating, repetitive
- **After:** Smooth, efficient

### Data Integrity
- **Before:** Risk of mismatches between documents
- **After:** Guaranteed consistency

## 📝 Notes

- Import button only appears for "sortie" type états
- Financial fields (Valeur, Montant) remain empty for manual entry
- Import is additive - doesn't replace existing rows
- Button can be used multiple times if page is refreshed (but not recommended)
- All imported data can be edited after import
- Maximum 20 rows total (including imported + manual)

## 🐛 Troubleshooting

**Button doesn't appear?**
- Check that this is a "sortie" type état
- Verify that exit inventory has equipment/comment data
- Check browser console for JavaScript errors

**Import not working?**
- Refresh the page and try again
- Check browser console for errors
- Verify you clicked "Confirm" in the dialog

**Imported data looks wrong?**
- Check the source data in edit-etat-lieux.php
- Data is imported exactly as saved
- You can delete rows and re-add them manually

**Button is disabled?**
- This is normal after import
- Refresh page if you need to import again
- Or manually add/edit rows as needed
