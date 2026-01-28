<?php

namespace PHPFrarm\Modules\Role\Controllers;

use PHPFrarm\Core\Response;
use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Attributes\RouteGroup;
use PHPFrarm\Core\Database;

/**
 * Role Management API - Frontend facing endpoints
 */
#[RouteGroup('/api/v1/roles', middleware: ['cors', 'auth'])]
class RoleApiController
{
    /**
     * List all roles with search and pagination
     */
    #[Route('', method: 'GET', middleware: ['auth', 'permission:role:read|system:read'])]
    public function index(array $request): void
    {
        $search = $request['query']['search'] ?? '';
        $page = (int)($request['query']['page'] ?? 1);
        $perPage = (int)($request['query']['per_page'] ?? 20);
        
        $offset = ($page - 1) * $perPage;
        
        try {
            // Get roles with search
            if ($search) {
                $roles = Database::callProcedure('sp_search_roles', [$search, $perPage, $offset]);
                $total = Database::callProcedure('sp_count_roles_search', [$search])[0]['total'] ?? 0;
            } else {
                $roles = Database::callProcedure('sp_get_all_roles', [$perPage, $offset]);
                $total = Database::callProcedure('sp_count_roles', [])[0]['total'] ?? 0;
            }

            Response::success([
                'roles' => $roles,
                'pagination' => [
                    'total' => (int)$total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => ceil($total / $perPage),
                ]
            ]);
        } catch (\Exception $e) {
            Response::error('Failed to fetch roles: ' . $e->getMessage(), 'FETCH_FAILED', 500);
        }
    }

    /**
     * Get role by ID
     */
    #[Route('/{id}', method: 'GET', middleware: ['auth', 'permission:role:read|system:read'])]
    public function show(array $request, string $id): void
    {
        try {
            $role = Database::callProcedure('sp_get_role_by_id', [$id]);
            
            if (empty($role)) {
                Response::error('Role not found', 'NOT_FOUND', 404);
                return;
            }

            Response::success($role[0]);
        } catch (\Exception $e) {
            Response::error('Failed to fetch role: ' . $e->getMessage(), 'FETCH_FAILED', 500);
        }
    }

    /**
     * Create new role
     */
    #[Route('', method: 'POST', middleware: ['auth', 'permission:role:create|role:manage'])]
    public function create(array $request): void
    {
        $data = $request['body'] ?? [];
        
        // Validate required fields
        if (empty($data['name'])) {
            Response::error('Role name is required', 'VALIDATION_FAILED', 400);
            return;
        }

        try {
            $roleId = UuidGenerator::v4();
            
            Database::callProcedure('sp_create_role', [
                $roleId,
                $data['name'],
                $data['description'] ?? null
            ]);

            // Assign permissions if provided
            if (!empty($data['permissions']) && is_array($data['permissions'])) {
                foreach ($data['permissions'] as $permissionId) {
                    Database::callProcedure('sp_assign_permission_to_role', [
                        $roleId,
                        $permissionId
                    ]);
                }
            }

            $role = Database::callProcedure('sp_get_role_by_id', [$roleId]);
            Response::success($role[0] ?? ['role_id' => $roleId], 'Role created successfully', 201);
        } catch (\Exception $e) {
            Response::error('Failed to create role: ' . $e->getMessage(), 'CREATE_FAILED', 500);
        }
    }

    /**
     * Update role
     */
    #[Route('/{id}', method: 'PUT', middleware: ['auth', 'permission:role:update|role:manage'])]
    public function update(array $request, string $id): void
    {
        $data = $request['body'] ?? [];
        
        try {
            // Update role basic info
            Database::callProcedure('sp_update_role', [
                $id,
                $data['name'] ?? null,
                $data['description'] ?? null
            ]);

            // Update permissions if provided
            if (isset($data['permissions']) && is_array($data['permissions'])) {
                // Remove all existing permissions
                Database::callProcedure('sp_remove_all_role_permissions', [$id]);
                
                // Assign new permissions
                foreach ($data['permissions'] as $permissionId) {
                    Database::callProcedure('sp_assign_permission_to_role', [
                        $id,
                        $permissionId
                    ]);
                }
            }

            $role = Database::callProcedure('sp_get_role_by_id', [$id]);
            Response::success($role[0] ?? ['role_id' => $id], 'Role updated successfully');
        } catch (\Exception $e) {
            Response::error('Failed to update role: ' . $e->getMessage(), 'UPDATE_FAILED', 500);
        }
    }

    /**
     * Delete role
     */
    #[Route('/{id}', method: 'DELETE', middleware: ['auth', 'permission:role:delete|role:manage'])]
    public function delete(array $request, string $id): void
    {
        try {
            Database::callProcedure('sp_delete_role', [$id]);
            Response::success(['deleted' => true], 'Role deleted successfully');
        } catch (\Exception $e) {
            Response::error('Failed to delete role: ' . $e->getMessage(), 'DELETE_FAILED', 500);
        }
    }

}
