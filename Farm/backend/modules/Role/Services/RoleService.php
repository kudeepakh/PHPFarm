<?php

declare(strict_types=1);

namespace PHPFrarm\Modules\Role\Services;

use PHPFrarm\Modules\Role\DAO\RoleDAO;
use PHPFrarm\Core\Logger;
use Exception;

class RoleService
{
    private RoleDAO $roleDAO;

    public function __construct()
    {
        $this->roleDAO = new RoleDAO();
    }

    /**
     * Normalize role data - convert role_id to id for frontend consistency
     */
    private function normalizeRole(array $role): array
    {
        if (isset($role['role_id'])) {
            $role['id'] = $role['role_id'];
        }
        
        // Normalize permissions if present
        if (isset($role['permissions']) && is_array($role['permissions'])) {
            $role['permissions'] = array_map(function($perm) {
                if (isset($perm['permission_id'])) {
                    $perm['id'] = $perm['permission_id'];
                }
                return $perm;
            }, $role['permissions']);
        }
        
        return $role;
    }

    /**
     * Normalize array of roles
     */
    private function normalizeRoles(array $roles): array
    {
        return array_map([$this, 'normalizeRole'], $roles);
    }

    /**
     * List roles with pagination
     */
    public function listRoles(int $page = 1, int $perPage = 10): array
    {
        try {
            $offset = ($page - 1) * $perPage;
            $roles = $this->roleDAO->listRoles($perPage, $offset);
            $total = $this->roleDAO->countRoles();

            return [
                'roles' => $this->normalizeRoles($roles),
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'total_pages' => ceil($total / $perPage)
                ]
            ];
        } catch (Exception $e) {
            Logger::error('Failed to list roles', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new Exception('Failed to retrieve roles');
        }
    }

    /**
     * Get role by ID with permissions
     */
    public function getRoleById(string $roleId): ?array
    {
        try {
            $role = $this->roleDAO->getRoleById($roleId);
            
            if (!$role) {
                return null;
            }

            // Get role permissions
            $permissions = $this->roleDAO->getRolePermissions($roleId);
            $role['permissions'] = $permissions;

            return $this->normalizeRole($role);
        } catch (Exception $e) {
            Logger::error('Failed to get role', [
                'role_id' => $roleId,
                'error' => $e->getMessage()
            ]);
            throw new Exception('Failed to retrieve role');
        }
    }

    /**
     * Create new role
     */
    public function createRole(array $data, string $adminId): array
    {
        try {
            // Validate required fields
            if (empty($data['name'])) {
                throw new Exception('Role name is required');
            }

            // Check if role name already exists
            $existing = $this->roleDAO->getRoleByName($data['name']);
            if ($existing) {
                throw new Exception('Role with this name already exists');
            }

            // Create role
            $success = $this->roleDAO->createRole($data);

            if (!$success) {
                throw new Exception('Failed to create role');
            }

            // Log audit trail
            Logger::audit('role_created', [
                'role_name' => $data['name'],
                'description' => $data['description'] ?? null,
                'created_by' => $adminId
            ]);

            return [
                'success' => true,
                'message' => 'Role created successfully'
            ];
        } catch (Exception $e) {
            Logger::error('Failed to create role', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Update role
     */
    public function updateRole(string $roleId, array $data, string $adminId): array
    {
        try {
            // Check if role exists
            $role = $this->roleDAO->getRoleById($roleId);
            if (!$role) {
                throw new Exception('Role not found');
            }

            // If name is being changed, check for duplicates
            if (!empty($data['name']) && $data['name'] !== $role['name']) {
                $existing = $this->roleDAO->getRoleByName($data['name']);
                if ($existing) {
                    throw new Exception('Role with this name already exists');
                }
            }

            // Update role
            $success = $this->roleDAO->updateRole($roleId, $data);

            if (!$success) {
                throw new Exception('Failed to update role');
            }

            // Log audit trail
            Logger::audit('role_updated', [
                'role_id' => $roleId,
                'old_data' => $role,
                'new_data' => $data,
                'updated_by' => $adminId
            ]);

            return [
                'success' => true,
                'message' => 'Role updated successfully'
            ];
        } catch (Exception $e) {
            Logger::error('Failed to update role', [
                'role_id' => $roleId,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Soft delete role
     */
    public function deleteRole(string $roleId, string $adminId): array
    {
        try {
            // Check if role exists
            $role = $this->roleDAO->getRoleById($roleId);
            if (!$role) {
                throw new Exception('Role not found');
            }

            // Soft delete role
            $success = $this->roleDAO->softDeleteRole($roleId);

            if (!$success) {
                throw new Exception('Failed to delete role');
            }

            // Log audit trail
            Logger::audit('role_deleted', [
                'role_id' => $roleId,
                'role_name' => $role['name'],
                'deleted_by' => $adminId
            ]);

            return [
                'success' => true,
                'message' => 'Role deleted successfully'
            ];
        } catch (Exception $e) {
            Logger::error('Failed to delete role', [
                'role_id' => $roleId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Sync role permissions
     */
    public function syncPermissions(string $roleId, array $permissionIds, string $adminId): array
    {
        try {
            // Check if role exists
            $role = $this->roleDAO->getRoleById($roleId);
            if (!$role) {
                throw new Exception('Role not found');
            }

            // Get current permissions
            $oldPermissions = $this->roleDAO->getRolePermissions($roleId);
            
            // Sync permissions
            $success = $this->roleDAO->syncRolePermissions($roleId, $permissionIds);

            if (!$success) {
                throw new Exception('Failed to sync permissions');
            }

            // Get new permissions
            $newPermissions = $this->roleDAO->getRolePermissions($roleId);

            // Log audit trail
            Logger::audit('role_permissions_synced', [
                'role_id' => $roleId,
                'role_name' => $role['name'],
                'old_permissions' => array_column($oldPermissions, 'permission_id'),
                'new_permissions' => $permissionIds,
                'updated_by' => $adminId
            ]);

            return [
                'success' => true,
                'message' => 'Permissions updated successfully',
                'permissions' => $newPermissions
            ];
        } catch (Exception $e) {
            Logger::error('Failed to sync permissions', [
                'role_id' => $roleId,
                'permission_ids' => $permissionIds,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
