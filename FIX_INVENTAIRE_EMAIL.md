# Fix: Email Sending Error in Finalize Inventory

## Problem
When clicking "Finaliser et envoyer par email" (Finalize and send by email) on the inventory finalization page (`/admin-v2/finalize-inventaire.php`), users received the error:

```
Erreur lors de la finalisation: Erreur lors de l'envoi des emails aux locataires
```

## Root Cause
The code was attempting to use a non-existent email template `inventaire_envoye`. 

In `admin-v2/finalize-inventaire.php` line 72, the code had:
```php
$templateId = 'inventaire_envoye'; // Use unified template
```

However, the actual email templates created by migration `035_add_inventaire_email_templates.sql` are:
- `inventaire_entree_envoye` - for entry inventories (inventaire d'entrée)
- `inventaire_sortie_envoye` - for exit inventories (inventaire de sortie)

When `getEmailTemplate()` was called with the non-existent template ID, it returned `false`, causing the email sending to fail.

## Solution
Modified `admin-v2/finalize-inventaire.php` to dynamically select the correct template based on the inventory type:

```php
// Determine template ID based on inventory type
$templateId = ($inventaire['type'] === 'sortie') ? 'inventaire_sortie_envoye' : 'inventaire_entree_envoye';
error_log("Using email template: " . $templateId);
```

## Files Modified
- `admin-v2/finalize-inventaire.php` - Fixed template selection logic

## Verification Steps

### 1. Ensure Email Templates Exist
Run this SQL query to verify the templates exist:
```sql
SELECT identifiant, nom FROM email_templates 
WHERE identifiant IN ('inventaire_entree_envoye', 'inventaire_sortie_envoye');
```

Expected result: 2 rows

If templates are missing, run:
```bash
cd /home/runner/work/gestion-loca/gestion-loca
mysql -u [username] -p [database] < migrations/035_add_inventaire_email_templates.sql
```

### 2. Test Entry Inventory
1. Create or edit an entry inventory (type = 'entree')
2. Navigate to finalize page
3. Click "Finaliser et envoyer par email"
4. Verify email is sent successfully using template `inventaire_entree_envoye`

### 3. Test Exit Inventory  
1. Create or edit an exit inventory (type = 'sortie')
2. Navigate to finalize page
3. Click "Finaliser et envoyer par email"
4. Verify email is sent successfully using template `inventaire_sortie_envoye`

### 4. Check Logs
After clicking finalize, check error logs for:
```
Using email template: inventaire_entree_envoye
```
or
```
Using email template: inventaire_sortie_envoye
```

## Impact
- ✅ Fixes email sending for both entry and exit inventories
- ✅ Maintains separate templates with appropriate messaging for each type
- ✅ No database migration needed (templates already exist from migration 035)
- ✅ Backward compatible with existing inventories

## Related Files
- `migrations/035_add_inventaire_email_templates.sql` - Creates the email templates
- `includes/functions.php` - Contains `sendTemplatedEmail()` and `getEmailTemplate()`
- `includes/mail-templates.php` - Contains `sendEmail()` implementation
