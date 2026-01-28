-- ============================================
-- OTP History Stored Procedures
-- ============================================

DELIMITER $$

-- ============================================
-- Log OTP Request
-- ============================================
DROP PROCEDURE IF EXISTS sp_log_otp_request$$
CREATE PROCEDURE sp_log_otp_request(
    IN p_history_id VARCHAR(36),
    IN p_user_id VARCHAR(36),
    IN p_identifier_type VARCHAR(50),
    IN p_identifier_value VARCHAR(255),
    IN p_ip_address VARCHAR(45),
    IN p_user_agent VARCHAR(255),
    IN p_metadata JSON
)
BEGIN
    INSERT INTO otp_history (
        history_id,
        user_id,
        action,
        identifier_type,
        identifier_value,
        ip_address,
        user_agent,
        reason,
        metadata,
        created_at
    ) VALUES (
        p_history_id,
        p_user_id,
        'request',
        p_identifier_type,
        p_identifier_value,
        p_ip_address,
        p_user_agent,
        'OTP requested',
        p_metadata,
        NOW()
    );
END$$

-- ============================================
-- Log OTP Verification (Success/Fail)
-- ============================================
DROP PROCEDURE IF EXISTS sp_log_otp_verification$$
CREATE PROCEDURE sp_log_otp_verification(
    IN p_history_id VARCHAR(36),
    IN p_user_id VARCHAR(36),
    IN p_action VARCHAR(50),
    IN p_identifier_type VARCHAR(50),
    IN p_identifier_value VARCHAR(255),
    IN p_ip_address VARCHAR(45),
    IN p_user_agent VARCHAR(255),
    IN p_reason VARCHAR(255),
    IN p_metadata JSON
)
BEGIN
    INSERT INTO otp_history (
        history_id,
        user_id,
        action,
        identifier_type,
        identifier_value,
        ip_address,
        user_agent,
        reason,
        metadata,
        created_at
    ) VALUES (
        p_history_id,
        p_user_id,
        p_action,
        p_identifier_type,
        p_identifier_value,
        p_ip_address,
        p_user_agent,
        p_reason,
        p_metadata,
        NOW()
    );
END$$

-- ============================================
-- Get OTP History for User
-- ============================================
DROP PROCEDURE IF EXISTS sp_get_otp_history_by_user$$
CREATE PROCEDURE sp_get_otp_history_by_user(
    IN p_user_id VARCHAR(36),
    IN p_limit INT
)
BEGIN
    SELECT 
        history_id,
        action,
        identifier_type,
        identifier_value,
        ip_address,
        reason,
        metadata,
        created_at
    FROM otp_history
    WHERE user_id = p_user_id
    ORDER BY created_at DESC
    LIMIT p_limit;
END$$

-- ============================================
-- Get OTP History for Identifier
-- ============================================
DROP PROCEDURE IF EXISTS sp_get_otp_history_by_identifier$$
CREATE PROCEDURE sp_get_otp_history_by_identifier(
    IN p_identifier_type VARCHAR(50),
    IN p_identifier_value VARCHAR(255),
    IN p_limit INT
)
BEGIN
    SELECT 
        history_id,
        user_id,
        action,
        ip_address,
        user_agent,
        reason,
        metadata,
        created_at
    FROM otp_history
    WHERE identifier_type = p_identifier_type
    AND identifier_value = p_identifier_value
    ORDER BY created_at DESC
    LIMIT p_limit;
END$$

-- ============================================
-- Get OTP Statistics
-- ============================================
DROP PROCEDURE IF EXISTS sp_get_otp_statistics$$
CREATE PROCEDURE sp_get_otp_statistics(
    IN p_time_window_hours INT
)
BEGIN
    SELECT 
        action,
        COUNT(*) as count,
        COUNT(DISTINCT user_id) as unique_users,
        COUNT(DISTINCT ip_address) as unique_ips
    FROM otp_history
    WHERE created_at > DATE_SUB(NOW(), INTERVAL p_time_window_hours HOUR)
    GROUP BY action
    ORDER BY count DESC;
END$$

-- ============================================
-- Get Failed Attempts Count
-- ============================================
DROP PROCEDURE IF EXISTS sp_get_failed_attempts_count$$
CREATE PROCEDURE sp_get_failed_attempts_count(
    IN p_identifier_type VARCHAR(50),
    IN p_identifier_value VARCHAR(255),
    IN p_time_window_minutes INT,
    OUT p_count INT
)
BEGIN
    SELECT COUNT(*)
    INTO p_count
    FROM otp_history
    WHERE identifier_type = p_identifier_type
    AND identifier_value = p_identifier_value
    AND action IN ('verify_fail', 'max_retries_exceeded')
    AND created_at > DATE_SUB(NOW(), INTERVAL p_time_window_minutes MINUTE);
END$$

-- ============================================
-- Cleanup Old History
-- ============================================
DROP PROCEDURE IF EXISTS sp_cleanup_old_history$$
CREATE PROCEDURE sp_cleanup_old_history(
    IN p_retention_days INT
)
BEGIN
    DECLARE v_deleted_count INT;
    
    DELETE FROM otp_history
    WHERE created_at < DATE_SUB(NOW(), INTERVAL p_retention_days DAY);
    
    SET v_deleted_count = ROW_COUNT();
    
    SELECT v_deleted_count as deleted_count;
END$$

-- ============================================
-- Get Recent Activity Summary
-- ============================================
DROP PROCEDURE IF EXISTS sp_get_recent_activity$$
CREATE PROCEDURE sp_get_recent_activity(
    IN p_limit INT
)
BEGIN
    SELECT 
        history_id,
        user_id,
        action,
        identifier_type,
        identifier_value,
        ip_address,
        reason,
        created_at
    FROM otp_history
    ORDER BY created_at DESC
    LIMIT p_limit;
END$$

DELIMITER ;
