-- Add system permissions for dashboard access
INSERT IGNORE INTO `permissions` (`permission_id`, `name`, `description`, `resource`, `action`) VALUES
('02000000-0000-7000-8000-000000000080', 'system:*', 'Full access to system', 'system', '*'),
('02000000-0000-7000-8000-000000000081', 'system:read', 'Read system metrics', 'system', 'read'),
('02000000-0000-7000-8000-000000000082', 'system:update', 'Update system settings', 'system', 'update');

-- Superadmin already has *:* permission, so they automatically have system:* access
-- But let's also add it explicitly for admin role
INSERT IGNORE INTO `role_permissions` (`role_permission_id`, `role_id`, `permission_id`) VALUES
('03000000-0000-7000-8000-000000000050', '01000000-0000-7000-8000-000000000002', '02000000-0000-7000-8000-000000000081');
