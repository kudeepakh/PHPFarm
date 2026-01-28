-- Phone OTP Registration Stored Procedures
-- These procedures handle phone-based user registration with OTP verification

DELIMITER $$

-- Register user with phone number (step 1 - sends OTP)
DROP PROCEDURE IF EXISTS sp_register_user_with_phone$$
CREATE PROCEDURE sp_register_user_with_phone(
    IN p_phone VARCHAR(20),
    IN p_first_name VARCHAR(100),
    IN p_last_name VARCHAR(100),
    IN p_password_hash VARCHAR(255),
    IN p_otp_id VARCHAR(36),
    IN p_otp_hash VARCHAR(255),
    IN p_otp_expires_at DATETIME,
    IN p_correlation_id VARCHAR(36) DEFAULT NULL
)
BEGIN
    DECLARE v_existing_phone_count INT DEFAULT 0;
    DECLARE v_user_id VARCHAR(36);
    DECLARE v_message VARCHAR(255);
    
    -- Log the operation
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_register_user_with_phone', 
                CONCAT('Phone registration started for: ', SUBSTRING(p_phone, 1, 3), '***'), NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    -- Check if phone already exists
    SELECT COUNT(*) INTO v_existing_phone_count
    FROM users
    WHERE phone = p_phone AND deleted_at IS NULL;
    
    IF v_existing_phone_count > 0 THEN
        SET v_message = 'Phone number already registered';
        IF p_correlation_id IS NOT NULL THEN
            INSERT INTO operation_logs (correlation_id, operation, details, created_at)
            VALUES (p_correlation_id, 'sp_register_user_with_phone_error', v_message, NOW())
            ON DUPLICATE KEY UPDATE details = VALUES(details);
        END IF;
        SELECT FALSE AS success, v_message AS message, NULL AS user_id, NULL AS otp_id;
    ELSE
        -- Generate new user ID
        SET v_user_id = UUID();
        
        -- Create user with pending status (not verified yet)
        INSERT INTO users (
            id, 
            phone, 
            password_hash, 
            first_name, 
            last_name, 
            status,
            phone_verified,
            created_at,
            updated_at
        ) VALUES (
            v_user_id,
            p_phone,
            p_password_hash,
            p_first_name,
            p_last_name,
            'pending_phone_verification',
            0,
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP
        );
        
        -- Create OTP record
        INSERT INTO otp_verifications (
            id, 
            identifier, 
            identifier_type, 
            otp_hash, 
            purpose, 
            attempts, 
            verified, 
            expires_at, 
            created_at
        ) VALUES (
            p_otp_id,
            p_phone,
            'phone',
            p_otp_hash,
            'phone_registration',
            0,
            FALSE,
            p_otp_expires_at,
            NOW()
        );
        
        SET v_message = 'Registration initiated, OTP sent to phone';
        IF p_correlation_id IS NOT NULL THEN
            INSERT INTO operation_logs (correlation_id, operation, details, created_at)
            VALUES (p_correlation_id, 'sp_register_user_with_phone_success', 
                    CONCAT(v_message, ' - User ID: ', v_user_id), NOW())
            ON DUPLICATE KEY UPDATE details = VALUES(details);
        END IF;
        
        SELECT TRUE AS success, v_message AS message, v_user_id AS user_id, p_otp_id AS otp_id;
    END IF;
END$$

