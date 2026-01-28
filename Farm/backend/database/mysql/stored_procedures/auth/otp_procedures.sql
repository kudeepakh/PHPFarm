DELIMITER $$

-- Create OTP record
DROP PROCEDURE IF EXISTS sp_create_otp$$
CREATE PROCEDURE sp_create_otp(
    IN p_otp_id VARCHAR(36),
    IN p_identifier VARCHAR(255),
    IN p_identifier_type ENUM('email', 'phone'),
    IN p_otp_hash VARCHAR(255),
    IN p_purpose VARCHAR(50),
    IN p_expires_at DATETIME,
    IN p_correlation_id VARCHAR(36) DEFAULT NULL
)
BEGIN
    -- Log the operation start
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_create_otp', 
                CONCAT('Creating OTP for ', p_identifier, ' purpose: ', p_purpose), NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    -- Invalidate previous OTPs for same identifier and purpose
    UPDATE otp_verifications
    SET verified = TRUE
    WHERE identifier = p_identifier
      AND purpose = p_purpose
      AND verified = FALSE;
    
    -- Insert new OTP record
    INSERT INTO otp_verifications (
        id, identifier, identifier_type, otp_hash, purpose, 
        attempts, verified, expires_at, created_at
    ) VALUES (
        p_otp_id, p_identifier, p_identifier_type, p_otp_hash, p_purpose,
        0, FALSE, p_expires_at, NOW()
    );
    
    SELECT 'success' AS status, 'OTP created successfully' AS message, p_otp_id AS otp_id;
END$$

-- Verify OTP with retry limit
DROP PROCEDURE IF EXISTS sp_verify_otp_with_retry$$
CREATE PROCEDURE sp_verify_otp_with_retry(
    IN p_identifier VARCHAR(255),
    IN p_otp_hash VARCHAR(255),
    IN p_purpose VARCHAR(50),
    IN p_max_retries INT,
    IN p_ip_address VARCHAR(45),
    IN p_user_agent VARCHAR(500),
    IN p_correlation_id VARCHAR(36) DEFAULT NULL
)
BEGIN
    DECLARE v_otp_id VARCHAR(36);
    DECLARE v_stored_hash VARCHAR(255);
    DECLARE v_attempts INT;
    DECLARE v_verified BOOLEAN;
    DECLARE v_expires_at DATETIME;
    DECLARE v_is_valid BOOLEAN DEFAULT FALSE;
    DECLARE v_message VARCHAR(255);
    
    -- Log the operation start
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_verify_otp_with_retry', 
                CONCAT('Verifying OTP for ', p_identifier, ' purpose: ', p_purpose), NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    -- Get the latest unverified OTP
    SELECT id, otp_hash, attempts, verified, expires_at
    INTO v_otp_id, v_stored_hash, v_attempts, v_verified, v_expires_at
    FROM otp_verifications
    WHERE identifier = p_identifier
      AND purpose = p_purpose
      AND verified = FALSE
    ORDER BY created_at DESC
    LIMIT 1;
    
    -- Check if OTP exists
    IF v_otp_id IS NULL THEN
        SET v_message = 'OTP not found or already verified';
        IF p_correlation_id IS NOT NULL THEN
            INSERT INTO operation_logs (correlation_id, operation, details, created_at)
            VALUES (p_correlation_id, 'sp_verify_otp_with_retry_error', v_message, NOW())
            ON DUPLICATE KEY UPDATE details = VALUES(details);
        END IF;
        SELECT FALSE AS is_valid, v_message AS message;
    -- Check if expired
    ELSEIF NOW() > v_expires_at THEN
        SET v_message = 'OTP has expired';
        IF p_correlation_id IS NOT NULL THEN
            INSERT INTO operation_logs (correlation_id, operation, details, created_at)
            VALUES (p_correlation_id, 'sp_verify_otp_with_retry_error', v_message, NOW())
            ON DUPLICATE KEY UPDATE details = VALUES(details);
        END IF;
        SELECT FALSE AS is_valid, v_message AS message;
    -- Check retry limit
    ELSEIF v_attempts >= p_max_retries THEN
        SET v_message = 'Maximum retry attempts exceeded';
        IF p_correlation_id IS NOT NULL THEN
            INSERT INTO operation_logs (correlation_id, operation, details, created_at)
            VALUES (p_correlation_id, 'sp_verify_otp_with_retry_error', v_message, NOW())
            ON DUPLICATE KEY UPDATE details = VALUES(details);
        END IF;
        SELECT FALSE AS is_valid, v_message AS message;
    -- Verify OTP hash
    ELSEIF v_stored_hash = p_otp_hash THEN
        -- Mark as verified
        UPDATE otp_verifications
        SET verified = TRUE,
            verified_at = NOW(),
            verified_ip = p_ip_address,
            verified_user_agent = p_user_agent
        WHERE id = v_otp_id;
        
        SET v_message = 'OTP verified successfully';
        IF p_correlation_id IS NOT NULL THEN
            INSERT INTO operation_logs (correlation_id, operation, details, created_at)
            VALUES (p_correlation_id, 'sp_verify_otp_with_retry_success', v_message, NOW())
            ON DUPLICATE KEY UPDATE details = VALUES(details);
        END IF;
        SELECT TRUE AS is_valid, v_message AS message;
    ELSE
        -- Increment attempts
        UPDATE otp_verifications
        SET attempts = attempts + 1
        WHERE id = v_otp_id;
        
        SET v_attempts = v_attempts + 1;
        SET v_message = CONCAT('Invalid OTP. Attempts: ', v_attempts, '/', p_max_retries);
        IF p_correlation_id IS NOT NULL THEN
            INSERT INTO operation_logs (correlation_id, operation, details, created_at)
            VALUES (p_correlation_id, 'sp_verify_otp_with_retry_attempt', v_message, NOW())
            ON DUPLICATE KEY UPDATE details = VALUES(details);
        END IF;
        SELECT FALSE AS is_valid, v_message AS message;
    END IF;
END$$

-- Cleanup expired OTPs (maintenance task)
DROP PROCEDURE IF EXISTS sp_cleanup_expired_otps$$
CREATE PROCEDURE sp_cleanup_expired_otps()
BEGIN
    DELETE FROM otp_verifications
    WHERE expires_at < NOW() - INTERVAL 24 HOUR;
    
    SELECT ROW_COUNT() AS deleted_count;
END$$

-- Get OTP status (for admin/debugging)
DROP PROCEDURE IF EXISTS sp_get_otp_status$$
CREATE PROCEDURE sp_get_otp_status(
    IN p_identifier VARCHAR(255),
    IN p_purpose VARCHAR(50)
)
BEGIN
    SELECT 
        id,
        identifier,
        identifier_type,
        purpose,
        attempts,
        verified,
        expires_at,
        created_at,
        verified_at,
        CASE
            WHEN verified = TRUE THEN 'VERIFIED'
            WHEN NOW() > expires_at THEN 'EXPIRED'
            WHEN attempts >= 3 THEN 'LOCKED'
            ELSE 'ACTIVE'
        END AS status
    FROM otp_verifications
    WHERE identifier = p_identifier
      AND purpose = p_purpose
    ORDER BY created_at DESC
    LIMIT 5;
END$$

DELIMITER ;
