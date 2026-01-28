DELIMITER $$

DROP PROCEDURE IF EXISTS sp_get_all_users$$

CREATE PROCEDURE sp_get_all_users(
    IN p_limit INT,
    IN p_offset INT
)
BEGIN
    -- Get users with pagination, including identifiers and roles
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
    
    -- Get total count
    SELECT COUNT(*) as total
    FROM users
    WHERE deleted_at IS NULL;
END$$

DELIMITER ;
