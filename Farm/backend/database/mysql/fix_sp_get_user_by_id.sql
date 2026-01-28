DELIMITER $$

DROP PROCEDURE IF EXISTS sp_get_user_by_id$$

CREATE PROCEDURE sp_get_user_by_id(
    IN p_user_id VARCHAR(36)
)
BEGIN
    SELECT id, email, phone, first_name, last_name, 
        status, email_verified, phone_verified, token_version, last_login_at, created_at
    FROM users
    WHERE id COLLATE utf8mb4_unicode_ci = p_user_id COLLATE utf8mb4_unicode_ci 
      AND deleted_at IS NULL;
END$$

DELIMITER ;
