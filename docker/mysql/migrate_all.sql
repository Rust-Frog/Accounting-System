-- ============================================
-- Comprehensive Schema Migration Script
-- Run this on existing databases to add missing columns/tables
-- Safe to run multiple times (uses IF NOT EXISTS / IF NOT COLUMN)
-- ============================================

-- 1. Add otp_secret column to users table
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'users'
                   AND COLUMN_NAME = 'otp_secret');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE users ADD COLUMN otp_secret VARCHAR(255) NULL DEFAULT NULL AFTER last_login_ip',
    'SELECT "otp_secret column already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Add proof_json column to approvals table
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'approvals'
                   AND COLUMN_NAME = 'proof_json');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE approvals ADD COLUMN proof_json JSON NULL DEFAULT NULL AFTER status',
    'SELECT "proof_json column already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Create journal_entries table if not exists
CREATE TABLE IF NOT EXISTS journal_entries (
    id CHAR(36) NOT NULL PRIMARY KEY,
    company_id CHAR(36) NOT NULL,
    transaction_id CHAR(36) NOT NULL,
    entry_type VARCHAR(20) NOT NULL COMMENT 'POSTING or REVERSAL',
    bookings_json JSON NOT NULL,
    occurred_at DATETIME(6) NOT NULL,
    content_hash CHAR(64) NOT NULL,
    previous_hash CHAR(64) NULL,
    chain_hash CHAR(64) NULL,

    INDEX idx_journal_company_occurred (company_id, occurred_at),
    INDEX idx_journal_transaction (transaction_id),
    UNIQUE INDEX idx_journal_previous_hash (previous_hash),

    CONSTRAINT fk_journal_entries_company FOREIGN KEY (company_id)
        REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_journal_entries_transaction FOREIGN KEY (transaction_id)
        REFERENCES transactions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Summary
SELECT 'Migration complete. Please verify:' AS message
UNION ALL SELECT '- users.otp_secret column exists'
UNION ALL SELECT '- approvals.proof_json column exists'
UNION ALL SELECT '- journal_entries table exists';

