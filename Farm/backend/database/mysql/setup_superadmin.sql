-- =============================================
-- Add Missing Permissions for New Features
-- =============================================

-- Account Status Management Permissions
INSERT IGNORE INTO `permissions` (`permission_id`, `name`, `description`, `resource`, `action`) VALUES
('02000000-0000-7000-8000-000000000080', 'users:lock', 'Lock user accounts', 'users', 'lock'),
('02000000-0000-7000-8000-000000000081', 'users:unlock', 'Unlock user accounts', 'users', 'unlock'),
('02000000-0000-7000-8000-000000000082', 'users:suspend', 'Suspend user accounts', 'users', 'suspend'),
('02000000-0000-7000-8000-000000000083', 'users:activate', 'Activate user accounts', 'users', 'activate');

-- User Verification Permissions
INSERT IGNORE INTO `permissions` (`permission_id`, `name`, `description`, `resource`, `action`) VALUES
('02000000-0000-7000-8000-000000000084', 'users:verify-email', 'Verify email addresses', 'users', 'verify-email'),
('02000000-0000-7000-8000-000000000085', 'users:verify-phone', 'Verify phone numbers', 'users', 'verify-phone');

-- OTP Administration Permissions
INSERT IGNORE INTO `permissions` (`permission_id`, `name`, `description`, `resource`, `action`) VALUES
('02000000-0000-7000-8000-000000000090', 'otp:*', 'Full access to OTP management', 'otp', '*'),
('02000000-0000-7000-8000-000000000091', 'otp:read', 'View OTP history and statistics', 'otp', 'read'),
('02000000-0000-7000-8000-000000000092', 'otp:manage', 'Manage OTP blacklist and settings', 'otp', 'manage');

-- Cache Administration Permissions
INSERT IGNORE INTO `permissions` (`permission_id`, `name`, `description`, `resource`, `action`) VALUES
('02000000-0000-7000-8000-000000000100', 'cache:*', 'Full access to cache management', 'cache', '*'),
('02000000-0000-7000-8000-000000000101', 'cache:read', 'View cache statistics', 'cache', 'read'),
('02000000-0000-7000-8000-000000000102', 'cache:manage', 'Manage cache operations', 'cache', 'manage');

-- Storage Permissions
INSERT IGNORE INTO `permissions` (`permission_id`, `name`, `description`, `resource`, `action`) VALUES
('02000000-0000-7000-8000-000000000110', 'storage:*', 'Full access to storage', 'storage', '*'),
('02000000-0000-7000-8000-000000000111', 'storage:upload', 'Upload files', 'storage', 'upload'),
('02000000-0000-7000-8000-000000000112', 'storage:download', 'Download files', 'storage', 'download'),
('02000000-0000-7000-8000-000000000113', 'storage:delete', 'Delete files', 'storage', 'delete');

-- =============================================
-- Assign New Permissions to Superadmin Role
-- =============================================
INSERT IGNORE INTO `role_permissions` (`role_permission_id`, `role_id`, `permission_id`) 
SELECT 
    CONCAT('03', LPAD(FLOOR(RAND() * 1000000000000), 12, '0'), '-0000-7000-8000-', LPAD(FLOOR(RAND() * 1000000), 12, '0')),
    '01000000-0000-7000-8000-000000000001',
    permission_id
FROM `permissions`
WHERE permission_id >= '02000000-0000-7000-8000-000000000080'
AND NOT EXISTS (
    SELECT 1 FROM `role_permissions` 
    WHERE role_id = '01000000-0000-7000-8000-000000000001' 
    AND `role_permissions`.permission_id = `permissions`.permission_id
);

-- =============================================
-- Assign Superadmin Role to First User
-- =============================================
INSERT IGNORE INTO `user_roles` (`user_role_id`, `user_id`, `role_id`, `assigned_by`)
SELECT 
    UUID(),
    (SELECT id FROM users ORDER BY created_at ASC LIMIT 1),
    '01000000-0000-7000-8000-000000000001',
    (SELECT id FROM users ORDER BY created_at ASC LIMIT 1);

-- Verify assignment
SELECT 
    u.id,
    u.email,
    r.name as role_name,
    r.description as role_description
FROM users u
JOIN user_roles ur ON u.id = ur.user_id
JOIN roles r ON ur.role_id = r.role_id
WHERE u.id = (SELECT id FROM users ORDER BY created_at ASC LIMIT 1);
