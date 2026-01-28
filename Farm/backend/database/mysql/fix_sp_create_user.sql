-- Fix sp_create_user stored procedure
DELIMITER //

CREATE PROCEDURE sp_create_user(
    IN p_user_id VARCHAR(36),
    IN p_email VARCHAR(255),
    IN p_password_hash VARCHAR(255),
    IN p_first_name VARCHAR(100),
    IN p_last_name VARCHAR(100),
    IN p_phone VARCHAR(20),
    IN p_status VARCHAR(20),
    IN p_correlation_id VARCHAR(36)
)
BEGIN
    INSERT INTO users (
        id, 
        email, 
        password_hash, 
        first_name, 
        last_name, 
        phone, 
        status,
        account_status,
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
        'pending_verification',
        0,
        CURRENT_TIMESTAMP,
        CURRENT_TIMESTAMP
    );
    
    SELECT p_user_id as id;
END//

DELIMITER ;
