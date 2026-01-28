<?php

declare(strict_types=1);

namespace PHPFrarm\Modules\Role\Controllers;

use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Request;
use PHPFrarm\Core\Response;
use PHPFrarm\Core\Validator;
use PHPFrarm\Modules\Role\Services\RoleService;
use Exception;

class RoleController
{
    private RoleService $roleService;

    public function __construct()
    {
        $this->roleService = new RoleService();
    }

    /**
     * List all roles with pagination
     */
    #[Route(
        path: '/api/v1/system/roles',
        method: 'GET',
        middleware: ['auth', 'permission:roles:list']
    )]
    public function listRoles(Request $request): Response
    {
        try {
            $page = (int) ($request->query('page') ?? 1);
            $perPage = (int) ($request->query('per_page') ?? 10);

            if ($page < 1) $page = 1;
            if ($perPage < 1 || $perPage > 100) $perPage = 10;

            $result = $this->roleService->listRoles($page, $perPage);

            return Response::success($result, 'Roles retrieved successfully');
        } catch (Exception $e) {
            return Response::error($e->getMessage(), 500);
        }
    }

    /**
     * Get role by ID with permissions
     */
    #[Route(
        path: '/api/v1/system/roles/{roleId}',
        method: 'GET',
        middleware: ['auth', 'permission:roles:read']
    )]
    public function getRoleById(Request $request, string $roleId): Response
    {
        try {
            $role = $this->roleService->getRoleById($roleId);

            if (!$role) {
                return Response::error('Role not found', 404);
            }

            return Response::success(['role' => $role], 'Role retrieved successfully');
        } catch (Exception $e) {
            return Response::error($e->getMessage(), 500);
        }
    }

    /**
     * Create new role
     */
    #[Route(
        path: '/api/v1/system/roles',
        method: 'POST',
        middleware: ['auth', 'jsonParser', 'permission:roles:create']
    )]
    public function createRole(Request $request): Response
    {
        try {
            $data = $request->body();

            // Validate input
            $errors = [];

            if (empty($data['name'])) {
                $errors['name'] = 'Role name is required';
            } elseif (strlen($data['name']) < 2 || strlen($data['name']) > 100) {
                $errors['name'] = 'Role name must be between 2 and 100 characters';
            }

            if (!empty($errors)) {
                return Response::validationError($errors);
            }

            $adminId = $request->user()['id'] ?? '';
            $result = $this->roleService->createRole($data, $adminId);

            return Response::success($result, 'Role created successfully', 201);
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'already exists')) {
                return Response::error($e->getMessage(), 409);
            }
            return Response::error($e->getMessage(), 500);
        }
    }

    /**
     * Update role
     */
    #[Route(
        path: '/api/v1/system/roles/{roleId}',
        method: 'PUT',
        middleware: ['auth', 'jsonParser', 'permission:roles:update']
    )]
    public function updateRole(Request $request, string $roleId): Response
    {
        try {
            $data = $request->body();

            // Validate input
            $errors = [];

            if (isset($data['name'])) {
                if (empty($data['name'])) {
                    $errors['name'] = 'Role name cannot be empty';
                } elseif (strlen($data['name']) < 2 || strlen($data['name']) > 100) {
                    $errors['name'] = 'Role name must be between 2 and 100 characters';
                }
            }

            if (!empty($errors)) {
                return Response::validationError($errors);
            }

            $adminId = $request->user()['id'] ?? '';
            $result = $this->roleService->updateRole($roleId, $data, $adminId);

            return Response::success($result, 'Role updated successfully');
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'not found')) {
                return Response::error($e->getMessage(), 404);
            }
            if (str_contains($e->getMessage(), 'already exists')) {
                return Response::error($e->getMessage(), 409);
            }
            return Response::error($e->getMessage(), 500);
        }
    }

    /**
     * Soft delete role
     */
    #[Route(
        path: '/api/v1/system/roles/{roleId}',
        method: 'DELETE',
        middleware: ['auth', 'permission:roles:delete']
    )]
    public function deleteRole(Request $request, string $roleId): Response
    {
        try {
            $adminId = $request->user()['id'] ?? '';
            $result = $this->roleService->deleteRole($roleId, $adminId);

            return Response::success($result, 'Role deleted successfully');
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'not found')) {
                return Response::error($e->getMessage(), 404);
            }
            return Response::error($e->getMessage(), 500);
        }
    }

    /**
     * Get role permissions
     */
    #[Route(
        path: '/api/v1/system/roles/{roleId}/permissions',
        method: 'GET',
        middleware: ['auth', 'permission:roles:read']
    )]
    public function getRolePermissions(Request $request, string $roleId): Response
    {
        try {
            $role = $this->roleService->getRoleById($roleId);

            if (!$role) {
                return Response::error('Role not found', 404);
            }

            return Response::success([
                'permissions' => $role['permissions'] ?? []
            ], 'Permissions retrieved successfully');
        } catch (Exception $e) {
            return Response::error($e->getMessage(), 500);
        }
    }

    /**
     * Sync role permissions
     */
    #[Route(
        path: '/api/v1/system/roles/{roleId}/permissions/sync',
        method: 'POST',
        middleware: ['auth', 'jsonParser', 'permission:roles:update']
    )]
    public function syncPermissions(Request $request, string $roleId): Response
    {
        try {
            $data = $request->body();

            // Validate input
            if (!isset($data['permission_ids']) || !is_array($data['permission_ids'])) {
                return Response::error('permission_ids must be an array', 400);
            }

            // Filter out null, empty strings, and non-string values
            $permissionIds = array_filter($data['permission_ids'], function($id) {
                return is_string($id) && !empty(trim($id));
            });

            $adminId = $request->user()['id'] ?? '';
            $result = $this->roleService->syncPermissions(
                $roleId,
                array_values($permissionIds), // Re-index array
                $adminId
            );

            return Response::success($result, 'Permissions synced successfully');
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'not found')) {
                return Response::error($e->getMessage(), 404);
            }
            return Response::error($e->getMessage(), 500);
        }
    }
}
