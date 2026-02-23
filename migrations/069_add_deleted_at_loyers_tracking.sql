-- Migration 069: Add soft delete support to loyers_tracking table
-- Date: 2026-02-23
-- Description: Add deleted_at column to loyers_tracking to enable soft deletes
--              when a contract is deleted

ALTER TABLE loyers_tracking 
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete timestamp - NULL = active, NOT NULL = deleted',
ADD INDEX idx_loyers_tracking_deleted_at (deleted_at);
