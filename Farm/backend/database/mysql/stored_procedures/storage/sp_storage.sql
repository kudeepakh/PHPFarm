-- ============================================================
-- Storage Module - Stored Procedures
-- ============================================================

DELIMITER //

-- Create file record
CREATE PROCEDURE IF NOT EXISTS sp_file_create(
    IN p_id CHAR(36),
    IN p_user_id CHAR(36),
    IN p_category VARCHAR(50),
    IN p_disk VARCHAR(50),
    IN p_path VARCHAR(500),
    IN p_original_name VARCHAR(255),
    IN p_mime_type VARCHAR(100),
    IN p_size BIGINT UNSIGNED,
    IN p_visibility VARCHAR(10),
    IN p_metadata JSON,
    IN p_checksum VARCHAR(64),
    IN p_expires_at TIMESTAMP
)
BEGIN
    INSERT INTO files (
        id, user_id, category, disk, path, original_name, 
        mime_type, size, visibility, metadata, checksum, expires_at
    ) VALUES (
        p_id, p_user_id, p_category, p_disk, p_path, p_original_name,
        p_mime_type, p_size, p_visibility, p_metadata, p_checksum, p_expires_at
    );
    
    -- Update user quota
    IF p_user_id IS NOT NULL THEN
        INSERT INTO storage_quotas (user_id, used_bytes, file_count)
        VALUES (p_user_id, p_size, 1)
        ON DUPLICATE KEY UPDATE 
            used_bytes = used_bytes + p_size,
            file_count = file_count + 1;
    END IF;
    
    SELECT * FROM files WHERE id = p_id;
END //

-- Get file by ID
CREATE PROCEDURE IF NOT EXISTS sp_file_get_by_id(
    IN p_id CHAR(36)
)
BEGIN
    SELECT * FROM files 
    WHERE id = p_id AND deleted_at IS NULL;
END //

-- Get file by path
CREATE PROCEDURE IF NOT EXISTS sp_file_get_by_path(
    IN p_category VARCHAR(50),
    IN p_path VARCHAR(500)
)
BEGIN
    SELECT * FROM files 
    WHERE category = p_category 
      AND path = p_path 
      AND deleted_at IS NULL;
END //

-- List files by user
CREATE PROCEDURE IF NOT EXISTS sp_file_list_by_user(
    IN p_user_id CHAR(36),
    IN p_category VARCHAR(50),
    IN p_limit INT,
    IN p_offset INT
)
BEGIN
    SELECT * FROM files 
    WHERE user_id = p_user_id 
      AND (p_category IS NULL OR category = p_category)
      AND deleted_at IS NULL
    ORDER BY created_at DESC
    LIMIT p_limit OFFSET p_offset;
END //

-- Soft delete file
CREATE PROCEDURE IF NOT EXISTS sp_file_delete(
    IN p_id CHAR(36),
    IN p_user_id CHAR(36)
)
BEGIN
    DECLARE v_size BIGINT UNSIGNED;
    DECLARE v_file_user_id CHAR(36);
    
    SELECT size, user_id INTO v_size, v_file_user_id
    FROM files WHERE id = p_id AND deleted_at IS NULL;
    
    IF v_size IS NOT NULL THEN
        UPDATE files SET deleted_at = NOW() WHERE id = p_id;
        
        -- Update user quota
        IF v_file_user_id IS NOT NULL THEN
            UPDATE storage_quotas 
            SET used_bytes = GREATEST(0, used_bytes - v_size),
                file_count = GREATEST(0, file_count - 1)
            WHERE user_id = v_file_user_id;
        END IF;
        
        SELECT 1 AS success;
    ELSE
        SELECT 0 AS success;
    END IF;
END //

-- Log file access
CREATE PROCEDURE IF NOT EXISTS sp_file_log_access(
    IN p_file_id CHAR(36),
    IN p_user_id CHAR(36),
    IN p_action VARCHAR(20),
    IN p_ip_address VARCHAR(45),
    IN p_user_agent VARCHAR(500),
    IN p_correlation_id CHAR(36)
)
BEGIN
    INSERT INTO file_access_logs (
        file_id, user_id, action, ip_address, user_agent, correlation_id
    ) VALUES (
        p_file_id, p_user_id, p_action, p_ip_address, p_user_agent, p_correlation_id
    );
END //

