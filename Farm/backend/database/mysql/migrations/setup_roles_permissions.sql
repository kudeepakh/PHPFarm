-- Setup script for Roles & Permissions Management
-- This script creates the necessary permissions and assigns them to superadmin role
-- Run this script ONCE after implementing the roles management feature

-- Use existing superadmin role ID
SET @superadmin_role_id = '01000000-0000-7000-8000-000000000001';

-- Create superadmin role if it doesn't exist (it should already exist)
INSERT IGNORE INTO roles (role_id, name, description, priority, is_system_role, created_at, updated_at)
VALUES (
    @superadmin_role_id,
    'superadmin',
    'Super Administrator with full system access',
    1000,
    1,
    NOW(),
    NOW()
);

-- Create core permissions for roles management
INSERT INTO permissions (permission_id, name, resource, action, description, created_at, updated_at)
VALUES
    -- Roles permissions
    (UUID(), 'roles:list', 'roles', 'list', 'List all roles', NOW(), NOW()),
    (UUID(), 'roles:read', 'roles', 'read', 'View role details', NOW(), NOW()),
    (UUID(), 'roles:create', 'roles', 'create', 'Create new roles', NOW(), NOW()),
    (UUID(), 'roles:update', 'roles', 'update', 'Update existing roles', NOW(), NOW()),
    (UUID(), 'roles:delete', 'roles', 'delete', 'Delete roles', NOW(), NOW()),
    (UUID(), 'roles:manage', 'roles', 'manage', 'Full role management access', NOW(), NOW()),
    
    -- Permissions management
    (UUID(), 'permissions:list', 'permissions', 'list', 'List all permissions', NOW(), NOW()),
    (UUID(), 'permissions:read', 'permissions', 'read', 'View permission details', NOW(), NOW()),
    (UUID(), 'permissions:manage', 'permissions', 'manage', 'Manage permissions and auto-discovery', NOW(), NOW()),
    
    -- Users management (if not already exists)
    (UUID(), 'users:list', 'users', 'list', 'List all users', NOW(), NOW()),
    (UUID(), 'users:read', 'users', 'read', 'View user details', NOW(), NOW()),
    (UUID(), 'users:create', 'users', 'create', 'Create new users', NOW(), NOW()),
    (UUID(), 'users:update', 'users', 'update', 'Update existing users', NOW(), NOW()),
    (UUID(), 'users:delete', 'users', 'delete', 'Delete users', NOW(), NOW()),
    (UUID(), 'users:import', 'users', 'import', 'Import users from CSV/Excel', NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Assign ALL permissions to superadmin role
INSERT INTO role_permissions (role_permission_id, role_id, permission_id)
SELECT 
    UUID() as role_permission_id,
    @superadmin_role_id as role_id,
    permissions.permission_id as permission_id
FROM permissions
WHERE permissions.deleted_at IS NULL
ON DUPLICATE KEY UPDATE role_permissions.role_id = role_permissions.role_id;

-- Verify the test user has superadmin role
-- First get the user ID
SET @test_user_id = (SELECT id FROM users WHERE email = 'test@example.com' LIMIT 1);

-- Assign superadmin role to test user if not already assigned
INSERT IGNORE INTO user_roles (user_role_id, user_id, role_id)
SELECT UUID(), @test_user_id, @superadmin_role_id
WHERE @test_user_id IS NOT NULL;

-- Display results
SELECT 'Setup Complete!' as status;
SELECT COUNT(*) as total_permissions FROM permissions WHERE deleted_at IS NULL;
SELECT COUNT(*) as superadmin_permissions FROM role_permissions WHERE role_id = @superadmin_role_id;
SELECT email, 
       (SELECT GROUP_CONCAT(r.name) 
        FROM user_roles ur 
        JOIN roles r ON ur.role_id = r.role_id 
        WHERE ur.user_id = users.id) as roles
FROM users 
WHERE email = 'test@example.com';
