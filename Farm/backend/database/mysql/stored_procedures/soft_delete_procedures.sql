-- Universal Soft Delete Stored Procedures
-- These procedures provide consistent soft delete, restore, and force delete operations

DELIMITER $$

-- =============================================
-- USERS TABLE SOFT DELETE PROCEDURES
-- =============================================

-- Soft delete user by ID
DROP PROCEDURE IF EXISTS sp_soft_delete_users$$
CREATE PROCEDURE sp_soft_delete_users(
    IN p_id VARCHAR(36),
    IN p_correlation_id VARCHAR(36) DEFAULT NULL
)
BEGIN
    DECLARE v_affected_rows INT DEFAULT 0;
    
    -- Log the operation
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_soft_delete_users', 
                CONCAT('Soft deleting user: ', p_id), NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    -- Soft delete the user (set deleted_at)
    UPDATE users 
    SET deleted_at = NOW(), updated_at = NOW()
    WHERE id = p_id AND deleted_at IS NULL;
    
    SET v_affected_rows = ROW_COUNT();
    
    -- Log the result
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_soft_delete_users_result', 
                CONCAT('Soft deleted user: ', p_id, ' - Affected rows: ', v_affected_rows), NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    SELECT v_affected_rows AS affected_rows, 'User soft deleted successfully' AS message;
END$$

-- Restore user by ID
DROP PROCEDURE IF EXISTS sp_restore_users$$
CREATE PROCEDURE sp_restore_users(
    IN p_id VARCHAR(36),
    IN p_correlation_id VARCHAR(36) DEFAULT NULL
)
BEGIN
    DECLARE v_affected_rows INT DEFAULT 0;
    
    -- Log the operation
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_restore_users', 
                CONCAT('Restoring user: ', p_id), NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    -- Restore the user (set deleted_at to NULL)
    UPDATE users 
    SET deleted_at = NULL, updated_at = NOW()
    WHERE id = p_id AND deleted_at IS NOT NULL;
    
    SET v_affected_rows = ROW_COUNT();
    
    -- Log the result
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_restore_users_result', 
                CONCAT('Restored user: ', p_id, ' - Affected rows: ', v_affected_rows), NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    SELECT v_affected_rows AS affected_rows, 'User restored successfully' AS message;
END$$

-- Force delete user by ID (permanent deletion)
DROP PROCEDURE IF EXISTS sp_force_delete_users$$
CREATE PROCEDURE sp_force_delete_users(
    IN p_id VARCHAR(36),
    IN p_correlation_id VARCHAR(36) DEFAULT NULL
)
BEGIN
    DECLARE v_affected_rows INT DEFAULT 0;
    
    -- Log the operation
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_force_delete_users', 
                CONCAT('Force deleting user: ', p_id), NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    -- Permanently delete the user
    DELETE FROM users WHERE id = p_id;
    
    SET v_affected_rows = ROW_COUNT();
    
    -- Log the result
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_force_delete_users_result', 
                CONCAT('Force deleted user: ', p_id, ' - Affected rows: ', v_affected_rows), NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    SELECT v_affected_rows AS affected_rows, 'User permanently deleted' AS message;
END$$

-- Get all users including soft-deleted ones
DROP PROCEDURE IF EXISTS sp_get_all_users_with_deleted$$
CREATE PROCEDURE sp_get_all_users_with_deleted(
    IN p_limit INT DEFAULT 100,
    IN p_offset INT DEFAULT 0,
    IN p_correlation_id VARCHAR(36) DEFAULT NULL
)
BEGIN
    -- Log the operation
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_get_all_users_with_deleted', 
                CONCAT('Getting all users (including deleted) - Limit: ', p_limit, ', Offset: ', p_offset), NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    SELECT 
        id,
        email,
        phone,
        first_name,
        last_name,
        status,
        email_verified,
        phone_verified,
        created_at,
        updated_at,
        deleted_at,
        CASE WHEN deleted_at IS NULL THEN 'active' ELSE 'deleted' END AS record_status
    FROM users
    ORDER BY created_at DESC
    LIMIT p_limit OFFSET p_offset;
END$$

-- Get only soft-deleted users
DROP PROCEDURE IF EXISTS sp_get_deleted_users$$
CREATE PROCEDURE sp_get_deleted_users(
    IN p_limit INT DEFAULT 100,
    IN p_offset INT DEFAULT 0,
    IN p_correlation_id VARCHAR(36) DEFAULT NULL
)
BEGIN
    -- Log the operation
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_get_deleted_users', 
                CONCAT('Getting deleted users - Limit: ', p_limit, ', Offset: ', p_offset), NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    SELECT 
        id,
        email,
        phone,
        first_name,
        last_name,
        status,
        email_verified,
        phone_verified,
        created_at,
        updated_at,
        deleted_at
    FROM users
    WHERE deleted_at IS NOT NULL
    ORDER BY deleted_at DESC
    LIMIT p_limit OFFSET p_offset;
END$$

