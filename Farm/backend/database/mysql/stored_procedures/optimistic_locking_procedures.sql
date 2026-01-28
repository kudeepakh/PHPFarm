-- Optimistic Locking Stored Procedures
-- These procedures provide version-based concurrency control to prevent lost updates

DELIMITER $$

-- =============================================
-- USERS TABLE OPTIMISTIC LOCKING PROCEDURES
-- =============================================

-- Update user with optimistic locking
DROP PROCEDURE IF EXISTS sp_update_user_with_locking$$
CREATE PROCEDURE sp_update_user_with_locking(
    IN p_id VARCHAR(36),
    IN p_expected_version INT,
    IN p_first_name VARCHAR(100),
    IN p_last_name VARCHAR(100),
    IN p_phone VARCHAR(20),
    IN p_status VARCHAR(20),
    IN p_correlation_id VARCHAR(36) DEFAULT NULL
)
BEGIN
    DECLARE v_current_version INT;
    DECLARE v_affected_rows INT DEFAULT 0;
    DECLARE v_new_version INT;
    
    -- Log the operation
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_update_user_with_locking', 
                CONCAT('Updating user with locking - ID: ', p_id, ', Expected version: ', p_expected_version), NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    -- Get current version
    SELECT version INTO v_current_version
    FROM users
    WHERE id = p_id AND deleted_at IS NULL;
    
    -- Check if record exists
    IF v_current_version IS NULL THEN
        SELECT 
            0 AS success,
            'User not found or has been deleted' AS message,
            NULL AS current_version,
            NULL AS new_version,
            0 AS affected_rows;
    -- Check version match (optimistic locking)
    ELSEIF v_current_version != p_expected_version THEN
        IF p_correlation_id IS NOT NULL THEN
            INSERT INTO operation_logs (correlation_id, operation, details, created_at)
            VALUES (p_correlation_id, 'sp_update_user_with_locking_conflict', 
                    CONCAT('Version conflict - Expected: ', p_expected_version, ', Current: ', v_current_version), NOW())
            ON DUPLICATE KEY UPDATE details = VALUES(details);
        END IF;
        
        SELECT 
            0 AS success,
            'Version conflict: Record has been modified by another process' AS message,
            v_current_version AS current_version,
            NULL AS new_version,
            0 AS affected_rows;
    ELSE
        -- Version matches, perform update with version increment
        SET v_new_version = v_current_version + 1;
        
        UPDATE users
        SET 
            first_name = COALESCE(p_first_name, first_name),
            last_name = COALESCE(p_last_name, last_name),
            phone = COALESCE(p_phone, phone),
            status = COALESCE(p_status, status),
            version = v_new_version,
            updated_at = NOW()
        WHERE id = p_id 
        AND version = p_expected_version 
        AND deleted_at IS NULL;
        
        SET v_affected_rows = ROW_COUNT();
        
        IF p_correlation_id IS NOT NULL THEN
            INSERT INTO operation_logs (correlation_id, operation, details, created_at)
            VALUES (p_correlation_id, 'sp_update_user_with_locking_success', 
                    CONCAT('User updated successfully - New version: ', v_new_version), NOW())
            ON DUPLICATE KEY UPDATE details = VALUES(details);
        END IF;
        
        SELECT 
            1 AS success,
            'User updated successfully' AS message,
            p_expected_version AS previous_version,
            v_new_version AS new_version,
            v_affected_rows AS affected_rows;
    END IF;
END$$

