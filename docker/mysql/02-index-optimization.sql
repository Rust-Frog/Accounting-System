-- ============================================
-- Index Optimization Migration
-- Phase 4B: Database Query Optimization
-- ============================================

-- Composite indexes for common query patterns

-- Transactions: findByStatus, countByStatus queries
-- Query: WHERE company_id = ? AND status = ?
ALTER TABLE transactions
    ADD INDEX idx_transactions_company_status (company_id, status);

-- Approvals: findByStatus queries
-- Query: WHERE company_id = ? AND status = ?
ALTER TABLE approvals
    ADD INDEX idx_approvals_company_status (company_id, status);

-- Activity Logs: findByActivityType queries
-- Query: WHERE company_id = ? AND activity_type = ?
ALTER TABLE activity_logs
    ADD INDEX idx_activity_logs_company_type (company_id, activity_type);

-- Activity Logs: findByUser with date range queries
-- Query: WHERE actor_user_id = ? AND occurred_at BETWEEN ? AND ?
ALTER TABLE activity_logs
    ADD INDEX idx_activity_logs_actor_date (actor_user_id, occurred_at);

-- Accounts: Filter active accounts by company
-- Query: WHERE company_id = ? AND is_active = ?
ALTER TABLE accounts
    ADD INDEX idx_accounts_company_active (company_id, is_active);

-- Transaction Lines: Better join performance with transactions
-- Query: JOIN transaction_lines ON transaction_id = ? ORDER BY line_order
ALTER TABLE transaction_lines
    ADD INDEX idx_transaction_lines_txn_order (transaction_id, line_order);

-- Reports: Common query for latest reports by type
-- Query: WHERE company_id = ? AND report_type = ? ORDER BY generated_at DESC
ALTER TABLE reports
    ADD INDEX idx_reports_company_type_date (company_id, report_type, generated_at DESC);

SELECT 'Index optimization migration complete' AS status;
