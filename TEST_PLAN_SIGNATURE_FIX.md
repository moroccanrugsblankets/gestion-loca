# Test Plan: Signature Flow Fix

## Issue Description
**Critical Issue**: The signature flow was jumping from `/signature/index.php` directly to `/signature/step3-documents.php` where locataire 2 appeared instead of locataire 1. The system should show locataire 1 first, then ask if there's a second tenant.

## Fix Summary
- Moved the "second tenant question" from step3-documents.php to step2-signature.php
- Question now appears immediately after locataire 1 signs
- Ensures correct ordering: locataire 1 always before locataire 2

## Test Scenarios

### Test Case 1: Single Tenant Contract
**Setup**: Create a contract with `nb_locataires = 1`

**Steps**:
1. Navigate to `/signature/index.php?token=<contract_token>`
2. Accept the contract → should redirect to step1-info.php
3. Fill in tenant information (nom, prenom, date_naissance, email)
4. Click "Suivant" → should redirect to step2-signature.php
5. Draw signature and check "Certifié exact"
6. Click "Valider la signature"
7. **Expected**: Should redirect directly to step3-documents.php (no question about second tenant)
8. **Expected**: Should see "Locataire 1" on the documents page
9. Upload identity documents (recto required, verso optional)
10. Click "Finaliser"
11. **Expected**: Contract should be finalized, redirected to confirmation page

**Result**: ✓ PASS / ✗ FAIL

---

### Test Case 2: Two Tenant Contract - User Declines Second Tenant
**Setup**: Create a contract with `nb_locataires = 2`

**Steps**:
1. Navigate to `/signature/index.php?token=<contract_token>`
2. Accept the contract → should redirect to step1-info.php
3. Fill in first tenant information
4. Click "Suivant" → should redirect to step2-signature.php
5. Draw signature and check "Certifié exact"
6. Click "Valider la signature"
7. **Expected**: Should see success message "Votre signature a été enregistrée avec succès !"
8. **Expected**: Should see question "Y a-t-il un second locataire ?"
9. Select "Non, je suis le seul locataire"
10. Click "Continuer" → should redirect to step3-documents.php
11. **Expected**: Should see "Locataire 1" on the documents page
12. Upload identity documents for locataire 1
13. Click "Finaliser"
14. **Expected**: Contract should be finalized, redirected to confirmation page

**Result**: ✓ PASS / ✗ FAIL

---

### Test Case 3: Two Tenant Contract - User Accepts Second Tenant
**Setup**: Create a contract with `nb_locataires = 2`

**Steps**:
1. Navigate to `/signature/index.php?token=<contract_token>`
2. Accept the contract → should redirect to step1-info.php
3. Fill in first tenant information (Locataire 1)
4. Click "Suivant" → should redirect to step2-signature.php
5. Draw signature for locataire 1 and check "Certifié exact"
6. Click "Valider la signature"
7. **Expected**: Should see question "Y a-t-il un second locataire ?"
8. Select "Oui, il y a un second locataire"
9. Click "Continuer" → should redirect to step1-info.php
10. **Expected**: Should see "Informations du locataire 2"
11. Fill in second tenant information (Locataire 2)
12. Click "Suivant" → should redirect to step2-signature.php
13. **Expected**: Should see "Signature du locataire 2"
14. Draw signature for locataire 2 and check "Certifié exact"
15. Click "Valider la signature" → should redirect to step3-documents.php
16. **Expected**: Should see "Locataire 1" on the documents page (NOT Locataire 2!)
17. Upload identity documents for locataire 1
18. Click "Finaliser" → should reload step3-documents.php
19. **Expected**: Should now see "Locataire 2" on the documents page
20. Upload identity documents for locataire 2
21. Click "Finaliser"
22. **Expected**: Contract should be finalized, redirected to confirmation page

**Result**: ✓ PASS / ✗ FAIL

---

## Critical Validation Points

### ✓ Locataire 1 Always First
- In step3-documents.php, locataire 1's documents MUST be uploaded before locataire 2's
- The heading should show "Locataire 1" first, then "Locataire 2"

### ✓ No Skipping Steps
- Flow must go through: index → step1 → step2 → (question for 2 tenants) → step3
- Cannot jump directly from index to step3

### ✓ Session Management
- After answering "oui" to second tenant question, session should be cleared
- After locataire 2 signs, session should be cleared before step3
- This ensures defensive fallback in step3 finds tenants in correct order

### ✓ Question Only Shows for Locataire 1
- The "second tenant question" should only appear after locataire 1 signs
- Should NOT appear after locataire 2 signs
- Should NOT appear for single tenant contracts

## Database Verification

After completing the flow, verify in the database:

```sql
-- Check that locataires are ordered correctly
SELECT ordre, nom, prenom, signature_timestamp, piece_identite_recto 
FROM locataires 
WHERE contrat_id = <contract_id> 
ORDER BY ordre ASC;

-- Should show:
-- ordre=1: locataire 1 with signature and documents
-- ordre=2: locataire 2 with signature and documents (if applicable)
```

## Logs to Check

Enable `DEBUG_MODE` in config.php and check error logs for:
- "Step2-Signature: Signature enregistrée avec succès"
- "Step2-Signature: Locataire ID: X, Numéro: Y"
- These should show locataire 1 before locataire 2

## Rollback Plan

If the fix causes issues, revert commits:
```bash
git revert 3906642 63427b2
```

This will restore the old behavior where the question was in step3-documents.php.
