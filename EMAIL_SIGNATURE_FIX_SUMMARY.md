# Email Signature Fix - Implementation Summary

## Problem Statement
There was a problem with email signatures:
- Email signature was automatically appended to the end of every email
- Need to remove automatic signature appending
- Need to add ability to include signature in email templates using `{{signature}}` placeholder

## Solution Implemented

### 1. Modified `includes/mail-templates.php`

#### `sendEmail()` function changes:
- **REMOVED**: Automatic signature fetching and appending before email body
- **ADDED**: Signature replacement when `{{signature}}` placeholder is present in the body
- **Logic**: Only fetches signature from database if `{{signature}}` is found in the email body
- **Placement**: Lines ~193-217 (previously lines ~139-201)

**Before:**
```php
// Get signature and append to ALL emails automatically
if ($isHtml && !empty($signature)) {
    $finalBody = $body . '<br><br>' . $signature;
}
```

**After:**
```php
// Replace {{signature}} placeholder if present
if ($isHtml && strpos($body, '{{signature}}') !== false) {
    // Fetch signature from database (with caching)
    $signature = $signatureCache !== null ? $signatureCache : '';
    $finalBody = str_replace('{{signature}}', $signature, $body);
}
```

#### `sendEmailFallback()` function changes:
- Same changes as `sendEmail()` - replaced automatic appending with placeholder replacement
- Ensures consistency between PHPMailer and fallback email methods

### 2. Modified `includes/functions.php`

#### `replaceTemplateVariables()` function changes:
- **ADDED**: Special handling for `{{signature}}` variable to avoid HTML escaping
- **REASON**: Signature contains HTML markup that should not be escaped
- **SAFETY**: All other variables are still properly escaped to prevent XSS attacks
- **IMPROVED**: Warning for unreplaced variables now ignores `{{signature}}` (handled by sendEmail)

**Key code:**
```php
if ($key === 'signature') {
    $template = str_replace($placeholder, $value, $template);
} else {
    $template = str_replace($placeholder, htmlspecialchars($value, ENT_QUOTES, 'UTF-8'), $template);
}
```

### 3. Modified `admin-v2/email-templates.php`

#### Email Template Editor UI changes:
- **ADDED**: `{{signature}}` to the list of available variables (green badge)
- **ADDED**: Explanation note about `{{signature}}` variable
- **UI**: Shows in the "Variables disponibles" section when editing a template

### 4. Created Migration `migrations/012_add_signature_placeholder_to_templates.sql`

Updates all existing email templates to include `{{signature}}` placeholder:

- `candidature_recue` - Added at end of content section
- `candidature_acceptee` - Added at end of content section
- `candidature_refusee` - Added at end of content section
- `admin_nouvelle_candidature` - Added at end of content section

## How It Works Now

### For Template-Based Emails:

1. Admin edits email template in `/admin-v2/email-templates.php`
2. Admin can include `{{signature}}` anywhere in the email body
3. When email is sent via `sendTemplatedEmail()`:
   - `replaceTemplateVariables()` processes all variables except `{{signature}}`
   - The body with `{{signature}}` is passed to `sendEmail()`
   - `sendEmail()` detects `{{signature}}` and replaces it with the actual signature from database

### For Direct Emails:

1. Code creates email body with `{{signature}}` placeholder
2. Passes to `sendEmail()` which replaces the placeholder with actual signature
3. If no `{{signature}}` placeholder exists, no signature is added

## Benefits

1. **Control**: Admins can choose where to place signature or omit it entirely
2. **Flexibility**: Different templates can have signature in different positions
3. **Clean**: No automatic appending - only when explicitly requested via placeholder
4. **Secure**: Signature HTML is not escaped, but all user data is still protected
5. **Backward Compatible**: Emails without `{{signature}}` work normally (just without signature)

## Testing

Created `test-signature-standalone.php` which verifies:
- ✓ `{{signature}}` is NOT HTML-escaped
- ✓ Regular variables ARE HTML-escaped (XSS protection)
- ✓ Unreplaced variable warnings skip `{{signature}}`
- ✓ Signature replacement works correctly in email body
- ✓ Emails without `{{signature}}` work normally

## Migration Instructions

To apply the changes to an existing installation:

1. **Update code files** (already done in this PR)
2. **Run migration**:
   ```bash
   php run-migrations.php
   ```
   This will add `{{signature}}` to all existing email templates

3. **Verify signature parameter exists**:
   - Go to `/admin-v2/parametres.php`
   - Check that "Signature des emails" is configured
   - If not, add signature HTML

4. **Test emails**:
   - Submit a test candidature
   - Verify emails include signature from templates

## Files Changed

1. `includes/mail-templates.php` - Modified signature handling in sendEmail and sendEmailFallback
2. `includes/functions.php` - Modified replaceTemplateVariables to handle signature
3. `admin-v2/email-templates.php` - Added {{signature}} to UI documentation
4. `migrations/012_add_signature_placeholder_to_templates.sql` - Added {{signature}} to templates

## Breaking Changes

**IMPORTANT**: After this update, emails will NOT have automatic signatures unless:
- The email template includes `{{signature}}` placeholder, OR
- The email body includes `{{signature}}` placeholder

**Action Required**: Run migration 012 to add `{{signature}}` to existing templates.
