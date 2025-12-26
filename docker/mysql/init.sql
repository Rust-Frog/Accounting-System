-- Accounting System Database Schema
-- Version: 1.0
-- Created: 2025-12-18

-- ============================================
-- Identity Domain
-- ============================================

CREATE TABLE IF NOT EXISTS users (
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

CREATE TABLE IF NOT EXISTS sessions (
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

CREATE TABLE IF NOT EXISTS companies (
    id CHAR(36) PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    legal_name VARCHAR(255) NOT NULL,
    tax_id VARCHAR(50) NOT NULL UNIQUE,
    
    -- Address fields (denormalized for simplicity)
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

-- Add foreign key for users -> companies after companies table exists
ALTER TABLE users 
    ADD CONSTRAINT fk_users_company FOREIGN KEY (company_id) 
    REFERENCES companies(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS company_settings (
    id CHAR(36) PRIMARY KEY,
    company_id CHAR(36) NOT NULL UNIQUE,
    fiscal_year_start_month TINYINT NOT NULL DEFAULT 1,
    fiscal_year_start_day TINYINT NOT NULL DEFAULT 1,
    settings_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_company_settings_company FOREIGN KEY (company_id) 
        REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Chart of Accounts Domain
-- ============================================

CREATE TABLE IF NOT EXISTS accounts (
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
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_accounts_company_code (company_id, code),
    INDEX idx_accounts_company (company_id),
    INDEX idx_accounts_parent (parent_account_id),
    INDEX idx_accounts_active (is_active),
    INDEX idx_accounts_code (code),
    
    CONSTRAINT fk_accounts_company FOREIGN KEY (company_id) 
        REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_accounts_parent FOREIGN KEY (parent_account_id) 
        REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Transaction Domain
-- ============================================

CREATE TABLE IF NOT EXISTS transactions (
    id CHAR(36) PRIMARY KEY,
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
    
    INDEX idx_transactions_company (company_id),
    INDEX idx_transactions_date (transaction_date),
    INDEX idx_transactions_status (status),
    INDEX idx_transactions_created_by (created_by),
    INDEX idx_transactions_company_date (company_id, transaction_date),
    
    CONSTRAINT fk_transactions_company FOREIGN KEY (company_id) 
        REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_transactions_created_by FOREIGN KEY (created_by) 
        REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_transactions_posted_by FOREIGN KEY (posted_by) 
        REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_transactions_voided_by FOREIGN KEY (voided_by) 
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transaction_lines (
    id CHAR(36) PRIMARY KEY,
    transaction_id CHAR(36) NOT NULL,
    account_id CHAR(36) NOT NULL,
    line_type VARCHAR(10) NOT NULL, -- 'debit' or 'credit'
    amount_cents BIGINT NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    description VARCHAR(255) NULL,
    line_order INT NOT NULL DEFAULT 0,
    
    INDEX idx_transaction_lines_transaction (transaction_id),
    INDEX idx_transaction_lines_account (account_id),
    INDEX idx_transaction_lines_type (line_type),
    
    CONSTRAINT fk_transaction_lines_transaction FOREIGN KEY (transaction_id) 
        REFERENCES transactions(id) ON DELETE CASCADE,
    CONSTRAINT fk_transaction_lines_account FOREIGN KEY (account_id) 
        REFERENCES accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Approval Domain
-- ============================================

CREATE TABLE IF NOT EXISTS approvals (
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
    
    INDEX idx_approvals_company (company_id),
    INDEX idx_approvals_entity (entity_type, entity_id),
    INDEX idx_approvals_status (status),
    INDEX idx_approvals_requested_by (requested_by),
    INDEX idx_approvals_reviewed_by (reviewed_by),
    INDEX idx_approvals_expires (expires_at),
    INDEX idx_approvals_priority (priority),
    
    CONSTRAINT fk_approvals_company FOREIGN KEY (company_id) 
        REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_approvals_requested_by FOREIGN KEY (requested_by) 
        REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_approvals_reviewed_by FOREIGN KEY (reviewed_by) 
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Ledger Domain
-- ============================================

CREATE TABLE IF NOT EXISTS account_balances (
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

CREATE TABLE IF NOT EXISTS balance_changes (
    id CHAR(36) PRIMARY KEY,
    account_balance_id CHAR(36) NOT NULL,
    transaction_line_id CHAR(36) NOT NULL,
    change_type VARCHAR(20) NOT NULL, -- 'debit' or 'credit'
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

-- ============================================
-- Audit Domain (Append-Only)
-- ============================================

CREATE TABLE IF NOT EXISTS activity_logs (
    id CHAR(36) PRIMARY KEY,
    company_id CHAR(36) NOT NULL,
    
    -- Actor information
    actor_user_id CHAR(36) NULL,
    actor_username VARCHAR(50) NULL,
    actor_ip_address VARCHAR(45) NULL,
    actor_user_agent VARCHAR(500) NULL,
    
    -- Activity details
    activity_type VARCHAR(100) NOT NULL,
    severity VARCHAR(20) NOT NULL DEFAULT 'info',
    
    -- Entity being acted upon
    entity_type VARCHAR(50) NOT NULL,
    entity_id CHAR(36) NOT NULL,
    
    -- Change details (JSON for flexibility)
    changes_json JSON NULL,
    
    -- Request context
    request_id CHAR(36) NULL,
    correlation_id CHAR(36) NULL,
    
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- NOTE: No UPDATE or DELETE allowed - append-only table
    INDEX idx_activity_logs_company (company_id),
    INDEX idx_activity_logs_actor (actor_user_id),
    INDEX idx_activity_logs_entity (entity_type, entity_id),
    INDEX idx_activity_logs_type (activity_type),
    INDEX idx_activity_logs_severity (severity),
    INDEX idx_activity_logs_occurred (occurred_at),
    INDEX idx_activity_logs_company_date (company_id, occurred_at),
    
    CONSTRAINT fk_activity_logs_company FOREIGN KEY (company_id) 
        REFERENCES companies(id) ON DELETE RESTRICT
    -- NOTE: No FK to users - audit logs must persist even if user is deleted
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Reporting Domain
-- ============================================

CREATE TABLE IF NOT EXISTS reports (
    id CHAR(36) PRIMARY KEY,
    company_id CHAR(36) NOT NULL,
    report_type VARCHAR(50) NOT NULL,
    period_type VARCHAR(20) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    
    generated_by CHAR(36) NOT NULL,
    generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Report data stored as JSON
    data_json JSON NOT NULL,
    
    INDEX idx_reports_company (company_id),
    INDEX idx_reports_type (report_type),
    INDEX idx_reports_period (period_start, period_end),
    
    CONSTRAINT fk_reports_company FOREIGN KEY (company_id) 
        REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_reports_generated_by FOREIGN KEY (generated_by) 
        REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- User Settings (Per-User Preferences)
-- ============================================

CREATE TABLE IF NOT EXISTS user_settings (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL UNIQUE,
    
    -- UI Preferences
    theme VARCHAR(20) NOT NULL DEFAULT 'light',
    locale VARCHAR(10) NOT NULL DEFAULT 'en-US',
    timezone VARCHAR(50) NOT NULL DEFAULT 'UTC',
    date_format VARCHAR(20) NOT NULL DEFAULT 'YYYY-MM-DD',
    number_format VARCHAR(20) NOT NULL DEFAULT 'en-US',
    
    -- Notification Preferences
    email_notifications TINYINT(1) NOT NULL DEFAULT 1,
    browser_notifications TINYINT(1) NOT NULL DEFAULT 1,
    
    -- Security Preferences
    session_timeout_minutes INT NOT NULL DEFAULT 30,
    
    -- Recovery
    backup_codes_hash TEXT NULL,
    backup_codes_generated_at DATETIME NULL,
    
    -- Additional settings as JSON
    extra_settings_json JSON NULL,
    
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user_settings_user (user_id),
    
    CONSTRAINT fk_user_settings_user FOREIGN KEY (user_id) 
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Initial Data (Optional)
-- ============================================

-- You can add seed data here if needed for development
