-- Roles and Permissions Management Stored Procedures

-- Drop existing procedures first
DROP PROCEDURE IF EXISTS sp_list_roles;
DROP PROCEDURE IF EXISTS sp_count_roles;
DROP PROCEDURE IF EXISTS sp_get_role_by_name;
DROP PROCEDURE IF EXISTS sp_create_role;
DROP PROCEDURE IF EXISTS sp_get_role_by_id;
DROP PROCEDURE IF EXISTS sp_get_role_permissions;
DROP PROCEDURE IF EXISTS sp_update_role;
DROP PROCEDURE IF EXISTS sp_soft_delete_role;
DROP PROCEDURE IF EXISTS sp_assign_permission_to_role;
DROP PROCEDURE IF EXISTS sp_remove_permission_from_role;
DROP PROCEDURE IF EXISTS sp_sync_role_permissions;
DROP PROCEDURE IF EXISTS sp_list_permissions;
DROP PROCEDURE IF EXISTS sp_count_permissions;
DROP PROCEDURE IF EXISTS sp_get_permission_by_name;
DROP PROCEDURE IF EXISTS sp_create_permission;
DROP PROCEDURE IF EXISTS sp_get_permission_by_id;
DROP PROCEDURE IF EXISTS sp_update_permission;
DROP PROCEDURE IF EXISTS sp_soft_delete_permission;
DROP PROCEDURE IF EXISTS sp_upsert_permission;

DELIMITER $$

-- List all roles with pagination
CREATE PROCEDURE sp_list_roles(
    IN p_limit INT,
    IN p_offset INT
)
BEGIN
    SELECT * FROM roles 
    WHERE deleted_at IS NULL 
    ORDER BY created_at DESC
    LIMIT p_limit OFFSET p_offset;
END$$

-- Count total roles
CREATE PROCEDURE sp_count_roles()
BEGIN
    SELECT COUNT(*) as total FROM roles WHERE deleted_at IS NULL;
END$$

-- Get role by ID
CREATE PROCEDURE sp_get_role_by_id(
    IN p_role_id VARCHAR(36)
)
BEGIN
    SELECT * FROM roles 
    WHERE role_id = p_role_id 
    AND deleted_at IS NULL;
END$$

-- Get role by name
CREATE PROCEDURE sp_get_role_by_name(
    IN p_name VARCHAR(100)
)
BEGIN
    SELECT * FROM roles 
    WHERE name = p_name 
    AND deleted_at IS NULL;
END$$

-- Create new role
CREATE PROCEDURE sp_create_role(
    IN p_role_id VARCHAR(36),
    IN p_name VARCHAR(100),
    IN p_description TEXT
)
BEGIN
    INSERT INTO roles (role_id, name, description, created_at, updated_at)
    VALUES (p_role_id, p_name, p_description, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);
    
    SELECT p_role_id as role_id;
END$$

-- Update role
CREATE PROCEDURE sp_update_role(
    IN p_role_id VARCHAR(36),
    IN p_name VARCHAR(100),
    IN p_description TEXT
)
BEGIN
    UPDATE roles 
    SET 
        name = p_name,
        description = p_description,
        updated_at = CURRENT_TIMESTAMP
    WHERE role_id = p_role_id
    AND deleted_at IS NULL;
    
    SELECT ROW_COUNT() as affected_rows;
END$$

-- Soft delete role
CREATE PROCEDURE sp_soft_delete_role(
    IN p_role_id VARCHAR(36)
)
BEGIN
    -- Append timestamp to name to free it for reuse and set deleted_at
    UPDATE roles 
    SET name = CONCAT(name, '_deleted_', UNIX_TIMESTAMP()),
        deleted_at = CURRENT_TIMESTAMP
    WHERE role_id = p_role_id
    AND deleted_at IS NULL;
    
    SELECT ROW_COUNT() as affected_rows;
END$$

-- List all permissions with pagination
CREATE PROCEDURE sp_list_permissions(
    IN p_limit INT,
    IN p_offset INT
)
BEGIN
    SELECT * FROM permissions 
    ORDER BY resource, action
    LIMIT p_limit OFFSET p_offset;
END$$

-- Count total permissions
CREATE PROCEDURE sp_count_permissions()
BEGIN
    SELECT COUNT(*) as total FROM permissions;
END$$

-- Get permissions for a role
CREATE PROCEDURE sp_get_role_permissions(
    IN p_role_id VARCHAR(36)
)
BEGIN
    SELECT p.* 
    FROM permissions p
    INNER JOIN role_permissions rp ON p.permission_id = rp.permission_id
    WHERE rp.role_id = p_role_id;
END$$

-- Assign permission to role
CREATE PROCEDURE sp_assign_permission_to_role(
    IN p_role_id VARCHAR(36),
    IN p_permission_id VARCHAR(36)
)
BEGIN
    INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
    VALUES (p_role_id, p_permission_id, CURRENT_TIMESTAMP);
    
    SELECT ROW_COUNT() as affected_rows;
END$$

-- Remove permission from role
CREATE PROCEDURE sp_remove_permission_from_role(
    IN p_role_id VARCHAR(36),
    IN p_permission_id VARCHAR(36)
)
BEGIN
    DELETE FROM role_permissions 
    WHERE role_id = p_role_id 
    AND permission_id = p_permission_id;
    
    SELECT ROW_COUNT() as affected_rows;
END$$

-- Sync role permissions (replace all)
CREATE PROCEDURE sp_sync_role_permissions(
    IN p_role_id VARCHAR(36),
    IN p_permission_ids TEXT
)
BEGIN
    -- Delete existing permissions
    DELETE FROM role_permissions WHERE role_id = p_role_id;
    
    -- Insert new permissions (p_permission_ids is comma-separated)
    -- This will be handled in application code due to dynamic SQL complexity
    
    SELECT 1 as success;
END$$

-- Create or update permission
CREATE PROCEDURE sp_upsert_permission(
    IN p_permission_id VARCHAR(36),
    IN p_resource VARCHAR(100),
    IN p_action VARCHAR(100),
    IN p_description TEXT
)
BEGIN
    INSERT INTO permissions (id, resource, action, description, created_at, updated_at)
    VALUES (p_permission_id, p_resource, p_action, p_description, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
    ON DUPLICATE KEY UPDATE
        description = p_description,
        updated_at = CURRENT_TIMESTAMP;
    
    SELECT p_permission_id as id;
END$$

DELIMITER ;
