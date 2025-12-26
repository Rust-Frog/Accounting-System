-- ============================================
-- Accounting System - Safe Database Cleanup
-- Version: 1.0
--
-- Safely removes all data while preserving schema.
-- Tables are truncated in dependency order.
--
-- Usage:
--   mysql -u user -p database < cleanup.sql
--   OR via docker: docker exec -i container mysql -u user -p database < cleanup.sql
-- ============================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- Truncate tables in reverse dependency order
-- ============================================

-- Reporting & Audit (leaf tables, no dependents)
TRUNCATE TABLE reports;
TRUNCATE TABLE activity_logs;

-- User Settings
TRUNCATE TABLE user_settings;

-- Ledger Domain
TRUNCATE TABLE balance_changes;
TRUNCATE TABLE account_balances;
TRUNCATE TABLE journal_entries;

-- Approval Domain
TRUNCATE TABLE approvals;

-- Transaction Domain
TRUNCATE TABLE transaction_lines;
TRUNCATE TABLE transactions;

-- Chart of Accounts
TRUNCATE TABLE accounts;

-- Company Domain
TRUNCATE TABLE company_settings;

-- Sessions
TRUNCATE TABLE sessions;

-- Core tables (users before companies due to FK)
TRUNCATE TABLE users;
TRUNCATE TABLE companies;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- Cleanup Complete
-- ============================================
-- All data has been removed.
-- Schema remains intact.
-- ============================================