-- Update user password with optimistic locking
DROP PROCEDURE IF EXISTS sp_update_user_password_with_locking$$
CREATE PROCEDURE sp_update_user_password_with_locking(
    IN p_id VARCHAR(36),
    IN p_expected_version INT,
    IN p_new_password_hash VARCHAR(255),
    IN p_correlation_id VARCHAR(36) DEFAULT NULL
)
BEGIN
    DECLARE v_current_version INT;
    DECLARE v_affected_rows INT DEFAULT 0;
    DECLARE v_new_version INT;
    
    -- Log the operation
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_update_user_password_with_locking', 
                CONCAT('Updating user password with locking - ID: ', p_id), NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    -- Get current version
    SELECT version INTO v_current_version
    FROM users
    WHERE id = p_id AND deleted_at IS NULL;
    
    -- Check if record exists
    IF v_current_version IS NULL THEN
        SELECT 
            0 AS success,
            'User not found or has been deleted' AS message,
            NULL AS current_version,
            NULL AS new_version;
    -- Check version match
    ELSEIF v_current_version != p_expected_version THEN
        SELECT 
            0 AS success,
            'Version conflict: Record has been modified by another process' AS message,
            v_current_version AS current_version,
            NULL AS new_version;
    ELSE
        -- Update password with version increment
        SET v_new_version = v_current_version + 1;
        
        UPDATE users
        SET 
            password_hash = p_new_password_hash,
            version = v_new_version,
            updated_at = NOW()
        WHERE id = p_id 
        AND version = p_expected_version 
        AND deleted_at IS NULL;
        
        SET v_affected_rows = ROW_COUNT();
        
        SELECT 
            1 AS success,
            'Password updated successfully' AS message,
            p_expected_version AS previous_version,
            v_new_version AS new_version;
    END IF;
END$$

-- =============================================
-- ROLES TABLE OPTIMISTIC LOCKING PROCEDURES
-- =============================================

-- Update role with optimistic locking
DROP PROCEDURE IF EXISTS sp_update_role_with_locking$$
CREATE PROCEDURE sp_update_role_with_locking(
    IN p_role_id VARCHAR(36),
    IN p_expected_version INT,
    IN p_name VARCHAR(50),
    IN p_description VARCHAR(255),
    IN p_priority INT,
    IN p_correlation_id VARCHAR(36) DEFAULT NULL
)
BEGIN
    DECLARE v_current_version INT;
    DECLARE v_affected_rows INT DEFAULT 0;
    DECLARE v_new_version INT;
    DECLARE v_is_system_role BOOLEAN DEFAULT FALSE;
    
    -- Log the operation
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_update_role_with_locking', 
                CONCAT('Updating role with locking - ID: ', p_role_id, ', Expected version: ', p_expected_version), NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    -- Get current version and system role status
    SELECT version, is_system_role INTO v_current_version, v_is_system_role
    FROM roles
    WHERE role_id = p_role_id AND deleted_at IS NULL;
    
    -- Check if record exists
    IF v_current_version IS NULL THEN
        SELECT 
            0 AS success,
            'Role not found or has been deleted' AS message,
            NULL AS current_version,
            NULL AS new_version;
    -- Check if it's a system role (restricted updates)
    ELSEIF v_is_system_role AND (p_name IS NOT NULL) THEN
        SELECT 
            0 AS success,
            'Cannot modify name of system role' AS message,
            v_current_version AS current_version,
            NULL AS new_version;
    -- Check version match
    ELSEIF v_current_version != p_expected_version THEN
        SELECT 
            0 AS success,
            'Version conflict: Record has been modified by another process' AS message,
            v_current_version AS current_version,
            NULL AS new_version;
    ELSE
        -- Update role with version increment
        SET v_new_version = v_current_version + 1;
        
        UPDATE roles
        SET 
            name = COALESCE(p_name, name),
            description = COALESCE(p_description, description),
            priority = COALESCE(p_priority, priority),
            version = v_new_version,
            updated_at = NOW()
        WHERE role_id = p_role_id 
        AND version = p_expected_version 
        AND deleted_at IS NULL;
        
        SET v_affected_rows = ROW_COUNT();
        
        SELECT 
            1 AS success,
            'Role updated successfully' AS message,
            p_expected_version AS previous_version,
            v_new_version AS new_version;
    END IF;
