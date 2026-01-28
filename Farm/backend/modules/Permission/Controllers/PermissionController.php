<?php

declare(strict_types=1);

namespace PHPFrarm\Modules\Permission\Controllers;

use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Request;
use PHPFrarm\Core\Response;
use PHPFrarm\Modules\Permission\Services\PermissionService;
use Exception;

class PermissionController
{
    private PermissionService $permissionService;

    public function __construct()
    {
        $this->permissionService = new PermissionService();
    }

    /**
     * List all permissions with pagination
     */
    #[Route(
        path: '/api/v1/system/permissions',
        method: 'GET',
        middleware: ['auth', 'permission:permissions:list']
    )]
    public function listPermissions(Request $request): Response
    {
        try {
            $page = (int) ($request->query('page') ?? 1);
            $perPage = (int) ($request->query('per_page') ?? 100);

            if ($page < 1) $page = 1;
            if ($perPage < 1 || $perPage > 500) $perPage = 100;

            $result = $this->permissionService->listPermissions($page, $perPage);

            return Response::success($result, 'Permissions retrieved successfully');
        } catch (Exception $e) {
            return Response::error($e->getMessage(), 500);
        }
    }

    /**
     * Get all permissions without pagination
     */
    #[Route(
        path: '/api/v1/system/permissions/all',
        method: 'GET',
        middleware: ['auth', 'permission:permissions:list']
    )]
    public function getAllPermissions(Request $request): Response
    {
        try {
            $permissions = $this->permissionService->getAllPermissions();

            return Response::success([
                'permissions' => $permissions
            ], 'Permissions retrieved successfully');
        } catch (Exception $e) {
            return Response::error($e->getMessage(), 500);
        }
    }

    /**
     * Auto-discover permissions from route attributes
     */
    #[Route(
        path: '/api/v1/system/permissions/discover',
        method: 'POST',
        middleware: ['auth', 'permission:permissions:manage']
    )]
    public function discoverPermissions(Request $request): Response
    {
        try {
            $result = $this->permissionService->discoverPermissions();

            return Response::success($result, 'Permissions discovered successfully');
        } catch (Exception $e) {
            return Response::error($e->getMessage(), 500);
        }
    }
}
