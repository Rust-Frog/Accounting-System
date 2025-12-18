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
    
    INDEX idx_company_occurred (company_id, occurred_at),
    INDEX idx_transaction (transaction_id),
    UNIQUE INDEX idx_previous_hash (previous_hash) -- Branching not allowed
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