END$$

-- =============================================
-- PERMISSIONS TABLE OPTIMISTIC LOCKING PROCEDURES
-- =============================================

-- Update permission with optimistic locking
DROP PROCEDURE IF EXISTS sp_update_permission_with_locking$$
CREATE PROCEDURE sp_update_permission_with_locking(
    IN p_permission_id VARCHAR(36),
    IN p_expected_version INT,
    IN p_description VARCHAR(255),
    IN p_correlation_id VARCHAR(36) DEFAULT NULL
)
BEGIN
    DECLARE v_current_version INT;
    DECLARE v_affected_rows INT DEFAULT 0;
    DECLARE v_new_version INT;
    
    -- Log the operation
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_update_permission_with_locking', 
                CONCAT('Updating permission with locking - ID: ', p_permission_id, ', Expected version: ', p_expected_version), NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    -- Get current version
    SELECT version INTO v_current_version
    FROM permissions
    WHERE permission_id = p_permission_id AND deleted_at IS NULL;
    
    -- Check if record exists
    IF v_current_version IS NULL THEN
        SELECT 
            0 AS success,
            'Permission not found or has been deleted' AS message,
            NULL AS current_version,
            NULL AS new_version;
    -- Check version match
    ELSEIF v_current_version != p_expected_version THEN
        SELECT 
            0 AS success,
            'Version conflict: Record has been modified by another process' AS message,
            v_current_version AS current_version,
            NULL AS new_version;
    ELSE
        -- Update permission with version increment
        SET v_new_version = v_current_version + 1;
        
        UPDATE permissions
        SET 
            description = COALESCE(p_description, description),
            version = v_new_version,
            updated_at = NOW()
        WHERE permission_id = p_permission_id 
        AND version = p_expected_version 
        AND deleted_at IS NULL;
        
        SET v_affected_rows = ROW_COUNT();
        
        SELECT 
            1 AS success,
            'Permission updated successfully' AS message,
            p_expected_version AS previous_version,
            v_new_version AS new_version;
    END IF;
END$$

-- =============================================
-- UTILITY PROCEDURES FOR OPTIMISTIC LOCKING
-- =============================================

-- Get current version of a record (generic utility)
DROP PROCEDURE IF EXISTS sp_get_record_version$$
CREATE PROCEDURE sp_get_record_version(
    IN p_table_name VARCHAR(64),
    IN p_id VARCHAR(36),
    IN p_correlation_id VARCHAR(36) DEFAULT NULL
)
BEGIN
    DECLARE v_version INT DEFAULT NULL;
    
    -- Log the operation
    IF p_correlation_id IS NOT NULL THEN
        INSERT INTO operation_logs (correlation_id, operation, details, created_at)
        VALUES (p_correlation_id, 'sp_get_record_version', 
                CONCAT('Getting version for table: ', p_table_name, ', ID: ', p_id), NOW())
        ON DUPLICATE KEY UPDATE details = VALUES(details);
    END IF;
    
    -- Dynamic SQL to get version from specified table
    CASE p_table_name
        WHEN 'users' THEN
            SELECT version INTO v_version FROM users WHERE id = p_id AND deleted_at IS NULL;
        WHEN 'roles' THEN
            SELECT version INTO v_version FROM roles WHERE role_id = p_id AND deleted_at IS NULL;
        WHEN 'permissions' THEN
            SELECT version INTO v_version FROM permissions WHERE permission_id = p_id AND deleted_at IS NULL;
        WHEN 'user_sessions' THEN
            SELECT version INTO v_version FROM user_sessions WHERE id = p_id;
        ELSE
            SET v_version = NULL;
    END CASE;
    
    SELECT 
        CASE WHEN v_version IS NOT NULL THEN 1 ELSE 0 END AS found,
        COALESCE(v_version, 0) AS version,
        p_table_name AS table_name,
        p_id AS record_id;
END$$

DELIMITER ;