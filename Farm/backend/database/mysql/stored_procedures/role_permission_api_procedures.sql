-- Create missing stored procedures for Role and Permission APIs

-- sp_get_all_roles with pagination
DROP PROCEDURE IF EXISTS sp_get_all_roles;
DELIMITER //
CREATE PROCEDURE sp_get_all_roles(
    IN p_per_page INT,
    IN p_offset INT
)
BEGIN
    SELECT
        r.role_id,
        r.name,
        r.description,
        r.priority,
        r.is_system_role,
        r.created_at,
        r.updated_at,
        COALESCE(COUNT(DISTINCT ur.user_id), 0) AS user_count,
        COALESCE(COUNT(DISTINCT rp.permission_id), 0) AS permissions_count
    FROM roles r
    LEFT JOIN user_roles ur ON r.role_id = ur.role_id
    LEFT JOIN role_permissions rp ON r.role_id = rp.role_id
    WHERE r.deleted_at IS NULL
    GROUP BY r.role_id, r.name, r.description, r.priority, r.is_system_role, r.created_at, r.updated_at
    ORDER BY r.priority ASC, r.name ASC
    LIMIT p_per_page OFFSET p_offset;
END //
DELIMITER ;

-- sp_search_roles
DROP PROCEDURE IF EXISTS sp_search_roles;
DELIMITER //
CREATE PROCEDURE sp_search_roles(
    IN p_search VARCHAR(255),
    IN p_per_page INT,
    IN p_offset INT
)
BEGIN
    SELECT
        r.role_id,
        r.name,
        r.description,
        r.priority,
        r.is_system_role,
        r.created_at,
        r.updated_at,
        COALESCE(COUNT(DISTINCT ur.user_id), 0) AS user_count,
        COALESCE(COUNT(DISTINCT rp.permission_id), 0) AS permissions_count
    FROM roles r
    LEFT JOIN user_roles ur ON r.role_id = ur.role_id
    LEFT JOIN role_permissions rp ON r.role_id = rp.role_id
    WHERE r.deleted_at IS NULL
      AND (r.name LIKE CONCAT('%', p_search, '%') OR r.description LIKE CONCAT('%', p_search, '%'))
    GROUP BY r.role_id, r.name, r.description, r.priority, r.is_system_role, r.created_at, r.updated_at
    ORDER BY r.priority ASC, r.name ASC
    LIMIT p_per_page OFFSET p_offset;
END //
DELIMITER ;

-- sp_count_roles_search
DROP PROCEDURE IF EXISTS sp_count_roles_search;
DELIMITER //
CREATE PROCEDURE sp_count_roles_search(
    IN p_search VARCHAR(255)
)
BEGIN
    SELECT COUNT(*) as total
    FROM roles
    WHERE deleted_at IS NULL
      AND (name LIKE CONCAT('%', p_search, '%') OR description LIKE CONCAT('%', p_search, '%'));
END //
DELIMITER ;

-- sp_delete_role (soft delete)
DROP PROCEDURE IF EXISTS sp_delete_role;
DELIMITER //
CREATE PROCEDURE sp_delete_role(
    IN p_role_id VARCHAR(36)
)
BEGIN
    UPDATE roles
    SET deleted_at = NOW()
    WHERE role_id = p_role_id;
END //
DELIMITER ;

-- sp_remove_all_role_permissions
DROP PROCEDURE IF EXISTS sp_remove_all_role_permissions;
DELIMITER //
CREATE PROCEDURE sp_remove_all_role_permissions(
    IN p_role_id VARCHAR(36)
)
BEGIN
    DELETE FROM role_permissions
    WHERE role_id = p_role_id;
END //
DELIMITER ;

-- sp_get_all_permissions with pagination
DROP PROCEDURE IF EXISTS sp_get_all_permissions;
DELIMITER //
CREATE PROCEDURE sp_get_all_permissions(
    IN p_per_page INT,
    IN p_offset INT
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
    WHERE deleted_at IS NULL
    ORDER BY resource ASC, action ASC
    LIMIT p_per_page OFFSET p_offset;
END //
DELIMITER ;

-- sp_search_permissions
DROP PROCEDURE IF EXISTS sp_search_permissions;
DELIMITER //
CREATE PROCEDURE sp_search_permissions(
    IN p_search VARCHAR(255),
    IN p_per_page INT,
    IN p_offset INT
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
    WHERE deleted_at IS NULL
      AND (name LIKE CONCAT('%', p_search, '%') OR description LIKE CONCAT('%', p_search, '%'))
    ORDER BY resource ASC, action ASC
    LIMIT p_per_page OFFSET p_offset;
END //
DELIMITER ;

-- sp_count_permissions_search
DROP PROCEDURE IF EXISTS sp_count_permissions_search;
DELIMITER //
CREATE PROCEDURE sp_count_permissions_search(
    IN p_search VARCHAR(255)
)
BEGIN
    SELECT COUNT(*) as total
    FROM permissions
    WHERE deleted_at IS NULL
      AND (name LIKE CONCAT('%', p_search, '%') OR description LIKE CONCAT('%', p_search, '%'));
END //
DELIMITER ;

-- sp_count_permissions_by_resource
DROP PROCEDURE IF EXISTS sp_count_permissions_by_resource;
DELIMITER //
CREATE PROCEDURE sp_count_permissions_by_resource(
    IN p_resource VARCHAR(50)
)
BEGIN
    SELECT COUNT(*) as total
    FROM permissions
    WHERE deleted_at IS NULL
      AND resource = p_resource;
END //
DELIMITER ;

-- sp_get_permission_by_id
DROP PROCEDURE IF EXISTS sp_get_permission_by_id;
DELIMITER //
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
END //
DELIMITER ;

-- sp_create_permission
DROP PROCEDURE IF EXISTS sp_create_permission;
DELIMITER //
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
END //
DELIMITER ;

-- sp_update_permission
DROP PROCEDURE IF EXISTS sp_update_permission;
DELIMITER //
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
    WHERE permission_id = p_permission_id;
END //
DELIMITER ;

-- sp_delete_permission (soft delete)
DROP PROCEDURE IF EXISTS sp_delete_permission;
DELIMITER //
CREATE PROCEDURE sp_delete_permission(
    IN p_permission_id VARCHAR(36)
)
BEGIN
    UPDATE permissions
    SET deleted_at = NOW()
    WHERE permission_id = p_permission_id;
END //
DELIMITER ;
