# Example Error Log Output

This document shows examples of what the error.log will contain when accessing `/admin-v2/finalize-etat-lieux.php?id=1`.

## Scenario 1: Successful Flow

```
[05-Feb-2026 22:50:23 Europe/Paris] === FINALIZE ETAT LIEUX - START ===
[05-Feb-2026 22:50:23 Europe/Paris] Requested ID: 1
[05-Feb-2026 22:50:23 Europe/Paris] Fetching etat des lieux from database with ID: 1
[05-Feb-2026 22:50:23 Europe/Paris] État des lieux found - ID: 1
[05-Feb-2026 22:50:23 Europe/Paris] Contrat ID: 123
[05-Feb-2026 22:50:23 Europe/Paris] Type: entree
[05-Feb-2026 22:50:23 Europe/Paris] Reference unique: EDL-ENTREE-CONT-001-20240205120000
[05-Feb-2026 22:50:23 Europe/Paris] Locataire email: jean.dupont@example.com
[05-Feb-2026 22:50:23 Europe/Paris] Locataire nom complet: Jean Dupont
[05-Feb-2026 22:50:23 Europe/Paris] Adresse: 123 Rue de la Paix, 75001 Paris
[05-Feb-2026 22:50:23 Europe/Paris] Date etat: 2024-02-05
[05-Feb-2026 22:50:23 Europe/Paris] Contrat ref: CONT-001

[05-Feb-2026 22:50:30 Europe/Paris] === FINALIZE ETAT LIEUX - POST REQUEST ===
[05-Feb-2026 22:50:30 Europe/Paris] Action: finalize
[05-Feb-2026 22:50:30 Europe/Paris] Starting transaction...
[05-Feb-2026 22:50:30 Europe/Paris] Generating PDF for contrat_id: 123, type: entree

[05-Feb-2026 22:50:30 Europe/Paris] === generateEtatDesLieuxPDF - START ===
[05-Feb-2026 22:50:30 Europe/Paris] Input - Contrat ID: 123, Type: entree
[05-Feb-2026 22:50:30 Europe/Paris] Fetching contrat data from database...
[05-Feb-2026 22:50:30 Europe/Paris] Contrat found - Reference: CONT-001
[05-Feb-2026 22:50:30 Europe/Paris] Logement - Adresse: 123 Rue de la Paix, 75001 Paris
[05-Feb-2026 22:50:30 Europe/Paris] Fetching locataires...
[05-Feb-2026 22:50:30 Europe/Paris] Found 1 locataire(s)
[05-Feb-2026 22:50:30 Europe/Paris] Checking for existing état des lieux...
[05-Feb-2026 22:50:30 Europe/Paris] Existing état des lieux found - ID: 1
[05-Feb-2026 22:50:30 Europe/Paris] Generating HTML content...
[05-Feb-2026 22:50:30 Europe/Paris] HTML generated - Length: 45678 characters
[05-Feb-2026 22:50:30 Europe/Paris] Creating TCPDF instance...
[05-Feb-2026 22:50:30 Europe/Paris] Writing HTML to PDF...
[05-Feb-2026 22:50:31 Europe/Paris] HTML written to PDF successfully
[05-Feb-2026 22:50:31 Europe/Paris] Saving PDF to file...
[05-Feb-2026 22:50:31 Europe/Paris] Saving to: /path/to/pdf/etat_des_lieux/etat_lieux_entree_CONT-001_20240205.pdf
[05-Feb-2026 22:50:31 Europe/Paris] PDF file created successfully - Size: 234567 bytes
[05-Feb-2026 22:50:31 Europe/Paris] Updating etat_lieux status to 'finalise'...
[05-Feb-2026 22:50:31 Europe/Paris] === generateEtatDesLieuxPDF - SUCCESS ===
[05-Feb-2026 22:50:31 Europe/Paris] PDF Generated: /path/to/pdf/etat_des_lieux/etat_lieux_entree_CONT-001_20240205.pdf

[05-Feb-2026 22:50:31 Europe/Paris] PDF generated successfully: /path/to/pdf/etat_des_lieux/etat_lieux_entree_CONT-001_20240205.pdf
[05-Feb-2026 22:50:31 Europe/Paris] PDF file size: 234567 bytes
[05-Feb-2026 22:50:31 Europe/Paris] Preparing email with PHPMailer...
[05-Feb-2026 22:50:31 Europe/Paris] Configuring SMTP settings...
[05-Feb-2026 22:50:31 Europe/Paris] SMTP Host: smtp.gmail.com
[05-Feb-2026 22:50:31 Europe/Paris] SMTP Port: 587
[05-Feb-2026 22:50:31 Europe/Paris] SMTP Username: contact@myinvest-immobilier.com
[05-Feb-2026 22:50:31 Europe/Paris] Setting email recipients...
[05-Feb-2026 22:50:31 Europe/Paris] From: contact@myinvest-immobilier.com (MY Invest Immobilier)
[05-Feb-2026 22:50:31 Europe/Paris] To: jean.dupont@example.com (Jean Dupont)
[05-Feb-2026 22:50:31 Europe/Paris] Email subject: État des lieux d'entrée - 123 Rue de la Paix, 75001 Paris
[05-Feb-2026 22:50:31 Europe/Paris] Attaching PDF as: etat_lieux_entree_CONT-001.pdf
[05-Feb-2026 22:50:31 Europe/Paris] Sending email...
[05-Feb-2026 22:50:33 Europe/Paris] Email sent successfully!
[05-Feb-2026 22:50:33 Europe/Paris] Updating database status...
[05-Feb-2026 22:50:33 Europe/Paris] Database updated successfully
[05-Feb-2026 22:50:33 Europe/Paris] Transaction committed
[05-Feb-2026 22:50:33 Europe/Paris] Cleaning up temporary PDF: /path/to/tmp/pdf_temp.pdf
[05-Feb-2026 22:50:33 Europe/Paris] === FINALIZE ETAT LIEUX - SUCCESS ===
```

