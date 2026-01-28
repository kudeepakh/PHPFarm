-- ============================================
-- OTP Blacklist Stored Procedures
-- ============================================

DELIMITER $$

-- ============================================
-- Check if Identifier is Blacklisted
-- ============================================
DROP PROCEDURE IF EXISTS sp_check_blacklist$$
CREATE PROCEDURE sp_check_blacklist(
    IN p_identifier_type VARCHAR(50),
    IN p_identifier_value VARCHAR(255),
    OUT p_is_blacklisted BOOLEAN,
    OUT p_reason VARCHAR(255),
    OUT p_expires_at TIMESTAMP
)
BEGIN
    DECLARE v_blacklist_id VARCHAR(36);
    DECLARE v_is_permanent BOOLEAN;
    
    SET p_is_blacklisted = FALSE;
    SET p_reason = NULL;
    SET p_expires_at = NULL;
    
    SELECT blacklist_id, reason, expires_at, is_permanent
    INTO v_blacklist_id, p_reason, p_expires_at, v_is_permanent
    FROM otp_blacklist
    WHERE identifier_type = p_identifier_type
    AND identifier_value COLLATE utf8mb4_unicode_ci = p_identifier_value COLLATE utf8mb4_unicode_ci
    AND (is_permanent = TRUE OR expires_at > NOW())
    LIMIT 1;
    
    IF v_blacklist_id IS NOT NULL THEN
        SET p_is_blacklisted = TRUE;
    END IF;
END$$

-- ============================================
-- Add to Blacklist
-- ============================================
DROP PROCEDURE IF EXISTS sp_add_to_blacklist$$
CREATE PROCEDURE sp_add_to_blacklist(
    IN p_blacklist_id VARCHAR(36),
    IN p_identifier_type VARCHAR(50),
    IN p_identifier_value VARCHAR(255),
    IN p_reason VARCHAR(255),
    IN p_blacklisted_by VARCHAR(36),
    IN p_duration_hours INT,
    IN p_is_permanent BOOLEAN,
    IN p_auto_blacklisted BOOLEAN
)
BEGIN
    DECLARE v_expires_at TIMESTAMP;
    
    IF p_is_permanent = TRUE THEN
        SET v_expires_at = NULL;
    ELSE
        SET v_expires_at = DATE_ADD(NOW(), INTERVAL p_duration_hours HOUR);
    END IF;
    
    -- Insert or update blacklist entry
    INSERT INTO otp_blacklist (
        blacklist_id,
        identifier_type,
        identifier_value,
        reason,
        blacklisted_by,
        blacklisted_at,
        expires_at,
        is_permanent,
        auto_blacklisted
    ) VALUES (
        p_blacklist_id,
        p_identifier_type,
        p_identifier_value,
        p_reason,
        p_blacklisted_by,
        NOW(),
        v_expires_at,
        p_is_permanent,
        p_auto_blacklisted
    )
    ON DUPLICATE KEY UPDATE
        reason = p_reason,
        blacklisted_by = p_blacklisted_by,
        blacklisted_at = NOW(),
        expires_at = v_expires_at,
        is_permanent = p_is_permanent;
    
    -- Log to history
    INSERT INTO otp_history (
        history_id, action, identifier_type, identifier_value,
        reason, metadata, created_at
    ) VALUES (
        UUID(), 'blocked', p_identifier_type, p_identifier_value,
        p_reason, JSON_OBJECT('duration_hours', p_duration_hours, 'permanent', p_is_permanent), NOW()
    );
END$$

-- ============================================
-- Remove from Blacklist
-- ============================================
DROP PROCEDURE IF EXISTS sp_remove_from_blacklist$$
CREATE PROCEDURE sp_remove_from_blacklist(
    IN p_blacklist_id VARCHAR(36)
)
BEGIN
    DELETE FROM otp_blacklist
    WHERE blacklist_id = p_blacklist_id;
END$$

-- ============================================
-- Auto-Blacklist Based on Failed Attempts
-- ============================================
DROP PROCEDURE IF EXISTS sp_auto_blacklist_if_threshold$$
CREATE PROCEDURE sp_auto_blacklist_if_threshold(
    IN p_identifier_type VARCHAR(50),
    IN p_identifier_value VARCHAR(255),
    IN p_threshold INT,
    IN p_time_window_minutes INT
)
BEGIN
    DECLARE v_failed_count INT;
    DECLARE v_blacklist_id VARCHAR(36);
    
    -- Count failed attempts in time window
    SELECT COUNT(*)
    INTO v_failed_count
    FROM otp_history
    WHERE identifier_type = p_identifier_type
    AND identifier_value COLLATE utf8mb4_unicode_ci = p_identifier_value COLLATE utf8mb4_unicode_ci
    AND action = 'verify_fail'
    AND created_at > DATE_SUB(NOW(), INTERVAL p_time_window_minutes MINUTE);
    
    -- If threshold exceeded, blacklist
    IF v_failed_count >= p_threshold THEN
        SET v_blacklist_id = UUID();
        
        CALL sp_add_to_blacklist(
            v_blacklist_id,
            p_identifier_type,
            p_identifier_value,
            CONCAT('Auto-blacklisted: ', v_failed_count, ' failed attempts in ', p_time_window_minutes, ' minutes'),
            'system',
            24, -- 24 hour blacklist
            FALSE,
            TRUE
        );
    END IF;
END$$

-- ============================================
-- Get Blacklist Entries
-- ============================================
DROP PROCEDURE IF EXISTS sp_get_blacklist$$
CREATE PROCEDURE sp_get_blacklist(
    IN p_limit INT,
    IN p_offset INT
)
BEGIN
    SELECT 
        blacklist_id,
        identifier_type,
        identifier_value,
        reason,
        blacklisted_by,
        blacklisted_at,
        expires_at,
        is_permanent,
        auto_blacklisted,
        CASE
            WHEN is_permanent = TRUE THEN 'permanent'
            WHEN expires_at < NOW() THEN 'expired'
            ELSE 'active'
        END as status
    FROM otp_blacklist
    ORDER BY blacklisted_at DESC
    LIMIT p_limit OFFSET p_offset;
END$$

-- ============================================
-- Cleanup Expired Blacklist Entries
-- ============================================
DROP PROCEDURE IF EXISTS sp_cleanup_expired_blacklist$$
CREATE PROCEDURE sp_cleanup_expired_blacklist()
BEGIN
    DECLARE v_deleted_count INT;
    
    DELETE FROM otp_blacklist
    WHERE is_permanent = FALSE
    AND expires_at < NOW();
    
    SET v_deleted_count = ROW_COUNT();
    
    SELECT v_deleted_count as deleted_count;
END$$

DELIMITER ;
