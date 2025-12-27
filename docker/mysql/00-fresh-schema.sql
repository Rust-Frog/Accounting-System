-- ============================================
-- Accounting System - Complete Database Schema
-- Version: 3.0 (Consolidated)
--
-- This is a consolidated schema that:
-- 1. Drops all existing tables (clean slate)
-- 2. Creates all required tables including:
--    - System Activities (hash chain audit trail)
--    - Transaction Sequences (auto-numbering)
--    - Hash chain columns on all auditable tables
--
-- No seed data included - schema only.
-- ============================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- DROP ALL TABLES (Clean Slate)
-- ============================================
DROP TABLE IF EXISTS transaction_sequences;
DROP TABLE IF EXISTS system_activities;
DROP TABLE IF EXISTS user_settings;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS balance_changes;
DROP TABLE IF EXISTS account_balances;
DROP TABLE IF EXISTS journal_entries;
DROP TABLE IF EXISTS closed_periods;
DROP TABLE IF EXISTS approvals;
DROP TABLE IF EXISTS transaction_lines;
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS accounts;
DROP TABLE IF EXISTS company_settings;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS companies;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- Identity Domain
-- ============================================

CREATE TABLE users (
    id CHAR(36) PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'user',
    registration_status VARCHAR(20) NOT NULL DEFAULT 'pending',
    company_id CHAR(36) NULL,
    failed_login_attempts INT NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    last_login_ip VARCHAR(45) NULL,
    otp_secret VARCHAR(255) NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_users_username (username),
    INDEX idx_users_email (email),
    INDEX idx_users_company (company_id),
    INDEX idx_users_status (registration_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sessions (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(500) NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_sessions_user (user_id),
    INDEX idx_sessions_token (token),
    INDEX idx_sessions_expires (expires_at),

    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Company Domain
-- ============================================

CREATE TABLE companies (
    id CHAR(36) PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    legal_name VARCHAR(255) NOT NULL,
    tax_id VARCHAR(50) NOT NULL UNIQUE,

    address_street VARCHAR(255) NOT NULL,
    address_city VARCHAR(100) NOT NULL,
    address_state VARCHAR(100) NULL,
    address_postal_code VARCHAR(20) NULL,
    address_country VARCHAR(100) NOT NULL,

    currency CHAR(3) NOT NULL DEFAULT 'USD',
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_companies_tax_id (tax_id),
    INDEX idx_companies_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign key for users -> companies
ALTER TABLE users
    ADD CONSTRAINT fk_users_company FOREIGN KEY (company_id)
    REFERENCES companies(id) ON DELETE SET NULL;

CREATE TABLE company_settings (
    id CHAR(36) PRIMARY KEY,
    company_id CHAR(36) NOT NULL UNIQUE,
    fiscal_year_start_month TINYINT NOT NULL DEFAULT 1,
    fiscal_year_start_day TINYINT NOT NULL DEFAULT 1,
    settings_json JSON NULL,
    -- Edge case detection thresholds
    large_transaction_threshold_cents BIGINT NOT NULL DEFAULT 1000000,
    backdated_days_threshold INT NOT NULL DEFAULT 30,
    future_dated_allowed TINYINT(1) NOT NULL DEFAULT 1,
    require_approval_contra_entry TINYINT(1) NOT NULL DEFAULT 1,
    require_approval_equity_adjustment TINYINT(1) NOT NULL DEFAULT 1,
    require_approval_negative_balance TINYINT(1) NOT NULL DEFAULT 1,
    flag_round_numbers TINYINT(1) NOT NULL DEFAULT 0,
    flag_period_end_entries TINYINT(1) NOT NULL DEFAULT 0,
    dormant_account_days_threshold INT NOT NULL DEFAULT 90,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_company_settings_company FOREIGN KEY (company_id)
        REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Chart of Accounts Domain
-- ============================================

CREATE TABLE accounts (
    id CHAR(36) PRIMARY KEY,
    company_id CHAR(36) NOT NULL,
    code INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(20) NOT NULL,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    parent_account_id CHAR(36) NULL,
    balance_cents BIGINT NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    
    -- Hash chain columns
    content_hash CHAR(64) NULL,
    previous_hash CHAR(64) NULL,
    chain_hash CHAR(64) NULL,
    
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_accounts_company_code (company_id, code),
    INDEX idx_accounts_company (company_id),
    INDEX idx_accounts_parent (parent_account_id),
    INDEX idx_accounts_active (is_active),
    INDEX idx_accounts_code (code),
    INDEX idx_accounts_company_active (company_id, is_active),

    CONSTRAINT fk_accounts_company FOREIGN KEY (company_id)
        REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_accounts_parent FOREIGN KEY (parent_account_id)
        REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Transaction Domain
-- ============================================

CREATE TABLE transactions (
    id CHAR(36) PRIMARY KEY,
    transaction_number VARCHAR(20) NULL COMMENT 'Auto-generated: TXN-YYYYMM-XXXXX',
    company_id CHAR(36) NOT NULL,
    transaction_date DATE NOT NULL,
    description VARCHAR(500) NOT NULL,
    reference_number VARCHAR(100) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',

    created_by CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    posted_by CHAR(36) NULL,
    posted_at DATETIME NULL,

    voided_by CHAR(36) NULL,
    voided_at DATETIME NULL,
    void_reason VARCHAR(500) NULL,
    
    -- Hash chain columns
    content_hash CHAR(64) NULL,
    previous_hash CHAR(64) NULL,
    chain_hash CHAR(64) NULL,

    UNIQUE KEY uk_txn_number (company_id, transaction_number),
    INDEX idx_transactions_company (company_id),
    INDEX idx_transactions_date (transaction_date),
    INDEX idx_transactions_status (status),
    INDEX idx_transactions_created_by (created_by),
    INDEX idx_transactions_company_date (company_id, transaction_date),
    INDEX idx_transactions_company_status (company_id, status),

    CONSTRAINT fk_transactions_company FOREIGN KEY (company_id)
        REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_transactions_created_by FOREIGN KEY (created_by)
        REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_transactions_posted_by FOREIGN KEY (posted_by)
        REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_transactions_voided_by FOREIGN KEY (voided_by)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transaction number sequences for auto-generation
CREATE TABLE `transaction_sequences` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `company_id` CHAR(36) NOT NULL,
    `period` CHAR(6) NOT NULL,
    `sequence` INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY `uk_company_period` (`company_id`, `period`),
    INDEX `idx_company_id` (`company_id`),
    
    CONSTRAINT `fk_txn_seq_company` 
        FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transaction_lines (
    id CHAR(36) PRIMARY KEY,
    transaction_id CHAR(36) NOT NULL,
    account_id CHAR(36) NOT NULL,
    line_type VARCHAR(10) NOT NULL,
    amount_cents BIGINT NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    description VARCHAR(255) NULL,
    line_order INT NOT NULL DEFAULT 0,

    INDEX idx_transaction_lines_transaction (transaction_id),
    INDEX idx_transaction_lines_account (account_id),
    INDEX idx_transaction_lines_type (line_type),
    INDEX idx_transaction_lines_txn_order (transaction_id, line_order),

    CONSTRAINT fk_transaction_lines_transaction FOREIGN KEY (transaction_id)
        REFERENCES transactions(id) ON DELETE CASCADE,
    CONSTRAINT fk_transaction_lines_account FOREIGN KEY (account_id)
        REFERENCES accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Approval Domain
-- ============================================

CREATE TABLE approvals (
    id CHAR(36) PRIMARY KEY,
    company_id CHAR(36) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id CHAR(36) NOT NULL,
    approval_type VARCHAR(50) NOT NULL,
    reason TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    proof_json JSON NULL DEFAULT NULL,
    amount_cents BIGINT NOT NULL DEFAULT 0,
    priority INT NOT NULL DEFAULT 0,

    requested_by CHAR(36) NOT NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    reviewed_by CHAR(36) NULL,
    reviewed_at DATETIME NULL,
    review_notes TEXT NULL,

    expires_at DATETIME NULL,
    
    -- Hash chain columns
    content_hash CHAR(64) NULL,
    previous_hash CHAR(64) NULL,
    chain_hash CHAR(64) NULL,

    INDEX idx_approvals_company (company_id),
    INDEX idx_approvals_entity (entity_type, entity_id),
    INDEX idx_approvals_status (status),
    INDEX idx_approvals_requested_by (requested_by),
    INDEX idx_approvals_reviewed_by (reviewed_by),
    INDEX idx_approvals_expires (expires_at),
    INDEX idx_approvals_priority (priority),
    INDEX idx_approvals_company_status (company_id, status),

    CONSTRAINT fk_approvals_company FOREIGN KEY (company_id)
        REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_approvals_requested_by FOREIGN KEY (requested_by)
        REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_approvals_reviewed_by FOREIGN KEY (reviewed_by)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Closed Periods (Period Close Tracking)
-- ============================================

CREATE TABLE closed_periods (
    id CHAR(36) PRIMARY KEY,
    company_id CHAR(36) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    closed_by CHAR(36) NOT NULL,
    closed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approval_id CHAR(36) NULL,
    net_income_cents BIGINT NOT NULL DEFAULT 0,
    chain_hash VARCHAR(128) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_closed_periods_company (company_id),
    INDEX idx_closed_periods_dates (company_id, start_date, end_date),
    
    CONSTRAINT fk_closed_periods_company FOREIGN KEY (company_id)
        REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_closed_periods_user FOREIGN KEY (closed_by)
        REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_closed_periods_approval FOREIGN KEY (approval_id)
        REFERENCES approvals(id) ON DELETE SET NULL,
        
    CONSTRAINT uq_closed_period UNIQUE (company_id, start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Ledger Domain
-- ============================================

CREATE TABLE account_balances (
    id CHAR(36) PRIMARY KEY,
    account_id CHAR(36) NOT NULL,
    company_id CHAR(36) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,

    opening_balance_cents BIGINT NOT NULL DEFAULT 0,
    current_balance_cents BIGINT NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'USD',

    total_debits_cents BIGINT NOT NULL DEFAULT 0,
    total_credits_cents BIGINT NOT NULL DEFAULT 0,
    transaction_count INT NOT NULL DEFAULT 0,

    last_transaction_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_account_balances_account_period (account_id, period_start, period_end),
    INDEX idx_account_balances_company (company_id),
    INDEX idx_account_balances_period (period_start, period_end),

    CONSTRAINT fk_account_balances_account FOREIGN KEY (account_id)
        REFERENCES accounts(id) ON DELETE RESTRICT,
    CONSTRAINT fk_account_balances_company FOREIGN KEY (company_id)
        REFERENCES companies(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE balance_changes (
    id CHAR(36) PRIMARY KEY,
    account_balance_id CHAR(36) NOT NULL,
    transaction_line_id CHAR(36) NOT NULL,
    change_type VARCHAR(20) NOT NULL,
    amount_cents BIGINT NOT NULL,
    running_balance_cents BIGINT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_balance_changes_account_balance (account_balance_id),
    INDEX idx_balance_changes_transaction_line (transaction_line_id),

    CONSTRAINT fk_balance_changes_account_balance FOREIGN KEY (account_balance_id)
        REFERENCES account_balances(id) ON DELETE CASCADE,
    CONSTRAINT fk_balance_changes_transaction_line FOREIGN KEY (transaction_line_id)
        REFERENCES transaction_lines(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE journal_entries (
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

-- ============================================
-- Audit Domain (Append-Only)
-- ============================================

-- Company-scoped activity logs
CREATE TABLE activity_logs (
    id CHAR(36) PRIMARY KEY,
    company_id CHAR(36) NOT NULL,

    actor_user_id CHAR(36) NULL,
    actor_username VARCHAR(50) NULL,
    actor_ip_address VARCHAR(45) NULL,
    actor_user_agent VARCHAR(500) NULL,

    activity_type VARCHAR(100) NOT NULL,
    severity VARCHAR(20) NOT NULL DEFAULT 'info',

    entity_type VARCHAR(50) NOT NULL,
    entity_id CHAR(36) NOT NULL,

    changes_json JSON NULL,

    request_id CHAR(36) NULL,
    correlation_id CHAR(36) NULL,
    content_hash CHAR(64) NULL,
    previous_hash CHAR(64) NULL,
    chain_hash CHAR(64) NULL,

    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_activity_logs_company (company_id),
    INDEX idx_activity_logs_actor (actor_user_id),
    INDEX idx_activity_logs_entity (entity_type, entity_id),
    INDEX idx_activity_logs_type (activity_type),
    INDEX idx_activity_logs_severity (severity),
    INDEX idx_activity_logs_occurred (occurred_at),
    INDEX idx_activity_logs_company_date (company_id, occurred_at),
    INDEX idx_activity_logs_company_type (company_id, activity_type),
    INDEX idx_activity_logs_actor_date (actor_user_id, occurred_at),

    CONSTRAINT fk_activity_logs_company FOREIGN KEY (company_id)
        REFERENCES companies(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System-wide activity logs (global audit trail with hash chain)
CREATE TABLE system_activities (
    id CHAR(36) PRIMARY KEY,
    sequence_number BIGINT UNSIGNED AUTO_INCREMENT UNIQUE,
    
    -- Chain link to previous activity (immutable chain)
    previous_id CHAR(36) NULL,
    
    -- Actor information
    actor_user_id CHAR(36) NULL,
    actor_username VARCHAR(50) NULL,
    actor_ip_address VARCHAR(45) NULL,
    
    -- Activity details
    activity_type VARCHAR(100) NOT NULL,
    severity VARCHAR(20) NOT NULL DEFAULT 'info',
    entity_type VARCHAR(50) NOT NULL,
    entity_id CHAR(36) NOT NULL,
    description TEXT NOT NULL,
    metadata_json JSON NULL,
    
    -- Cryptographic hash chain
    content_hash CHAR(64) NOT NULL,
    previous_hash CHAR(64) NULL,
    chain_hash CHAR(64) NOT NULL,
    
    -- Timestamp with microsecond precision
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    
    -- Indexes
    INDEX idx_system_activities_type (activity_type),
    INDEX idx_system_activities_entity (entity_type, entity_id),
    INDEX idx_system_activities_actor (actor_user_id),
    INDEX idx_system_activities_severity (severity),
    INDEX idx_system_activities_created (created_at),
    INDEX idx_system_activities_sequence (sequence_number),
    
    -- IMMUTABLE: Cannot delete if another record references this
    CONSTRAINT fk_system_activities_previous FOREIGN KEY (previous_id) 
        REFERENCES system_activities(id) ON DELETE RESTRICT,
    
    -- Actor reference (optional - allows logging system actions)
    CONSTRAINT fk_system_activities_actor FOREIGN KEY (actor_user_id)
        REFERENCES users(id) ON DELETE RESTRICT
        
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Reporting Domain
-- ============================================

CREATE TABLE reports (
    id CHAR(36) PRIMARY KEY,
    company_id CHAR(36) NOT NULL,
    report_type VARCHAR(50) NOT NULL,
    period_type VARCHAR(20) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,

    generated_by CHAR(36) NOT NULL,
    generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    data_json JSON NOT NULL,

    INDEX idx_reports_company (company_id),
    INDEX idx_reports_type (report_type),
    INDEX idx_reports_period (period_start, period_end),
    INDEX idx_reports_company_type_date (company_id, report_type, generated_at DESC),

    CONSTRAINT fk_reports_company FOREIGN KEY (company_id)
        REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_reports_generated_by FOREIGN KEY (generated_by)
        REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- User Settings
-- ============================================

CREATE TABLE user_settings (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL UNIQUE,

    theme VARCHAR(20) NOT NULL DEFAULT 'light',
    locale VARCHAR(10) NOT NULL DEFAULT 'en-US',
    timezone VARCHAR(50) NOT NULL DEFAULT 'UTC',
    date_format VARCHAR(20) NOT NULL DEFAULT 'YYYY-MM-DD',
    number_format VARCHAR(20) NOT NULL DEFAULT 'en-US',

    email_notifications TINYINT(1) NOT NULL DEFAULT 1,
    browser_notifications TINYINT(1) NOT NULL DEFAULT 1,

    session_timeout_minutes INT NOT NULL DEFAULT 30,

    backup_codes_hash TEXT NULL,
    backup_codes_generated_at DATETIME NULL,

    extra_settings_json JSON NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_user_settings_user (user_id),

    CONSTRAINT fk_user_settings_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Schema Complete - Version 3.0
-- Includes: Hash chains, System activities, Transaction sequences
-- ============================================
SELECT 'Fresh schema v3.0 created successfully' AS status;
