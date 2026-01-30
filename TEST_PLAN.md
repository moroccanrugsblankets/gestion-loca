# Test Plan for Issue Fixes

## Overview
This document describes the testing procedure for the three issues addressed in this PR:
1. Email signature management (centralized via Paramètres)
2. Document download 404 error fix
3. "Revenus nets mensuels" field verification

## Issue 1: Email Signature Management

### What Was Changed
- **Migration 010**: Removed hardcoded signatures from 3 email templates:
  - `candidature_recue` (Accusé de réception)
  - `candidature_acceptee` (Candidature acceptée)
  - `candidature_refusee` (Candidature refusée)
- The `sendEmail()` function in `includes/mail-templates.php` already appends the signature dynamically from the `parametres` table

### How It Works
1. Email templates no longer contain hardcoded "Cordialement, MY Invest Immobilier..." text
2. The `sendEmail()` function fetches the signature from `parametres.email_signature`
3. Signature is appended automatically to all HTML emails: `$finalBody = $body . '<br><br>' . $signature`
4. Admins can modify the signature via Admin → Paramètres

### Testing Steps
1. **Run the migration:**
   ```bash
   php run-migrations.php
   ```
   Expected: Migration 010 should execute successfully

2. **Verify template updates:**
   ```sql
   SELECT identifiant, SUBSTRING(corps_html, -100) as ending
   FROM email_templates
   WHERE identifiant IN ('candidature_recue', 'candidature_acceptee', 'candidature_refusee');
   ```
   Expected: No hardcoded "Cordialement, MY Invest..." should appear in the HTML

3. **Check signature parameter:**
   ```sql
   SELECT cle, valeur FROM parametres WHERE cle = 'email_signature';
   ```
   Expected: Should return the HTML signature content

4. **Test email sending:**
   - Submit a test candidature
   - Check received email
   - Expected: Email should contain the dynamic signature from parametres

5. **Modify signature:**
   - Go to Admin → Paramètres
   - Update the "Signature des emails" field
   - Submit a new candidature
   - Expected: New emails should use the updated signature

### Success Criteria
- ✅ No hardcoded signatures in email templates
- ✅ All emails include the signature from parametres table
- ✅ Signature can be modified via Admin interface
- ✅ No duplicate signatures in emails

## Issue 2: Document Download 404 Error

### What Was Changed
- **File**: `admin-v2/candidature-detail.php` line 374
- **Change**: Added `/uploads/` prefix to document download links
- **Before**: `href="../<?php echo htmlspecialchars($doc['path']); ?>"`
- **After**: `href="../uploads/<?php echo htmlspecialchars($doc['path']); ?>"`

### How It Works
1. Documents are uploaded to: `/uploads/candidatures/{candidature_id}/filename.ext`
2. Database stores relative path: `candidatures/{candidature_id}/filename.ext`
3. Download link now correctly resolves to: `/uploads/candidatures/{candidature_id}/filename.ext`

### Testing Steps
1. **Verify directory structure:**
   ```bash
   ls -la uploads/candidatures/
   ```
   Expected: Should see subdirectories for each candidature ID

2. **Submit a test candidature with documents:**
   - Go to candidature form
   - Upload required documents (ID, payslips, etc.)
   - Submit candidature

3. **Check file storage:**
   ```bash
   ls -la uploads/candidatures/{new_candidature_id}/
   ```
   Expected: Uploaded files should be present

4. **Test document download:**
   - Go to Admin → Candidatures → Click on candidature
   - Click "Télécharger" button on any document
   - Expected: Document should download successfully (no 404 error)

5. **Verify database paths:**
   ```sql
   SELECT id, nom_fichier, chemin_fichier 
   FROM candidature_documents 
   WHERE candidature_id = {test_id};
   ```
   Expected: `chemin_fichier` should be `candidatures/{id}/filename.ext`

### Success Criteria
- ✅ Document downloads work without 404 errors
- ✅ Files are stored in correct location: `/uploads/candidatures/{id}/`
- ✅ Download links correctly point to `/uploads/candidatures/{id}/filename`
- ✅ All document types (PDF, JPG, PNG) download successfully

## Issue 3: "Revenus nets mensuels" Field

### What Was Found
The field **already exists** in `admin-v2/candidature-detail.php` at lines 309-314:
```php
<div class="info-row">
    <div class="info-label">Revenus nets mensuels:</div>
    <div class="info-value">
        <strong><?php echo htmlspecialchars($candidature['revenus_mensuels']); ?></strong>
    </div>
</div>
```

### Testing Steps
1. **Verify field display:**
   - Go to Admin → Candidatures
   - Click on any candidature
   - Look for "Situation Financière" section
   - Expected: "Revenus nets mensuels" field should be visible

2. **Verify data retrieval:**
   ```sql
   SELECT id, nom, prenom, revenus_mensuels 
   FROM candidatures 
   LIMIT 5;
   ```
   Expected: Column exists and contains data (e.g., "< 2300", "2300-3000", "3000+")

3. **Submit test candidature:**
   - Submit a new candidature with specific revenue range
   - Check the candidature detail page
   - Expected: Selected revenue range should display correctly

### Success Criteria
- ✅ Field is visible in candidature detail page
- ✅ Field is in the "Situation Financière" section
- ✅ Data is retrieved from database correctly
- ✅ Data is displayed properly (not empty or null)

## Security Validation

### Document Access Security
The `.htaccess` file in `/uploads/` ensures:
- Only image files (jpg, jpeg, png) and PDFs can be accessed
- PHP scripts cannot be executed
- This prevents malicious file uploads

Test this with:
```bash
cat uploads/.htaccess
```

### File Upload Validation
The `candidature/submit.php` validates:
- File MIME type (only PDF, JPEG, PNG allowed)
- File size (max 5MB)
- Secure filename generation with random bytes

## Deployment Checklist

Before deploying to production:
1. [ ] Backup database
2. [ ] Run migration 010: `php run-migrations.php`
3. [ ] Verify email templates updated correctly
4. [ ] Test document downloads on existing candidatures
5. [ ] Test new candidature submission
6. [ ] Verify email signature displays correctly
7. [ ] Test signature modification via Paramètres
8. [ ] Check that "Revenus nets mensuels" displays on all candidature detail pages

## Rollback Plan

If issues are encountered:

### For Email Signatures
- Revert migration 010 or manually restore old template content
- Templates can be edited via Admin → Templates d'Email

### For Document Downloads
- Revert `admin-v2/candidature-detail.php` to remove `/uploads/` prefix
- Or update database paths to include `/uploads/` prefix

### For Revenus Field
- No rollback needed (field was already present)

## Notes
- All changes are minimal and focused on the specific issues
- No breaking changes to existing functionality
- Email signature system was already implemented; we just removed duplication
- Document storage location hasn't changed; only the download link was fixed