-- Create file share
CREATE PROCEDURE IF NOT EXISTS sp_file_share_create(
    IN p_id CHAR(36),
    IN p_file_id CHAR(36),
    IN p_created_by CHAR(36),
    IN p_share_token VARCHAR(64),
    IN p_password_hash VARCHAR(255),
    IN p_max_downloads INT UNSIGNED,
    IN p_expires_at TIMESTAMP
)
BEGIN
    INSERT INTO file_shares (
        id, file_id, created_by, share_token, password_hash, max_downloads, expires_at
    ) VALUES (
        p_id, p_file_id, p_created_by, p_share_token, p_password_hash, p_max_downloads, p_expires_at
    );
    
    SELECT * FROM file_shares WHERE id = p_id;
END //

-- Get share by token
CREATE PROCEDURE IF NOT EXISTS sp_file_share_get_by_token(
    IN p_token VARCHAR(64)
)
BEGIN
    SELECT fs.*, f.path, f.original_name, f.mime_type, f.size, f.category, f.disk
    FROM file_shares fs
    JOIN files f ON fs.file_id = f.id
    WHERE fs.share_token = p_token
      AND (fs.expires_at IS NULL OR fs.expires_at > NOW())
      AND (fs.max_downloads IS NULL OR fs.download_count < fs.max_downloads)
      AND f.deleted_at IS NULL;
END //

-- Increment share download count
CREATE PROCEDURE IF NOT EXISTS sp_file_share_increment_download(
    IN p_token VARCHAR(64)
)
BEGIN
    UPDATE file_shares 
    SET download_count = download_count + 1 
    WHERE share_token = p_token;
END //

-- Get user storage quota
CREATE PROCEDURE IF NOT EXISTS sp_storage_quota_get(
    IN p_user_id CHAR(36)
)
BEGIN
    SELECT 
        COALESCE(sq.quota_bytes, 1073741824) AS quota_bytes,
        COALESCE(sq.used_bytes, 0) AS used_bytes,
        COALESCE(sq.file_count, 0) AS file_count,
        COALESCE(sq.quota_bytes, 1073741824) - COALESCE(sq.used_bytes, 0) AS available_bytes,
        ROUND(COALESCE(sq.used_bytes, 0) / COALESCE(sq.quota_bytes, 1073741824) * 100, 2) AS usage_percent
    FROM users u
    LEFT JOIN storage_quotas sq ON u.id = sq.user_id
    WHERE u.id = p_user_id;
END //

-- Update user quota limit
CREATE PROCEDURE IF NOT EXISTS sp_storage_quota_update(
    IN p_user_id CHAR(36),
    IN p_quota_bytes BIGINT UNSIGNED
)
BEGIN
    INSERT INTO storage_quotas (user_id, quota_bytes)
    VALUES (p_user_id, p_quota_bytes)
    ON DUPLICATE KEY UPDATE quota_bytes = p_quota_bytes;
END //

-- Cleanup expired files
CREATE PROCEDURE IF NOT EXISTS sp_file_cleanup_expired()
BEGIN
    DECLARE v_deleted_count INT DEFAULT 0;
    
    -- Get count of files to delete
    SELECT COUNT(*) INTO v_deleted_count
    FROM files 
    WHERE expires_at IS NOT NULL 
      AND expires_at < NOW() 
      AND deleted_at IS NULL;
    
    -- Soft delete expired files
    UPDATE files 
    SET deleted_at = NOW() 
    WHERE expires_at IS NOT NULL 
      AND expires_at < NOW() 
      AND deleted_at IS NULL;
    
    SELECT v_deleted_count AS deleted_count;
END //

-- Purge deleted files (for permanent deletion after retention period)
CREATE PROCEDURE IF NOT EXISTS sp_file_purge_deleted(
    IN p_days_old INT
)
BEGIN
    DECLARE v_purged_count INT DEFAULT 0;
    
    SELECT COUNT(*) INTO v_purged_count
    FROM files 
    WHERE deleted_at IS NOT NULL 
      AND deleted_at < DATE_SUB(NOW(), INTERVAL p_days_old DAY);
    
    DELETE FROM files 
    WHERE deleted_at IS NOT NULL 
      AND deleted_at < DATE_SUB(NOW(), INTERVAL p_days_old DAY);
    
    SELECT v_purged_count AS purged_count;
END //

DELIMITER ;
