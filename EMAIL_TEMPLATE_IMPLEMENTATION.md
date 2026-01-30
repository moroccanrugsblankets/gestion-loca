# Email Template System - Implementation Documentation

## Overview
This document describes the implementation of the database-driven email template system with proper signature support for all emails sent by the application.

## Problems Fixed

### 1. {{signature}} Not Being Interpreted
**Problem:** The `{{signature}}` placeholder was not being replaced in emails because:
- Hardcoded email functions (`getCandidatureRecueEmailHTML`, `getAdminNewCandidatureEmailHTML`) were used instead of database templates
- These functions didn't include the `{{signature}}` placeholder

**Solution:**
- Updated `candidature/submit.php` to use `sendTemplatedEmail()` which loads templates from database
- All database templates include `{{signature}}` placeholder
- The `sendEmail()` function replaces `{{signature}}` with content from `parametres.email_signature`

### 2. Email Templates Not Manageable in Admin
**Problem:** 
- The "Candidature locative reçue" email was hardcoded and not visible in admin panel
- Admin notification email was also hardcoded

**Solution:**
- All emails now use database templates that can be managed via `/admin-v2/email-templates.php`
- Templates in database:
  - `candidature_recue` - Confirmation email to candidate
  - `admin_nouvelle_candidature` - Notification to administrators
  - `candidature_acceptee` - Acceptance email
  - `candidature_refusee` - Rejection email

### 3. Missing Email Signature Format
**Problem:** Email signature didn't include logo and proper company formatting as required

**Solution:**
- Created migration `013_update_email_signature_format.sql`
- Updated `parametres.email_signature` with HTML including:
  - Company logo (https://www.myinvest-immobilier.com/images/logo.png)
  - Company name in formatted table
  - "Sincères salutations" greeting

### 4. Additional Admin Email Support
**Problem:** No way to configure additional administrator to receive candidature notifications

**Solution:**
- Added `email_admin_candidature` parameter to database
- Can be configured via `/admin-v2/parametres.php`
- `sendEmailToAdmins()` now checks this parameter and sends to additional admin if configured

## Technical Implementation

### Files Modified

#### 1. `candidature/submit.php`
**Before:**
```php
$subject = 'Candidature locative reçue - MY Invest Immobilier';
$htmlBody = getCandidatureRecueEmailHTML($prenom, $nom, $logement, $total_uploaded);
$emailSent = sendEmail($email, $subject, $htmlBody, null, true);
```

**After:**
```php
$candidateVariables = [
    'nom' => $nom,
    'prenom' => $prenom,
    'email' => $email,
    'logement' => $logement['reference'],
    'reference' => $reference_unique,
    'date' => date('d/m/Y H:i')
];

$emailSent = sendTemplatedEmail('candidature_recue', $email, $candidateVariables);
```

#### 2. `includes/mail-templates.php`
**Changes:**
- Added helper functions:
  - `getEmailTemplate($identifiant)` - Fetch template from database
  - `replaceTemplateVariables($template, $data)` - Replace {{variable}} placeholders
  - `getParameter($cle, $default)` - Get parameter from database
  
- Updated `sendEmailToAdmins()`:
  - Added `$templateVariables` parameter
  - Loads `admin_nouvelle_candidature` template when variables provided
  - Checks `email_admin_candidature` parameter for additional recipients

#### 3. `migrations/013_update_email_signature_format.sql`
**Creates:**
- Updates `email_signature` parameter with logo and company info HTML
- Adds `email_admin_candidature` parameter

## Email Template Variables

### candidature_recue
- `{{nom}}` - Candidate last name
- `{{prenom}}` - Candidate first name
- `{{email}}` - Candidate email
- `{{logement}}` - Property reference
- `{{reference}}` - Application reference
- `{{date}}` - Submission date
- `{{signature}}` - Email signature (auto-replaced)

### admin_nouvelle_candidature
- `{{reference}}` - Application reference
- `{{nom}}` - Candidate last name
- `{{prenom}}` - Candidate first name
- `{{email}}` - Candidate email
- `{{telephone}}` - Candidate phone
- `{{logement}}` - Property info
- `{{revenus}}` - Monthly income
- `{{statut_pro}}` - Professional status
- `{{date}}` - Submission date
- `{{lien_admin}}` - Link to admin panel
- `{{signature}}` - Email signature (auto-replaced)

## How Signature Replacement Works

1. **Template Storage**: Database templates contain `{{signature}}` placeholder
2. **Variable Replacement**: `replaceTemplateVariables()` replaces all variables EXCEPT `{{signature}}`
3. **Email Sending**: `sendEmail()` function:
   - Detects `{{signature}}` in body
   - Fetches signature from `parametres.email_signature`
   - Replaces `{{signature}}` with actual signature HTML

This two-step process ensures:
- Templates remain editable without breaking signature
- Signature is centrally managed
- All emails get consistent signature

## Configuration

### Email Signature
Location: `/admin-v2/parametres.php`
Parameter: `email_signature`

Contains HTML:
```html
<p>Sincères salutations</p>
<br><br>
<table>
    <tbody>
        <tr>
            <td>
                <img src="https://www.myinvest-immobilier.com/images/logo.png" alt="MY Invest Immobilier" style="max-width: 120px;">
            </td>
            <td>&nbsp;&nbsp;&nbsp;</td>
            <td>
                <h3 style="margin: 0; color: #2c3e50;">
                    MY INVEST IMMOBILIER
                </h3>
            </td>
        </tr>
    </tbody>
</table>
```

### Additional Admin Email
Location: `/admin-v2/parametres.php`
Parameter: `email_admin_candidature`

Set to email address of additional administrator who should receive candidature notifications.

## Testing

Run the test script to verify:
```bash
php /tmp/test-email-templates.php
```

The test checks:
- ✓ Templates exist in database
- ✓ Templates contain {{signature}} placeholder
- ✓ Email signature parameter is set correctly
- ✓ email_admin_candidature parameter exists
- ✓ Variable replacement works
- ✓ Signature replacement works

## Migration Path

1. **Deploy Code**: Update files on server
2. **Run Migration**: Execute `migrations/013_update_email_signature_format.sql`
3. **Configure**: Set `email_admin_candidature` parameter if needed
4. **Verify**: Check templates in `/admin-v2/email-templates.php`
5. **Test**: Submit test candidature and verify emails

## Backward Compatibility

The hardcoded email functions (`getCandidatureRecueEmailHTML`, `getAdminNewCandidatureEmailHTML`) are kept for:
- Backward compatibility with old code
- Testing purposes
- Reference implementation

They are no longer used by the main application flow.

## Benefits

1. **Centralized Management**: All emails editable from admin panel
2. **Consistent Signatures**: Single source of truth for email signature
3. **Flexible Recipients**: Easy to add/remove admin notification recipients
4. **Professional Appearance**: Logo and company branding in all emails
5. **Easy Customization**: Templates can be modified without code changes

## Date
2026-01-30
