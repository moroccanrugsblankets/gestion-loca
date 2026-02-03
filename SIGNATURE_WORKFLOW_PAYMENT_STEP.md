# Signature Workflow - New Payment Step

## Summary of Changes

Added a new step in the signature workflow for security deposit payment proof upload and updated the identity verification step with new regulatory compliance text.

## Workflow Changes

### Before (3 steps):
1. Step 1/3 - Informations du locataire
2. Step 2/3 - Signature électronique
3. Step 3/3 - Documents d'identité

### After (4 steps):
1. Step 1/4 - Informations du locataire
2. Step 2/4 - Signature électronique
3. **Step 3/4 - Versement du dépôt de garantie** ✨ NEW
4. Step 4/4 - Vérification d'identité (updated content)

## New Step 3: Security Deposit Payment

### Page Title
**Versement du dépôt de garantie**

### Content
- Alert box with instructions:
  - "Afin de finaliser la prise d'effet du bail, nous vous remercions d'effectuer le virement bancaire immédiat d'un montant de X €, correspondant au dépôt de garantie."
  - "Une fois le virement effectué, merci de nous transmettre la preuve de paiement en la téléchargeant ci-dessous."
- Upload field: "👉 Télécharger la preuve de virement"
- Accepts: JPG, PNG, PDF - Max 5 MB

### Features
- File validation (same as other document uploads)
- Saves to `locataires.preuve_paiement_depot` field
- Logs action: `upload_preuve_paiement`
- Redirects to Step 4 (documents) after upload

## Updated Step 4: Identity Verification

### Page Title (updated)
**Vérification d'identité du ou des locataires**

### Section Title
**Justificatif(s) d'identité**

### New Regulatory Compliance Text
Info alert with:
- "Conformément à la réglementation en vigueur et afin de finaliser le dossier de location, nous vous remercions de nous transmettre une copie de la pièce d'identité de chaque titulaire du bail (carte nationale d'identité ou passeport)."
- "Ces documents sont nécessaires afin de vérifier que les signataires du bail sont bien les personnes qui louent le logement. Les données transmises sont traitées de manière strictement confidentielle."

### Upload Fields
- Pièce d'identité - Recto
- Pièce d'identité - Verso

## Database Changes

### Migration: 024_add_payment_proof_field.sql
```sql
ALTER TABLE locataires
ADD COLUMN preuve_paiement_depot VARCHAR(255) DEFAULT NULL AFTER piece_identite_verso;
```

## Code Changes

### Files Modified:
1. `signature/step1-info.php` - Progress bar: 1/4 (was 1/3)
2. `signature/step2-signature.php` - Progress bar: 2/4 (was 2/3), redirect to step3-payment.php
3. `signature/step3-payment.php` - NEW FILE for payment proof upload
4. `signature/step4-documents.php` - NEW FILE (replaces step3-documents.php) with updated content
5. `signature/step3-documents.php` - DELETED (replaced by step4-documents.php)
6. `includes/functions.php` - Added `updateTenantPaymentProof()` function
7. `migrations/024_add_payment_proof_field.sql` - NEW migration

### New Function Added:
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

## Progress Bars

All progress bars have been updated to reflect 4 steps:
- Step 1: width: 25% (was 33%)
- Step 2: width: 50% (was 66%)
- Step 3: width: 75% (NEW)
- Step 4: width: 100% (was Step 3)

## User Flow

```
Step 1: Enter tenant information
    ↓
Step 2: Electronic signature
    ↓
Step 3: Upload payment proof ← NEW STEP
    ↓
Step 4: Upload ID documents (recto/verso)
    ↓
Confirmation page
```

## Testing

To test this workflow:
1. Run migration: `php run-migrations.php`
2. Create a contract and generate signature link
3. Follow the signature process through all 4 steps
4. Verify payment proof is saved in database
5. Verify all content matches requirements

## Deployment

1. Deploy code changes
2. Run migration: `php run-migrations.php` to add the new database column
3. Test the complete signature workflow
