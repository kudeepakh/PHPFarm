-- =============================================
-- Permission Management Stored Procedures
-- =============================================

DELIMITER //

-- Get all permissions (excluding soft-deleted)
DROP PROCEDURE IF EXISTS sp_get_all_permissions//
CREATE PROCEDURE sp_get_all_permissions()
BEGIN
    SELECT 
        permission_id,
        name,
        description,
        resource,
        action,
        created_at,
        updated_at
    FROM permissions
    WHERE deleted_at IS NULL
    ORDER BY resource ASC, action ASC;
END//

-- Get permission by ID
DROP PROCEDURE IF EXISTS sp_get_permission_by_id//
CREATE PROCEDURE sp_get_permission_by_id(
    IN p_permission_id VARCHAR(36)
)
BEGIN
    SELECT 
        permission_id,
        name,
        description,
        resource,
        action,
        created_at,
        updated_at
    FROM permissions
    WHERE permission_id = p_permission_id
    AND deleted_at IS NULL;
END//

-- Get permission by name
DROP PROCEDURE IF EXISTS sp_get_permission_by_name//
CREATE PROCEDURE sp_get_permission_by_name(
    IN p_name VARCHAR(100)
)
BEGIN
    SELECT 
        permission_id,
        name,
        description,
        resource,
        action,
        created_at,
        updated_at
    FROM permissions
    WHERE name = p_name
    AND deleted_at IS NULL;
END//

-- Create permission
DROP PROCEDURE IF EXISTS sp_create_permission//
CREATE PROCEDURE sp_create_permission(
    IN p_permission_id VARCHAR(36),
    IN p_name VARCHAR(100),
    IN p_description VARCHAR(255),
    IN p_resource VARCHAR(50),
    IN p_action VARCHAR(50)
)
BEGIN
    INSERT INTO permissions (permission_id, name, description, resource, action)
    VALUES (p_permission_id, p_name, p_description, p_resource, p_action);
END//

-- Update permission
DROP PROCEDURE IF EXISTS sp_update_permission//
CREATE PROCEDURE sp_update_permission(
    IN p_permission_id VARCHAR(36),
    IN p_name VARCHAR(100),
    IN p_description VARCHAR(255),
    IN p_resource VARCHAR(50),
    IN p_action VARCHAR(50)
)
BEGIN
    UPDATE permissions
    SET 
        name = COALESCE(p_name, name),
        description = COALESCE(p_description, description),
        resource = COALESCE(p_resource, resource),
        action = COALESCE(p_action, action)
    WHERE permission_id = p_permission_id
    AND deleted_at IS NULL;
END//

-- Soft delete permission
DROP PROCEDURE IF EXISTS sp_soft_delete_permission//
CREATE PROCEDURE sp_soft_delete_permission(
    IN p_permission_id VARCHAR(36),
    IN p_deleted_by VARCHAR(36)
)
BEGIN
    UPDATE permissions
    SET deleted_at = NOW()
    WHERE permission_id = p_permission_id;
END//

-- Get permissions by resource
DROP PROCEDURE IF EXISTS sp_get_permissions_by_resource//
CREATE PROCEDURE sp_get_permissions_by_resource(
    IN p_resource VARCHAR(50)
)
BEGIN
    SELECT 
        permission_id,
        name,
        description,
        resource,
        action,
        created_at,
        updated_at
    FROM permissions
    WHERE resource = p_resource
    AND deleted_at IS NULL
    ORDER BY action ASC;
END//

DELIMITER ;
