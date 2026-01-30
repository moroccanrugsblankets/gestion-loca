# Flexible Delay Units and Cron Job Management

## Overview

This implementation adds two major features:
1. **Flexible delay units** for automatic response emails (minutes/hours/days)
2. **Cron job management interface** for monitoring and controlling scheduled tasks

## 1. Flexible Delay Configuration

### Database Changes

**Migration: `008_add_flexible_delay_parameters.sql`**
- Adds `delai_reponse_unite` parameter (unit: minutes/heures/jours)
- Adds `delai_reponse_valeur` parameter (numeric value)
- Maintains backward compatibility with `delai_reponse_jours`

### User Interface Changes

**File: `admin-v2/parametres.php`**

The parameters page now displays a combined delay configuration:

```
Délai de réponse automatique
┌─────────────────┬─────────────────┐
│ Valeur: [  4  ] │ Unité: [Jours ▼]│
└─────────────────┴─────────────────┘
```

**Options for unit:**
- Minutes
- Heures (Hours)
- Jours (ouvrés) - Business days (default)

### Backend Changes

**File: `cron/process-candidatures.php`**

The cron job now:
1. Reads `delai_reponse_unite` and `delai_reponse_valeur` parameters
2. Calculates delay based on selected unit:
   - **jours**: Uses existing business day logic (v_candidatures_a_traiter view)
   - **heures**: Converts to hours and uses TIMESTAMPDIFF
   - **minutes**: Converts to hours (minutes/60) and uses TIMESTAMPDIFF

**Example configurations:**
- 4 jours = 4 business days (default, existing behavior)
- 48 heures = 48 hours from submission
- 120 minutes = 2 hours from submission

## 2. Cron Job Management Interface

### Database Changes

**Migration: `009_create_cron_jobs_table.sql`**

Creates `cron_jobs` table with:
- `id` - Auto-increment primary key
- `nom` - Job name
- `description` - Detailed description
- `fichier` - PHP file path (relative to project root)
- `frequence` - Execution frequency (hourly/daily/weekly)
- `cron_expression` - Cron syntax (e.g., "0 9 * * *")
- `actif` - Enabled/disabled status
- `derniere_execution` - Last execution timestamp
- `statut_derniere_execution` - Last status (success/error/running)
- `log_derniere_execution` - Output from last execution

**Initial data:**
- Pre-configured entry for `process-candidatures.php`

### User Interface

**File: `admin-v2/cron-jobs.php`**

New admin page accessible via menu: **Tâches Automatisées**

#### Features:

1. **Job List Display**
   - Job name with status badges (Actif/Désactivé, Succès/Erreur)
   - Description
   - File path
   - Frequency and cron expression
   - Last execution time

2. **Actions per Job**
   - **Execute Now**: Run the job immediately (manual trigger)
   - **Enable/Disable**: Toggle job active status
   - **View Logs**: Expand to see output from last execution

3. **Help Modal**
   - Instructions for configuring server cron
   - Copy-ready cron expressions for each active job

### Menu Integration

**File: `admin-v2/includes/menu.php`**

Added new menu item:
```html
<li class="nav-item">
    <a class="nav-link" href="cron-jobs.php">
        <i class="bi bi-clock-history"></i> Tâches Automatisées
    </a>
</li>
```

## Usage Guide

### Configuring Delay

1. Navigate to **Admin** → **Paramètres**
2. Find "Délai de réponse automatique" section
3. Set value (e.g., 48)
4. Select unit (Minutes/Heures/Jours)
5. Click "Enregistrer les paramètres"

### Managing Cron Jobs

1. Navigate to **Admin** → **Tâches Automatisées**
2. View list of configured jobs
3. For each job:
   - Click "Exécuter maintenant" to run immediately
   - Click "Désactiver/Activer" to toggle status
   - Click "Voir les logs" to view execution output

### Server Configuration

For automatic execution, configure your server's crontab:

```bash
# Edit crontab
crontab -e

# Add entry (example for daily execution at 9 AM)
0 9 * * * /usr/bin/php /path/to/contrat-de-bail/cron/process-candidatures.php
```

The cron jobs page provides the exact commands for your installation.

## Technical Details

### Execution Tracking

When a job is executed (manually or automatically):
1. Status set to "running"
2. `derniere_execution` timestamp updated
3. Output captured via `ob_start()`/`ob_get_clean()`
4. On completion:
   - Status set to "success" or "error"
   - Output saved to `log_derniere_execution` (truncated to 5000 chars)
   - Execution time calculated

### Security

- Only authenticated admin users can access cron management
- Jobs must exist in database and be enabled
- File paths are validated before execution
- All actions logged in session messages

### Error Handling

- File not found: Error message displayed
- Execution exception: Captured and logged
- Invalid job ID: Error message
- Disabled job: Cannot execute

## Migration Instructions

1. Backup your database
2. Run migrations:
   ```bash
   php run-migrations.php
   ```
3. Verify new parameters in `parametres` table:
   - `delai_reponse_unite`
   - `delai_reponse_valeur`
4. Verify `cron_jobs` table created
5. Access admin interface to configure

## Backward Compatibility

- Existing `delai_reponse_jours` parameter kept
- If new parameters not set, falls back to jours/4
- Existing cron configurations continue to work
- Database schema changes are additive only

## Future Enhancements

Possible improvements:
- Add more cron jobs (cleanup, reminders, etc.)
- Schedule multiple executions per day
- Email notifications on job failures
- Execution history (beyond last execution)
- Job dependencies (run job B after job A)
- Custom parameters per job