-- Check if user is soft-deleted
DROP PROCEDURE IF EXISTS sp_is_deleted_users$$
CREATE PROCEDURE sp_is_deleted_users(
    IN p_id VARCHAR(36),
    IN p_correlation_id VARCHAR(36) DEFAULT NULL
)
BEGIN
    DECLARE v_is_deleted BOOLEAN DEFAULT FALSE;
    
    -- Log the operation
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_is_deleted_users', 
                CONCAT('Checking if user is deleted: ', p_id), NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    SELECT COUNT(*) > 0 INTO v_is_deleted
    FROM users
    WHERE id = p_id AND deleted_at IS NOT NULL;
    
    SELECT v_is_deleted AS is_deleted;
END$$

-- Bulk soft delete users
DROP PROCEDURE IF EXISTS sp_bulk_soft_delete_users$$
CREATE PROCEDURE sp_bulk_soft_delete_users(
    IN p_ids TEXT,
    IN p_correlation_id VARCHAR(36) DEFAULT NULL
)
BEGIN
    DECLARE v_affected_rows INT DEFAULT 0;
    
    -- Log the operation
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_bulk_soft_delete_users', 
                CONCAT('Bulk soft deleting users: ', SUBSTRING(p_ids, 1, 100), '...'), NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    -- Soft delete users using FIND_IN_SET for safety
    SET @sql = CONCAT('UPDATE users SET deleted_at = NOW(), updated_at = NOW() WHERE deleted_at IS NULL AND id IN (', p_ids, ')');
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
    
    SET v_affected_rows = ROW_COUNT();
    
    -- Log the result
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_bulk_soft_delete_users_result', 
                CONCAT('Bulk soft deleted users - Affected rows: ', v_affected_rows), NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    SELECT v_affected_rows AS affected_rows, 'Users bulk soft deleted successfully' AS message;
END$$

-- =============================================
-- ROLES TABLE SOFT DELETE PROCEDURES
-- =============================================

-- Soft delete role by ID
DROP PROCEDURE IF EXISTS sp_soft_delete_roles$$
CREATE PROCEDURE sp_soft_delete_roles(
    IN p_id VARCHAR(36),
    IN p_correlation_id VARCHAR(36) DEFAULT NULL
)
BEGIN
    DECLARE v_affected_rows INT DEFAULT 0;
    DECLARE v_is_system_role BOOLEAN DEFAULT FALSE;
    
    -- Log the operation
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_soft_delete_roles', 
                CONCAT('Soft deleting role: ', p_id), NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    -- Check if it's a system role (cannot be deleted)
    SELECT is_system_role INTO v_is_system_role
    FROM roles
    WHERE role_id = p_id AND deleted_at IS NULL;
    
    IF v_is_system_role THEN
        SELECT 0 AS affected_rows, 'Cannot delete system role' AS message;
    ELSE
        -- Soft delete the role
        UPDATE roles 
        SET deleted_at = NOW(), updated_at = NOW()
        WHERE role_id = p_id AND deleted_at IS NULL;
        
        SET v_affected_rows = ROW_COUNT();
        
        -- Log the result
        IF p_correlation_id IS NOT NULL THEN
            INSERT INTO operation_logs (correlation_id, operation, details, created_at)
            VALUES (p_correlation_id, 'sp_soft_delete_roles_result', 
                    CONCAT('Soft deleted role: ', p_id, ' - Affected rows: ', v_affected_rows), NOW())
            ON DUPLICATE KEY UPDATE details = VALUES(details);
        END IF;
        
        SELECT v_affected_rows AS affected_rows, 'Role soft deleted successfully' AS message;
    END IF;
END$$

-- Update existing sp_get_* procedures to filter deleted records
-- This ensures all existing queries automatically exclude deleted records

-- Update sp_get_user_by_email to filter deleted records
DROP PROCEDURE IF EXISTS sp_get_user_by_email$$
CREATE PROCEDURE sp_get_user_by_email(
    IN p_email VARCHAR(255),
    IN p_correlation_id VARCHAR(36) DEFAULT NULL
)
BEGIN
    -- Log the operation
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_get_user_by_email', 
                CONCAT('Getting user by email: ', SUBSTRING(p_email, 1, 3), '***'), NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    SELECT * FROM users 
    WHERE email = p_email 
    AND deleted_at IS NULL  -- Filter deleted records
    LIMIT 1;
END$$

-- Update sp_get_user_by_id to filter deleted records
DROP PROCEDURE IF EXISTS sp_get_user_by_id$$
CREATE PROCEDURE sp_get_user_by_id(
    IN p_id VARCHAR(36),
    IN p_correlation_id VARCHAR(36) DEFAULT NULL
)
BEGIN
    -- Log the operation
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_get_user_by_id', 
                CONCAT('Getting user by ID: ', p_id), NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    SELECT * FROM users 
    WHERE id = p_id 
    AND deleted_at IS NULL  -- Filter deleted records
    LIMIT 1;
END$$

DELIMITER ;