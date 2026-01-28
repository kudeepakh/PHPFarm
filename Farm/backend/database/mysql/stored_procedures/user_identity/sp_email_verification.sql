-- =============================================
-- Email Verification Stored Procedures
-- =============================================

DELIMITER //

-- Create email verification token
DROP PROCEDURE IF EXISTS sp_create_email_verification_token//
CREATE PROCEDURE sp_create_email_verification_token(
    IN p_token_id VARCHAR(36),
    IN p_user_id VARCHAR(36),
    IN p_token VARCHAR(255),
    IN p_email VARCHAR(255),
    IN p_expires_at DATETIME,
    IN p_ip_address VARCHAR(45)
)
BEGIN
    -- Invalidate old unused tokens for this user+email
    UPDATE email_verification_tokens
    SET used_at = NOW()
    WHERE user_id = p_user_id 
    AND email = p_email
    AND used_at IS NULL;
    
    -- Insert new token
    INSERT INTO email_verification_tokens (
        token_id, user_id, token, email, expires_at, ip_address
    ) VALUES (
        p_token_id, p_user_id, p_token, p_email, p_expires_at, p_ip_address
    );
END//

-- Verify email token and mark as used
DROP PROCEDURE IF EXISTS sp_verify_email_token//
CREATE PROCEDURE sp_verify_email_token(
    IN p_token VARCHAR(255),
    OUT p_is_valid TINYINT(1),
    OUT p_user_id VARCHAR(36),
    OUT p_email VARCHAR(255),
    OUT p_message VARCHAR(255)
)
BEGIN
    DECLARE v_expires_at DATETIME;
    DECLARE v_used_at DATETIME;
    
    -- Get token details
    SELECT user_id, email, expires_at, used_at
    INTO p_user_id, p_email, v_expires_at, v_used_at
    FROM email_verification_tokens
    WHERE token = p_token
    LIMIT 1;
    
    -- Check token validity
    IF p_user_id IS NULL THEN
        SET p_is_valid = 0;
        SET p_message = 'Invalid verification token';
    ELSEIF v_used_at IS NOT NULL THEN
        SET p_is_valid = 0;
        SET p_message = 'Token already used';
    ELSEIF v_expires_at < NOW() THEN
        SET p_is_valid = 0;
        SET p_message = 'Token expired';
    ELSE
        SET p_is_valid = 1;
        SET p_message = 'Token valid';
        
        -- Mark token as used
        UPDATE email_verification_tokens
        SET used_at = NOW()
        WHERE token = p_token;
    END IF;
END//

-- Mark email as verified
DROP PROCEDURE IF EXISTS sp_mark_email_verified//
CREATE PROCEDURE sp_mark_email_verified(
    IN p_user_id VARCHAR(36),
    IN p_email VARCHAR(255)
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Update users table
    UPDATE users
    SET email_verified = 1,
        account_status = IF(account_status = 'pending_verification', 'active', account_status)
    WHERE id = p_user_id AND email = p_email;
    
    -- Update user_identifiers table if exists
    UPDATE user_identifiers
    SET is_verified = 1, verified_at = NOW()
    WHERE user_id = p_user_id 
    AND identifier_type = 'email' 
    AND identifier_value = p_email;
    
    COMMIT;
END//

-- Get pending verification status
DROP PROCEDURE IF EXISTS sp_get_pending_verification//
CREATE PROCEDURE sp_get_pending_verification(
    IN p_user_id VARCHAR(36)
)
BEGIN
    SELECT 
        token_id,
        email,
        expires_at,
        created_at,
        CASE 
            WHEN expires_at < NOW() THEN 1
            ELSE 0
        END AS is_expired
    FROM email_verification_tokens
    WHERE user_id = p_user_id
    AND used_at IS NULL
    ORDER BY created_at DESC
    LIMIT 1;
END//

-- Check if email is verified
DROP PROCEDURE IF EXISTS sp_is_email_verified//
CREATE PROCEDURE sp_is_email_verified(
    IN p_user_id VARCHAR(36),
    OUT p_is_verified TINYINT(1)
)
BEGIN
    SELECT email_verified INTO p_is_verified
    FROM users
    WHERE user_id = p_user_id;
END//

-- Clean up expired tokens (maintenance)
DROP PROCEDURE IF EXISTS sp_cleanup_expired_verification_tokens//
CREATE PROCEDURE sp_cleanup_expired_verification_tokens()
BEGIN
    DELETE FROM email_verification_tokens
    WHERE expires_at < NOW() - INTERVAL 7 DAY
    AND used_at IS NULL;
END//

DELIMITER ;
