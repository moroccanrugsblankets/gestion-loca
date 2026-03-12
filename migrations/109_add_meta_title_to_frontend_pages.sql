-- Migration 109: Add meta_title column to frontend_pages
-- The meta_title is used for the <title> SEO tag.
-- The existing `titre` column continues to be used as the H1 page heading.
-- When meta_title is empty the front-end falls back to `titre`.

ALTER TABLE frontend_pages
    ADD COLUMN meta_title VARCHAR(255) NOT NULL DEFAULT '' AFTER titre;
