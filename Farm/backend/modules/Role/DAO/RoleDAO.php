<?php

namespace PHPFrarm\Modules\Role\DAO;

use PHPFrarm\Core\Database;
use PHPFrarm\Core\Logger;
use PHPFrarm\Core\TraceContext;
use PHPFrarm\Core\DAO\BaseDAO;
use PHPFrarm\Core\DAO\Traits\OptimisticLockingTrait;

/**
 * Role Data Access Object
 * Manages all role-related database operations via stored procedures
 * Now includes universal soft delete support and optimistic locking
 */
class RoleDAO extends BaseDAO
{
    use OptimisticLockingTrait;
    
    public function __construct()
    {
        parent::__construct('roles');
        $this->setPrimaryKeyColumn('role_id');
        $this->setSoftDeleteSupport(true);
        $this->setDeletedAtColumn('deleted_at');
    }
    
    /**
     * Get table name for optimistic locking trait
     */
    protected function getTableName(): string
    {
        return 'roles';
    }
    
    /**
     * Update role with optimistic locking protection
     * 
     * @param string $roleId Role ID
     * @param int $expectedVersion Expected version number
     * @param array $data Update data
     * @return array Update result
     * @throws \PHPFrarm\Core\Exceptions\HttpExceptions\ConflictHttpException
     */
    public function updateRoleWithLocking(string $roleId, int $expectedVersion, array $data): array
    {
        return $this->updateWithOptimisticLocking($roleId, $expectedVersion, $data);
    }
    /**
     * Get all roles (excluding soft-deleted)
     */
    public function getAllRoles(): array
    {
        try {
            $correlationId = TraceContext::getCorrelationId();
            return Database::callProcedure('sp_get_all_roles', [$correlationId]);
        } catch (\Exception $e) {
            Logger::error('Failed to get all roles', [
                'error' => $e->getMessage(),
                'correlation_id' => TraceContext::getCorrelationId()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get role by ID (excluding soft-deleted)
     */
    public function getRoleById(string $roleId): ?array
    {
        try {
            $stmt = Database::prepare("CALL sp_get_role_by_id(?)");
            $stmt->execute([$roleId]);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (\Exception $e) {
            Logger::error('Failed to get role by ID', [
                'role_id' => $roleId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get role by name
     */
    public static function getRoleByName(string $name): ?array
    {
        try {
            $stmt = Database::prepare("CALL sp_get_role_by_name(?)");
            $stmt->execute([$name]);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (\Exception $e) {
            Logger::error('Failed to get role by name', [
                'name' => $name,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Create new role
     */
    public static function createRole(
        string $roleId,
        string $name,
        ?string $description = null,
        int $priority = 0
    ): array {
        try {
            $stmt = Database::prepare("CALL sp_create_role(?, ?, ?, ?)");
            $stmt->execute([$roleId, $name, $description, $priority]);
            
            Logger::info('Role created', [
                'role_id' => $roleId,
                'name' => $name
            ]);
            
            return self::getRoleById($roleId);
        } catch (\Exception $e) {
            Logger::error('Failed to create role', [
                'role_id' => $roleId,
                'name' => $name,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Update role
     */
    public static function updateRole(
        string $roleId,
        ?string $name = null,
        ?string $description = null,
        ?int $priority = null
    ): array {
        try {
            $stmt = Database::prepare("CALL sp_update_role(?, ?, ?, ?)");
            $stmt->execute([$roleId, $name, $description, $priority]);
            
            Logger::info('Role updated', ['role_id' => $roleId]);
            
            return self::getRoleById($roleId);
        } catch (\Exception $e) {
            Logger::error('Failed to update role', [
                'role_id' => $roleId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Soft delete role
     */
    public static function deleteRole(string $roleId, string $deletedBy): bool
    {
        try {
            $stmt = Database::prepare("CALL sp_soft_delete_role(?, ?)");
            $stmt->execute([$roleId, $deletedBy]);
            
            Logger::info('Role deleted', [
                'role_id' => $roleId,
                'deleted_by' => $deletedBy
            ]);
            
            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to delete role', [
                'role_id' => $roleId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get permissions for a role
     */
    public static function getRolePermissions(string $roleId): array
    {
        try {
            $stmt = Database::prepare("CALL sp_get_role_permissions(?)");
            $stmt->execute([$roleId]);
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            Logger::error('Failed to get role permissions', [
                'role_id' => $roleId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Assign permission to role
     */
    public static function assignPermission(string $roleId, string $permissionId): bool
    {
        try {
            $stmt = Database::prepare("CALL sp_assign_permission_to_role(?, ?)");
            $stmt->execute([$roleId, $permissionId]);
            
            Logger::info('Permission assigned to role', [
                'role_id' => $roleId,
                'permission_id' => $permissionId
            ]);
            
            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to assign permission to role', [
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Remove permission from role
     */
    public static function removePermission(string $roleId, string $permissionId): bool
    {
        try {
            $stmt = Database::prepare("CALL sp_remove_permission_from_role(?, ?)");
            $stmt->execute([$roleId, $permissionId]);
            
            Logger::info('Permission removed from role', [
                'role_id' => $roleId,
                'permission_id' => $permissionId
            ]);
            
            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to remove permission from role', [
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Assign role to user
     */
    public static function assignRoleToUser(string $userId, string $roleId): bool
    {
        try {
            $stmt = Database::prepare("CALL sp_assign_role_to_user(?, ?)");
            $stmt->execute([$userId, $roleId]);
            
            Logger::info('Role assigned to user', [
                'user_id' => $userId,
                'role_id' => $roleId
            ]);
            
            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to assign role to user', [
                'user_id' => $userId,
                'role_id' => $roleId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Remove role from user
     */
    public static function removeRoleFromUser(string $userId, string $roleId): bool
    {
        try {
            $stmt = Database::prepare("CALL sp_remove_role_from_user(?, ?)");
            $stmt->execute([$userId, $roleId]);
            
            Logger::info('Role removed from user', [
                'user_id' => $userId,
                'role_id' => $roleId
            ]);
            
            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to remove role from user', [
                'user_id' => $userId,
                'role_id' => $roleId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get user roles
     */
    public static function getUserRoles(string $userId): array
    {
        try {
            $stmt = Database::prepare("CALL sp_get_user_roles(?)");
            $stmt->execute([$userId]);
            $roles = $stmt->fetchAll();
            
            // Normalize role_id to id for consistency
            return array_map(function($role) {
                if (isset($role['role_id'])) {
                    $role['id'] = $role['role_id'];
                }
                return $role;
            }, $roles);
        } catch (\Exception $e) {
            Logger::error('Failed to get user roles', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get all permissions for a user (aggregated from all roles)
     */
    public static function getUserPermissions(string $userId): array
    {
        try {
            $stmt = Database::prepare("CALL sp_get_user_permissions(?)");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            Logger::error('Failed to get user permissions', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
