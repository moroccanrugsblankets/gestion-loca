# Pull Request Summary

## ✅ Issue Resolved: Email Notifications Use Configured Templates

### Problem Statement
The application was sending emails with hardcoded subjects and content instead of using email templates configured in the backoffice (`/admin-v2/email-templates.php`).

**Example of the issue:**
- **Email received:** `[ADMIN] Contrat signé - BAIL-69814790F242E`
- **Template configured:** `Contrat signé - {{reference}} - Vérification requise`

### Root Cause
Multiple files were calling `sendEmail()` directly with hardcoded subjects and HTML bodies instead of using the `sendTemplatedEmail()` function that fetches templates from the database.

## Solution Implemented

### 1️⃣ Fixed Status Change Emails
**File:** `/admin-v2/change-status.php`

**Before:**
```php
switch ($nouveau_statut) {
    case 'Accepté':
        $subject = "Candidature acceptée - MyInvest Immobilier";
        break;
    // ... more hardcoded subjects
}
$htmlBody = getStatusChangeEmailHTML($nom_complet, $nouveau_statut, $commentaire);
sendEmail($to, $subject, $htmlBody, null, true, $isAdminEmail);
```

**After:**
```php
$templateMap = [
    'accepte' => 'candidature_acceptee',
    'refuse' => 'candidature_refusee',
    'visite_planifiee' => 'statut_visite_planifiee',
    'contrat_envoye' => 'statut_contrat_envoye',
    'contrat_signe' => 'statut_contrat_signe'
];
$templateId = $templateMap[$nouveau_statut] ?? null;
sendTemplatedEmail($templateId, $to, $variables, null, $isAdminEmail);
```

### 2️⃣ Fixed Candidature Response Emails
**File:** `/candidature/reponse-candidature.php`

**Before:**
```php
$emailSubject = ($action === 'positive') 
    ? 'Suite à votre candidature'
    : 'Réponse à votre candidature';
$emailBody = getStatusChangeEmailHTML($nomComplet, ucfirst($newStatus), '');
sendEmail($candidature['email'], $emailSubject, $emailBody, null, true);
```

**After:**
```php
$templateId = ($action === 'positive') ? 'candidature_acceptee' : 'candidature_refusee';
sendTemplatedEmail($templateId, $candidature['email'], $variables, null, false);
```

### 3️⃣ Created Missing Email Templates

Added 3 new templates for status change notifications:

| Template ID | Subject | Purpose |
|------------|---------|---------|
| `statut_visite_planifiee` | "Visite de logement planifiée - MY Invest Immobilier" | Sent when visit is scheduled |
| `statut_contrat_envoye` | "Contrat de bail - MY Invest Immobilier" | Sent when contract is sent |
| `statut_contrat_signe` | "Contrat signé - MY Invest Immobilier" | Sent when contract is signed (candidature status) |

### 4️⃣ Updated Admin Notification Template

**Template:** `contrat_finalisation_admin`
- **Old subject:** `[ADMIN] Contrat signé - {{reference}}`
- **New subject:** `Contrat signé - {{reference}} - Vérification requise` ✅

This now matches the template configured in the backoffice.

### 5️⃣ Created Database Migration

**File:** `migrations/023_update_email_templates_add_status_templates.sql`

The migration:
1. Updates the `contrat_finalisation_admin` template subject
2. Adds the 3 new status change templates

## Files Changed

| File | Type | Description |
|------|------|-------------|
| `admin-v2/change-status.php` | Modified | Use templates for status change emails |
| `candidature/reponse-candidature.php` | Modified | Use templates for response emails |
| `init-email-templates.php` | Modified | Add new templates, update existing |
| `migrations/022_add_contract_finalisation_email_templates.sql` | Modified | Update template subject |
| `migrations/023_update_email_templates_add_status_templates.sql` | New | Migration for new templates |
| `FIX_EMAIL_TEMPLATES_USAGE.md` | New | Documentation |
| `PR_SUMMARY_EMAIL_TEMPLATES.md` | New | This file |

## Verification

### ✅ All Automated Emails Now Use Templates

Verified that ALL automated email sending in the application uses templates:

| Location | Email Type | Uses Template? |
|----------|-----------|----------------|
| `/candidature/submit.php` | Candidature received | ✅ `candidature_recue` |
| `/candidature/submit.php` | Admin notification | ✅ `admin_nouvelle_candidature` |
| `/candidature/reponse-candidature.php` | Accept/Refuse response | ✅ `candidature_acceptee/refusee` |
| `/admin-v2/change-status.php` | Status change | ✅ Status-specific templates |
| `/admin-v2/generer-contrat.php` | Contract invitation | ✅ `contrat_signature` |
| `/signature/step3-documents.php` | Contract finalized (client) | ✅ `contrat_finalisation_client` |
| `/signature/step3-documents.php` | Contract finalized (admin) | ✅ `contrat_finalisation_admin` |
| `/cron/process-candidatures.php` | Automatic responses | ✅ `candidature_acceptee/refusee` |

**Exception:** `/admin-v2/send-email-candidature.php` allows admins to send custom emails with custom subjects/bodies. This is **intentional functionality** for flexibility.

### ✅ Template Management

All templates are now:
- Stored in the `email_templates` table
- Editable via the backoffice interface at `/admin-v2/email-templates.php`
- Support variable replacement (e.g., `{{nom}}`, `{{reference}}`, `{{signature}}`)
- Versioned and tracked in migrations

## Deployment Instructions

1. **Deploy code changes** to production
2. **Run migrations:**
   ```bash
   php run-migrations.php
   ```
   OR alternatively:
   ```bash
   php init-email-templates.php
   ```

3. **Verify:** Send a test email by changing a candidature status and confirming the email uses the template

## Testing

To test the fix:

1. Go to `/admin-v2/candidatures.php`
2. Change a candidature status with "Send email" checked
3. Verify the email received has the subject from the template
4. Modify the template in `/admin-v2/email-templates.php`
5. Send another email and verify changes are applied

## Benefits

✅ **Centralized email management:** All email content is managed in one place  
✅ **No code changes needed:** Email content can be updated via admin interface  
✅ **Consistent branding:** All emails use the same design templates  
✅ **Template variables:** Dynamic content using `{{variable}}` syntax  
✅ **Maintainability:** Easier to update email content without touching code

## Security

- ✅ No security vulnerabilities introduced
- ✅ All user input is properly escaped before being used in templates
- ✅ Template variables are replaced safely
- ✅ No SQL injection risks in migrations
- ✅ CodeQL analysis: No issues detected

---

**Issue Status:** ✅ **RESOLVED**

All emails sent by the application now use templates configured in the backoffice, exactly as specified in the requirements.
