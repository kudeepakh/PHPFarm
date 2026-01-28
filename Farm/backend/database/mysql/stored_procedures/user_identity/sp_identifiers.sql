-- =============================================
-- User Identifier Management Stored Procedures
-- =============================================

DELIMITER //

-- Find user by any identifier (email, phone, username)
DROP PROCEDURE IF EXISTS sp_find_user_by_identifier//
CREATE PROCEDURE sp_find_user_by_identifier(
    IN p_identifier_value VARCHAR(255),
    IN p_identifier_type ENUM('email', 'phone', 'username')
)
BEGIN
    IF p_identifier_type = 'email' THEN
        SELECT * FROM users WHERE email = p_identifier_value AND deleted_at IS NULL;
    ELSEIF p_identifier_type = 'phone' THEN
        SELECT * FROM users WHERE phone = p_identifier_value AND deleted_at IS NULL;
    ELSEIF p_identifier_type = 'username' THEN
        SELECT * FROM users WHERE username = p_identifier_value AND deleted_at IS NULL;
    ELSE
        -- Check user_identifiers table for other types
        SELECT u.* 
        FROM users u
        INNER JOIN user_identifiers ui ON u.id = ui.user_id
        WHERE ui.identifier_value = p_identifier_value 
        AND ui.identifier_type = p_identifier_type
        AND u.deleted_at IS NULL;
    END IF;
END//

-- Add identifier to user
DROP PROCEDURE IF EXISTS sp_add_identifier_to_user//
CREATE PROCEDURE sp_add_identifier_to_user(
    IN p_identifier_id VARCHAR(36),
    IN p_user_id VARCHAR(36),
    IN p_identifier_type ENUM('email', 'phone', 'username', 'oauth_google', 'oauth_github'),
    IN p_identifier_value VARCHAR(255),
    IN p_is_primary TINYINT(1),
    IN p_is_verified TINYINT(1)
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- If setting as primary, unset other primary identifiers of same type
    IF p_is_primary = 1 THEN
        UPDATE user_identifiers 
        SET is_primary = 0 
        WHERE user_id = p_user_id AND identifier_type = p_identifier_type;
    END IF;
    
    -- Insert new identifier
    INSERT INTO user_identifiers (
        identifier_id, user_id, identifier_type, identifier_value, 
        is_primary, is_verified, verified_at
    ) VALUES (
        p_identifier_id, p_user_id, p_identifier_type, p_identifier_value,
        p_is_primary, p_is_verified, IF(p_is_verified = 1, NOW(), NULL)
    );
    
    COMMIT;
END//

-- Verify identifier
DROP PROCEDURE IF EXISTS sp_verify_identifier//
CREATE PROCEDURE sp_verify_identifier(
    IN p_user_id VARCHAR(36),
    IN p_identifier_type ENUM('email', 'phone', 'username', 'oauth_google', 'oauth_github'),
    IN p_identifier_value VARCHAR(255)
)
BEGIN
    -- Update user_identifiers table
    UPDATE user_identifiers
    SET is_verified = 1, verified_at = NOW()
    WHERE user_id = p_user_id 
    AND identifier_type = p_identifier_type
    AND identifier_value = p_identifier_value;
    
    -- Update users table for email/phone
    IF p_identifier_type = 'email' THEN
        UPDATE users SET email_verified = 1 WHERE id = p_user_id;
    ELSEIF p_identifier_type = 'phone' THEN
        UPDATE users SET phone_verified = 1 WHERE id = p_user_id;
    END IF;
END//

-- Get all identifiers for a user
DROP PROCEDURE IF EXISTS sp_get_user_identifiers//
CREATE PROCEDURE sp_get_user_identifiers(
    IN p_user_id VARCHAR(36)
)
BEGIN
    SELECT 
        identifier_id,
        user_id,
        identifier_type,
        identifier_value,
        is_primary,
        is_verified,
        verified_at,
        created_at
    FROM user_identifiers
    WHERE user_id = p_user_id
    ORDER BY is_primary DESC, created_at ASC;
END//

-- Remove identifier from user
DROP PROCEDURE IF EXISTS sp_remove_identifier//
CREATE PROCEDURE sp_remove_identifier(
    IN p_user_id VARCHAR(36),
    IN p_identifier_id VARCHAR(36)
)
BEGIN
    DECLARE v_is_primary TINYINT(1);
    
    -- Check if it's a primary identifier
    SELECT is_primary INTO v_is_primary
    FROM user_identifiers
    WHERE identifier_id = p_identifier_id AND user_id = p_user_id;
    
    -- Don't allow removing primary identifier if it's the only one
    IF v_is_primary = 1 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Cannot remove primary identifier. Set another identifier as primary first.';
    END IF;
    
    DELETE FROM user_identifiers
    WHERE identifier_id = p_identifier_id AND user_id = p_user_id;
END//

-- Set identifier as primary
DROP PROCEDURE IF EXISTS sp_set_primary_identifier//
CREATE PROCEDURE sp_set_primary_identifier(
    IN p_user_id VARCHAR(36),
    IN p_identifier_id VARCHAR(36)
)
BEGIN
    DECLARE v_identifier_type ENUM('email', 'phone', 'username', 'oauth_google', 'oauth_github');
    
    -- Get identifier type
    SELECT identifier_type INTO v_identifier_type
    FROM user_identifiers
    WHERE identifier_id = p_identifier_id AND user_id = p_user_id;
    
    -- Unset other primary identifiers of same type
    UPDATE user_identifiers
    SET is_primary = 0
    WHERE user_id = p_user_id AND identifier_type = v_identifier_type;
    
    -- Set this as primary
    UPDATE user_identifiers
    SET is_primary = 1
    WHERE identifier_id = p_identifier_id AND user_id = p_user_id;
    
    -- Update users table primary_identifier
    UPDATE users
    SET primary_identifier = v_identifier_type
    WHERE id = p_user_id;
END//

DELIMITER ;
