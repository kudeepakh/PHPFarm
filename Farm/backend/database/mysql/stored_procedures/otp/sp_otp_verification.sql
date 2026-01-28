-- ============================================
-- OTP Verification Stored Procedures
-- With Retry Limits and Replay Prevention
-- ============================================

DELIMITER $$

-- ============================================
-- Verify OTP with Retry Limit
-- ============================================
DROP PROCEDURE IF EXISTS sp_verify_otp_with_retry_limit$$
CREATE PROCEDURE sp_verify_otp_with_retry_limit(
    IN p_identifier VARCHAR(255),
    IN p_otp VARCHAR(10),
    IN p_ip_address VARCHAR(45),
    IN p_user_agent VARCHAR(255),
    OUT p_is_valid BOOLEAN,
    OUT p_user_id VARCHAR(36),
    OUT p_message VARCHAR(255)
)
proc: BEGIN
    DECLARE v_stored_otp VARCHAR(10);
    DECLARE v_expires_at TIMESTAMP;
    DECLARE v_retry_count INT;
    DECLARE v_is_used BOOLEAN;
    DECLARE v_used_at TIMESTAMP;
    DECLARE v_request_id VARCHAR(36);
    DECLARE v_max_retries INT DEFAULT 3;
    
    -- Default output
    SET p_is_valid = FALSE;
    SET p_user_id = NULL;
    SET p_message = 'Invalid OTP';
    
    -- Get OTP request details
    SELECT request_id, otp, user_id, expires_at, retry_count, is_used, used_at
    INTO v_request_id, v_stored_otp, p_user_id, v_expires_at, v_retry_count, v_is_used, v_used_at
    FROM otp_requests
    WHERE identifier = p_identifier
    AND is_used = FALSE
    ORDER BY created_at DESC
    LIMIT 1;
    
    -- Check if OTP request exists
    IF v_request_id IS NULL THEN
        SET p_message = 'No OTP request found';
        LEAVE proc;
    END IF;
    
    -- Check if already used (replay attack)
    IF v_is_used = TRUE THEN
        SET p_message = 'OTP already used';
        
        -- Log replay attempt
        INSERT INTO otp_history (
            history_id, user_id, action, identifier_type, identifier_value,
            ip_address, user_agent, reason, created_at
        ) VALUES (
            UUID(), p_user_id, 'replay_attempt', 'phone', p_identifier,
            p_ip_address, p_user_agent, 'Attempted to reuse OTP', NOW()
        );
        
        LEAVE proc;
    END IF;
    
    -- Check if expired
    IF NOW() > v_expires_at THEN
        SET p_message = 'OTP expired';
        
        -- Mark as expired in history
        INSERT INTO otp_history (
            history_id, user_id, action, identifier_type, identifier_value,
            ip_address, user_agent, reason, created_at
        ) VALUES (
            UUID(), p_user_id, 'expired', 'phone', p_identifier,
            p_ip_address, p_user_agent, 'OTP verification attempted after expiry', NOW()
        );
        
        LEAVE proc;
    END IF;
    
    -- Check retry limit
    IF v_retry_count >= v_max_retries THEN
        SET p_message = 'Maximum retry attempts exceeded';
        
        -- Log max retries exceeded
        INSERT INTO otp_history (
            history_id, user_id, action, identifier_type, identifier_value,
            ip_address, user_agent, reason, created_at
        ) VALUES (
            UUID(), p_user_id, 'max_retries_exceeded', 'phone', p_identifier,
            p_ip_address, p_user_agent, CONCAT('Exceeded ', v_max_retries, ' attempts'), NOW()
        );
        
        LEAVE proc;
    END IF;
    
    -- Increment retry count
    UPDATE otp_requests
    SET retry_count = retry_count + 1,
        last_verify_attempt = NOW()
    WHERE request_id = v_request_id;
    
    -- Verify OTP
    IF v_stored_otp = p_otp THEN
        SET p_is_valid = TRUE;
        SET p_message = 'OTP verified successfully';
        
        -- Mark OTP as used
        UPDATE otp_requests
        SET is_used = TRUE,
            used_at = NOW()
        WHERE request_id = v_request_id;
        
        -- Log successful verification
        INSERT INTO otp_history (
            history_id, user_id, action, identifier_type, identifier_value,
            ip_address, user_agent, reason, created_at
        ) VALUES (
            UUID(), p_user_id, 'verify_success', 'phone', p_identifier,
            p_ip_address, p_user_agent, 'OTP verified successfully', NOW()
        );
    ELSE
        SET p_message = 'Invalid OTP';
        
        -- Log failed verification
        INSERT INTO otp_history (
            history_id, user_id, action, identifier_type, identifier_value,
            ip_address, user_agent, reason, created_at
        ) VALUES (
            UUID(), p_user_id, 'verify_fail', 'phone', p_identifier,
            p_ip_address, p_user_agent, CONCAT('Failed attempt ', v_retry_count + 1, ' of ', v_max_retries), NOW()
        );
    END IF;
