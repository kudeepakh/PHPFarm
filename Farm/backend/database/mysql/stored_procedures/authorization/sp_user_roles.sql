-- =============================================
-- User-Role Assignment Stored Procedures
-- =============================================

DELIMITER //

-- Get user's roles
DROP PROCEDURE IF EXISTS sp_get_user_roles//
CREATE PROCEDURE sp_get_user_roles(
    IN p_user_id VARCHAR(36)
)
BEGIN
    SELECT 
        r.role_id,
        r.name,
        r.description,
        r.priority,
        ur.assigned_at,
        ur.assigned_by
    FROM user_roles ur
    INNER JOIN roles r ON ur.role_id = r.role_id
    WHERE ur.user_id = p_user_id
    AND r.deleted_at IS NULL
    ORDER BY r.priority DESC, r.name ASC;
END//

-- Get user's permissions (aggregated from all roles)
DROP PROCEDURE IF EXISTS sp_get_user_permissions//
CREATE PROCEDURE sp_get_user_permissions(
    IN p_user_id VARCHAR(36)
)
BEGIN
    SELECT DISTINCT
        p.permission_id,
        p.name,
        p.description,
        p.resource,
        p.action
    FROM user_roles ur
    INNER JOIN role_permissions rp ON ur.role_id = rp.role_id
    INNER JOIN permissions p ON rp.permission_id = p.permission_id
    WHERE ur.user_id = p_user_id
    AND p.deleted_at IS NULL
    ORDER BY p.resource ASC, p.action ASC;
END//

-- Assign role to user
DROP PROCEDURE IF EXISTS sp_assign_role_to_user//
CREATE PROCEDURE sp_assign_role_to_user(
    IN p_user_id VARCHAR(36),
    IN p_role_id VARCHAR(36)
)
BEGIN
    DECLARE v_user_role_id VARCHAR(36);
    
    -- Generate UUID v7 for user_role_id
    SET v_user_role_id = UUID();
    
    -- Insert if not exists
    INSERT IGNORE INTO user_roles (user_role_id, user_id, role_id)
    VALUES (v_user_role_id, p_user_id, p_role_id);
END//

-- Remove role from user
DROP PROCEDURE IF EXISTS sp_remove_role_from_user//
CREATE PROCEDURE sp_remove_role_from_user(
    IN p_user_id VARCHAR(36),
    IN p_role_id VARCHAR(36)
)
BEGIN
    DELETE FROM user_roles
    WHERE user_id = p_user_id
    AND role_id = p_role_id;
END//

-- Check if user has specific role
DROP PROCEDURE IF EXISTS sp_user_has_role//
CREATE PROCEDURE sp_user_has_role(
    IN p_user_id VARCHAR(36),
    IN p_role_name VARCHAR(50),
    OUT p_has_role TINYINT(1)
)
BEGIN
    SELECT COUNT(*) > 0 INTO p_has_role
    FROM user_roles ur
    INNER JOIN roles r ON ur.role_id = r.role_id
    WHERE ur.user_id = p_user_id
    AND r.name = p_role_name
    AND r.deleted_at IS NULL;
END//

-- Check if user has specific permission
DROP PROCEDURE IF EXISTS sp_user_has_permission//
CREATE PROCEDURE sp_user_has_permission(
    IN p_user_id VARCHAR(36),
    IN p_permission_name VARCHAR(100),
    OUT p_has_permission TINYINT(1)
)
BEGIN
    SELECT COUNT(*) > 0 INTO p_has_permission
    FROM user_roles ur
    INNER JOIN role_permissions rp ON ur.role_id = rp.role_id
    INNER JOIN permissions p ON rp.permission_id = p.permission_id
    WHERE ur.user_id = p_user_id
    AND p.name = p_permission_name
    AND p.deleted_at IS NULL;
END//

DELIMITER ;
