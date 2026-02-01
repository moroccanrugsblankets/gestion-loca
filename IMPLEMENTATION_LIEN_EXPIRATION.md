# Contract Link Expiration - Implementation Summary

## Problem Statement

The contract signature links were expiring too early (before 24 hours from when the email was sent). The system needed:
1. A configurable expiration delay parameter
2. Display of the expiration date in email templates

## Solution Implementation

### 1. Database Changes

#### Migration 018: Add Parameter
**File:** `migrations/018_add_delai_expiration_lien_parameter.sql`

Added a new parameter to the `parametres` table:
- **Key:** `delai_expiration_lien_contrat`
- **Default Value:** 24 (hours)
- **Type:** integer
- **Group:** general
- **Description:** Délai d'expiration du lien de signature (en heures)

This parameter is now configurable through the admin panel at **Paramètres > Général**.

#### Migration 019: Update Email Template
**File:** `migrations/019_add_date_expiration_to_email_template.sql`

Updated the `contrat_signature` email template to:
- Add `{{date_expiration_lien_contrat}}` to available variables
- Display expiration date in a prominent warning box
- Format: "⚠️ IMPORTANT : Ce lien expire le **02/02/2026 à 15:30**"

### 2. Code Changes

#### includes/functions.php
**Function:** `createContract()`

Updated to use the configurable parameter:
```php
// Before:
$expiration = date('Y-m-d H:i:s', strtotime('+' . $config['TOKEN_EXPIRY_HOURS'] . ' hours'));

// After:
$expiryHours = getParameter('delai_expiration_lien_contrat', $config['TOKEN_EXPIRY_HOURS']);
$expiration = date('Y-m-d H:i:s', strtotime('+' . $expiryHours . ' hours'));
```

#### includes/mail-templates.php
**Function:** `getInvitationEmailTemplate()`

Updated to:
- Accept an optional `$dateExpiration` parameter
- Format the expiration date for display (d/m/Y à H:i)
- Include expiration warning in email body

#### admin/generate-link.php

Updated to pass the expiration date to the email template:
```php
$emailData = getInvitationEmailTemplate($signatureLink, $logement, $contrat['expiration']);
```

#### admin-v2/envoyer-signature.php

Updated to:
- Use `getParameter('delai_expiration_lien_contrat', 24)` for expiration delay
- Format expiration date: `date('d/m/Y à H:i', strtotime($date_expiration))`
- Pass `date_expiration_lien_contrat` variable to email template

#### admin-v2/renvoyer-lien-signature.php

Updated to:
- Use `getParameter('delai_expiration_lien_contrat', 24)` for expiration delay
- Format and pass expiration date to email template

### 3. Features

#### Configurable Expiration
Administrators can now configure the link expiration delay in the admin panel:
- Navigate to **Paramètres** (Settings)
- Find **Délai d'expiration du lien de signature** in the General section
- Set the value in hours (default: 24)

#### Email Template Variable
The email template now supports the `{{date_expiration_lien_contrat}}` variable:
- Automatically replaced with the formatted expiration date
- Format: "02/02/2026 à 15:30"
- Displayed in a red warning box for visibility

### 4. How It Works

1. **When creating a contract:**
   - System reads `delai_expiration_lien_contrat` parameter from database
   - Calculates expiration as: current time + X hours
   - Stores expiration in `contrats.date_expiration`

2. **When sending the email:**
   - System formats the expiration date
   - Passes it as `date_expiration_lien_contrat` variable
   - Email template displays it prominently

3. **When checking link validity:**
   - `isContractValid()` compares current time with `date_expiration`
   - Shows formatted expiration date if link has expired

### 5. Testing

To test the implementation:

1. **Run migrations:**
   ```bash
   php run-migrations.php
   ```

2. **Verify parameter exists:**
   - Login to admin panel
   - Go to Paramètres
   - Check for "Délai d'expiration du lien de signature" in General section

3. **Test link generation:**
   - Generate a new contract signature link
   - Check that the email shows the expiration date
   - Verify the date is 24 hours (or configured value) from now

4. **Test parameter modification:**
   - Change the parameter to 48 hours
   - Generate a new link
   - Verify the expiration is now 48 hours from now

### 6. Backward Compatibility

- Falls back to `$config['TOKEN_EXPIRY_HOURS']` if parameter doesn't exist
- Email template works with or without the expiration date parameter
- Existing code paths continue to function normally

### 7. Benefits

✅ **Configurable:** No code changes needed to adjust expiration time  
✅ **Transparent:** Users see exactly when their link expires  
✅ **Centralized:** Single source of truth for expiration delay  
✅ **Flexible:** Easy to change for different business requirements  

## Files Modified

- `includes/functions.php` - Updated createContract()
- `includes/mail-templates.php` - Updated getInvitationEmailTemplate()
- `admin/generate-link.php` - Pass expiration to template
- `admin-v2/envoyer-signature.php` - Use parameter and pass expiration
- `admin-v2/renvoyer-lien-signature.php` - Use parameter and pass expiration
- `migrations/018_add_delai_expiration_lien_parameter.sql` - New
- `migrations/019_add_date_expiration_to_email_template.sql` - New

## Next Steps

After deployment:
1. Run the migrations on the production database
2. Configure the parameter value if 24 hours is not suitable
3. Test the email sending and link generation
4. Monitor for any issues with expired links
