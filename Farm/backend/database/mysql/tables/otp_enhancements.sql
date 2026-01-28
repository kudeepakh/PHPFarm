-- ============================================
-- OTP Module - Security Enhancements
-- ============================================
-- Purpose: Add retry limits, replay prevention, 
--          history tracking, and blacklisting
-- ============================================

-- ALTER existing otp_verifications table
ALTER TABLE otp_verifications ADD COLUMN retry_count INT DEFAULT 0;
ALTER TABLE otp_verifications ADD COLUMN used_at TIMESTAMP NULL;
ALTER TABLE otp_verifications ADD COLUMN is_used BOOLEAN DEFAULT FALSE;
ALTER TABLE otp_verifications ADD COLUMN user_agent VARCHAR(255);
ALTER TABLE otp_verifications ADD COLUMN last_verify_attempt TIMESTAMP NULL;

-- Add indexes for performance
CREATE INDEX idx_otp_used_at ON otp_verifications(used_at);
CREATE INDEX idx_otp_is_used ON otp_verifications(is_used);
CREATE INDEX idx_otp_retry_count ON otp_verifications(retry_count);

-- ============================================
-- OTP History Table (Audit Trail)
-- ============================================
CREATE TABLE IF NOT EXISTS otp_history (
    history_id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36),
    action ENUM(
        'request', 
        'verify_success', 
        'verify_fail', 
        'expired', 
        'blocked', 
        'max_retries_exceeded',
        'replay_attempt'
    ) NOT NULL,
    identifier_type ENUM('phone', 'email') NOT NULL,
    identifier_value VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    reason VARCHAR(255),
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_history_user_id (user_id),
    INDEX idx_history_action (action),
    INDEX idx_history_identifier (identifier_type, identifier_value),
    INDEX idx_history_created_at (created_at),
    INDEX idx_history_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- OTP Blacklist Table
-- ============================================
CREATE TABLE IF NOT EXISTS otp_blacklist (
    blacklist_id VARCHAR(36) PRIMARY KEY,
    identifier_type ENUM('user_id', 'ip_address', 'phone', 'email') NOT NULL,
    identifier_value VARCHAR(255) NOT NULL,
    reason VARCHAR(255),
    blacklisted_by VARCHAR(36),
    blacklisted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    is_permanent BOOLEAN DEFAULT FALSE,
    auto_blacklisted BOOLEAN DEFAULT FALSE,
    
    UNIQUE KEY uk_blacklist_identifier (identifier_type, identifier_value),
    INDEX idx_blacklist_expires (expires_at),
    INDEX idx_blacklist_permanent (is_permanent),
    INDEX idx_blacklist_auto (auto_blacklisted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- OTP Rate Limit Tracking Table
-- ============================================
CREATE TABLE IF NOT EXISTS otp_rate_limits (
    rate_limit_id VARCHAR(36) PRIMARY KEY,
    identifier_type ENUM('user_id', 'ip_address') NOT NULL,
    identifier_value VARCHAR(255) NOT NULL,
    request_count INT DEFAULT 0,
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    window_end TIMESTAMP,
    last_request_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_rate_limit (identifier_type, identifier_value),
    INDEX idx_rate_window_end (window_end),
    INDEX idx_rate_last_request (last_request_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Migration Notes
-- ============================================
-- For existing otp_verifications records:
-- 1. retry_count will default to 0
-- 2. used_at will be NULL (safe assumption: not used yet or expired)
-- 3. is_used will default to FALSE
-- 4. user_agent will be NULL

-- Recommended: Clean up expired OTPs before migration
-- DELETE FROM otp_verifications WHERE expires_at < NOW();

-- ============================================
-- Indexes Summary
-- ============================================
-- otp_verifications: 3 new indexes (used_at, is_used, retry_count)
-- otp_history: 5 indexes (user_id, action, identifier, created_at, ip)
-- otp_blacklist: 3 indexes (expires, permanent, auto)
-- otp_rate_limits: 2 indexes (window_end, last_request)
