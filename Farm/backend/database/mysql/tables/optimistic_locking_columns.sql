-- Optimistic Locking Enhancement
-- Adds version columns to critical tables and updates procedures with version checking

-- Add version column to users table (if not exists)
ALTER TABLE users ADD COLUMN IF NOT EXISTS version INT NOT NULL DEFAULT 1 COMMENT 'Version for optimistic locking';
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_user_version (id, version);

-- Add version column to roles table (if not exists) 
ALTER TABLE roles ADD COLUMN IF NOT EXISTS version INT NOT NULL DEFAULT 1 COMMENT 'Version for optimistic locking';
ALTER TABLE roles ADD INDEX IF NOT EXISTS idx_role_version (role_id, version);

-- Add version column to permissions table (if not exists)
ALTER TABLE permissions ADD COLUMN IF NOT EXISTS version INT NOT NULL DEFAULT 1 COMMENT 'Version for optimistic locking';
ALTER TABLE permissions ADD INDEX IF NOT EXISTS idx_permission_version (permission_id, version);

-- Add version column to user_sessions table for concurrent session management
ALTER TABLE user_sessions ADD COLUMN IF NOT EXISTS version INT NOT NULL DEFAULT 1 COMMENT 'Version for optimistic locking';
ALTER TABLE user_sessions ADD INDEX IF NOT EXISTS idx_session_version (id, version);