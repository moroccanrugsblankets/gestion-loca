# Email Template System Fix - Complete Summary

## Problem Statement (Original Issues)

1. **{{signature}} n'est pas interprétée**: The {{signature}} placeholder was not being replaced in emails
2. **Email "Candidature locative reçue" not found in admin**: This email template was hardcoded and not manageable
3. **All emails need manageable templates**: All site emails should be manageable via admin panel
4. **Need to send to additional admin**: Ability to send candidature notifications to another administrator
5. **Admin email needs proper signature**: Admin notification emails should include logo and formatted company info

## Solution Implemented

### 1. Database-Driven Email Templates

**Before**: Emails were generated using hardcoded PHP functions
```php
$htmlBody = getCandidatureRecueEmailHTML($prenom, $nom, $logement, $total_uploaded);
$emailSent = sendEmail($email, $subject, $htmlBody, null, true);
```

**After**: Emails use database templates with variable replacement
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

### 2. Email Templates in Database

All email templates are now stored in the `email_templates` table and manageable via `/admin-v2/email-templates.php`:

- **candidature_recue**: Confirmation email sent to candidate
- **admin_nouvelle_candidature**: Notification sent to administrators
- **candidature_acceptee**: Acceptance email (used by cron)
- **candidature_refusee**: Rejection email (used by cron)

Each template includes the `{{signature}}` placeholder which is automatically replaced.

### 3. Email Signature System

The signature is stored in `parametres.email_signature` and contains:
```html
<p>Sincères salutations</p>
<p style="margin-top: 20px;">
    <table style="border: 0; border-collapse: collapse;">
        <tbody>
            <tr>
                <td style="padding-right: 15px;">
                    <img src="https://www.myinvest-immobilier.com/images/logo.png" 
                         alt="MY Invest Immobilier" 
                         style="max-width: 120px;">
                </td>
                <td>
                    <h3 style="margin: 0; color: #2c3e50;">
                        MY INVEST IMMOBILIER
                    </h3>
                </td>
            </tr>
        </tbody>
    </table>
</p>
```

**How it works:**
1. Template contains `{{signature}}` placeholder
2. `replaceTemplateVariables()` replaces all variables except `{{signature}}`
3. `sendEmail()` replaces `{{signature}}` with the signature from database
4. Result: Professional email with logo and branding

### 4. Additional Administrator Email

New parameter `email_admin_candidature` allows configuring an additional administrator email address.

**Configuration**: `/admin-v2/parametres.php`
- Key: `email_admin_candidature`
- Type: string
- Description: Email d'un administrateur supplémentaire pour recevoir les notifications de candidatures

When set, candidature notifications are sent to:
1. `ADMIN_EMAIL` (from config.php)
2. `ADMIN_EMAIL_SECONDARY` (from config.php, if set)
3. `email_admin_candidature` (from database, if set)

## Files Modified

### 1. candidature/submit.php
- Line 323-334: Changed to use `sendTemplatedEmail('candidature_recue')`
- Line 336-364: Changed to use template-based admin notification

### 2. includes/mail-templates.php
- Line 581-690: Updated `sendEmailToAdmins()` to support template variables
- Added support for `email_admin_candidature` parameter

### 3. migrations/013_update_email_signature_format.sql (NEW)
- Updates `email_signature` parameter with logo and company info
- Adds `email_admin_candidature` parameter
- Uses INSERT ON DUPLICATE KEY UPDATE for robustness
- Uses CSS padding instead of br/nbsp for email client compatibility

### 4. EMAIL_TEMPLATE_IMPLEMENTATION.md (NEW)
- Complete documentation of the email template system
- How-to guides for configuration and testing
- Variable reference for all templates

## Testing

### Test Script
Location: `/tmp/test-email-templates.php`

Run with: `php /tmp/test-email-templates.php`

Verifies:
- ✓ Templates exist in database with {{signature}} placeholder
- ✓ Email signature parameter is correctly formatted
- ✓ email_admin_candidature parameter exists
- ✓ Variable replacement works correctly
- ✓ Signature replacement works correctly

### Manual Testing Steps

1. **Deploy the changes**
2. **Run migration**: `mysql -u root -p bail_signature < migrations/013_update_email_signature_format.sql`
3. **Verify templates**: Visit `/admin-v2/email-templates.php`
   - Check that 4 templates are listed
   - Edit one template to verify it contains `{{signature}}`
4. **Configure additional admin** (optional): Visit `/admin-v2/parametres.php`
   - Set `email_admin_candidature` to additional admin email
5. **Test candidature submission**:
   - Submit a test candidature via the public form
   - Verify candidate receives "Candidature locative reçue" email with signature
   - Verify admin(s) receive notification email with signature and logo
   - Check email subject matches template subject
   - Verify signature includes logo and company name

## Benefits

1. **✓ Manageable**: All emails editable from admin panel without code changes
2. **✓ Consistent**: Single signature source ensures all emails have same branding
3. **✓ Flexible**: Easy to add/remove admin notification recipients
4. **✓ Professional**: Logo and company branding in all emails
5. **✓ Maintainable**: Templates use variables, easy to customize
6. **✓ Centralized**: One place to manage all email content

## Migration Path

### For Development
1. Pull latest code
2. Run migration 013
3. Test candidature submission
4. Verify emails in spam folder if not received

### For Production
1. Backup database
2. Deploy code changes
3. Run migration: `mysql -u root -p bail_signature < migrations/013_update_email_signature_format.sql`
4. Verify templates in admin panel
5. Configure `email_admin_candidature` if needed
6. Test with real candidature
7. Monitor email logs for any issues

## Troubleshooting

### {{signature}} still not replaced
- Check that template contains `{{signature}}` placeholder
- Verify `email_signature` parameter exists in database
- Check sendEmail() function for signature replacement logic

### Email not sent to additional admin
- Verify `email_admin_candidature` parameter is set
- Check email format is valid
- Review server email logs

### Template not found
- Run migration 003 to create email_templates table
- Run migration 012 to add {{signature}} to templates
- Run migration 013 to update signature format

### Logo not showing in email
- Verify logo URL is accessible: https://www.myinvest-immobilier.com/images/logo.png
- Check email client supports images
- Some email clients block images by default

## Security Considerations

✓ **SQL Injection**: All database queries use prepared statements
✓ **XSS**: All template variables are HTML-escaped except signature
✓ **Email Injection**: Email addresses validated before sending
✓ **Parameter Validation**: getParameter() validates types and provides defaults

## Performance

- Email signature cached in memory during request
- Template loaded once per email type
- No impact on candidature submission performance

## Backward Compatibility

The old functions (`getCandidatureRecueEmailHTML`, `getAdminNewCandidatureEmailHTML`) are kept but no longer used by the main application. They remain for:
- Backward compatibility with any custom scripts
- Testing and reference
- Gradual migration if needed

## Future Improvements

Potential enhancements (not implemented now):
- Email preview in admin panel before saving
- Email logs/history in database
- A/B testing of email templates
- Scheduled email sending
- Email template versioning

## Date
2026-01-30

## Status
✅ **COMPLETE AND READY FOR DEPLOYMENT**
