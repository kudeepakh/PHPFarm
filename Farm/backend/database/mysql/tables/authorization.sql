-- =============================================
-- Authorization Module - Database Schema
-- Tables: roles, permissions, role_permissions, user_roles
-- =============================================

-- Roles Table
CREATE TABLE IF NOT EXISTS `roles` (
    `role_id` VARCHAR(36) NOT NULL PRIMARY KEY COMMENT 'UUID v7',
    `name` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique role name (e.g., admin, editor)',
    `description` VARCHAR(255) NULL COMMENT 'Role description',
    `priority` INT NOT NULL DEFAULT 0 COMMENT 'Role priority (higher = more access)',
    `is_system_role` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = system role (cannot be deleted)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL COMMENT 'Soft delete timestamp',
    INDEX `idx_role_name` (`name`),
    INDEX `idx_role_priority` (`priority`),
    INDEX `idx_role_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Role definitions for RBAC';

-- Permissions Table
CREATE TABLE IF NOT EXISTS `permissions` (
    `permission_id` VARCHAR(36) NOT NULL PRIMARY KEY COMMENT 'UUID v7',
    `name` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Permission name in resource:action format (e.g., users:create)',
    `description` VARCHAR(255) NULL COMMENT 'Permission description',
    `resource` VARCHAR(50) NOT NULL COMMENT 'Resource name (e.g., users, posts)',
    `action` VARCHAR(50) NOT NULL COMMENT 'Action name (e.g., create, read, update, delete, *)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL COMMENT 'Soft delete timestamp',
    INDEX `idx_permission_name` (`name`),
    INDEX `idx_permission_resource` (`resource`),
    INDEX `idx_permission_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Permission definitions for granular access control';

-- Role Permissions Junction Table
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role_permission_id` VARCHAR(36) NOT NULL PRIMARY KEY COMMENT 'UUID v7',
    `role_id` VARCHAR(36) NOT NULL COMMENT 'Foreign key to roles',
    `permission_id` VARCHAR(36) NOT NULL COMMENT 'Foreign key to permissions',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_role_permission` (`role_id`, `permission_id`),
    INDEX `idx_rp_role` (`role_id`),
    INDEX `idx_rp_permission` (`permission_id`),
    CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`role_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`permission_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Many-to-many relationship between roles and permissions';

-- User Roles Junction Table
CREATE TABLE IF NOT EXISTS `user_roles` (
    `user_role_id` VARCHAR(36) NOT NULL PRIMARY KEY COMMENT 'UUID v7',
    `user_id` VARCHAR(36) NOT NULL COMMENT 'Foreign key to users',
    `role_id` VARCHAR(36) NOT NULL COMMENT 'Foreign key to roles',
    `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `assigned_by` VARCHAR(36) NULL COMMENT 'User ID who assigned the role',
    UNIQUE KEY `uk_user_role` (`user_id`, `role_id`),
    INDEX `idx_ur_user` (`user_id`),
    INDEX `idx_ur_role` (`role_id`),
    CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`role_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Many-to-many relationship between users and roles';

-- =============================================
-- Seed System Roles and Permissions
-- =============================================

-- Insert system roles
INSERT IGNORE INTO `roles` (`role_id`, `name`, `description`, `priority`, `is_system_role`) VALUES
('01000000-0000-7000-8000-000000000001', 'superadmin', 'Super Administrator with full access', 1000, 1),
('01000000-0000-7000-8000-000000000002', 'admin', 'Administrator with management access', 900, 1),
('01000000-0000-7000-8000-000000000003', 'editor', 'Editor with content management access', 500, 1),
('01000000-0000-7000-8000-000000000004', 'author', 'Author with content creation access', 300, 1),
('01000000-0000-7000-8000-000000000005', 'viewer', 'Viewer with read-only access', 100, 1);

-- Insert system permissions
INSERT IGNORE INTO `permissions` (`permission_id`, `name`, `description`, `resource`, `action`) VALUES
-- Wildcard permissions
('02000000-0000-7000-8000-000000000001', '*:*', 'Full access to all resources', '*', '*'),
-- User permissions
('02000000-0000-7000-8000-000000000010', 'users:*', 'Full access to users', 'users', '*'),
('02000000-0000-7000-8000-000000000011', 'users:create', 'Create users', 'users', 'create'),
('02000000-0000-7000-8000-000000000012', 'users:read', 'Read users', 'users', 'read'),
('02000000-0000-7000-8000-000000000013', 'users:update', 'Update users', 'users', 'update'),
('02000000-0000-7000-8000-000000000014', 'users:delete', 'Delete users', 'users', 'delete'),
-- Role permissions
('02000000-0000-7000-8000-000000000020', 'roles:*', 'Full access to roles', 'roles', '*'),
('02000000-0000-7000-8000-000000000021', 'roles:create', 'Create roles', 'roles', 'create'),
('02000000-0000-7000-8000-000000000022', 'roles:read', 'Read roles', 'roles', 'read'),
('02000000-0000-7000-8000-000000000023', 'roles:update', 'Update roles', 'roles', 'update'),
('02000000-0000-7000-8000-000000000024', 'roles:delete', 'Delete roles', 'roles', 'delete'),
-- Permission permissions
('02000000-0000-7000-8000-000000000030', 'permissions:*', 'Full access to permissions', 'permissions', '*'),
('02000000-0000-7000-8000-000000000031', 'permissions:create', 'Create permissions', 'permissions', 'create'),
('02000000-0000-7000-8000-000000000032', 'permissions:read', 'Read permissions', 'permissions', 'read'),
('02000000-0000-7000-8000-000000000033', 'permissions:update', 'Update permissions', 'permissions', 'update'),
('02000000-0000-7000-8000-000000000034', 'permissions:delete', 'Delete permissions', 'permissions', 'delete'),
-- Settings permissions
('02000000-0000-7000-8000-000000000040', 'settings:*', 'Full access to settings', 'settings', '*'),
('02000000-0000-7000-8000-000000000041', 'settings:read', 'Read settings', 'settings', 'read'),
('02000000-0000-7000-8000-000000000042', 'settings:update', 'Update settings', 'settings', 'update'),
-- Post permissions
('02000000-0000-7000-8000-000000000050', 'posts:*', 'Full access to posts', 'posts', '*'),
('02000000-0000-7000-8000-000000000051', 'posts:create', 'Create posts', 'posts', 'create'),
('02000000-0000-7000-8000-000000000052', 'posts:read', 'Read posts', 'posts', 'read'),
('02000000-0000-7000-8000-000000000053', 'posts:update', 'Update posts', 'posts', 'update'),
('02000000-0000-7000-8000-000000000054', 'posts:delete', 'Delete posts', 'posts', 'delete'),
-- Page permissions
('02000000-0000-7000-8000-000000000060', 'pages:*', 'Full access to pages', 'pages', '*'),
-- Media permissions
('02000000-0000-7000-8000-000000000070', 'media:*', 'Full access to media', 'media', '*');

-- Assign permissions to superadmin role
INSERT IGNORE INTO `role_permissions` (`role_permission_id`, `role_id`, `permission_id`) VALUES
('03000000-0000-7000-8000-000000000001', '01000000-0000-7000-8000-000000000001', '02000000-0000-7000-8000-000000000001');

-- Assign permissions to admin role
INSERT IGNORE INTO `role_permissions` (`role_permission_id`, `role_id`, `permission_id`) VALUES
('03000000-0000-7000-8000-000000000010', '01000000-0000-7000-8000-000000000002', '02000000-0000-7000-8000-000000000010'),
('03000000-0000-7000-8000-000000000011', '01000000-0000-7000-8000-000000000002', '02000000-0000-7000-8000-000000000020'),
('03000000-0000-7000-8000-000000000012', '01000000-0000-7000-8000-000000000002', '02000000-0000-7000-8000-000000000030'),
('03000000-0000-7000-8000-000000000013', '01000000-0000-7000-8000-000000000002', '02000000-0000-7000-8000-000000000040');

-- Assign permissions to editor role
INSERT IGNORE INTO `role_permissions` (`role_permission_id`, `role_id`, `permission_id`) VALUES
('03000000-0000-7000-8000-000000000020', '01000000-0000-7000-8000-000000000003', '02000000-0000-7000-8000-000000000050'),
('03000000-0000-7000-8000-000000000021', '01000000-0000-7000-8000-000000000003', '02000000-0000-7000-8000-000000000060'),
('03000000-0000-7000-8000-000000000022', '01000000-0000-7000-8000-000000000003', '02000000-0000-7000-8000-000000000070');

-- Assign permissions to author role
INSERT IGNORE INTO `role_permissions` (`role_permission_id`, `role_id`, `permission_id`) VALUES
('03000000-0000-7000-8000-000000000030', '01000000-0000-7000-8000-000000000004', '02000000-0000-7000-8000-000000000051'),
('03000000-0000-7000-8000-000000000031', '01000000-0000-7000-8000-000000000004', '02000000-0000-7000-8000-000000000052'),
('03000000-0000-7000-8000-000000000032', '01000000-0000-7000-8000-000000000004', '02000000-0000-7000-8000-000000000053');

-- Assign permissions to viewer role
INSERT IGNORE INTO `role_permissions` (`role_permission_id`, `role_id`, `permission_id`) VALUES
('03000000-0000-7000-8000-000000000040', '01000000-0000-7000-8000-000000000005', '02000000-0000-7000-8000-000000000052');
