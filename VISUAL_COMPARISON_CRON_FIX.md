# Visual Comparison: Before vs After

## BEFORE (Buggy Behavior) ❌

```
┌─────────────────────────────────────────────────────────────────┐
│                     CRON JOB EXECUTION                          │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  SELECT candidatures WHERE reponse_automatique = 'en_attente'   │
│  AND delay passed                                               │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
            ┌─────────────────────────────────────┐
            │   Candidature #123 found            │
            │   Email: client@example.com         │
            └─────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  ❌ STEP 1: UPDATE candidature                                  │
│     SET reponse_automatique = 'accepte'                         │
│     WHERE id = 123                                              │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  STEP 2: Try to send email                                      │
│     sendTemplatedEmail('candidature_acceptee', ...)             │
└─────────────────────────────────────────────────────────────────┘
                              │
                ┌─────────────┴─────────────┐
                ▼                           ▼
        ┌───────────────┐          ┌────────────────┐
        │  ✅ Success   │          │  ❌ Failed     │
        │  (rare)       │          │  (SMTP error)  │
        └───────────────┘          └────────────────┘
                │                           │
                ▼                           ▼
        ┌───────────────┐          ┌────────────────────────┐
        │  Client       │          │  Client NEVER          │
        │  receives     │          │  receives email!       │
        │  email ✓      │          │                        │
        └───────────────┘          │  Status = 'accepte'    │
                                   │  Cron won't retry ❌   │
                                   └────────────────────────┘
```

## AFTER (Fixed Behavior) ✅

```
┌─────────────────────────────────────────────────────────────────┐
│                     CRON JOB EXECUTION                          │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  SELECT candidatures WHERE reponse_automatique = 'en_attente'   │
│  AND delay passed                                               │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
            ┌─────────────────────────────────────┐
            │   Candidature #123 found            │
            │   Email: client@example.com         │
            └─────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  ✅ STEP 1: Try to send email FIRST                             │
│     sendTemplatedEmail('candidature_acceptee', ...)             │
└─────────────────────────────────────────────────────────────────┘
                              │
                ┌─────────────┴─────────────┐
                ▼                           ▼
        ┌───────────────┐          ┌────────────────────────┐
        │  ✅ Success   │          │  ❌ Failed             │
        └───────────────┘          │  (SMTP error)          │
                │                  └────────────────────────┘
                ▼                           │
┌──────────────────────────┐                │
│  STEP 2: UPDATE only     │                ▼
│  if email sent           │     ┌─────────────────────────┐
│                          │     │  DON'T update status!   │
│  SET reponse_auto        │     │                         │
│    = 'accepte'           │     │  Status stays:          │
│  WHERE id = 123          │     │  'en_attente' ✓         │
└──────────────────────────┘     └─────────────────────────┘
                │                           │
                ▼                           ▼
┌──────────────────────────┐     ┌─────────────────────────┐
│  Log success             │     │  Log error with         │
│  action: 'email_accept'  │     │  action: 'email_error'  │
└──────────────────────────┘     └─────────────────────────┘
                │                           │
                ▼                           ▼
┌──────────────────────────┐     ┌─────────────────────────┐
│  Client receives         │     │  Will be retried on     │
│  email ✓                 │     │  next cron run! ✓       │
│                          │     │                         │
│  Status = 'accepte'      │     │  Shows in admin         │
│  Won't be selected       │     │  "Failed Emails"        │
│  again ✓                 │     │  section                │
└──────────────────────────┘     └─────────────────────────┘
                                            │
                                            ▼
                                 ┌─────────────────────────┐
                                 │  NEXT CRON RUN          │
                                 │  (5-10 min later)       │
                                 │                         │
                                 │  Tries again...         │
                                 │  until success! ✓       │
                                 └─────────────────────────┘
```

## Key Differences

| Aspect | Before ❌ | After ✅ |
|--------|----------|----------|
| **Status Update** | Before email send | After email send (only if successful) |
| **Failed Email** | Lost forever | Automatically retried |
| **Admin Visibility** | No indication | Shows in dashboard |
| **Debugging** | Error logged but no action | Error logged + retry |
| **Client Experience** | May never receive email | Guaranteed to receive email |

## Admin Dashboard - New Section

```
╔══════════════════════════════════════════════════════════════╗
║  ⚠️  Échecs d'envoi d'emails (2)                             ║
╠══════════════════════════════════════════════════════════════╣
║                                                              ║
║  ⚠️ Important: Ces emails seront automatiquement retentés   ║
║     lors de la prochaine exécution du cron.                 ║
║                                                              ║
╠══════════════════════════════════════════════════════════════╣
║  Référence | Candidat | Email | Tentatives | Dernière erreur║
║────────────┼──────────┼───────┼────────────┼────────────────║
║  REF-123   │ J. Doe   │ j@... │ 2 échecs   │ 10:30          ║
║  REF-456   │ M. Smith │ m@... │ 1 échec    │ 10:25          ║
╚══════════════════════════════════════════════════════════════╝
```

## Retry Mechanism Flow

```
Email Failure Detected
        │
        ▼
┌───────────────────┐
│ Leave status as   │
│ 'en_attente'      │
└───────────────────┘
        │
        ▼
┌───────────────────┐
│ Log to database   │
│ action: 'email_   │
│ error'            │
└───────────────────┘
        │
        ▼
┌───────────────────┐
│ Show in admin     │
│ dashboard (RED)   │
└───────────────────┘
        │
        ▼
    Wait for next
    cron run (5-10 min)
        │
        ▼
┌───────────────────┐
│ Cron selects it   │
│ again (still in   │
│ 'en_attente')     │
└───────────────────┘
        │
        ▼
    Try to send again
        │
    ┌───┴───┐
    ▼       ▼
Success   Failure
    │       │
    │       └─► Retry again...
    │
    ▼
Update status to
'accepte'/'refuse'
    │
    ▼
Remove from
retry queue ✓
```

## Code Change Visualization

### Before (Line 97-109 in process-candidatures.php)

```php
// ❌ UPDATE FIRST (WRONG!)
$updateStmt = $pdo->prepare("UPDATE candidatures SET ...");
$updateStmt->execute([$id]);

// Send email AFTER
if (sendTemplatedEmail(...)) {
    logMessage("Success");
} else {
    logMessage("ERROR"); // Too late! Status already changed!
}
```

### After (Line 96-113 in process-candidatures.php)

```php
// ✅ SEND EMAIL FIRST (CORRECT!)
if (sendTemplatedEmail(...)) {
    // Only update if successful
    $updateStmt = $pdo->prepare("UPDATE candidatures SET ...");
    $updateStmt->execute([$id]);
    logMessage("Success");
} else {
    logMessage("ERROR - will be retried");
    // Status NOT changed - will retry!
}
```

---

**Visual Summary**: The fix ensures emails are sent BEFORE marking candidatures as processed, enabling automatic retries for failures and guaranteeing delivery.
