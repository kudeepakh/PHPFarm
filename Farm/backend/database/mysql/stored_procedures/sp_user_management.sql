-- User Management Stored Procedures

DELIMITER $$

-- Get user by email
DROP PROCEDURE IF EXISTS sp_get_user_by_email$$
CREATE PROCEDURE sp_get_user_by_email(
    IN p_email VARCHAR(255),
    IN p_correlation_id VARCHAR(36) DEFAULT NULL
)
BEGIN
    -- Log the operation
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_get_user_by_email', 
                CONCAT('Getting user by email: ', SUBSTRING(p_email, 1, 3), '***'), NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    SELECT * FROM users 
    WHERE email = p_email 
    AND deleted_at IS NULL 
    LIMIT 1;
END$$

-- Create new user
DROP PROCEDURE IF EXISTS sp_create_user$$
CREATE PROCEDURE sp_create_user(
    IN p_user_id VARCHAR(36),
    IN p_email VARCHAR(255),
    IN p_password_hash VARCHAR(255),
    IN p_first_name VARCHAR(100),
    IN p_last_name VARCHAR(100),
    IN p_phone VARCHAR(20),
    IN p_status VARCHAR(20),
    IN p_correlation_id VARCHAR(36) DEFAULT NULL
)
BEGIN
    -- Log the operation
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_create_user', 
                CONCAT('Creating user: ', SUBSTRING(p_email, 1, 3), '***'), NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    INSERT INTO users (
        id, 
        email, 
        password_hash, 
        first_name, 
        last_name, 
        phone, 
        status,
        email_verified,
        created_at,
        updated_at
    ) VALUES (
        p_user_id,
        p_email,
        p_password_hash,
        p_first_name,
        p_last_name,
        p_phone,
        COALESCE(p_status, 'active'),
        0,
        CURRENT_TIMESTAMP,
        CURRENT_TIMESTAMP
    );
    
    SELECT p_user_id as id;
END$$

-- Update user
CREATE PROCEDURE sp_update_user(
    IN p_user_id VARCHAR(36),
    IN p_first_name VARCHAR(100),
    IN p_last_name VARCHAR(100),
    IN p_phone VARCHAR(20),
    IN p_status VARCHAR(20)
)
BEGIN
    UPDATE users 
    SET 
        first_name = COALESCE(p_first_name, first_name),
        last_name = COALESCE(p_last_name, last_name),
        phone = COALESCE(p_phone, phone),
        status = COALESCE(p_status, status),
        updated_at = CURRENT_TIMESTAMP
    WHERE id = p_user_id
    AND deleted_at IS NULL;
    
    SELECT ROW_COUNT() as affected_rows;
END$$

DELIMITER ;
