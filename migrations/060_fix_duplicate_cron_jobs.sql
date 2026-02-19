-- Migration: Fix duplicate cron jobs and add unique constraint on fichier
-- Date: 2026-02-19
-- Description: Removes duplicate entries in cron_jobs table (keeping the first/lowest id)
--              and adds a UNIQUE constraint on the fichier column to prevent future duplicates.

-- Step 1: Remove duplicate cron_jobs entries, keeping the one with the lowest id per fichier
DELETE FROM cron_jobs
WHERE id NOT IN (
    SELECT min_id FROM (
        SELECT MIN(id) AS min_id FROM cron_jobs GROUP BY fichier
    ) AS t
);

-- Step 2: Add unique constraint on fichier column (if it doesn't already exist)
ALTER TABLE cron_jobs
    ADD UNIQUE INDEX IF NOT EXISTS unique_fichier (fichier);
