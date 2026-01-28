<?php

namespace PHPFrarm\Modules\Permission\DAO;

use PHPFrarm\Core\Database;
use PHPFrarm\Core\Logger;

/**
 * Permission Data Access Object
 * Manages all permission-related database operations via stored procedures
 */
class PermissionDAO
{
    /**
     * Get all permissions
     */
    public static function getAllPermissions(): array
    {
        try {
            $stmt = Database::prepare("CALL sp_get_all_permissions()");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            Logger::error('Failed to get all permissions', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Get permission by ID
     */
    public static function getPermissionById(string $permissionId): ?array
    {
        try {
            $stmt = Database::prepare("CALL sp_get_permission_by_id(?)");
            $stmt->execute([$permissionId]);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (\Exception $e) {
            Logger::error('Failed to get permission by ID', [
                'permission_id' => $permissionId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get permission by name
     */
    public static function getPermissionByName(string $name): ?array
    {
        try {
            $stmt = Database::prepare("CALL sp_get_permission_by_name(?)");
            $stmt->execute([$name]);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (\Exception $e) {
            Logger::error('Failed to get permission by name', [
                'name' => $name,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Create new permission
     */
    public static function createPermission(
        string $permissionId,
        string $name,
        ?string $description = null,
        ?string $resource = null,
        ?string $action = null
    ): array {
        try {
            $stmt = Database::prepare("CALL sp_create_permission(?, ?, ?, ?, ?)");
            $stmt->execute([$permissionId, $name, $description, $resource, $action]);
            
            Logger::info('Permission created', [
                'permission_id' => $permissionId,
                'name' => $name
            ]);
            
            return self::getPermissionById($permissionId);
        } catch (\Exception $e) {
            Logger::error('Failed to create permission', [
                'permission_id' => $permissionId,
                'name' => $name,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Update permission
     */
    public static function updatePermission(
        string $permissionId,
        ?string $name = null,
        ?string $description = null,
        ?string $resource = null,
        ?string $action = null
    ): array {
        try {
            $stmt = Database::prepare("CALL sp_update_permission(?, ?, ?, ?, ?)");
            $stmt->execute([$permissionId, $name, $description, $resource, $action]);
            
            Logger::info('Permission updated', ['permission_id' => $permissionId]);
            
            return self::getPermissionById($permissionId);
        } catch (\Exception $e) {
            Logger::error('Failed to update permission', [
                'permission_id' => $permissionId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Soft delete permission
     */
    public static function deletePermission(string $permissionId, string $deletedBy): bool
    {
        try {
            $stmt = Database::prepare("CALL sp_soft_delete_permission(?, ?)");
            $stmt->execute([$permissionId, $deletedBy]);
            
            Logger::info('Permission deleted', [
                'permission_id' => $permissionId,
                'deleted_by' => $deletedBy
            ]);
            
            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to delete permission', [
                'permission_id' => $permissionId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get permissions by resource
     */
    public static function getPermissionsByResource(string $resource): array
    {
        try {
            $stmt = Database::prepare("CALL sp_get_permissions_by_resource(?)");
            $stmt->execute([$resource]);
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            Logger::error('Failed to get permissions by resource', [
                'resource' => $resource,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
