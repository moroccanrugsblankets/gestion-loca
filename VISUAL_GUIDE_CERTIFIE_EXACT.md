# Visual Guide: "Certifié exact" Checkbox Implementation

## 📋 Overview
This guide shows the visual changes made to add the "Certifié exact" checkbox to the état des lieux form and PDF.

---

## 🖥️ Form Changes (admin-v2/edit-etat-lieux.php)

### Before:
```
┌──────────────────────────────────────────────┐
│ Signature locataire 1 - Jean Dupont         │
├──────────────────────────────────────────────┤
│ Veuillez signer dans le cadre ci-dessous :  │
│                                              │
│ ┌────────────────────────────────┐          │
│ │                                │          │
│ │     [Canvas de signature]      │          │
│ │                                │          │
│ └────────────────────────────────┘          │
│                                              │
│ [Effacer]                                    │
│                                              │
└──────────────────────────────────────────────┘
```

### After:
```
┌──────────────────────────────────────────────┐
│ Signature locataire 1 - Jean Dupont         │
├──────────────────────────────────────────────┤
│ Veuillez signer dans le cadre ci-dessous :  │
│                                              │
│ ┌────────────────────────────────┐          │
│ │                                │          │
│ │     [Canvas de signature]      │          │
│ │                                │          │
│ └────────────────────────────────┘          │
│                                              │
│ [Effacer]                                    │
│                                              │
│ ☑ Certifié exact  ← NEW!                   │
│                                              │
└──────────────────────────────────────────────┘
```

---

## 📄 PDF Changes (pdf/generate-etat-lieux.php)

### Before:
```
┌─────────────────────┬─────────────────────┬─────────────────────┐
│   Le bailleur :     │   Locataire 1:      │   Locataire 2:      │
│                     │                     │                     │
│  [Signature Img]    │  [Signature Img]    │  [Signature Img]    │
│                     │                     │                     │
│                     │   Signé le          │   Signé le          │
│ Fait à Annemasse    │   07/02/2026        │   07/02/2026        │
│ Le 07/02/2026       │   à 14:30           │   à 15:15           │
│                     │                     │                     │
│ MY Invest           │   Jean Dupont       │   Marie Martin      │
│ Immobilier          │                     │                     │
└─────────────────────┴─────────────────────┴─────────────────────┘
```

### After:
```
┌─────────────────────┬─────────────────────┬─────────────────────┐
│   Le bailleur :     │   Locataire 1:      │   Locataire 2:      │
│                     │                     │                     │
│  [Signature Img]    │  [Signature Img]    │  [Signature Img]    │
│                     │                     │                     │
│                     │   Signé le          │   Signé le          │
│ Fait à Annemasse    │   07/02/2026        │   07/02/2026        │
│ Le 07/02/2026       │   à 14:30           │   à 15:15           │
│                     │                     │                     │
│ MY Invest           │   ☑ Certifié exact │   ☑ Certifié exact │
│ Immobilier          │   ← NEW!            │   ← NEW!            │
│                     │                     │                     │
│                     │   Jean Dupont       │   Marie Martin      │
└─────────────────────┴─────────────────────┴─────────────────────┘
```

**Note:** The "☑ Certifié exact" only appears in the PDF if the tenant checked the box in the form.

---

## 🗄️ Database Schema Change

### New Column in `etat_lieux_locataires` table:

```sql
ALTER TABLE etat_lieux_locataires 
ADD COLUMN certifie_exact BOOLEAN DEFAULT FALSE AFTER signature_ip;
```

**Table Structure After Migration:**
```
etat_lieux_locataires
├── id (PRIMARY KEY)
├── etat_lieux_id
├── locataire_id
├── ordre
├── nom
├── prenom
├── email
├── signature_data
├── signature_timestamp
├── signature_ip
└── certifie_exact ← NEW!
```

---

## 💻 Code Changes Summary

### 1. Form Submission Handler
**Location:** `admin-v2/edit-etat-lieux.php` (lines 100-105)

```php
// Update certifie_exact checkbox
$certifieExact = isset($tenantInfo['certifie_exact']) ? 1 : 0;
$stmt = $pdo->prepare("UPDATE etat_lieux_locataires SET certifie_exact = ? WHERE id = ?");
$stmt->execute([$certifieExact, $tenantId]);
```

### 2. Form Display
**Location:** `admin-v2/edit-etat-lieux.php` (lines 955-967)

```html
<div class="mt-3">
    <div class="form-check">
        <input class="form-check-input" type="checkbox" 
               name="tenants[<?php echo $tenant['id']; ?>][certifie_exact]" 
               id="certifie_exact_<?php echo $tenant['id']; ?>" 
               value="1"
               <?php echo !empty($tenant['certifie_exact']) ? 'checked' : ''; ?>>
        <label class="form-check-label" for="certifie_exact_<?php echo $tenant['id']; ?>">
            <strong>Certifié exact</strong>
        </label>
    </div>
</div>
```

### 3. PDF Display
**Location:** `pdf/generate-etat-lieux.php` (lines 1225-1229)

```php
// Display "Certifié exact" checkbox status
if (!empty($tenantInfo['certifie_exact'])) {
    $html .= '<p style="font-size:8pt; margin-top: 5px;">☑ Certifié exact</p>';
}
```

---

## ✅ Usage Scenario

### Step 1: Edit Form
1. Admin navigates to `/admin-v2/edit-etat-lieux.php?id=5`
2. Tenant signs using the canvas
3. Tenant (or admin on behalf) checks "☑ Certifié exact"
4. Form is saved

### Step 2: View PDF
1. PDF is generated for the état des lieux
2. In the signature section, under the signature timestamp, the text "☑ Certifié exact" appears
3. This certifies that the tenant has reviewed and certified the inventory as accurate

---

## 🎯 Benefits

1. **Legal Compliance:** Provides explicit tenant certification of inventory accuracy
2. **Per-Tenant Tracking:** Each tenant can independently certify
3. **PDF Evidence:** Certification appears in the official PDF document
4. **User-Friendly:** Simple checkbox interface, consistent with existing form patterns
5. **Database Persistence:** Checkbox state is permanently stored

---

## 📝 Testing Checklist

- [ ] Migration runs successfully
- [ ] Checkbox appears on edit form for each tenant
- [ ] Checking the box and saving persists the value
- [ ] Unchecking the box and saving clears the value
- [ ] PDF shows "☑ Certifié exact" only when checked
- [ ] PDF does NOT show the text when unchecked
- [ ] Works correctly with 1 tenant
- [ ] Works correctly with 2 tenants
- [ ] Works with mixed states (one tenant checked, one unchecked)
