DELIMITER $$

DROP PROCEDURE IF EXISTS sp_count_users$$

CREATE PROCEDURE sp_count_users()
BEGIN
    SELECT COUNT(*) as total
    FROM users
    WHERE deleted_at IS NULL;
END$$

DELIMITER ;
