DELIMITER $$
DROP PROCEDURE IF EXISTS sp_get_user_by_phone$$
CREATE PROCEDURE sp_get_user_by_phone(IN p_phone VARCHAR(20))
BEGIN
    SELECT id, email, phone, password_hash, first_name, last_name, 
           status, email_verified, phone_verified, token_version, last_login_at, created_at
    FROM users
    WHERE phone = p_phone AND deleted_at IS NULL;
END$$
DELIMITER ;
