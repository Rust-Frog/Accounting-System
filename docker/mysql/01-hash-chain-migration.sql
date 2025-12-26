-- ============================================
-- Migration: Add System Activities Table & Hash Chain Columns
-- Version: 3.0
-- Purpose: Implement immutable cryptographic audit trail
-- ============================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- 1. System-Wide Activity Table (Global Audit Trail)
-- ============================================

CREATE TABLE IF NOT EXISTS system_activities (
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
-- 2. Add Hash Chain Columns to Transactions
-- ============================================

-- Check if columns exist before adding
SET @dbname = DATABASE();

-- Add content_hash to transactions if not exists
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'transactions' AND COLUMN_NAME = 'content_hash');
SET @query = IF(@col = 0, 'ALTER TABLE transactions ADD COLUMN content_hash CHAR(64) NULL', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add previous_hash to transactions if not exists  
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'transactions' AND COLUMN_NAME = 'previous_hash');
SET @query = IF(@col = 0, 'ALTER TABLE transactions ADD COLUMN previous_hash CHAR(64) NULL', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add chain_hash to transactions if not exists
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'transactions' AND COLUMN_NAME = 'chain_hash');
SET @query = IF(@col = 0, 'ALTER TABLE transactions ADD COLUMN chain_hash CHAR(64) NULL', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- 3. Add Hash Chain Columns to Accounts
-- ============================================

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'accounts' AND COLUMN_NAME = 'content_hash');
SET @query = IF(@col = 0, 'ALTER TABLE accounts ADD COLUMN content_hash CHAR(64) NULL', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'accounts' AND COLUMN_NAME = 'previous_hash');
SET @query = IF(@col = 0, 'ALTER TABLE accounts ADD COLUMN previous_hash CHAR(64) NULL', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'accounts' AND COLUMN_NAME = 'chain_hash');
SET @query = IF(@col = 0, 'ALTER TABLE accounts ADD COLUMN chain_hash CHAR(64) NULL', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- 4. Add Hash Chain Columns to Approvals
-- ============================================

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'approvals' AND COLUMN_NAME = 'content_hash');
SET @query = IF(@col = 0, 'ALTER TABLE approvals ADD COLUMN content_hash CHAR(64) NULL', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'approvals' AND COLUMN_NAME = 'previous_hash');
SET @query = IF(@col = 0, 'ALTER TABLE approvals ADD COLUMN previous_hash CHAR(64) NULL', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'approvals' AND COLUMN_NAME = 'chain_hash');
SET @query = IF(@col = 0, 'ALTER TABLE approvals ADD COLUMN chain_hash CHAR(64) NULL', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- Migration Complete
-- ============================================
SELECT 'Hash chain migration completed successfully' AS status;
