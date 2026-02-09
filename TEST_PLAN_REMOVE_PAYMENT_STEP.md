# Test Plan - Remove Payment Proof Step

## Objective
Validate that the signature workflow has been successfully simplified from 4 steps to 3 steps, and that payment proof request emails are sent automatically after contract signature.

## Prerequisites
- [ ] Code deployed
- [ ] Migration 038 executed successfully
- [ ] Email template `demande_justificatif_paiement` exists and is active
- [ ] Test environment configured with working email system

## Test Cases

### TC1: Verify Workflow Steps Count
**Priority**: HIGH  
**Steps**:
1. Access a signature link for a contract
2. Count the number of steps displayed
3. Verify progress bar percentages

**Expected Result**:
- ✅ 3 steps displayed (not 4)
- ✅ Step 1: 33% - "Étape 1/3 - Informations"
- ✅ Step 2: 66% - "Étape 2/3 - Signature"
- ✅ Step 3: 100% - "Étape 3/3 - Documents d'identité"

### TC2: Verify Step 3 is NOT Payment Upload
**Priority**: HIGH  
**Steps**:
1. Complete steps 1 and 2
2. Observe step 3 content

**Expected Result**:
- ✅ Step 3 shows "Vérification d'identité"
- ✅ Step 3 requests ID documents (recto/verso)
- ✅ Step 3 does NOT request payment proof upload
- ✅ No file upload field for "preuve de virement"

### TC3: Verify Email Sending After Signature
**Priority**: HIGH  
**Steps**:
1. Complete entire signature workflow
2. Upload ID documents (recto/verso)
3. Submit final step
4. Check email inbox of the tenant

**Expected Result**:
- ✅ 2 emails received
- ✅ Email 1: Subject contains "Contrat de bail – Finalisation"
- ✅ Email 1: Contains PDF attachment (signed contract)
- ✅ Email 2: Subject contains "Justificatif de virement"
- ✅ Email 2: Contains payment instructions and banking details
- ✅ Both emails received within reasonable time (< 5 minutes)

### TC4: Verify Email Content (Confirmation)
**Priority**: MEDIUM  
**Steps**:
1. Open the "Contrat de bail – Finalisation" email
2. Review content

**Expected Result**:
- ✅ Personalized greeting with tenant name
- ✅ Contract reference displayed
- ✅ Deposit amount displayed correctly
- ✅ Banking details (IBAN, BIC) displayed
- ✅ PDF contract attached
- ✅ Signature/footer present

### TC5: Verify Email Content (Payment Proof Request)
**Priority**: MEDIUM  
**Steps**:
1. Open the "Justificatif de virement" email
2. Review content

**Expected Result**:
- ✅ Confirmation that contract is signed
- ✅ Contract reference displayed
- ✅ Deposit amount displayed correctly
- ✅ Banking details reminder (IBAN, BIC, amount)
- ✅ Clear instructions on how to send proof (email, phone)
- ✅ Contact email displayed: contact@myinvest-immobilier.fr
- ✅ Contact phone displayed: 01 23 45 67 89
- ✅ Message about bail effectiveness conditional on proof reception

### TC6: Verify Confirmation Page
**Priority**: MEDIUM  
**Steps**:
1. Complete signature workflow
2. Review confirmation page content

**Expected Result**:
- ✅ Success message displayed
- ✅ Mentions "2 emails" will be received
- ✅ Lists both emails:
  - Email with signed contract
  - Email requesting payment proof
- ✅ Instructions to send proof by email
- ✅ Banking details displayed
- ✅ Mentions bail effectiveness conditional on proof verification

### TC7: Verify Multi-Tenant Workflow
**Priority**: MEDIUM  
**Steps**:
1. Create a contract with 2 tenants
2. Complete signature for tenant 1
3. Complete signature for tenant 2
4. Check both tenants' emails

**Expected Result**:
- ✅ After tenant 1 finishes, redirected to step1 for tenant 2
- ✅ After tenant 2 finishes, workflow completes
- ✅ Each tenant receives 2 emails
- ✅ Total: 4 emails sent (2 per tenant)

### TC8: Verify Admin Email
**Priority**: MEDIUM  
**Steps**:
1. Complete signature workflow
2. Check admin email inbox

**Expected Result**:
- ✅ Admin receives notification email
- ✅ Email subject: "Contrat signé - [reference] - Vérification requise"
- ✅ Email contains contract details
- ✅ Email contains link to admin panel
- ✅ PDF contract attached

### TC9: Verify Email Template in Admin
**Priority**: LOW  
**Steps**:
1. Login to admin panel
2. Navigate to /admin-v2/email-templates.php
3. Search for "demande_justificatif_paiement"

