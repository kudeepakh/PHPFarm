-- Fix identifier column names in stored procedures
-- The table uses 'identifier_id' not 'id'

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_check_identifier_exists$$
CREATE PROCEDURE sp_check_identifier_exists(
    IN p_identifier_value VARCHAR(255)
)
BEGIN
    SELECT 
        ui.identifier_id,
        ui.user_id,
        ui.identifier_type,
        ui.is_verified,
        u.status AS user_status
    FROM user_identifiers ui
    INNER JOIN users u ON ui.user_id = u.id
    WHERE ui.identifier_value = p_identifier_value
      AND u.deleted_at IS NULL
    LIMIT 1;
END$$

DROP PROCEDURE IF EXISTS sp_add_user_identifier$$
CREATE PROCEDURE sp_add_user_identifier(
    IN p_identifier_id VARCHAR(36),
    IN p_user_id VARCHAR(36),
    IN p_identifier_type ENUM('email', 'phone', 'username'),
    IN p_identifier_value VARCHAR(255),
    IN p_is_verified BOOLEAN,
    IN p_is_primary BOOLEAN,
    IN p_correlation_id VARCHAR(36)
)
BEGIN
    DECLARE v_existing_count INT DEFAULT 0;
    
    SELECT COUNT(*) INTO v_existing_count
    FROM user_identifiers
    WHERE identifier_type = p_identifier_type 
      AND identifier_value = p_identifier_value;
    
    IF v_existing_count > 0 THEN
        SELECT FALSE AS success, 
               CONCAT(p_identifier_type, ' already registered') AS message,
               NULL AS identifier_id;
    ELSE
        IF p_is_primary THEN
            UPDATE user_identifiers 
            SET is_primary = FALSE 
            WHERE user_id = p_user_id 
              AND identifier_type = p_identifier_type;
        END IF;
        
        INSERT INTO user_identifiers (
            identifier_id,
            user_id,
            identifier_type,
            identifier_value,
            is_verified,
            is_primary,
            verified_at,
            created_at
        ) VALUES (
            p_identifier_id,
            p_user_id,
            p_identifier_type,
            p_identifier_value,
            p_is_verified,
            p_is_primary,
            IF(p_is_verified, CURRENT_TIMESTAMP, NULL),
            CURRENT_TIMESTAMP
        );
        
        SELECT TRUE AS success, 
               'Identifier added' AS message,
               p_identifier_id AS identifier_id;
    END IF;
END$$

DROP PROCEDURE IF EXISTS sp_get_user_identifiers$$
CREATE PROCEDURE sp_get_user_identifiers(
    IN p_user_id VARCHAR(36)
)
BEGIN
    SELECT 
        identifier_id,
        identifier_type,
        identifier_value,
        is_verified,
        is_primary,
        verified_at,
        created_at
    FROM user_identifiers
    WHERE user_id = p_user_id
    ORDER BY is_primary DESC, created_at ASC;
END$$

DROP PROCEDURE IF EXISTS sp_register_with_email$$
CREATE PROCEDURE sp_register_with_email(
    IN p_user_id VARCHAR(36),
    IN p_identifier_id VARCHAR(36),
    IN p_email VARCHAR(255),
    IN p_password_hash VARCHAR(255),
    IN p_first_name VARCHAR(100),
    IN p_last_name VARCHAR(100),
    IN p_correlation_id VARCHAR(36)
)
BEGIN
    DECLARE v_existing_count INT DEFAULT 0;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SELECT FALSE AS success, 'Registration failed' AS message, NULL AS user_id;
    END;
    
    SELECT COUNT(*) INTO v_existing_count
    FROM user_identifiers
    WHERE identifier_type = 'email' 
      AND identifier_value = p_email;
    
    IF v_existing_count > 0 THEN
        SELECT FALSE AS success, 'Email already registered' AS message, NULL AS user_id;
    ELSE
        START TRANSACTION;
        
        INSERT INTO users (
            id, email, password_hash, first_name, last_name, 
            status, account_status, token_version,
            created_at, updated_at
        ) VALUES (
            p_user_id, NULL, p_password_hash, p_first_name, p_last_name,
            'active', 'active', 0,
            CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
        );
        
        INSERT INTO user_identifiers (
            identifier_id, user_id, identifier_type, identifier_value,
            is_verified, is_primary, created_at
        ) VALUES (
            p_identifier_id, p_user_id, 'email', p_email,
            FALSE, TRUE, CURRENT_TIMESTAMP
        );
        
        COMMIT;
        
        SELECT TRUE AS success, 'Registration successful' AS message, p_user_id AS user_id;
    END IF;
END$$

