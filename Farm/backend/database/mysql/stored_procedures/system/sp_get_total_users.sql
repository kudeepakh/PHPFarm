DELIMITER $$

DROP PROCEDURE IF EXISTS sp_get_total_users$$

CREATE PROCEDURE sp_get_total_users()
BEGIN
    -- Count total users in the system
    SELECT COUNT(*) as total_users
    FROM users
    WHERE deleted_at IS NULL;
END$$

DELIMITER ;
