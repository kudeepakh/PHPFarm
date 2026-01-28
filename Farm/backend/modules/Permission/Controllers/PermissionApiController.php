<?php

namespace PHPFrarm\Modules\Permission\Controllers;

use PHPFrarm\Core\Response;
use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Attributes\RouteGroup;
use PHPFrarm\Core\Database;

/**
 * Permission Management API - Frontend facing endpoints
 */
#[RouteGroup('/api/v1/permissions', middleware: ['cors', 'auth'])]
class PermissionApiController
{
    /**
     * List all permissions
     */
    #[Route('', method: 'GET', middleware: ['auth', 'permission:permission:read|system:read'])]
    public function index(array $request): void
    {
        $search = $request['query']['search'] ?? '';
        $resource = $request['query']['resource'] ?? '';
        $page = (int)($request['query']['page'] ?? 1);
        $perPage = (int)($request['query']['per_page'] ?? 100);
        
        $offset = ($page - 1) * $perPage;
        
        try {
            // Get permissions
            if ($search) {
                $permissions = Database::callProcedure('sp_search_permissions', [$search, $perPage, $offset]);
                $total = Database::callProcedure('sp_count_permissions_search', [$search])[0]['total'] ?? 0;
            } elseif ($resource) {
                $permissions = Database::callProcedure('sp_get_permissions_by_resource', [$resource, $perPage, $offset]);
                $total = Database::callProcedure('sp_count_permissions_by_resource', [$resource])[0]['total'] ?? 0;
            } else {
                $permissions = Database::callProcedure('sp_get_all_permissions', [$perPage, $offset]);
                $total = Database::callProcedure('sp_count_permissions', [])[0]['total'] ?? 0;
            }

            Response::success([
                'permissions' => $permissions,
                'pagination' => [
                    'total' => (int)$total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => ceil($total / $perPage),
                ]
            ]);
        } catch (\Exception $e) {
            Response::error('Failed to fetch permissions: ' . $e->getMessage(), 'FETCH_FAILED', 500);
        }
    }

    /**
     * Get permission by ID
     */
    #[Route('/{id}', method: 'GET', middleware: ['auth', 'permission:permission:read|system:read'])]
    public function show(array $request, string $id): void
    {
        try {
            $permission = Database::callProcedure('sp_get_permission_by_id', [$id]);
            
            if (empty($permission)) {
                Response::error('Permission not found', 'NOT_FOUND', 404);
                return;
            }

            Response::success($permission[0]);
        } catch (\Exception $e) {
            Response::error('Failed to fetch permission: ' . $e->getMessage(), 'FETCH_FAILED', 500);
        }
    }

    /**
     * Create new permission
     */
    #[Route('', method: 'POST', middleware: ['auth', 'permission:permission:create|permission:manage'])]
    public function create(array $request): void
    {
        $data = $request['body'] ?? [];
        
        // Validate
        if (empty($data['name']) || empty($data['resource']) || empty($data['action'])) {
            Response::error('Name, resource and action are required', 'VALIDATION_FAILED', 400);
            return;
        }

        try {
            $permissionId = UuidGenerator::v4();
            
            Database::callProcedure('sp_create_permission', [
                $permissionId,
                $data['name'],
                $data['description'] ?? null,
                $data['resource'],
                $data['action']
            ]);

            $permission = Database::callProcedure('sp_get_permission_by_id', [$permissionId]);
            Response::success($permission[0] ?? ['permission_id' => $permissionId], 'Permission created successfully', 201);
        } catch (\Exception $e) {
            Response::error('Failed to create permission: ' . $e->getMessage(), 'CREATE_FAILED', 500);
        }
    }

    /**
     * Update permission
     */
    #[Route('/{id}', method: 'PUT', middleware: ['auth', 'permission:permission:update|permission:manage'])]
    public function update(array $request, string $id): void
    {
        $data = $request['body'] ?? [];
        
        try {
            Database::callProcedure('sp_update_permission', [
                $id,
                $data['name'] ?? null,
                $data['description'] ?? null,
                $data['resource'] ?? null,
                $data['action'] ?? null
            ]);

            $permission = Database::callProcedure('sp_get_permission_by_id', [$id]);
            Response::success($permission[0] ?? ['permission_id' => $id], 'Permission updated successfully');
        } catch (\Exception $e) {
            Response::error('Failed to update permission: ' . $e->getMessage(), 'UPDATE_FAILED', 500);
        }
    }

    /**
     * Delete permission
     */
    #[Route('/{id}', method: 'DELETE', middleware: ['auth', 'permission:permission:delete|permission:manage'])]
    public function delete(array $request, string $id): void
    {
        try {
            Database::callProcedure('sp_delete_permission', [$id]);
            Response::success(['deleted' => true], 'Permission deleted successfully');
        } catch (\Exception $e) {
            Response::error('Failed to delete permission: ' . $e->getMessage(), 'DELETE_FAILED', 500);
        }
    }

}
