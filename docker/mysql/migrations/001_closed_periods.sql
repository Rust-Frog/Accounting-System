-- ============================================
-- Closed Periods - Migration Script
-- Tracks which accounting periods are closed per company
-- ============================================

CREATE TABLE IF NOT EXISTS closed_periods (
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
