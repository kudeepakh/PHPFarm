DELIMITER $$

-- Create User (via Email + Password)
CREATE PROCEDURE IF NOT EXISTS sp_create_user(
    IN p_user_id VARCHAR(36),
    IN p_email VARCHAR(255),
    IN p_password_hash VARCHAR(255),
    IN p_first_name VARCHAR(100),
    IN p_last_name VARCHAR(100)
)
BEGIN
    DECLARE v_sqlstate CHAR(5);
    DECLARE v_message TEXT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1 v_sqlstate = RETURNED_SQLSTATE, v_message = MESSAGE_TEXT;
        ROLLBACK;
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Failed to create user';
    END;

    START TRANSACTION;

    INSERT INTO users (id, email, password_hash, first_name, last_name, status, email_verified, token_version)
    VALUES (p_user_id, p_email, p_password_hash, p_first_name, p_last_name, 'active', FALSE, 0);

    COMMIT;
    
    SELECT id, email, first_name, last_name, status, token_version, created_at 
    FROM users 
    WHERE id = p_user_id;
END$$

-- Get User by Email
CREATE PROCEDURE IF NOT EXISTS sp_get_user_by_email(
    IN p_email VARCHAR(255)
)
BEGIN
        SELECT id, email, phone, password_hash, first_name, last_name, 
            status, email_verified, phone_verified, token_version, last_login_at, created_at
    FROM users
    WHERE email COLLATE utf8mb4_unicode_ci = p_email COLLATE utf8mb4_unicode_ci
      AND deleted_at IS NULL;
END$$

-- Get User by Phone
CREATE PROCEDURE IF NOT EXISTS sp_get_user_by_phone(
    IN p_phone VARCHAR(20)
)
BEGIN
    SELECT id, email, phone, password_hash, first_name, last_name, 
           status, email_verified, phone_verified, token_version, last_login_at, created_at
    FROM users
    WHERE phone COLLATE utf8mb4_unicode_ci = p_phone COLLATE utf8mb4_unicode_ci
      AND deleted_at IS NULL;
END$$

-- Get User by ID
CREATE PROCEDURE IF NOT EXISTS sp_get_user_by_id(
    IN p_user_id VARCHAR(36)
)
BEGIN
        SELECT id, email, phone, first_name, last_name, 
            status, email_verified, phone_verified, token_version, last_login_at, created_at
    FROM users
    WHERE id COLLATE utf8mb4_unicode_ci = p_user_id COLLATE utf8mb4_unicode_ci AND deleted_at IS NULL;
END$$

-- Update User Last Login
CREATE PROCEDURE IF NOT EXISTS sp_update_user_last_login(
    IN p_user_id VARCHAR(36)
)
BEGIN
    UPDATE users
    SET last_login_at = CURRENT_TIMESTAMP
    WHERE id COLLATE utf8mb4_unicode_ci = p_user_id COLLATE utf8mb4_unicode_ci;
END$$

-- Verify User Email
CREATE PROCEDURE IF NOT EXISTS sp_verify_user_email(
    IN p_user_id VARCHAR(36)
)
BEGIN
    UPDATE users
    SET email_verified = TRUE
    WHERE id = p_user_id;
END$$

-- Get All Users (with pagination)
CREATE PROCEDURE IF NOT EXISTS sp_get_all_users(
    IN p_limit INT,
    IN p_offset INT
)
BEGIN
    SELECT 
        u.id, 
        -- Get email from users table or user_identifiers (prefer verified)
        COALESCE(u.email, 
            (SELECT identifier_value FROM user_identifiers 
             WHERE user_id = u.id AND identifier_type = 'email' 
             ORDER BY is_verified DESC, created_at ASC LIMIT 1)
        ) as email,
        -- Get phone from users table or user_identifiers (prefer verified)
        COALESCE(u.phone,
            (SELECT identifier_value FROM user_identifiers 
             WHERE user_id = u.id AND identifier_type = 'phone' 
             ORDER BY is_verified DESC, created_at ASC LIMIT 1)
        ) as phone,
        u.first_name, 
        u.last_name, 
        u.status,
        u.account_status,
        u.email_verified,
        u.phone_verified,
        u.last_login_at,
        u.created_at,
        u.updated_at,
        -- Get all roles (comma-separated, ordered by priority)
        (SELECT GROUP_CONCAT(r.name ORDER BY r.priority DESC SEPARATOR ', ') 
         FROM user_roles ur 
         JOIN roles r ON ur.role_id = r.role_id 
         WHERE ur.user_id = u.id) as role_name
    FROM users u
    WHERE u.deleted_at IS NULL
    ORDER BY u.created_at DESC
    LIMIT p_limit OFFSET p_offset;
END$$

-- Count Users
CREATE PROCEDURE IF NOT EXISTS sp_count_users()
BEGIN
    SELECT COUNT(*) as total
    FROM users
    WHERE deleted_at IS NULL;
END$$

-- Update User Password (and optionally increment token_version)
CREATE PROCEDURE IF NOT EXISTS sp_update_user_password(
    IN p_user_id VARCHAR(36),
    IN p_password_hash VARCHAR(255),
    IN p_increment_token_version TINYINT(1)
)
BEGIN
    UPDATE users
    SET password_hash = p_password_hash,
        token_version = IF(p_increment_token_version = 1, token_version + 1, token_version),
        updated_at = CURRENT_TIMESTAMP
    WHERE id = p_user_id
      AND deleted_at IS NULL;

    SELECT id, email, token_version
    FROM users
    WHERE id = p_user_id;
END$$

