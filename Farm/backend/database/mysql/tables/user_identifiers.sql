-- User Identifiers Table
-- Stores multiple login identifiers (email, phone, username) per user
-- This is the enterprise pattern used by Google, Facebook, etc.

CREATE TABLE IF NOT EXISTS user_identifiers (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    identifier_type ENUM('email', 'phone', 'username') NOT NULL,
    identifier_value VARCHAR(255) NOT NULL,
    is_verified BOOLEAN DEFAULT FALSE,
    is_primary BOOLEAN DEFAULT FALSE,
    verified_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Each identifier must be globally unique
    UNIQUE KEY uk_identifier (identifier_type, identifier_value),
    
    -- Only one primary identifier per type per user
    UNIQUE KEY uk_user_primary (user_id, identifier_type, is_primary),
    
    -- Foreign key to users table
    CONSTRAINT fk_user_identifiers_user 
        FOREIGN KEY (user_id) REFERENCES users(id) 
        ON DELETE CASCADE,
    
    -- Indexes for fast lookups
    INDEX idx_user_id (user_id),
    INDEX idx_identifier_lookup (identifier_type, identifier_value, is_verified),
    INDEX idx_verified (is_verified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
