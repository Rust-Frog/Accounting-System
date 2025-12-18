-- Add hash chain columns to activity_logs
ALTER TABLE activity_logs ADD COLUMN content_hash VARCHAR(64) NOT NULL DEFAULT '';
ALTER TABLE activity_logs ADD COLUMN previous_hash VARCHAR(64) NOT NULL DEFAULT '';
ALTER TABLE activity_logs ADD COLUMN chain_hash VARCHAR(64) NOT NULL DEFAULT '';
ALTER TABLE activity_logs ADD INDEX idx_chain_hash (chain_hash);

-- New table for chain metadata
CREATE TABLE IF NOT EXISTS audit_chains (
    company_id CHAR(36) PRIMARY KEY,
    genesis_hash VARCHAR(64) NOT NULL,
    latest_hash VARCHAR(64) NOT NULL,
    chain_length INT NOT NULL DEFAULT 0,
    last_verified_at DATETIME,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add proof_json to approvals
ALTER TABLE approvals ADD COLUMN proof_json JSON DEFAULT NULL;
