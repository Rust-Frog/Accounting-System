-- Migration: Add proof_json to approvals table
-- Required for approval audit proof chain
-- Run after init.sql

ALTER TABLE approvals
ADD COLUMN IF NOT EXISTS proof_json JSON NULL DEFAULT NULL
AFTER status;

-- Add index for proof lookups (MySQL 8.0+ functional index on JSON)
-- This indexes the hash field from within the JSON for efficient lookups
CREATE INDEX idx_approvals_proof_hash ON approvals ((CAST(proof_json->>'$.hash' AS CHAR(64) CHARSET utf8mb4)));

