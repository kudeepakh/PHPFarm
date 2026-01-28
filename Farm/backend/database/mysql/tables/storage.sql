-- ============================================================
-- Storage Module - Database Tables
-- ============================================================
-- Tracks file metadata, access logs, and lifecycle management
-- ============================================================

-- File metadata table
CREATE TABLE IF NOT EXISTS files (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NULL,
    category VARCHAR(50) NOT NULL DEFAULT 'media',
    disk VARCHAR(50) NOT NULL DEFAULT 'default',
    path VARCHAR(500) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    size BIGINT UNSIGNED NOT NULL DEFAULT 0,
    visibility ENUM('public', 'private') NOT NULL DEFAULT 'private',
    metadata JSON NULL,
    checksum VARCHAR(64) NULL COMMENT 'SHA-256 hash',
    version INT UNSIGNED NOT NULL DEFAULT 1,
    expires_at TIMESTAMP NULL COMMENT 'For temporary files',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    INDEX idx_files_user (user_id),
    INDEX idx_files_category (category),
    INDEX idx_files_path (path(255)),
    INDEX idx_files_created (created_at),
    INDEX idx_files_expires (expires_at),
    INDEX idx_files_deleted (deleted_at),
    
    CONSTRAINT fk_files_user FOREIGN KEY (user_id) 
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- File access logs (for analytics and audit)
CREATE TABLE IF NOT EXISTS file_access_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_id CHAR(36) NOT NULL,
    user_id CHAR(36) NULL,
    action ENUM('view', 'download', 'share', 'delete') NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    correlation_id CHAR(36) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_file_access_file (file_id),
    INDEX idx_file_access_user (user_id),
    INDEX idx_file_access_action (action),
    INDEX idx_file_access_created (created_at),
    INDEX idx_file_access_correlation (correlation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- File shares (for sharing files with expiration)
CREATE TABLE IF NOT EXISTS file_shares (
    id CHAR(36) PRIMARY KEY,
    file_id CHAR(36) NOT NULL,
    created_by CHAR(36) NOT NULL,
    share_token VARCHAR(64) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NULL COMMENT 'Optional password protection',
    max_downloads INT UNSIGNED NULL COMMENT 'Download limit',
    download_count INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_file_shares_file (file_id),
    INDEX idx_file_shares_token (share_token),
    INDEX idx_file_shares_expires (expires_at),
    
    CONSTRAINT fk_file_shares_file FOREIGN KEY (file_id) 
        REFERENCES files(id) ON DELETE CASCADE,
    CONSTRAINT fk_file_shares_user FOREIGN KEY (created_by) 
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Storage quotas per user
CREATE TABLE IF NOT EXISTS storage_quotas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id CHAR(36) NOT NULL UNIQUE,
    quota_bytes BIGINT UNSIGNED NOT NULL DEFAULT 1073741824 COMMENT 'Default 1GB',
    used_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    file_count INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_storage_quotas_user FOREIGN KEY (user_id) 
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