-- Verify phone registration OTP (step 2 - completes registration)
DROP PROCEDURE IF EXISTS sp_verify_phone_registration$$
CREATE PROCEDURE sp_verify_phone_registration(
    IN p_phone VARCHAR(20),
    IN p_otp_hash VARCHAR(255),
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
    DECLARE v_user_id VARCHAR(36);
    DECLARE v_message VARCHAR(255);
    
    -- Log the operation
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_verify_phone_registration', 
                CONCAT('Verifying phone registration for: ', SUBSTRING(p_phone, 1, 3), '***'), NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    -- Get the latest unverified OTP for phone registration
    SELECT id, otp_hash, attempts, verified, expires_at
    INTO v_otp_id, v_stored_hash, v_attempts, v_verified, v_expires_at
    FROM otp_verifications
    WHERE identifier = p_phone
      AND purpose = 'phone_registration'
      AND verified = FALSE
    ORDER BY created_at DESC
    LIMIT 1;
    
    -- Get user ID for this phone
    SELECT id INTO v_user_id
    FROM users
    WHERE phone = p_phone AND deleted_at IS NULL;
    
    -- Check if OTP exists
    IF v_otp_id IS NULL THEN
        SET v_message = 'Registration OTP not found or already verified';
        IF p_correlation_id IS NOT NULL THEN
            INSERT INTO operation_logs (correlation_id, operation, details, created_at)
            VALUES (p_correlation_id, 'sp_verify_phone_registration_error', v_message, NOW())
            ON DUPLICATE KEY UPDATE details = VALUES(details);
        END IF;
        SELECT FALSE AS success, v_message AS message, NULL AS user_id;
        
    -- Check if expired
    ELSEIF NOW() > v_expires_at THEN
        SET v_message = 'Registration OTP has expired';
        IF p_correlation_id IS NOT NULL THEN
            INSERT INTO operation_logs (correlation_id, operation, details, created_at)
            VALUES (p_correlation_id, 'sp_verify_phone_registration_error', v_message, NOW())
            ON DUPLICATE KEY UPDATE details = VALUES(details);
        END IF;
        SELECT FALSE AS success, v_message AS message, NULL AS user_id;
        
    -- Check retry limit
    ELSEIF v_attempts >= p_max_retries THEN
        SET v_message = 'Maximum retry attempts exceeded for registration';
        IF p_correlation_id IS NOT NULL THEN
            INSERT INTO operation_logs (correlation_id, operation, details, created_at)
            VALUES (p_correlation_id, 'sp_verify_phone_registration_error', v_message, NOW())
            ON DUPLICATE KEY UPDATE details = VALUES(details);
        END IF;
        SELECT FALSE AS success, v_message AS message, NULL AS user_id;
        
    -- Verify OTP hash
    ELSEIF v_stored_hash = p_otp_hash THEN
        -- Mark OTP as verified
        UPDATE otp_verifications
        SET verified = TRUE,
            verified_at = NOW(),
            verified_ip = p_ip_address,
            verified_user_agent = p_user_agent
        WHERE id = v_otp_id;
        
        -- Update user status to active and mark phone as verified
        UPDATE users
        SET status = 'active',
            phone_verified = TRUE,
            phone_verified_at = NOW(),
            updated_at = NOW()
        WHERE id = v_user_id;
        
        SET v_message = 'Phone registration completed successfully';
        IF p_correlation_id IS NOT NULL THEN
            INSERT INTO operation_logs (correlation_id, operation, details, created_at)
            VALUES (p_correlation_id, 'sp_verify_phone_registration_success', 
                    CONCAT(v_message, ' - User ID: ', v_user_id), NOW())
            ON DUPLICATE KEY UPDATE details = VALUES(details);
        END IF;
        
        SELECT TRUE AS success, v_message AS message, v_user_id AS user_id;
        
    ELSE
        -- Increment attempts
        UPDATE otp_verifications
        SET attempts = attempts + 1
        WHERE id = v_otp_id;
        
        SET v_attempts = v_attempts + 1;
        SET v_message = CONCAT('Invalid registration OTP. Attempts: ', v_attempts, '/', p_max_retries);
        IF p_correlation_id IS NOT NULL THEN
            INSERT INTO operation_logs (correlation_id, operation, details, created_at)
            VALUES (p_correlation_id, 'sp_verify_phone_registration_attempt', v_message, NOW())
            ON DUPLICATE KEY UPDATE details = VALUES(details);
        END IF;
        
        SELECT FALSE AS success, v_message AS message, NULL AS user_id;
    END IF;
END$$

DELIMITER ;