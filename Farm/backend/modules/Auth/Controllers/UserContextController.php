<?php

namespace PHPFrarm\Modules\Auth\Controllers;

use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Attributes\RouteGroup;
use PHPFrarm\Core\Logger;
use PHPFrarm\Core\Response;
use PHPFrarm\Modules\Auth\Services\AuthService;
use PHPFrarm\Modules\UserManagement\Services\AuthorizationService;

/**
 * User Context Controller
 *
 * Provides authenticated user context endpoints.
 */
#[RouteGroup('/api/v1/user', middleware: ['cors', 'auth'])]
class UserContextController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * Get current authenticated user
     *
     * GET /api/v1/user/me
     */
    #[Route('/me', method: 'GET', description: 'Get current authenticated user')]
    public function me(array $request): void
    {
        $userId = $request['user']['user_id'] ?? null;

        if (!$userId) {
            Response::unauthorized('auth.not_authenticated');
            return;
        }

        try {
            $user = $this->authService->getUserById($userId);

            if (!$user) {
                Response::notFound('user.not_found');
                return;
            }

            // Get user roles and permissions
            $authz = AuthorizationService::getUserAuthorizationData($userId);
            $roles = array_map(
                fn($role) => $role['name'] ?? $role['role_name'] ?? null,
                $authz['roles'] ?? []
            );
            $roles = array_values(array_filter($roles));
            
            // If no roles, default to 'user'
            if (empty($roles)) {
                $roles = ['user'];
            }

            Response::success([
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'role' => $roles[0] ?? 'user',
                    'roles' => $roles,
                    'permissions' => $authz['permission_names'] ?? [],
                    'created_at' => $user['created_at'] ?? null,
                ]
            ], 'user.profile.retrieved');
        } catch (\Exception $e) {
            Logger::error('Failed to get user profile', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            Response::serverError('user.profile.failed');
        }
    }
}