---

## Scenario 2: Missing Required Fields

```
[05-Feb-2026 22:50:23 Europe/Paris] === FINALIZE ETAT LIEUX - START ===
[05-Feb-2026 22:50:23 Europe/Paris] Requested ID: 1
[05-Feb-2026 22:50:23 Europe/Paris] Fetching etat des lieux from database with ID: 1
[05-Feb-2026 22:50:23 Europe/Paris] État des lieux found - ID: 1
[05-Feb-2026 22:50:23 Europe/Paris] Contrat ID: 123
[05-Feb-2026 22:50:23 Europe/Paris] Type: entree
[05-Feb-2026 22:50:23 Europe/Paris] Reference unique: EDL-ENTREE-CONT-001-20240205120000
[05-Feb-2026 22:50:23 Europe/Paris] Locataire email: NULL
[05-Feb-2026 22:50:23 Europe/Paris] Locataire nom complet: NULL
[05-Feb-2026 22:50:23 Europe/Paris] Adresse: 123 Rue de la Paix, 75001 Paris
[05-Feb-2026 22:50:23 Europe/Paris] Date etat: 2024-02-05
[05-Feb-2026 22:50:23 Europe/Paris] Contrat ref: CONT-001
[05-Feb-2026 22:50:23 Europe/Paris] WARNING: Missing required fields: locataire_email, locataire_nom_complet
```

**Action needed:** Update the database to populate missing fields.

---

## Scenario 3: État des lieux Not Found

```
[05-Feb-2026 22:50:23 Europe/Paris] === FINALIZE ETAT LIEUX - START ===
[05-Feb-2026 22:50:23 Europe/Paris] Requested ID: 999
[05-Feb-2026 22:50:23 Europe/Paris] Fetching etat des lieux from database with ID: 999
[05-Feb-2026 22:50:23 Europe/Paris] ERROR: État des lieux not found in database for ID: 999
```

**Action needed:** Verify the ID is correct and the record exists.

---

## Scenario 4: Database Connection Error

```
[05-Feb-2026 22:50:23 Europe/Paris] === FINALIZE ETAT LIEUX - START ===
[05-Feb-2026 22:50:23 Europe/Paris] Requested ID: 1
[05-Feb-2026 22:50:23 Europe/Paris] Fetching etat des lieux from database with ID: 1
[05-Feb-2026 22:50:23 Europe/Paris] DATABASE ERROR while fetching etat des lieux: SQLSTATE[HY000] [2002] Connection refused
[05-Feb-2026 22:50:23 Europe/Paris] Stack trace: #0 /path/to/admin-v2/finalize-etat-lieux.php(35): PDO->prepare('SELECT edl.*...')
#1 {main}
```

**Action needed:** Check database server is running and credentials are correct.

---

## Scenario 5: PDF Generation Error

```
[05-Feb-2026 22:50:30 Europe/Paris] === FINALIZE ETAT LIEUX - POST REQUEST ===
[05-Feb-2026 22:50:30 Europe/Paris] Action: finalize
[05-Feb-2026 22:50:30 Europe/Paris] Starting transaction...
[05-Feb-2026 22:50:30 Europe/Paris] Generating PDF for contrat_id: 123, type: entree

[05-Feb-2026 22:50:30 Europe/Paris] === generateEtatDesLieuxPDF - START ===
[05-Feb-2026 22:50:30 Europe/Paris] Input - Contrat ID: 123, Type: entree
[05-Feb-2026 22:50:30 Europe/Paris] Fetching contrat data from database...
[05-Feb-2026 22:50:30 Europe/Paris] ERROR: Contrat #123 non trouvé

[05-Feb-2026 22:50:30 Europe/Paris] ERROR: PDF generation failed. Path returned: NULL
[05-Feb-2026 22:50:30 Europe/Paris] Rolling back transaction...
[05-Feb-2026 22:50:30 Europe/Paris] === FINALIZE ETAT LIEUX - ERROR ===
[05-Feb-2026 22:50:30 Europe/Paris] Exception type: Exception
[05-Feb-2026 22:50:30 Europe/Paris] Error message: Erreur lors de la génération du PDF
[05-Feb-2026 22:50:30 Europe/Paris] Error code: 0
[05-Feb-2026 22:50:30 Europe/Paris] Error file: /path/to/admin-v2/finalize-etat-lieux.php:82
[05-Feb-2026 22:50:30 Europe/Paris] Stack trace: #0 {main}
```

