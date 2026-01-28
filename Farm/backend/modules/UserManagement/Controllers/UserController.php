<?php

namespace PHPFrarm\Modules\UserManagement\Controllers;

use PHPFrarm\Core\Response;
use PHPFrarm\Core\Logger;
use PHPFrarm\Core\Database;
use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Attributes\RouteGroup;

/**
 * User Management Controller
 * 
 * Admin endpoints for user management
 */
#[RouteGroup('/api/v1/system/users', middleware: ['cors', 'auth'])]
class UserController
{
    /**
     * GET /api/v1/system/users
     * 
     * Get all users
     */
    #[Route('', method: 'GET')]
    public function index(array $request): void
    {
        try {
            $page = (int)($request['query']['page'] ?? 1);
            $limit = (int)($request['query']['limit'] ?? 20);
            $offset = ($page - 1) * $limit;

            // Get users with pagination
            $users = Database::callProcedure('sp_get_all_users', [$limit, $offset]);
            
            // Get total count
            $countResult = Database::callProcedure('sp_count_users', []);
            $total = $countResult[0]['total'] ?? 0;

            Response::success([
                'users' => $users,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to get users', [
                'error' => $e->getMessage()
            ]);
            Response::internalServerError('error.failed_to_get_users');
        }
    }

    /**
     * GET /api/v1/system/users/{id}
     * 
     * Get user by ID
     */
    #[Route('/{id}', method: 'GET')]
    public function show(array $request, string $id): void
    {
        try {
            $user = Database::callProcedure('sp_get_user_by_id', [$id]);
            
            if (empty($user)) {
                Response::notFound('error.user_not_found');
                return;
            }

            // Get user roles
            $authz = \PHPFrarm\Services\AuthorizationService::getUserAuthorizationData($id);

            Response::success([
                'user' => array_merge($user[0], [
                    'roles' => $authz['roles'] ?? [],
                    'permissions' => $authz['permissions'] ?? []
                ])
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to get user', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);
            Response::internalServerError('error.failed_to_get_user');
        }
    }

    /**
     * PUT /api/v1/system/users/{id}
     * 
     * Update user
     */
    #[Route('/{id}', method: 'PUT')]
    public function update(array $request, string $id): void
    {
        try {
            $data = $request['body'] ?? [];
            
            // Update user via stored procedure
            Database::callProcedure('sp_update_user', [
                $id,
                $data['first_name'] ?? null,
                $data['last_name'] ?? null,
                $data['status'] ?? null
            ]);

            Response::success(['updated' => true], 'user.updated');
        } catch (\Exception $e) {
            Logger::error('Failed to update user', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);
            Response::internalServerError('error.failed_to_update_user');
        }
    }

    /**
     * DELETE /api/v1/system/users/{id}
     * 
     * Soft delete user
     */
    #[Route('/{id}', method: 'DELETE')]
    public function destroy(array $request, string $id): void
    {
        try {
            $deletedBy = $request['user']['user_id'] ?? null;
            
            Database::callProcedure('sp_soft_delete_user', [$id, $deletedBy]);

            Logger::audit('User deleted', [
                'user_id' => $id,
                'deleted_by' => $deletedBy
            ]);

            Response::success(['deleted' => true], 'user.deleted');
        } catch (\Exception $e) {
            Logger::error('Failed to delete user', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);
            Response::internalServerError('error.failed_to_delete_user');
        }
    }
}
