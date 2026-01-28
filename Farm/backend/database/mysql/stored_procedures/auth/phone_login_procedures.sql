-- Phone OTP Login Stored Procedures
-- These procedures handle phone-based user login with OTP verification

DELIMITER $$

-- Initiate phone login (step 1 - sends OTP to existing user)
DROP PROCEDURE IF EXISTS sp_initiate_phone_login$$
CREATE PROCEDURE sp_initiate_phone_login(
    IN p_phone VARCHAR(20),
    IN p_otp_id VARCHAR(36),
    IN p_otp_hash VARCHAR(255),
    IN p_otp_expires_at DATETIME,
    IN p_correlation_id VARCHAR(36) DEFAULT NULL
)
BEGIN
    DECLARE v_user_id VARCHAR(36);
    DECLARE v_user_status VARCHAR(20);
    DECLARE v_phone_verified BOOLEAN;
    DECLARE v_message VARCHAR(255);
    
    -- Log the operation
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_initiate_phone_login', 
                CONCAT('Phone login initiated for: ', SUBSTRING(p_phone, 1, 3), '***'), NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    -- Check if user exists with this phone number
    SELECT id, status, phone_verified 
    INTO v_user_id, v_user_status, v_phone_verified
    FROM users
    WHERE phone = p_phone AND deleted_at IS NULL;
    
    -- Check if user exists
    IF v_user_id IS NULL THEN
        SET v_message = 'Phone number not registered';
        IF p_correlation_id IS NOT NULL THEN
            INSERT INTO operation_logs (correlation_id, operation, details, created_at)
            VALUES (p_correlation_id, 'sp_initiate_phone_login_error', v_message, NOW())
            ON DUPLICATE KEY UPDATE details = VALUES(details);
        END IF;
        SELECT FALSE AS success, v_message AS message, NULL AS user_id, NULL AS otp_id;
        
    -- Check if user account is active
    ELSEIF v_user_status NOT IN ('active', 'pending_email_verification') THEN
        SET v_message = CONCAT('Account is ', v_user_status, ' and cannot login');
        IF p_correlation_id IS NOT NULL THEN
            INSERT INTO operation_logs (correlation_id, operation, details, created_at)
            VALUES (p_correlation_id, 'sp_initiate_phone_login_error', 
                    CONCAT(v_message, ' - User ID: ', v_user_id), NOW())
            ON DUPLICATE KEY UPDATE details = VALUES(details);
        END IF;
        SELECT FALSE AS success, v_message AS message, v_user_id AS user_id, NULL AS otp_id;
        
    -- Check if phone is verified
    ELSEIF v_phone_verified = FALSE THEN
        SET v_message = 'Phone number not verified, please complete registration first';
        IF p_correlation_id IS NOT NULL THEN
            INSERT INTO operation_logs (correlation_id, operation, details, created_at)
            VALUES (p_correlation_id, 'sp_initiate_phone_login_error', 
                    CONCAT(v_message, ' - User ID: ', v_user_id), NOW())
            ON DUPLICATE KEY UPDATE details = VALUES(details);
        END IF;
        SELECT FALSE AS success, v_message AS message, v_user_id AS user_id, NULL AS otp_id;
        
    ELSE
        -- Invalidate any existing login OTPs for this phone
        UPDATE otp_verifications
        SET verified = TRUE
        WHERE identifier = p_phone
          AND purpose = 'phone_login'
          AND verified = FALSE;
        
        -- Create new OTP record
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
            'phone_login',
            0,
            FALSE,
            p_otp_expires_at,
            NOW()
        );
        
        SET v_message = 'Login OTP sent to phone number';
        IF p_correlation_id IS NOT NULL THEN
            INSERT INTO operation_logs (correlation_id, operation, details, created_at)
            VALUES (p_correlation_id, 'sp_initiate_phone_login_success', 
                    CONCAT(v_message, ' - User ID: ', v_user_id), NOW())
            ON DUPLICATE KEY UPDATE details = VALUES(details);
        END IF;
        
        SELECT TRUE AS success, v_message AS message, v_user_id AS user_id, p_otp_id AS otp_id;
    END IF;
END$$

