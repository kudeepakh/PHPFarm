<?php

namespace PHPFrarm\Modules\UserManagement\Services;

use PHPFrarm\Modules\Role\DAO\RoleDAO;
use PHPFrarm\Modules\Permission\DAO\PermissionDAO;
use PHPFrarm\Core\Utils\UuidGenerator;
use PHPFrarm\Core\Validator;

/**
 * Authorization Service
 * Business logic for role and permission management
 */
class AuthorizationService
{
    /**
     * Create a new role
     */
    public static function createRole(array $data): array
    {
        // Validate input
        Validator::validate($data, [
            'name' => ['required', 'string', 'min:2', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000']
        ]);
        
        // Check if role already exists
        $existing = RoleDAO::getRoleByName($data['name']);
        if ($existing) {
            throw new \Exception('Role with this name already exists');
        }
        
        $roleId = UuidGenerator::v4();
        
        return RoleDAO::createRole(
            $roleId,
            $data['name'],
            $data['description'] ?? null,
            $data['priority'] ?? 0
        );
    }
    
    /**
     * Update role
     */
    public static function updateRole(string $roleId, array $data): array
    {
        // Validate input
        Validator::validate($data, [
            'name' => ['nullable', 'string', 'min:2', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000']
        ]);
        
        // Check if role exists
        $role = RoleDAO::getRoleById($roleId);
        if (!$role) {
            throw new \Exception('Role not found');
        }
        
        // If changing name, check uniqueness
        if (isset($data['name']) && $data['name'] !== $role['name']) {
            $existing = RoleDAO::getRoleByName($data['name']);
            if ($existing) {
                throw new \Exception('Role with this name already exists');
            }
        }
        
        return RoleDAO::updateRole(
            $roleId,
            $data['name'] ?? null,
            $data['description'] ?? null,
            $data['priority'] ?? null
        );
    }
    
    /**
     * Delete role
     */
    public static function deleteRole(string $roleId, string $deletedBy): bool
    {
        $role = RoleDAO::getRoleById($roleId);
        if (!$role) {
            throw new \Exception('Role not found');
        }
        
        return RoleDAO::deleteRole($roleId, $deletedBy);
    }
    
    /**
     * Assign permission to role
     */
    public static function assignPermissionToRole(string $roleId, string $permissionId): bool
    {
        // Validate both exist
        $role = RoleDAO::getRoleById($roleId);
        if (!$role) {
            throw new \Exception('Role not found');
        }
        
        $permission = PermissionDAO::getPermissionById($permissionId);
        if (!$permission) {
            throw new \Exception('Permission not found');
        }
        
        return RoleDAO::assignPermission($roleId, $permissionId);
    }
    
    /**
     * Remove permission from role
     */
    public static function removePermissionFromRole(string $roleId, string $permissionId): bool
    {
        return RoleDAO::removePermission($roleId, $permissionId);
    }
    
    /**
     * Assign role to user
     */
    public static function assignRoleToUser(string $userId, string $roleId): bool
    {
        $role = RoleDAO::getRoleById($roleId);
        if (!$role) {
            throw new \Exception('Role not found');
        }
        
        return RoleDAO::assignRoleToUser($userId, $roleId);
    }
    
    /**
     * Remove role from user
     */
    public static function removeRoleFromUser(string $userId, string $roleId): bool
    {
        return RoleDAO::removeRoleFromUser($userId, $roleId);
    }
    
    /**
     * Create a new permission
     */
    public static function createPermission(array $data): array
    {
        // Validate input
        Validator::validate($data, [
            'name' => ['required', 'string', 'regex:/^[a-z]+:[a-z\*]+$/'],
            'description' => ['nullable', 'string', 'max:255'],
            'resource' => ['nullable', 'string', 'max:50'],
            'action' => ['nullable', 'string', 'max:50']
        ]);
        
        // Check if permission already exists
        $existing = PermissionDAO::getPermissionByName($data['name']);
        if ($existing) {
            throw new \Exception('Permission with this name already exists');
        }
        
        // Parse resource:action from name if not provided
        if (!isset($data['resource']) || !isset($data['action'])) {
            $parts = explode(':', $data['name']);
            $data['resource'] = $data['resource'] ?? $parts[0];
            $data['action'] = $data['action'] ?? ($parts[1] ?? '*');
        }
        
        $permissionId = UuidGenerator::v4();
        
        return PermissionDAO::createPermission(
            $permissionId,
            $data['name'],
            $data['description'] ?? null,
            $data['resource'],
            $data['action']
        );
    }
    
    /**
     * Update permission
     */
    public static function updatePermission(string $permissionId, array $data): array
    {
        // Validate input
        Validator::validate($data, [
            'name' => ['nullable', 'string', 'regex:/^[a-z]+:[a-z\*]+$/'],
            'description' => ['nullable', 'string', 'max:255'],
            'resource' => ['nullable', 'string', 'max:50'],
            'action' => ['nullable', 'string', 'max:50']
        ]);
        
        // Check if permission exists
        $permission = PermissionDAO::getPermissionById($permissionId);
        if (!$permission) {
            throw new \Exception('Permission not found');
        }
        
        // If changing name, check uniqueness
        if (isset($data['name']) && $data['name'] !== $permission['name']) {
            $existing = PermissionDAO::getPermissionByName($data['name']);
            if ($existing) {
                throw new \Exception('Permission with this name already exists');
            }
        }
        
        return PermissionDAO::updatePermission(
            $permissionId,
            $data['name'] ?? null,
            $data['description'] ?? null,
            $data['resource'] ?? null,
            $data['action'] ?? null
        );
    }
    
    /**
     * Delete permission
     */
    public static function deletePermission(string $permissionId, string $deletedBy): bool
    {
        $permission = PermissionDAO::getPermissionById($permissionId);
        if (!$permission) {
            throw new \Exception('Permission not found');
        }
        
        return PermissionDAO::deletePermission($permissionId, $deletedBy);
    }
    
    /**
     * Get user's complete authorization data
     */
    public static function getUserAuthorizationData(string $userId): array
    {
        // Cache key for user authorization data
        $cacheKey = "user_authz:{$userId}";
        $cacheTTL = 300; // 5 minutes
        
        // Try Redis cache first
        $redis = null;
        try {
            if (class_exists('\Redis')) {
                $redis = new \Redis();
                $redis->connect(
                    $_ENV['REDIS_HOST'] ?? 'redis',
                    (int)($_ENV['REDIS_PORT'] ?? 6379),
                    1.0  // 1 second timeout
                );
                
                if (!empty($_ENV['REDIS_PASSWORD'])) {
                    $redis->auth($_ENV['REDIS_PASSWORD']);
                }
                
                $cached = $redis->get($cacheKey);
                if ($cached !== false) {
                    return json_decode($cached, true);
                }
            }
        } catch (\Exception $e) {
            // Redis unavailable, continue to DB
            $redis = null;
        }
        
        // Fetch from database
        $roles = RoleDAO::getUserRoles($userId);
        $permissions = RoleDAO::getUserPermissions($userId);
        
        $authData = [
            'user_id' => $userId,
            'roles' => $roles,
            'permissions' => $permissions,
            'permission_names' => array_column($permissions, 'name')
        ];
        
        // Cache the result
        try {
            if ($redis !== null) {
                $redis->setex($cacheKey, $cacheTTL, json_encode($authData));
            }
        } catch (\Exception $e) {
            // Ignore cache write failures
        }
        
        return $authData;
    }
    
    /**
     * Invalidate authorization cache for a user
     * Call this after role/permission changes
     */
    public static function invalidateUserAuthCache(string $userId): void
    {
        $cacheKey = "user_authz:{$userId}";
        try {
            if (class_exists('\Redis')) {
                $redis = new \Redis();
                $redis->connect(
                    $_ENV['REDIS_HOST'] ?? 'redis',
                    (int)($_ENV['REDIS_PORT'] ?? 6379),
                    1.0
                );
                
                if (!empty($_ENV['REDIS_PASSWORD'])) {
                    $redis->auth($_ENV['REDIS_PASSWORD']);
                }
                
                $redis->del($cacheKey);
            }
        } catch (\Exception $e) {
            // Ignore cache failures
        }
    }
}