-- Create User Session
DROP PROCEDURE IF EXISTS sp_create_user_session$$
CREATE PROCEDURE sp_create_user_session(
    IN p_session_id VARCHAR(36),
    IN p_user_id VARCHAR(36),
    IN p_token_hash VARCHAR(255),
    IN p_refresh_token_hash VARCHAR(255),
    IN p_device_info TEXT,
    IN p_ip_address VARCHAR(45),
    IN p_user_agent TEXT,
    IN p_expires_at DATETIME,
    IN p_refresh_expires_at DATETIME,
    IN p_correlation_id VARCHAR(36) DEFAULT NULL
)
BEGIN
    -- Log the operation
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_create_user_session', 
                CONCAT('Creating session for user: ', p_user_id), NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    INSERT INTO user_sessions (
        id, user_id, token_hash, refresh_token_hash, 
        device_info, ip_address, user_agent, expires_at, refresh_expires_at
    )
    VALUES (
        p_session_id, p_user_id, p_token_hash, p_refresh_token_hash,
        p_device_info, p_ip_address, p_user_agent, p_expires_at, p_refresh_expires_at
    );
    
    SELECT id, user_id, expires_at, created_at
    FROM user_sessions
    WHERE id = p_session_id;
END$$

-- Get active session by access token hash
CREATE PROCEDURE IF NOT EXISTS sp_get_user_session_by_token_hash(
    IN p_token_hash VARCHAR(255)
)
BEGIN
    SELECT id, user_id, token_hash, refresh_token_hash, expires_at, refresh_expires_at, revoked_at
    FROM user_sessions
    WHERE token_hash = p_token_hash
      AND revoked_at IS NULL
      AND expires_at > NOW()
    LIMIT 1;
END$$

-- Get active session by refresh token hash
CREATE PROCEDURE IF NOT EXISTS sp_get_user_session_by_refresh_hash(
    IN p_refresh_token_hash VARCHAR(255)
)
BEGIN
    SELECT id, user_id, token_hash, refresh_token_hash, expires_at, refresh_expires_at, revoked_at
    FROM user_sessions
    WHERE refresh_token_hash = p_refresh_token_hash
      AND revoked_at IS NULL
      AND refresh_expires_at > NOW()
    LIMIT 1;
END$$

-- Rotate session tokens
CREATE PROCEDURE IF NOT EXISTS sp_update_user_session_tokens(
    IN p_session_id VARCHAR(36),
    IN p_token_hash VARCHAR(255),
    IN p_refresh_token_hash VARCHAR(255),
    IN p_expires_at DATETIME,
    IN p_refresh_expires_at DATETIME
)
BEGIN
    UPDATE user_sessions
    SET token_hash = p_token_hash,
        refresh_token_hash = p_refresh_token_hash,
        expires_at = p_expires_at,
        refresh_expires_at = p_refresh_expires_at
    WHERE id = p_session_id
      AND revoked_at IS NULL;
END$$

-- Revoke a single session
CREATE PROCEDURE IF NOT EXISTS sp_revoke_user_session(
    IN p_session_id VARCHAR(36)
)
BEGIN
    UPDATE user_sessions
    SET revoked_at = NOW()
    WHERE id = p_session_id;
END$$

-- Revoke all sessions for a user
CREATE PROCEDURE IF NOT EXISTS sp_revoke_user_sessions_by_user(
    IN p_user_id VARCHAR(36)
)
BEGIN
    UPDATE user_sessions
    SET revoked_at = NOW()
    WHERE user_id = p_user_id
      AND revoked_at IS NULL;
END$$

-- Create OTP
CREATE PROCEDURE IF NOT EXISTS sp_create_otp(
    IN p_otp_id VARCHAR(36),
    IN p_identifier VARCHAR(255),
    IN p_identifier_type ENUM('email', 'phone'),
    IN p_otp_hash VARCHAR(255),
    IN p_purpose ENUM('registration', 'login', 'password_reset', 'verification'),
    IN p_expires_at DATETIME
)
BEGIN
    -- Invalidate previous OTPs for same identifier and purpose
    UPDATE otp_verifications
    SET verified = TRUE
    WHERE identifier = p_identifier 
      AND purpose = p_purpose 
      AND verified = FALSE;

    INSERT INTO otp_verifications (
        id, identifier, identifier_type, otp_hash, purpose, expires_at
    )
    VALUES (
        p_otp_id, p_identifier, p_identifier_type, p_otp_hash, p_purpose, p_expires_at
    );
    
    SELECT id, expires_at, created_at
    FROM otp_verifications
    WHERE id = p_otp_id;
END$$

-- Verify OTP
CREATE PROCEDURE IF NOT EXISTS sp_verify_otp(
    IN p_identifier VARCHAR(255),
    IN p_otp_hash VARCHAR(255),
    IN p_purpose ENUM('registration', 'login', 'password_reset', 'verification')
)
BEGIN
    DECLARE v_otp_id VARCHAR(36);
    DECLARE v_attempts INT;
    DECLARE v_max_attempts INT DEFAULT 3;

    -- Get OTP record
    SELECT id, attempts INTO v_otp_id, v_attempts
    FROM otp_verifications
    WHERE identifier = p_identifier
      AND purpose = p_purpose
      AND verified = FALSE
      AND expires_at > NOW()
      AND otp_hash = p_otp_hash
    LIMIT 1;

    IF v_otp_id IS NOT NULL THEN
        -- OTP is valid
        UPDATE otp_verifications
        SET verified = TRUE
        WHERE id = v_otp_id;
        
        SELECT TRUE as is_valid, 'OTP verified successfully' as message;
    ELSE
        -- Increment attempts
        UPDATE otp_verifications
        SET attempts = attempts + 1
        WHERE identifier = p_identifier
          AND purpose = p_purpose
          AND verified = FALSE
          AND expires_at > NOW();
        
        SELECT FALSE as is_valid, 'Invalid or expired OTP' as message;
    END IF;
END$$

DELIMITER ;
