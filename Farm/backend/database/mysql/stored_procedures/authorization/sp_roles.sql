-- =============================================
-- Role Management Stored Procedures
-- =============================================

DELIMITER //

-- Get all roles (excluding soft-deleted)
DROP PROCEDURE IF EXISTS sp_get_all_roles//
CREATE PROCEDURE sp_get_all_roles(
    IN p_correlation_id VARCHAR(36) DEFAULT NULL
)
BEGIN
    -- Log the operation
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_get_all_roles', 'Getting all active roles', NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    SELECT 
        role_id,
        name,
        description,
        priority,
        is_system_role,
        created_at,
        updated_at
    FROM roles
    WHERE deleted_at IS NULL
    ORDER BY priority DESC, name ASC;
END//

-- Get role by ID
DROP PROCEDURE IF EXISTS sp_get_role_by_id//
CREATE PROCEDURE sp_get_role_by_id(
    IN p_role_id VARCHAR(36),
    IN p_correlation_id VARCHAR(36) DEFAULT NULL
)
BEGIN
    -- Log the operation
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_get_role_by_id', CONCAT('Getting role by ID: ', p_role_id), NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    SELECT 
        role_id,
        name,
        description,
        priority,
        is_system_role,
        created_at,
        updated_at
    FROM roles
    WHERE role_id = p_role_id
    AND deleted_at IS NULL;
END//

-- Get role by name
DROP PROCEDURE IF EXISTS sp_get_role_by_name//
CREATE PROCEDURE sp_get_role_by_name(
    IN p_name VARCHAR(50),
    IN p_correlation_id VARCHAR(36) DEFAULT NULL
)
BEGIN
    -- Log the operation
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_get_role_by_name', CONCAT('Getting role by name: ', p_name), NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    SELECT 
        role_id,
        name,
        description,
        priority,
        is_system_role,
        created_at,
        updated_at
    FROM roles
    WHERE name COLLATE utf8mb4_unicode_ci = p_name COLLATE utf8mb4_unicode_ci
    AND deleted_at IS NULL;
END//

-- Create role
DROP PROCEDURE IF EXISTS sp_create_role//
CREATE PROCEDURE sp_create_role(
    IN p_role_id VARCHAR(36),
    IN p_name VARCHAR(50),
    IN p_description VARCHAR(255),
    IN p_priority INT
)
BEGIN
    INSERT INTO roles (role_id, name, description, priority)
    VALUES (p_role_id, p_name, p_description, p_priority);
END//

-- Update role
DROP PROCEDURE IF EXISTS sp_update_role//
CREATE PROCEDURE sp_update_role(
    IN p_role_id VARCHAR(36),
    IN p_name VARCHAR(50),
    IN p_description VARCHAR(255),
    IN p_priority INT
)
BEGIN
    UPDATE roles
    SET 
        name = COALESCE(p_name, name),
        description = COALESCE(p_description, description),
        priority = COALESCE(p_priority, priority)
    WHERE role_id = p_role_id
    AND deleted_at IS NULL;
END//

-- Soft delete role
DROP PROCEDURE IF EXISTS sp_soft_delete_role//
CREATE PROCEDURE sp_soft_delete_role(
    IN p_role_id VARCHAR(36),
    IN p_deleted_by VARCHAR(36)
)
BEGIN
    -- Check if it's a system role
    DECLARE v_is_system TINYINT(1);
    
    SELECT is_system_role INTO v_is_system
    FROM roles
    WHERE role_id = p_role_id;
    
    IF v_is_system = 1 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Cannot delete system role';
    END IF;
    
    UPDATE roles
    SET deleted_at = NOW()
    WHERE role_id = p_role_id;
END//

DELIMITER ;