DROP PROCEDURE IF EXISTS sp_register_with_phone$$
CREATE PROCEDURE sp_register_with_phone(
    IN p_user_id VARCHAR(36),
    IN p_identifier_id VARCHAR(36),
    IN p_phone VARCHAR(20),
    IN p_password_hash VARCHAR(255),
    IN p_first_name VARCHAR(100),
    IN p_last_name VARCHAR(100),
    IN p_otp_id VARCHAR(36),
    IN p_otp_hash VARCHAR(255),
    IN p_otp_expires_at DATETIME,
    IN p_correlation_id VARCHAR(36)
)
BEGIN
    DECLARE v_existing_count INT DEFAULT 0;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SELECT FALSE AS success, 'Registration failed' AS message, NULL AS user_id, NULL AS otp_id;
    END;
    
    SELECT COUNT(*) INTO v_existing_count
    FROM user_identifiers
    WHERE identifier_type = 'phone' 
      AND identifier_value = p_phone;
    
    IF v_existing_count > 0 THEN
        SELECT FALSE AS success, 'Phone already registered' AS message, NULL AS user_id, NULL AS otp_id;
    ELSE
        START TRANSACTION;
        
        INSERT INTO users (
            id, email, password_hash, first_name, last_name, 
            status, account_status, token_version,
            created_at, updated_at
        ) VALUES (
            p_user_id, NULL, p_password_hash, p_first_name, p_last_name,
            'inactive', 'pending_verification', 0,
            CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
        );
        
        INSERT INTO user_identifiers (
            identifier_id, user_id, identifier_type, identifier_value,
            is_verified, is_primary, created_at
        ) VALUES (
            p_identifier_id, p_user_id, 'phone', p_phone,
            FALSE, TRUE, CURRENT_TIMESTAMP
        );
        
        INSERT INTO otp_verifications (
            id, identifier, identifier_type, otp_hash, purpose, 
            attempts, verified, expires_at, created_at
        ) VALUES (
            p_otp_id, p_phone, 'phone', p_otp_hash, 'phone_registration',
            0, FALSE, p_otp_expires_at, CURRENT_TIMESTAMP
        );
        
        COMMIT;
        
        SELECT TRUE AS success, 'Registration initiated' AS message, 
               p_user_id AS user_id, p_otp_id AS otp_id;
    END IF;
END$$

DROP PROCEDURE IF EXISTS sp_verify_identifier$$
CREATE PROCEDURE sp_verify_identifier(
    IN p_identifier_type ENUM('email', 'phone', 'username'),
    IN p_identifier_value VARCHAR(255),
    IN p_correlation_id VARCHAR(36)
)
BEGIN
    DECLARE v_user_id VARCHAR(36);
    
    SELECT user_id INTO v_user_id
    FROM user_identifiers
    WHERE identifier_type = p_identifier_type 
      AND identifier_value = p_identifier_value;
    
    IF v_user_id IS NULL THEN
        SELECT FALSE AS success, 'Identifier not found' AS message, NULL AS user_id;
    ELSE
        UPDATE user_identifiers
        SET is_verified = TRUE,
            verified_at = CURRENT_TIMESTAMP
        WHERE identifier_type = p_identifier_type 
          AND identifier_value = p_identifier_value;
        
        UPDATE users
        SET status = 'active',
            account_status = 'active'
        WHERE id = v_user_id
          AND account_status = 'pending_verification';
        
        SELECT TRUE AS success, 'Identifier verified' AS message, v_user_id AS user_id;
    END IF;
END$$

DROP PROCEDURE IF EXISTS sp_link_identifier$$
CREATE PROCEDURE sp_link_identifier(
    IN p_user_id VARCHAR(36),
    IN p_identifier_id VARCHAR(36),
    IN p_identifier_type ENUM('email', 'phone', 'username'),
    IN p_identifier_value VARCHAR(255),
    IN p_correlation_id VARCHAR(36)
)
BEGIN
    DECLARE v_existing_count INT DEFAULT 0;
    
    SELECT COUNT(*) INTO v_existing_count
    FROM user_identifiers
    WHERE identifier_type = p_identifier_type 
      AND identifier_value = p_identifier_value;
    
    IF v_existing_count > 0 THEN
        SELECT FALSE AS success, 
               CONCAT(p_identifier_type, ' already linked to another account') AS message;
    ELSE
        INSERT INTO user_identifiers (
            identifier_id, user_id, identifier_type, identifier_value,
            is_verified, is_primary, created_at
        ) VALUES (
            p_identifier_id, p_user_id, p_identifier_type, p_identifier_value,
            FALSE, FALSE, CURRENT_TIMESTAMP
        );
        
        SELECT TRUE AS success, 'Identifier linked successfully' AS message;
    END IF;
END$$

DELIMITER ;
