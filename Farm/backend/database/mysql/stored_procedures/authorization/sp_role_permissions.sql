-- =============================================
-- Role-Permission Assignment Stored Procedures
-- =============================================

DELIMITER //

-- Get permissions for a role
DROP PROCEDURE IF EXISTS sp_get_role_permissions//
CREATE PROCEDURE sp_get_role_permissions(
    IN p_role_id VARCHAR(36)
)
BEGIN
    SELECT 
        p.permission_id,
        p.name,
        p.description,
        p.resource,
        p.action,
        rp.created_at AS assigned_at
    FROM role_permissions rp
    INNER JOIN permissions p ON rp.permission_id = p.permission_id
    WHERE rp.role_id = p_role_id
    AND p.deleted_at IS NULL
    ORDER BY p.resource ASC, p.action ASC;
END//

-- Assign permission to role
DROP PROCEDURE IF EXISTS sp_assign_permission_to_role//
CREATE PROCEDURE sp_assign_permission_to_role(
    IN p_role_id VARCHAR(36),
    IN p_permission_id VARCHAR(36)
)
BEGIN
    DECLARE v_role_permission_id VARCHAR(36);
    
    -- Generate UUID v7 for role_permission_id
    SET v_role_permission_id = UUID();
    
    -- Insert if not exists
    INSERT IGNORE INTO role_permissions (role_permission_id, role_id, permission_id)
    VALUES (v_role_permission_id, p_role_id, p_permission_id);
END//

-- Remove permission from role
DROP PROCEDURE IF EXISTS sp_remove_permission_from_role//
CREATE PROCEDURE sp_remove_permission_from_role(
    IN p_role_id VARCHAR(36),
    IN p_permission_id VARCHAR(36)
)
BEGIN
    DELETE FROM role_permissions
    WHERE role_id = p_role_id
    AND permission_id = p_permission_id;
END//

DELIMITER ;
