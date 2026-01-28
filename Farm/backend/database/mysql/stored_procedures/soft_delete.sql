-- =============================================
-- Soft Delete Stored Procedures
-- Must be created in MySQL database
-- =============================================

DELIMITER //

-- =============================================
-- Soft Delete Record
-- =============================================
CREATE PROCEDURE IF NOT EXISTS sp_soft_delete(
    IN p_table VARCHAR(100),
    IN p_id VARCHAR(50),
    IN p_deleted_at DATETIME
)
BEGIN
    SET @sql = CONCAT('UPDATE ', p_table, ' SET deleted_at = ? WHERE id = ? AND deleted_at IS NULL');
    PREPARE stmt FROM @sql;
    SET @deleted_at = p_deleted_at;
    SET @id = p_id;
    EXECUTE stmt USING @deleted_at, @id;
    DEALLOCATE PREPARE stmt;
    
    SELECT ROW_COUNT() as affected_rows;
END //

-- =============================================
-- Restore Soft Deleted Record
-- =============================================
CREATE PROCEDURE IF NOT EXISTS sp_restore_deleted(
    IN p_table VARCHAR(100),
    IN p_id VARCHAR(50)
)
BEGIN
    SET @sql = CONCAT('UPDATE ', p_table, ' SET deleted_at = NULL WHERE id = ? AND deleted_at IS NOT NULL');
    PREPARE stmt FROM @sql;
    SET @id = p_id;
    EXECUTE stmt USING @id;
    DEALLOCATE PREPARE stmt;
    
    SELECT ROW_COUNT() as affected_rows;
END //

-- =============================================
-- Force Delete (Permanent)
-- =============================================
CREATE PROCEDURE IF NOT EXISTS sp_force_delete(
    IN p_table VARCHAR(100),
    IN p_id VARCHAR(50)
)
BEGIN
    SET @sql = CONCAT('DELETE FROM ', p_table, ' WHERE id = ?');
    PREPARE stmt FROM @sql;
    SET @id = p_id;
    EXECUTE stmt USING @id;
    DEALLOCATE PREPARE stmt;
    
    SELECT ROW_COUNT() as affected_rows;
END //

-- =============================================
-- Check If Record Is Deleted
-- =============================================
CREATE PROCEDURE IF NOT EXISTS sp_is_deleted(
    IN p_table VARCHAR(100),
    IN p_id VARCHAR(50)
)
BEGIN
    SET @sql = CONCAT('SELECT IF(deleted_at IS NOT NULL, 1, 0) as is_deleted FROM ', p_table, ' WHERE id = ?');
    PREPARE stmt FROM @sql;
    SET @id = p_id;
    EXECUTE stmt USING @id;
    DEALLOCATE PREPARE stmt;
END //

-- =============================================
-- Get Only Soft Deleted Records
-- =============================================
CREATE PROCEDURE IF NOT EXISTS sp_get_trashed(
    IN p_table VARCHAR(100)
)
BEGIN
    SET @sql = CONCAT('SELECT * FROM ', p_table, ' WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC');
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
END //

-- =============================================
-- Get All Records Including Soft Deleted
-- =============================================
CREATE PROCEDURE IF NOT EXISTS sp_get_with_trashed(
    IN p_table VARCHAR(100)
)
BEGIN
    SET @sql = CONCAT('SELECT * FROM ', p_table, ' ORDER BY created_at DESC');
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
END //

DELIMITER ;

-- =============================================
-- Add deleted_at column to existing tables (run manually for each table)
-- =============================================
-- ALTER TABLE users ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at;
-- ALTER TABLE otp_verifications ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at;
-- CREATE INDEX idx_deleted_at ON users(deleted_at);
-- CREATE INDEX idx_deleted_at ON otp_verifications(deleted_at);