END$$

-- ============================================
-- Mark OTP as Used (Manual)
-- ============================================
DROP PROCEDURE IF EXISTS sp_mark_otp_used$$
CREATE PROCEDURE sp_mark_otp_used(
    IN p_request_id VARCHAR(36)
)
BEGIN
    UPDATE otp_requests
    SET is_used = TRUE,
        used_at = NOW()
    WHERE request_id = p_request_id;
END$$

-- ============================================
-- Get OTP Retry Count
-- ============================================
DROP PROCEDURE IF EXISTS sp_get_otp_retry_count$$
CREATE PROCEDURE sp_get_otp_retry_count(
    IN p_identifier VARCHAR(255),
    OUT p_retry_count INT,
    OUT p_max_retries INT
)
BEGIN
    SET p_max_retries = 3;
    
    SELECT COALESCE(retry_count, 0)
    INTO p_retry_count
    FROM otp_requests
    WHERE identifier = p_identifier
    AND is_used = FALSE
    AND expires_at > NOW()
    ORDER BY created_at DESC
    LIMIT 1;
    
    IF p_retry_count IS NULL THEN
        SET p_retry_count = 0;
    END IF;
END$$

-- ============================================
-- Check if OTP is Valid (Without Verification)
-- ============================================
DROP PROCEDURE IF EXISTS sp_check_otp_validity$$
CREATE PROCEDURE sp_check_otp_validity(
    IN p_identifier VARCHAR(255),
    OUT p_is_valid BOOLEAN,
    OUT p_retry_count INT,
    OUT p_expires_in_seconds INT
)
BEGIN
    DECLARE v_expires_at TIMESTAMP;
    DECLARE v_is_used BOOLEAN;
    
    SET p_is_valid = FALSE;
    SET p_retry_count = 0;
    SET p_expires_in_seconds = 0;
    
    SELECT retry_count, expires_at, is_used
    INTO p_retry_count, v_expires_at, v_is_used
    FROM otp_requests
    WHERE identifier = p_identifier
    ORDER BY created_at DESC
    LIMIT 1;
    
    IF v_expires_at IS NOT NULL AND v_is_used = FALSE AND NOW() < v_expires_at AND p_retry_count < 3 THEN
        SET p_is_valid = TRUE;
        SET p_expires_in_seconds = TIMESTAMPDIFF(SECOND, NOW(), v_expires_at);
    END IF;
END$$

-- ============================================
-- Cleanup Expired OTPs
-- ============================================
DROP PROCEDURE IF EXISTS sp_cleanup_expired_otps$$
CREATE PROCEDURE sp_cleanup_expired_otps()
BEGIN
    DECLARE v_deleted_count INT;
    
    DELETE FROM otp_requests
    WHERE expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
    
    SET v_deleted_count = ROW_COUNT();
    
    SELECT v_deleted_count as deleted_count;
END$$

DELIMITER ;
