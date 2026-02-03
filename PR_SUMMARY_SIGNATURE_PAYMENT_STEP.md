# Pull Request: Add Security Deposit Payment Step to Signature Workflow

## 🎯 Objective

Add a new step in the signature workflow for uploading payment proof of the security deposit, and update the identity verification step with regulatory compliance text.

## 📋 Changes Summary

### New Features
1. **New Step 3: Security Deposit Payment Proof Upload**
   - Added between signature and identity document steps
   - Allows tenants to upload payment proof for the security deposit
   - Includes clear instructions and amount display

2. **Updated Step 4: Identity Verification** 
   - Enhanced regulatory compliance text
   - More professional and clear explanation of why documents are needed
   - Emphasizes data confidentiality

### Workflow Changes

**Before (3 steps):**
1. Step 1/3 - Informations du locataire
2. Step 2/3 - Signature électronique  
3. Step 3/3 - Documents d'identité

**After (4 steps):**
1. Step 1/4 - Informations du locataire
2. Step 2/4 - Signature électronique
3. **Step 3/4 - Versement du dépôt de garantie** ✨ NEW
4. Step 4/4 - Vérification d'identité (updated content)

## 📸 UI Preview

![Signature Workflow UI Changes](https://github.com/user-attachments/assets/ac773e3a-9826-4af1-ab6f-404004c8f2e6)

The screenshot shows:
- **NEW Step 3/4**: Payment proof upload with clear instructions
- **UPDATED Step 4/4**: Identity verification with enhanced compliance text
- Workflow comparison (before/after)
- Updated progress bars for all steps

## 🗂️ Files Changed

### New Files
- `signature/step3-payment.php` - Payment proof upload step
- `signature/step4-documents.php` - Updated identity verification step (replaces old step3)
- `migrations/024_add_payment_proof_field.sql` - Database migration
- `SIGNATURE_WORKFLOW_PAYMENT_STEP.md` - Technical documentation
- `SIGNATURE_WORKFLOW_UI_PREVIEW.html` - UI preview

### Modified Files
- `signature/step1-info.php` - Updated progress bar to 1/4
- `signature/step2-signature.php` - Updated progress bar to 2/4, redirect to step3-payment
- `includes/functions.php` - Added `updateTenantPaymentProof()` function

### Deleted Files
- `signature/step3-documents.php` - Replaced by step4-documents.php

## 📝 Step 3: Payment Proof Upload

### Page Content

**Title:** Versement du dépôt de garantie

**Instructions:**
> Afin de finaliser la prise d'effet du bail, nous vous remercions d'effectuer le virement bancaire immédiat d'un montant de **[montant] €**, correspondant au dépôt de garantie.
> 
> Une fois le virement effectué, merci de nous transmettre la preuve de paiement en la téléchargeant ci-dessous.

**Upload Field:**
- Label: "👉 Télécharger la preuve de virement"
- Accepts: JPG, PNG, PDF
- Max size: 5 MB

**Behavior:**
- Validates uploaded file
- Saves to `locataires.preuve_paiement_depot`
- Logs action as `upload_preuve_paiement`
- Redirects to Step 4 (identity documents)

## 📝 Step 4: Identity Verification (Updated)

### Page Content

**Title:** Vérification d'identité du ou des locataires

**Section Title:** Justificatif(s) d'identité

**Regulatory Compliance Text:**
> Conformément à la réglementation en vigueur et afin de finaliser le dossier de location, nous vous remercions de nous transmettre une copie de la pièce d'identité de chaque titulaire du bail (carte nationale d'identité ou passeport).
> 
> Ces documents sont nécessaires afin de vérifier que les signataires du bail sont bien les personnes qui louent le logement. Les données transmises sont traitées de manière strictement confidentielle.

**Upload Fields:**
- Pièce d'identité - Recto
- Pièce d'identité - Verso

## 🗄️ Database Changes

### Migration: 024_add_payment_proof_field.sql

```sql
ALTER TABLE locataires
ADD COLUMN preuve_paiement_depot VARCHAR(255) DEFAULT NULL AFTER piece_identite_verso;
```

Adds a new column to store the filename of the uploaded payment proof document.

## 💻 Code Changes

### New Function: `updateTenantPaymentProof()`

```php
/**
 * Mettre à jour la preuve de paiement du dépôt de garantie d'un locataire
 * @param int $locataireId
 * @param string $preuvePaiement
 * @return bool
 */
function updateTenantPaymentProof($locataireId, $preuvePaiement) {
    $sql = "UPDATE locataires SET preuve_paiement_depot = ? WHERE id = ?";
    $stmt = executeQuery($sql, [$preuvePaiement, $locataireId]);
    return $stmt !== false;
}
```

### Progress Bar Updates

All progress bars updated to reflect 4 steps:
- **Step 1/4:** 25% (was 33%)
- **Step 2/4:** 50% (was 66%)
- **Step 3/4:** 75% (NEW)
- **Step 4/4:** 100% (was Step 3/3)

## 🔄 User Flow

```
Step 1: Enter tenant information
    ↓
Step 2: Electronic signature
    ↓
Step 3: Upload payment proof ← NEW STEP
    ↓
Step 4: Upload ID documents (recto/verso) ← UPDATED CONTENT
    ↓
Confirmation page
```

## ✅ Testing Checklist

- [ ] Run migration: `php run-migrations.php`
- [ ] Create a test contract and generate signature link
- [ ] Complete Step 1: Enter tenant information
- [ ] Complete Step 2: Sign electronically
- [ ] Complete Step 3: Upload payment proof (verify file validation)
- [ ] Complete Step 4: Upload ID documents
- [ ] Verify all files are saved in database
- [ ] Check confirmation page displays correctly
- [ ] Verify progress bars show correctly on each step
- [ ] Test with 2 tenants to ensure flow works for multiple signers

## 🚀 Deployment Instructions

1. **Deploy code changes** to production
2. **Run migration:**
   ```bash
   php run-migrations.php
   ```
3. **Test the signature workflow** with a test contract
4. **Verify uploads** are working and files are stored correctly

## 📊 Impact

### For Administrators
- ✅ Collect payment proof earlier in the process
- ✅ Better verification that deposit has been paid before finalizing
- ✅ Stored in database for easy access

### For Tenants
- ✅ Clear instructions on what to upload
- ✅ Logical flow: sign → pay → verify identity
- ✅ Professional, compliant identity verification text

### Technical
- ✅ Minimal code changes
- ✅ Follows existing patterns (file upload, validation)
- ✅ Progress bars correctly reflect 4-step process
- ✅ No breaking changes to existing functionality

## 📚 Documentation

- `SIGNATURE_WORKFLOW_PAYMENT_STEP.md` - Complete technical documentation
- `SIGNATURE_WORKFLOW_UI_PREVIEW.html` - Interactive UI preview
- Code comments in all modified/new files

## 🔐 Security

- ✅ Same file validation as other uploads (type, size checks)
- ✅ CSRF token protection on all forms
- ✅ Session validation on all steps
- ✅ Files stored in secure upload directory

## ✨ Summary

This PR successfully adds a security deposit payment proof upload step to the signature workflow, making it a 4-step process instead of 3. The new step provides clear instructions for tenants and captures the payment proof early in the process. Additionally, the identity verification step now includes enhanced regulatory compliance language that better explains why documents are needed and emphasizes data confidentiality.

All changes follow existing code patterns, maintain security standards, and provide a better user experience for both tenants and administrators.
