# Implementation: "Certifié exact" Checkbox for État des Lieux

## Overview
Added a "Certifié exact" (Certified exact) checkbox to the état des lieux (inventory report) form and PDF generation, as requested for page `/admin-v2/edit-etat-lieux.php?id=5`.

## Changes Made

### 1. Database Migration
**File:** `migrations/031_add_certifie_exact_to_etat_lieux_locataires.php`
- Added `certifie_exact` BOOLEAN column to `etat_lieux_locataires` table
- Column default: FALSE
- Positioned after `signature_ip` column

**To run the migration:**
```bash
cd /home/runner/work/contrat-de-bail/contrat-de-bail
php migrations/031_add_certifie_exact_to_etat_lieux_locataires.php
```

### 2. Form Updates
**File:** `admin-v2/edit-etat-lieux.php`

**Changes:**
1. **Form Submission Handling (lines 100-105):**
   - Added logic to save `certifie_exact` checkbox value for each tenant
   - Checkbox value is stored in `etat_lieux_locataires` table per tenant

2. **Form Display (lines 955-967):**
   - Added checkbox input after the signature canvas
   - Checkbox appears for each tenant
   - Label: "Certifié exact" in bold
   - Pre-checked if value is already set in database

### 3. PDF Generation
**File:** `pdf/generate-etat-lieux.php`

**Changes (lines 1225-1229):**
- Added display of "☑ Certifié exact" in the PDF signature section
- Appears after the signature timestamp
- Only shown if the checkbox was checked (certifie_exact = 1)
- Font size: 8pt with 5px top margin
- Uses checkbox symbol: ☑

## Visual Location

### In the Form:
```
┌─────────────────────────────────────┐
│ Signature locataire [1/2]          │
│ ┌─────────────────┐                │
│ │ [Canvas Area]   │                │
│ └─────────────────┘                │
│ [Effacer Button]                   │
│ ☑ Certifié exact  ← NEW CHECKBOX  │
└─────────────────────────────────────┘
```

### In the PDF (Signature Section):
```
┌─────────────────────────────────────┐
│ Locataire 1:                        │
│ [Signature Image]                   │
│ Signé le 07/02/2026 à 14:30        │
│ ☑ Certifié exact  ← NEW DISPLAY    │
│ Jean Dupont                         │
└─────────────────────────────────────┘
```

## Testing Instructions

1. **Run the migration:**
   ```bash
   php migrations/031_add_certifie_exact_to_etat_lieux_locataires.php
   ```

2. **Test the form:**
   - Navigate to `/admin-v2/edit-etat-lieux.php?id=5`
   - Check the "Certifié exact" checkbox for one or more tenants
   - Save the form
   - Verify the checkbox remains checked after page reload

3. **Test the PDF:**
   - Generate a PDF for the état des lieux
   - Verify "☑ Certifié exact" appears after the signature for tenants who checked the box
   - Verify it does NOT appear for tenants who didn't check the box

## Database Schema

**Table:** `etat_lieux_locataires`

New column:
```sql
certifie_exact BOOLEAN DEFAULT FALSE
```

This column is:
- Tied to each tenant's record in the `etat_lieux_locataires` table
- Independent per tenant (one tenant can check it, another may not)
- Stored as a boolean (0/1) value

## Files Modified
1. `migrations/031_add_certifie_exact_to_etat_lieux_locataires.php` (NEW)
2. `admin-v2/edit-etat-lieux.php` (MODIFIED)
3. `pdf/generate-etat-lieux.php` (MODIFIED)

## Notes
- The checkbox is per-tenant, not per-état-lieux
- Each tenant can independently certify the inventory as exact
- The checkbox state is preserved in the database
- The PDF only shows the checkbox symbol when it was checked
