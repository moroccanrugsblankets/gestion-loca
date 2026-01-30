# Fix for Automatic Rejection Emails and Cron Jobs Display

## Problem Statement (French)

> Je ne reçois toujours pas la réponse automatique lors du refus malgré j'ai configuré le parametre !
> A nouter que sur /admin-v2/cron-jobs.php, je veux afficher la liste des réponse de refus / valide des candidatures programmés automatiquement et pas afficher le cron qui s'execute chaque jours à 9h00 : cron/process-candidatures.php

**Translation:**
1. Automatic rejection emails are not being sent even though parameters are configured
2. The cron-jobs.php page should show individual candidature acceptance/rejection responses scheduled automatically, not the daily cron job that executes at 9:00 AM

## Root Cause Analysis

### Issue 1: Emails Not Using Database Templates

The `cron/process-candidatures.php` script was using hardcoded email templates:
- `getAcceptanceEmailTemplate()` function (lines 188-241)
- `getRejectionEmailTemplate()` function (lines 244-289)

These functions generated HTML emails directly in code, bypassing the configured email templates in the database (`email_templates` table).

**Impact:**
- Changes made in `/admin-v2/email-templates.php` had no effect on automatic emails
- The {{signature}} placeholder was not being used
- Administrators couldn't customize automatic emails without modifying PHP code

### Issue 2: Cron Jobs Display

The `/admin-v2/cron-jobs.php` page showed all cron jobs from the `cron_jobs` table, including the main `process-candidatures.php` job.

**Impact:**
- Users saw the generic daily cron job instead of specific candidature responses
- No visibility into which candidatures were pending automatic responses
- No way to see expected response dates for each candidature

## Solution Implemented

### Fix 1: Use Database Email Templates

**Changes to `cron/process-candidatures.php`:**

1. **Replaced hardcoded email sending (lines 80-98):**
   ```php
   // BEFORE:
   $subject = "Candidature acceptée - MyInvest Immobilier";
   $message = getAcceptanceEmailTemplate($prenom, $nom, $reference);
   if (sendEmail($email, $subject, $message)) { ... }
   
   // AFTER:
   $variables = [
       'nom' => $nom,
       'prenom' => $prenom,
       'email' => $email,
       'logement' => $logement,
       'reference' => $reference,
       'date' => date('d/m/Y'),
       'lien_confirmation' => $confirmUrl
   ];
   if (sendTemplatedEmail('candidature_acceptee', $email, $variables)) { ... }
   ```

2. **Replaced hardcoded rejection emails (lines 100-118):**
   ```php
   // BEFORE:
   $subject = "Candidature - MyInvest Immobilier";
   $message = getRejectionEmailTemplate($prenom, $nom);
   if (sendEmail($email, $subject, $message)) { ... }
   
   // AFTER:
   $variables = [
       'nom' => $nom,
       'prenom' => $prenom,
       'email' => $email
   ];
   if (sendTemplatedEmail('candidature_refusee', $email, $variables)) { ... }
   ```

3. **Removed hardcoded template functions:**
   - Deleted `getAcceptanceEmailTemplate()` function
   - Deleted `getRejectionEmailTemplate()` function

**Benefits:**
- ✅ Emails now use templates from `email_templates` table
- ✅ Admins can customize emails via `/admin-v2/email-templates.php`
- ✅ {{signature}} placeholder is now supported
- ✅ Consistent with other email sending in the system

### Fix 2: Enhanced Cron Jobs Display

**Changes to `admin-v2/cron-jobs.php`:**

1. **Filter out main cron job (line 83):**
   ```php
   // BEFORE:
   $stmt = $pdo->query("SELECT * FROM cron_jobs ORDER BY id");
   
   // AFTER:
   $stmt = $pdo->query("
       SELECT * FROM cron_jobs 
       WHERE fichier != 'cron/process-candidatures.php'
       ORDER BY id
   ");
   ```

2. **Added query for pending responses (lines 86-110):**
   ```php
   $stmt = $pdo->query("
       SELECT 
           c.id,
           c.reference_unique,
           c.nom,
           c.prenom,
           c.email,
           c.created_at,
           c.statut,
           c.reponse_automatique,
           l.reference as logement_reference
       FROM candidatures c
       LEFT JOIN logements l ON c.logement_id = l.id
       WHERE c.statut = 'en_cours' 
       AND c.reponse_automatique = 'en_attente'
       ORDER BY c.created_at ASC
       LIMIT 50
   ");
   ```

3. **Added new section "Réponses Automatiques Programmées" (lines 219-320):**
   - Table showing all pending candidatures
   - Calculation of expected response date based on delay parameters
   - Visual indicators for candidatures ready to be processed
   - Direct links to candidature details

