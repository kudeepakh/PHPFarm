-- =============================================
-- Account Status Management Stored Procedures
-- =============================================

DELIMITER //

-- Update account status with history tracking
DROP PROCEDURE IF EXISTS sp_update_account_status//
CREATE PROCEDURE sp_update_account_status(
    IN p_history_id VARCHAR(36),
    IN p_user_id VARCHAR(36),
    IN p_new_status ENUM('active', 'locked', 'suspended', 'pending_verification', 'deactivated'),
    IN p_reason VARCHAR(255),
    IN p_changed_by VARCHAR(36),
    IN p_ip_address VARCHAR(45),
    IN p_user_agent VARCHAR(255)
)
BEGIN
    DECLARE v_old_status ENUM('active', 'locked', 'suspended', 'pending_verification', 'deactivated');
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Get current status
    SELECT account_status INTO v_old_status
    FROM users
    WHERE id = p_user_id;
    
    -- Update users table
    UPDATE users
    SET account_status = p_new_status,
        locked_at = IF(p_new_status = 'locked', NOW(), locked_at),
        locked_reason = IF(p_new_status = 'locked', p_reason, locked_reason),
        suspended_at = IF(p_new_status = 'suspended', NOW(), suspended_at),
        suspended_by = IF(p_new_status = 'suspended', p_changed_by, suspended_by),
        suspended_reason = IF(p_new_status = 'suspended', p_reason, suspended_reason),
        failed_login_attempts = IF(p_new_status = 'active', 0, failed_login_attempts)
    WHERE id = p_user_id;
    
    -- Insert history record
    INSERT INTO account_status_history (
        history_id, user_id, old_status, new_status, 
        changed_by, reason, ip_address, user_agent
    ) VALUES (
        p_history_id, p_user_id, v_old_status, p_new_status,
        p_changed_by, p_reason, p_ip_address, p_user_agent
    );
    
    COMMIT;
END//

-- Lock account (due to failed logins or admin action)
DROP PROCEDURE IF EXISTS sp_lock_account//
CREATE PROCEDURE sp_lock_account(
    IN p_history_id VARCHAR(36),
    IN p_user_id VARCHAR(36),
    IN p_reason VARCHAR(255),
    IN p_locked_by VARCHAR(36),
    IN p_ip_address VARCHAR(45)
)
BEGIN
    CALL sp_update_account_status(
        p_history_id, p_user_id, 'locked', p_reason, 
        p_locked_by, p_ip_address, NULL
    );
END//

-- Unlock account
DROP PROCEDURE IF EXISTS sp_unlock_account//
CREATE PROCEDURE sp_unlock_account(
    IN p_history_id VARCHAR(36),
    IN p_user_id VARCHAR(36),
    IN p_unlocked_by VARCHAR(36),
    IN p_ip_address VARCHAR(45)
)
BEGIN
    -- Reset failed login attempts
    UPDATE users
    SET failed_login_attempts = 0,
        last_failed_login = NULL
    WHERE id = p_user_id;
    
    CALL sp_update_account_status(
        p_history_id, p_user_id, 'active', 'Account unlocked by admin',
        p_unlocked_by, p_ip_address, NULL
    );
END//

-- Suspend account
DROP PROCEDURE IF EXISTS sp_suspend_account//
CREATE PROCEDURE sp_suspend_account(
    IN p_history_id VARCHAR(36),
    IN p_user_id VARCHAR(36),
    IN p_reason VARCHAR(255),
    IN p_suspended_by VARCHAR(36),
    IN p_ip_address VARCHAR(45)
)
BEGIN
    CALL sp_update_account_status(
        p_history_id, p_user_id, 'suspended', p_reason,
        p_suspended_by, p_ip_address, NULL
    );
END//

