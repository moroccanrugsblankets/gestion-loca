# Cron Configuration

## Setup

Add the following line to your crontab to process rental applications daily at 9 AM:

```bash
0 9 * * * /usr/bin/php /path/to/contrat-de-bail/cron/process-candidatures.php
```

To edit your crontab:
```bash
crontab -e
```

## What it does

The cron job processes rental applications that are 4 business days old and:
- Evaluates candidates based on acceptance criteria (income, professional status, trial period)
- Sends automated acceptance or rejection emails
- Updates application status (Accepté/Refusé)
- Logs all actions for tracking

## Manual execution (for testing)

```bash
php /path/to/contrat-de-bail/cron/process-candidatures.php
```

## Logs

Execution logs are stored in: `cron/cron-log.txt`