**Action needed:** Verify contrat exists and is linked to the état des lieux.

---

## Scenario 6: TCPDF Conversion Error

```
[05-Feb-2026 22:50:30 Europe/Paris] Generating HTML content...
[05-Feb-2026 22:50:30 Europe/Paris] HTML generated - Length: 45678 characters
[05-Feb-2026 22:50:30 Europe/Paris] Creating TCPDF instance...
[05-Feb-2026 22:50:30 Europe/Paris] Writing HTML to PDF...
[05-Feb-2026 22:50:31 Europe/Paris] TCPDF writeHTML ERROR: Invalid character encoding
[05-Feb-2026 22:50:31 Europe/Paris] HTML content length: 45678
[05-Feb-2026 22:50:31 Europe/Paris] Stack trace: #0 /path/to/vendor/tcpdf/tcpdf.php(1234): TCPDF->writeHTML()
#1 /path/to/pdf/generate-etat-lieux.php(131): TCPDF->writeHTML()
#2 {main}

[05-Feb-2026 22:50:31 Europe/Paris] === generateEtatDesLieuxPDF - ERROR ===
[05-Feb-2026 22:50:31 Europe/Paris] Exception type: Exception
[05-Feb-2026 22:50:31 Europe/Paris] Error message: Erreur lors de la conversion HTML vers PDF: Invalid character encoding
```

**Action needed:** Check HTML content for invalid characters or encoding issues.

---

## Scenario 7: Email Sending Error

```
[05-Feb-2026 22:50:31 Europe/Paris] Preparing email with PHPMailer...
[05-Feb-2026 22:50:31 Europe/Paris] Configuring SMTP settings...
[05-Feb-2026 22:50:31 Europe/Paris] SMTP Host: smtp.gmail.com
[05-Feb-2026 22:50:31 Europe/Paris] SMTP Port: 587
[05-Feb-2026 22:50:31 Europe/Paris] SMTP Username: contact@myinvest-immobilier.com
[05-Feb-2026 22:50:31 Europe/Paris] Setting email recipients...
[05-Feb-2026 22:50:31 Europe/Paris] From: contact@myinvest-immobilier.com (MY Invest Immobilier)
[05-Feb-2026 22:50:31 Europe/Paris] To: invalid-email (Jean Dupont)
[05-Feb-2026 22:50:31 Europe/Paris] Email subject: État des lieux d'entrée - 123 Rue de la Paix, 75001 Paris
[05-Feb-2026 22:50:31 Europe/Paris] Attaching PDF as: etat_lieux_entree_CONT-001.pdf
[05-Feb-2026 22:50:31 Europe/Paris] Sending email...
[05-Feb-2026 22:50:31 Europe/Paris] Rolling back transaction...
[05-Feb-2026 22:50:31 Europe/Paris] === FINALIZE ETAT LIEUX - ERROR ===
[05-Feb-2026 22:50:31 Europe/Paris] Exception type: PHPMailer\PHPMailer\Exception
[05-Feb-2026 22:50:31 Europe/Paris] Error message: Invalid address: invalid-email
[05-Feb-2026 22:50:31 Europe/Paris] Error code: 0
[05-Feb-2026 22:50:31 Europe/Paris] Error file: /path/to/vendor/phpmailer/src/PHPMailer.php:789
[05-Feb-2026 22:50:31 Europe/Paris] Stack trace: ...
```

**Action needed:** Fix the email address in the database.

---

## How to Read These Logs

1. **Look for section markers:**
   - `=== SECTION - START ===` - Beginning of operation
   - `=== SECTION - SUCCESS ===` - Success
   - `=== SECTION - ERROR ===` - Error occurred

2. **Pay attention to:**
   - `ERROR:` - Critical errors
   - `WARNING:` - Non-critical issues
   - `NULL` values - Missing data

3. **Follow the flow:**
   - Each step is logged in order
   - Stack traces show where errors occurred
   - Timestamps help correlate events

4. **Common fixes:**
   - Missing fields → Run migration or update database
   - Connection errors → Check database server
   - SMTP errors → Check configuration
   - Permission errors → Check file/folder permissions
