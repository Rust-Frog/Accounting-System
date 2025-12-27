-- Migration: Add edge case detection threshold columns to company_settings
-- Date: 2025-12-28
-- Purpose: Store configurable thresholds for transaction edge case validation

ALTER TABLE company_settings
    ADD COLUMN large_transaction_threshold_cents BIGINT NOT NULL DEFAULT 1000000 COMMENT '10000.00 default',
    ADD COLUMN backdated_days_threshold INT NOT NULL DEFAULT 30,
    ADD COLUMN future_dated_allowed TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN require_approval_contra_entry TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN require_approval_equity_adjustment TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN require_approval_negative_balance TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN flag_round_numbers TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Optional audit flag',
    ADD COLUMN flag_period_end_entries TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Optional audit flag',
    ADD COLUMN dormant_account_days_threshold INT NOT NULL DEFAULT 90;

-- Index for efficient threshold lookups
CREATE INDEX idx_company_settings_thresholds ON company_settings(company_id, large_transaction_threshold_cents);
