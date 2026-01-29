# Email Candidature System - Implementation Summary

## Overview
This implementation updates the rental application email system to match the functionality of the existing website code (`send-mail-candidature.php`).

## Changes Implemented

### 1. Email Templates Enhancement ✅

**File: `includes/mail-templates.php`**

- **Updated `getCandidatureRecueEmailHTML()`**: Added professional signature with company logo and table
- **Updated `getAdminNewCandidatureEmailHTML()`**: Added signature and response action links
- **Enhanced `sendEmail()` function**:
  - Support for multiple attachments (array or single file)
  - Added `replyTo` and `replyToName` parameters
  - Added BCC support for admin emails
  - Added base64 encoding for better compatibility
- **Enhanced `sendEmailToAdmins()` function**:
  - Pass-through for attachments and reply-to parameters
  - Support for custom reply-to addresses

### 2. Document Attachments ✅

**File: `candidature/submit.php`**

- Collects all uploaded files during processing
- Attaches files to admin notification emails
- Files are sent with original filenames
- Maintains file security (validation still in place)

### 3. Secure Token-Based Response System ✅

**Files: `candidature/submit.php`, `candidature/reponse-candidature.php`, `migrations/004_add_response_token_to_candidatures.sql`**

- Generates 64-character hex token for each candidature
- Stores token in database (new `response_token` field)
- Creates accept/reject links in admin emails
- Response handler validates token and updates status
- Sends notification email to candidate after admin action
- Prevents duplicate processing of same token

### 4. reCAPTCHA v3 Integration ✅

**Files: `candidature/index.php`, `candidature/candidature.js`, `candidature/submit.php`, `includes/config.php`**

- Added reCAPTCHA v3 configuration to config
- Frontend: Loads reCAPTCHA script conditionally
- Frontend: Generates token on form submission
- Backend: Verifies token with Google API
- Backend: Validates score against threshold (0.5 default)
- Fully configurable via `RECAPTCHA_ENABLED` flag

### 5. Email Configuration ✅

**File: `includes/config.php`**

- `ADMIN_EMAIL`: Primary recipient for admin notifications (location@myinvest-immobilier.com)
- `ADMIN_EMAIL_SECONDARY`: Optional secondary recipient
- `ADMIN_EMAIL_BCC`: BCC copy recipient (contact@myinvest-immobilier.com)
- `RECAPTCHA_SITE_KEY`: reCAPTCHA public key
- `RECAPTCHA_SECRET_KEY`: reCAPTCHA private key
- `RECAPTCHA_ENABLED`: Enable/disable reCAPTCHA (false by default)
- `RECAPTCHA_MIN_SCORE`: Minimum score threshold (0.5 by default)

## Database Changes

**Migration: `migrations/004_add_response_token_to_candidatures.sql`**

```sql
ALTER TABLE candidatures 
ADD COLUMN response_token VARCHAR(64) UNIQUE NULL 
AFTER reference_unique;

CREATE INDEX idx_response_token ON candidatures(response_token);
```

**To apply:** Run `php run-migrations.php` or execute the SQL manually

## Configuration Guide

### Email Setup

1. Configure SMTP settings in `includes/config.local.php`:
```php
'SMTP_HOST' => 'pro2.mail.ovh.net',
'SMTP_USERNAME' => 'contact@myinvest-immobilier.com',
'SMTP_PASSWORD' => 'your-password',
'SMTP_PORT' => 587,
'SMTP_SECURE' => 'tls',
```

2. Configure admin email addresses:
```php
'ADMIN_EMAIL' => 'location@myinvest-immobilier.com',
'ADMIN_EMAIL_BCC' => 'contact@myinvest-immobilier.com',
```

### reCAPTCHA Setup (Optional)

1. Register your site at https://www.google.com/recaptcha/admin
2. Select reCAPTCHA v3
3. Add your domain(s)
4. Copy the Site Key and Secret Key
5. Update `includes/config.local.php`:

```php
'RECAPTCHA_SITE_KEY' => 'your-site-key-here',
'RECAPTCHA_SECRET_KEY' => 'your-secret-key-here',
'RECAPTCHA_ENABLED' => true,
'RECAPTCHA_MIN_SCORE' => 0.5, // Adjust as needed (0.0 to 1.0)
```

## Email Flow

### Candidate Submission
1. Candidate fills out form and uploads documents
2. reCAPTCHA token generated (if enabled)
3. Backend validates reCAPTCHA score
4. Documents uploaded to server
5. Candidature record created with response_token
6. Candidate receives confirmation email with signature

### Admin Notification
1. Admin receives email with:
   - All uploaded documents as attachments
   - Candidate information summary
   - Accept/Reject action buttons
   - Reply-to set to candidate's email
   - BCC copy to contact@myinvest-immobilier.com
   - Professional signature with logo

### Admin Response (via Email)
1. Admin clicks Accept or Reject link in email
2. Token validated against database
3. Candidature status updated
4. Candidate receives notification email
5. Action logged in database

## Email Template Signature

Both candidate and admin emails now include:

```
Sincères salutations

[Logo]  MY INVEST IMMOBILIER
```

Logo URL: `https://www.myinvest-immobilier.com/images/logo.png`

## Security Features

✅ **CSRF Protection**: Token validation on form submission  
✅ **reCAPTCHA v3**: Bot detection with score threshold  
✅ **Prepared Statements**: SQL injection prevention  
✅ **Token-Based Actions**: 64-char random tokens for email responses  
✅ **File Validation**: MIME type and size checks on uploads  
✅ **XSS Prevention**: htmlspecialchars on all output  
✅ **Unique Tokens**: Database unique constraint prevents token reuse  

## Testing Checklist

- [ ] Run database migration
- [ ] Configure SMTP settings
- [ ] Configure admin email addresses
- [ ] Test candidature submission without reCAPTCHA
- [ ] Enable reCAPTCHA and test submission
- [ ] Verify admin email receives attachments
- [ ] Test accept link from admin email
- [ ] Test reject link from admin email
- [ ] Verify candidate receives notification
- [ ] Check BCC copy is received
- [ ] Verify reply-to works correctly

## Backward Compatibility

- reCAPTCHA is disabled by default (no breaking changes)
- All existing email functionality maintained
- Response token is optional (nullable field)
- Config defaults ensure system works without changes

## Files Modified

1. `candidature/submit.php` - Response token generation, file attachment, reCAPTCHA verification
2. `candidature/index.php` - reCAPTCHA script inclusion
3. `candidature/candidature.js` - reCAPTCHA token generation
4. `includes/mail-templates.php` - Email signatures, multi-attachments, reply-to
5. `includes/config.php` - Email and reCAPTCHA configuration

## Files Created

1. `candidature/reponse-candidature.php` - Token-based response handler
2. `migrations/004_add_response_token_to_candidatures.sql` - Database migration

## Support

For issues or questions:
- Check server logs for detailed error messages
- Verify SMTP configuration
- Ensure database migration has run
- Confirm file upload permissions (755 on uploads/candidatures/)
- Test reCAPTCHA with both valid and invalid scores