-- Deactivate account (user-initiated)
DROP PROCEDURE IF EXISTS sp_deactivate_account//
CREATE PROCEDURE sp_deactivate_account(
    IN p_history_id VARCHAR(36),
    IN p_user_id VARCHAR(36),
    IN p_reason VARCHAR(255),
    IN p_ip_address VARCHAR(45)
)
BEGIN
    CALL sp_update_account_status(
        p_history_id, p_user_id, 'deactivated', p_reason,
        p_user_id, p_ip_address, NULL
    );
END//

-- Activate account
DROP PROCEDURE IF EXISTS sp_activate_account//
CREATE PROCEDURE sp_activate_account(
    IN p_history_id VARCHAR(36),
    IN p_user_id VARCHAR(36),
    IN p_activated_by VARCHAR(36),
    IN p_ip_address VARCHAR(45)
)
BEGIN
    CALL sp_update_account_status(
        p_history_id, p_user_id, 'active', 'Account activated',
        p_activated_by, p_ip_address, NULL
    );
END//

-- Increment failed login attempts (auto-lock at 5 attempts)
DROP PROCEDURE IF EXISTS sp_increment_failed_login//
CREATE PROCEDURE sp_increment_failed_login(
    IN p_user_id VARCHAR(36),
    IN p_ip_address VARCHAR(45)
)
BEGIN
    DECLARE v_failed_attempts INT;
    DECLARE v_history_id VARCHAR(36);
    
    -- Increment counter
    UPDATE users
    SET failed_login_attempts = failed_login_attempts + 1,
        last_failed_login = NOW()
    WHERE id = p_user_id;
    
    -- Get updated count
    SELECT failed_login_attempts INTO v_failed_attempts
    FROM users
    WHERE id = p_user_id;
    
    -- Auto-lock at 5 failed attempts
    IF v_failed_attempts >= 5 THEN
        SET v_history_id = UUID();
        CALL sp_lock_account(
            v_history_id, p_user_id, 
            'Account locked due to 5 failed login attempts',
            NULL, p_ip_address
        );
    END IF;
END//

-- Reset failed login attempts
DROP PROCEDURE IF EXISTS sp_reset_failed_login//
CREATE PROCEDURE sp_reset_failed_login(
    IN p_user_id VARCHAR(36)
)
BEGIN
    UPDATE users
    SET failed_login_attempts = 0,
        last_failed_login = NULL
    WHERE id = p_user_id;
END//

-- Get account status history
DROP PROCEDURE IF EXISTS sp_get_account_status_history//
CREATE PROCEDURE sp_get_account_status_history(
    IN p_user_id VARCHAR(36),
    IN p_limit INT
)
BEGIN
    SELECT 
        history_id,
        user_id,
        old_status,
        new_status,
        changed_by,
        reason,
        ip_address,
        user_agent,
        changed_at
    FROM account_status_history
    WHERE user_id = p_user_id
    ORDER BY changed_at DESC
    LIMIT p_limit;
END//

-- Check if account is accessible (active status check)
DROP PROCEDURE IF EXISTS sp_check_account_accessible//
CREATE PROCEDURE sp_check_account_accessible(
    IN p_user_id VARCHAR(36),
    OUT p_is_accessible TINYINT(1),
    OUT p_status VARCHAR(50),
    OUT p_reason VARCHAR(255)
)
BEGIN
    SELECT 
        CASE 
            WHEN status = 'active' THEN 1
            ELSE 0
        END,
        status,
        CASE
            WHEN status = 'locked' THEN 'Account locked due to multiple failed login attempts'
            WHEN status = 'suspended' THEN 'Account suspended by administrator'
            WHEN status = 'inactive' THEN 'Account is inactive'
            ELSE 'Account not active'
        END
    INTO p_is_accessible, p_status, p_reason
    FROM users
    WHERE id = p_user_id AND deleted_at IS NULL;
    
    -- Set defaults if user not found
    IF p_is_accessible IS NULL THEN
        SET p_is_accessible = 0;
        SET p_status = 'not_found';
        SET p_reason = 'User not found';
    END IF;
END//

DELIMITER ;
