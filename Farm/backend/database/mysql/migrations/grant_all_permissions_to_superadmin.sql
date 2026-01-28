-- Grant ALL existing permissions to superadmin role
-- This ensures superadmin has complete access to everything in the system

SET @superadmin_role_id = '01000000-0000-7000-8000-000000000001';

-- First, let's see what we have
SELECT 'Current State:' as info;
SELECT COUNT(*) as total_permissions FROM permissions WHERE deleted_at IS NULL;
SELECT COUNT(*) as superadmin_permissions FROM role_permissions WHERE role_id = @superadmin_role_id;

-- Now assign ALL permissions to superadmin that are not already assigned
INSERT IGNORE INTO role_permissions (role_permission_id, role_id, permission_id)
SELECT 
    UUID() as role_permission_id,
    @superadmin_role_id as role_id,
    p.permission_id
FROM permissions p
WHERE p.deleted_at IS NULL
  AND NOT EXISTS (
    SELECT 1 FROM role_permissions rp 
    WHERE rp.role_id = @superadmin_role_id 
    AND rp.permission_id = p.permission_id
  );

-- Display final results
SELECT 'After Update:' as info;
SELECT COUNT(*) as total_permissions FROM permissions WHERE deleted_at IS NULL;
SELECT COUNT(*) as superadmin_permissions FROM role_permissions WHERE role_id = @superadmin_role_id;

-- Show permissions by resource
SELECT 
    p.resource,
    COUNT(*) as permission_count,
    GROUP_CONCAT(CONCAT(p.resource, ':', p.action) ORDER BY p.action SEPARATOR ', ') as permissions
FROM permissions p
INNER JOIN role_permissions rp ON rp.permission_id = p.permission_id
WHERE rp.role_id = @superadmin_role_id
  AND p.deleted_at IS NULL
GROUP BY p.resource
ORDER BY p.resource;

SELECT 'Superadmin now has access to ALL system actions!' as status;