-- Complete phone login (step 2 - verifies OTP and returns user info for JWT creation)
DROP PROCEDURE IF EXISTS sp_login_with_phone_otp$$
CREATE PROCEDURE sp_login_with_phone_otp(
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
    DECLARE v_email VARCHAR(255);
    DECLARE v_first_name VARCHAR(100);
    DECLARE v_last_name VARCHAR(100);
    DECLARE v_status VARCHAR(20);
    DECLARE v_message VARCHAR(255);
    
    -- Log the operation
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_login_with_phone_otp', 
                CONCAT('Phone login verification for: ', SUBSTRING(p_phone, 1, 3), '***'), NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    -- Get the latest unverified OTP for phone login
    SELECT id, otp_hash, attempts, verified, expires_at
    INTO v_otp_id, v_stored_hash, v_attempts, v_verified, v_expires_at
    FROM otp_verifications
    WHERE identifier = p_phone
      AND purpose = 'phone_login'
      AND verified = FALSE
    ORDER BY created_at DESC
    LIMIT 1;
    
    -- Get user details for this phone
    SELECT id, email, first_name, last_name, status
    INTO v_user_id, v_email, v_first_name, v_last_name, v_status
    FROM users
    WHERE phone = p_phone AND deleted_at IS NULL;
    
    -- Check if OTP exists
    IF v_otp_id IS NULL THEN
        SET v_message = 'Login OTP not found or already used';
        IF p_correlation_id IS NOT NULL THEN
            INSERT INTO operation_logs (correlation_id, operation, details, created_at)
            VALUES (p_correlation_id, 'sp_login_with_phone_otp_error', v_message, NOW())
            ON DUPLICATE KEY UPDATE details = VALUES(details);
        END IF;
        SELECT FALSE AS success, v_message AS message, NULL AS user_data;
        
    -- Check if expired
    ELSEIF NOW() > v_expires_at THEN
        SET v_message = 'Login OTP has expired';
        IF p_correlation_id IS NOT NULL THEN
            INSERT INTO operation_logs (correlation_id, operation, details, created_at)
            VALUES (p_correlation_id, 'sp_login_with_phone_otp_error', v_message, NOW())
            ON DUPLICATE KEY UPDATE details = VALUES(details);
        END IF;
        SELECT FALSE AS success, v_message AS message, NULL AS user_data;
        
    -- Check retry limit
    ELSEIF v_attempts >= p_max_retries THEN
        SET v_message = 'Maximum login attempts exceeded';
        IF p_correlation_id IS NOT NULL THEN
            INSERT INTO operation_logs (correlation_id, operation, details, created_at)
            VALUES (p_correlation_id, 'sp_login_with_phone_otp_error', v_message, NOW())
            ON DUPLICATE KEY UPDATE details = VALUES(details);
        END IF;
        SELECT FALSE AS success, v_message AS message, NULL AS user_data;
        
    -- Verify OTP hash
    ELSEIF v_stored_hash = p_otp_hash THEN
        -- Mark OTP as verified
        UPDATE otp_verifications
        SET verified = TRUE,
            verified_at = NOW(),
            verified_ip = p_ip_address,
            verified_user_agent = p_user_agent
        WHERE id = v_otp_id;
        
        -- Update user last login
        UPDATE users
        SET last_login_at = NOW(),
            updated_at = NOW()
        WHERE id = v_user_id;
        
        SET v_message = 'Phone login successful';
        IF p_correlation_id IS NOT NULL THEN
            INSERT INTO operation_logs (correlation_id, operation, details, created_at)
            VALUES (p_correlation_id, 'sp_login_with_phone_otp_success', 
                    CONCAT(v_message, ' - User ID: ', v_user_id), NOW())
            ON DUPLICATE KEY UPDATE details = VALUES(details);
        END IF;
        
        -- Return user data for JWT creation
        SELECT TRUE AS success, 
               v_message AS message, 
               JSON_OBJECT(
                   'user_id', v_user_id,
                   'email', v_email,
                   'phone', p_phone,
                   'first_name', v_first_name,
                   'last_name', v_last_name,
                   'status', v_status,
                   'login_method', 'phone_otp'
               ) AS user_data;
               
    ELSE
        -- Increment attempts
        UPDATE otp_verifications
        SET attempts = attempts + 1
        WHERE id = v_otp_id;
        
        SET v_attempts = v_attempts + 1;
        SET v_message = CONCAT('Invalid login OTP. Attempts: ', v_attempts, '/', p_max_retries);
        IF p_correlation_id IS NOT NULL THEN
            INSERT INTO operation_logs (correlation_id, operation, details, created_at)
            VALUES (p_correlation_id, 'sp_login_with_phone_otp_attempt', v_message, NOW())
            ON DUPLICATE KEY UPDATE details = VALUES(details);
        END IF;
        
        SELECT FALSE AS success, v_message AS message, NULL AS user_data;
    END IF;
END$$

DELIMITER ;