**Expected Result**:
- ✅ Template exists in list
- ✅ Template is active (actif = 1)
- ✅ Template name: "Demande de justificatif de paiement"
- ✅ Can edit template
- ✅ Variables list shown: nom, prenom, reference, depot_garantie

### TC10: Verify Database Migration
**Priority**: LOW  
**Steps**:
1. Connect to database
2. Query: `SELECT * FROM email_templates WHERE identifiant = 'demande_justificatif_paiement'`

**Expected Result**:
- ✅ Record exists
- ✅ `actif = 1`
- ✅ `sujet` contains "Justificatif de virement"
- ✅ `corps_html` contains HTML template
- ✅ `variables_disponibles` contains JSON array

### TC11: Verify Backward Compatibility
**Priority**: MEDIUM  
**Steps**:
1. Check contracts signed before this change
2. Verify they still display correctly in admin

**Expected Result**:
- ✅ Old contracts viewable in admin
- ✅ Payment proof field still visible (if previously uploaded)
- ✅ No errors when viewing old contracts

### TC12: Verify Progress Bar Styling
**Priority**: LOW  
**Steps**:
1. Go through signature workflow
2. Observe progress bars on each step

**Expected Result**:
- ✅ Step 1: Green bar at 33% width
- ✅ Step 2: Green bar at 66% width
- ✅ Step 3: Green bar at 100% width
- ✅ Progress bars display smoothly
- ✅ No visual glitches

## Regression Tests

### R1: ID Document Upload Still Works
**Steps**:
1. Complete steps 1-2
2. Upload ID documents (recto/verso) in step 3
3. Submit

**Expected Result**:
- ✅ Files upload successfully
- ✅ Files saved to server
- ✅ Database updated with file paths
- ✅ No errors

### R2: Second Tenant Flow Still Works
**Steps**:
1. Create contract with 2 tenants
2. Complete workflow for both

**Expected Result**:
- ✅ After tenant 1, redirected to step1 for tenant 2
- ✅ Second tenant can complete workflow
- ✅ Both tenants' data saved correctly

### R3: CSRF Protection Still Active
**Steps**:
1. Submit forms without CSRF token
2. Submit forms with invalid token

**Expected Result**:
- ✅ Requests rejected
- ✅ Error message displayed
- ✅ No data saved

### R4: Session Validation Still Works
**Steps**:
1. Try accessing steps without proper session
2. Try accessing with expired session

**Expected Result**:
- ✅ Access denied
- ✅ Error message displayed
- ✅ Redirected or blocked

## Performance Tests

### P1: Email Sending Speed
**Steps**:
1. Complete signature workflow
2. Measure time until emails received

**Expected Result**:
- ✅ Emails sent within 30 seconds
- ✅ No timeout errors
- ✅ Both emails sent successfully

### P2: Workflow Completion Time
**Steps**:
1. Measure time to complete full workflow
2. Compare with old 4-step workflow (if data available)

**Expected Result**:
- ✅ Workflow completes faster than before
- ✅ No performance degradation
- ✅ Page loads normally

## Edge Cases

### E1: Invalid Email Address
**Steps**:
1. Create contract with invalid email
2. Complete signature

**Expected Result**:
- ✅ Email sending fails gracefully
- ✅ Error logged
- ✅ Workflow still completes
- ✅ Admin notified of email failure

### E2: Missing Deposit Amount
**Steps**:
1. Create contract with depot_garantie = 0 or null
2. Complete signature

**Expected Result**:
- ✅ Email sent with "0 €" or placeholder
- ✅ No crash
- ✅ Workflow completes

### E3: Very Long Names
**Steps**:
1. Create tenant with very long name (>100 chars)
2. Complete signature

**Expected Result**:
- ✅ Name displayed correctly in email
- ✅ No truncation issues
- ✅ Email renders properly

## Test Summary Template

```
Test Date: _______________
Tester: _________________
Environment: ____________

Total Tests: 15
Passed: ____
Failed: ____
Blocked: ____
Not Run: ____

Critical Issues: ________
Major Issues: __________
Minor Issues: __________

Sign-off: ☐ Approved  ☐ Rejected  ☐ Conditional

Notes:
_________________________
_________________________
_________________________
```

## Acceptance Criteria

✅ All HIGH priority tests pass  
✅ All MEDIUM priority tests pass  
✅ No critical or major issues found  
✅ Email template configurable in admin  
✅ Documentation reviewed and approved  

**Once all criteria met**: ✅ Ready for Production Deployment