**Features:**
- ✅ Shows individual candidatures awaiting automatic response
- ✅ Displays expected response date/time for each candidature
- ✅ Highlights candidatures that are ready to be processed (past expected date)
- ✅ Shows configured delay (e.g., "4 jours")
- ✅ Provides direct access to candidature details
- ✅ Hides the main daily cron job from display

## Visual Changes

### Before:
```
Tâches Automatisées (Cron Jobs)
├── Traitement des candidatures
│   ├── Fichier: cron/process-candidatures.php
│   ├── Fréquence: Quotidien (0 9 * * *)
│   └── [Exécuter maintenant]
└── (No other information)
```

### After:
```
Réponses Automatiques Programmées
├── Délai configuré: 4 jours
├── Table showing:
│   ├── Référence candidature
│   ├── Candidat (nom/prénom)
│   ├── Email
│   ├── Logement
│   ├── Date Soumission
│   ├── Réponse Prévue (calculated)
│   ├── Statut (with badge)
│   └── [Voir détails] button
└── Note: Le traitement automatique s'exécute quotidiennement à 9h00

Autres Tâches Automatisées
└── (Shows other cron jobs if any, excluding process-candidatures.php)
```

## Testing

Run the test script:
```bash
php test-automatic-emails.php
```

The test verifies:
1. ✓ Email templates exist in database
2. ✓ Templates are active
3. ✓ Templates contain {{signature}} placeholder
4. ✓ Delay parameters are configured
5. ✓ Pending candidatures can be queried
6. ✓ Cron jobs are configured

## Migration Notes

### For Existing Installations

1. **Ensure email templates exist:**
   - Run migrations if needed: `php run-migrations.php`
   - Templates should include: `candidature_acceptee`, `candidature_refusee`

2. **Add {{signature}} to templates:**
   - Go to `/admin-v2/email-templates.php`
   - Edit each template
   - Add `{{signature}}` where signature should appear
   - Save changes

3. **Configure delay parameters:**
   - Go to `/admin-v2/parametres.php`
   - Set `delai_reponse_valeur` (e.g., 4)
   - Set `delai_reponse_unite` (jours/heures/minutes)

4. **Test the cron script manually:**
   ```bash
   php cron/process-candidatures.php
   ```
   Check `cron/cron-log.txt` for execution logs

5. **Verify cron jobs page:**
   - Visit `/admin-v2/cron-jobs.php`
   - Should see pending candidature responses
   - Should NOT see the main process-candidatures.php job

## Files Modified

1. **cron/process-candidatures.php**
   - Replaced hardcoded email templates with `sendTemplatedEmail()`
   - Removed `getAcceptanceEmailTemplate()` function
   - Removed `getRejectionEmailTemplate()` function

2. **admin-v2/cron-jobs.php**
   - Filtered out `process-candidatures.php` from cron jobs list
   - Added query for pending candidature responses
   - Added new section displaying pending responses with expected dates

3. **test-automatic-emails.php** (new)
   - Comprehensive test script to verify configuration

## Expected Behavior

### When Cron Runs:

1. Query candidatures with `statut='en_cours'` and `reponse_automatique='en_attente'`
2. Check if delay has passed (e.g., 4 business days)
3. For each candidature:
   - Evaluate acceptance criteria
   - If accepted:
     - Send email using `candidature_acceptee` template
     - Update status to `accepte`
     - Set `reponse_automatique='accepte'`
   - If rejected:
     - Send email using `candidature_refusee` template
     - Update status to `refuse`
     - Set `reponse_automatique='refuse'`
4. Log all actions

### Email Templates Used:

- **candidature_acceptee:** Uses variables: nom, prenom, email, logement, reference, date, lien_confirmation
- **candidature_refusee:** Uses variables: nom, prenom, email

Both templates should include `{{signature}}` placeholder for email signature.

## Troubleshooting

### Emails Still Not Sent?

1. Check email templates exist:
   ```sql
   SELECT * FROM email_templates WHERE identifiant IN ('candidature_acceptee', 'candidature_refusee');
   ```

2. Check templates are active:
   ```sql
   SELECT actif FROM email_templates WHERE identifiant = 'candidature_refusee';
   ```

3. Check SMTP configuration in `includes/config.php`

4. Run cron manually and check logs:
   ```bash
   php cron/process-candidatures.php
   tail -50 cron/cron-log.txt
   ```

### Cron Jobs Page Not Showing Candidatures?

1. Check if there are pending candidatures:
   ```sql
   SELECT COUNT(*) FROM candidatures 
   WHERE statut='en_cours' AND reponse_automatique='en_attente';
   ```

2. Check delay parameters:
   ```sql
   SELECT * FROM parametres 
   WHERE cle IN ('delai_reponse_valeur', 'delai_reponse_unite');
   ```

## Status

✅ **Issue 1 RESOLVED:** Automatic emails now use database templates
✅ **Issue 2 RESOLVED:** Cron jobs page shows pending candidature responses

All changes are backward compatible and require no database schema changes.
