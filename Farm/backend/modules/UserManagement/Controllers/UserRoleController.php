<?php

namespace PHPFrarm\Modules\UserManagement\Controllers;

use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Response;
use PHPFrarm\Modules\UserManagement\Services\AuthorizationService;
use PHPFrarm\Modules\Role\DAO\RoleDAO;
use PHPFrarm\Core\Request;

/**
 * User Role Assignment Controller
 * Admin APIs for managing user role assignments
 */
class UserRoleController
{
    /**
     * Get user's roles and permissions
     */
    #[Route('/api/v1/system/users/{userId}/roles', method: 'GET', middleware: ['auth', 'permission:users:read'])]
    public function getUserRoles(Request $request, string $userId): void
    {
        try {
            $authData = AuthorizationService::getUserAuthorizationData($userId);
            
            Response::success($authData, 'admin.user_roles.retrieved');
        } catch (\Exception $e) {
            Response::serverError('admin.user_roles.retrieve_failed');
        }
    }
    
    /**
     * Assign role to user
     */
    #[Route('/api/v1/system/users/{userId}/roles/{roleId}', method: 'POST', middleware: ['auth', 'permission:users:update'])]
    public function assignRole(Request $request, string $userId, string $roleId): void
    {
        try {
            AuthorizationService::assignRoleToUser($userId, $roleId);
            
            Response::success(null, 'admin.user_roles.assign_success');
        } catch (\Exception $e) {
            Response::badRequest($e->getMessage());
        }
    }
    
    /**
     * Remove role from user
     */
    #[Route('/api/v1/system/users/{userId}/roles/{roleId}', method: 'DELETE', middleware: ['auth', 'permission:users:update'])]
    public function removeRole(Request $request, string $userId, string $roleId): void
    {
        try {
            AuthorizationService::removeRoleFromUser($userId, $roleId);
            
            Response::success(null, 'admin.user_roles.remove_success');
        } catch (\Exception $e) {
            Response::badRequest($e->getMessage());
        }
    }
    
    /**
     * Bulk assign roles to user
     */
    #[Route('/api/v1/system/users/{userId}/roles/bulk', method: 'POST', middleware: ['auth', 'permission:users:update'])]
    public function bulkAssignRoles(Request $request, string $userId): void
    {
        try {
            $data = $request->body();
            $roleIds = $data['role_ids'] ?? [];
            
            if (empty($roleIds) || !is_array($roleIds)) {
                Response::badRequest('admin.user_roles.role_ids_required');
                return;
            }
            
            $results = [];
            foreach ($roleIds as $roleId) {
                try {
                    AuthorizationService::assignRoleToUser($userId, $roleId);
                    $results[] = ['role_id' => $roleId, 'status' => 'success'];
                } catch (\Exception $e) {
                    $results[] = ['role_id' => $roleId, 'status' => 'failed', 'error' => $e->getMessage()];
                }
            }
            
            Response::success($results, 'admin.user_roles.bulk_assign_completed');
        } catch (\Exception $e) {
            Response::badRequest($e->getMessage());
        }
    }
    
    /**
     * Sync user roles (replace all existing roles)
     */
    #[Route('/api/v1/system/users/{userId}/roles/sync', method: 'PUT', middleware: ['auth', 'permission:users:update'])]
    public function syncRoles(Request $request, string $userId): void
    {
        try {
            $data = $request->body();
            $roleIds = $data['role_ids'] ?? [];
            
            if (!is_array($roleIds)) {
                Response::badRequest('admin.user_roles.role_ids_must_be_array');
                return;
            }
            
            // Get current roles
            $currentRoles = RoleDAO::getUserRoles($userId);
            $currentRoleIds = array_column($currentRoles, 'role_id');
            
            // Remove roles not in new list
            foreach ($currentRoleIds as $currentRoleId) {
                if (!in_array($currentRoleId, $roleIds)) {
                    AuthorizationService::removeRoleFromUser($userId, $currentRoleId);
                }
            }
            
            // Add new roles
            foreach ($roleIds as $roleId) {
                if (!in_array($roleId, $currentRoleIds)) {
                    AuthorizationService::assignRoleToUser($userId, $roleId);
                }
            }
            
            // Return updated data
            $authData = AuthorizationService::getUserAuthorizationData($userId);
            
            Response::success($authData, 'admin.user_roles.sync_success');
        } catch (\Exception $e) {
            Response::badRequest($e->getMessage());
        }
    }
}
