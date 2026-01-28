<?php

namespace PHPFrarm\Modules\User\Controllers;

use PHPFrarm\Core\Response;
use PHPFrarm\Core\Logger;
use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Attributes\RouteGroup;
use PHPFrarm\Modules\User\Services\UserService;
use PHPFrarm\Modules\User\DTO\UpdateProfileDTO;

/**
 * User Controller - Handles HTTP requests for user operations
 * 
 * Routes are automatically discovered via attributes
 */
#[RouteGroup('/api/v1/users', middleware: ['cors', 'auth', 'rateLimit'])]
class UserController
{
    private UserService $userService;

    public function __construct()
    {
        $this->userService = new UserService();
    }

    /**
     * Get current user profile
     * 
     * @route GET /api/users/profile
     * @middleware auth - Requires authentication
     */
    #[Route('/profile', method: 'GET', description: 'Get user profile')]
    public function getProfile(array $request): void
    {
        $userId = $request['user']['user_id'] ?? null;
        
        if (!$userId) {
            Response::badRequest('user.id_missing');
            return;
        }

        try {
            $user = $this->userService->getUserProfile($userId);
            Response::success($user, 'user.profile.retrieved');

        } catch (\Exception $e) {
            Logger::error('Failed to get user profile', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            if ($e->getMessage() === 'User not found') {
                Response::notFound('user.not_found');
            } else {
                Response::serverError('user.profile.failed');
            }
        }
    }

    /**
     * Get current user profile (alias)
     * 
     * @route GET /api/users/me
     * @middleware auth - Requires authentication
     */
    #[Route('/me', method: 'GET', description: 'Get current user')]
    public function me(array $request): void
    {
        $this->getProfile($request);
    }

    /**
     * List users (basic pagination)
     *
     * @route GET /api/v1/users
     * @middleware auth - Requires authentication
     */
    #[Route('/', method: 'GET', middleware: ['auth'], description: 'List users')]
    public function list(array $request): void
    {
        $page = (int)($request['query']['page'] ?? 1);
        $perPage = (int)($request['query']['per_page'] ?? 20);

        try {
            $result = $this->userService->getAllUsers($page, $perPage);

            Response::paginated(
                $result['users'],
                $result['total'],
                $result['page'],
                $result['per_page'],
                'user.list.retrieved'
            );
        } catch (\Exception $e) {
            Logger::error('Failed to list users', ['error' => $e->getMessage()]);
            Response::serverError('user.list.failed');
        }
    }

    /**
     * Search users (alias for list)
     *
     * @route GET /api/v1/users/search
     */
    #[Route('/search', method: 'GET', description: 'Search users')]
    public function search(array $request): void
    {
        $this->list($request);
    }

    /**
     * Update user profile
     * 
     * @route PUT /api/users/profile
     * @middleware auth, jsonParser - Requires authentication and JSON body
     */
    #[Route('/profile', method: 'PUT', middleware: ['jsonParser'], description: 'Update user profile')]
    public function updateProfile(array $request): void
    {
        $userId = $request['user']['user_id'] ?? null;
        $dto = new UpdateProfileDTO($request['body']);
        
        // Validate DTO
        $errors = $dto->validate();
        if (!empty($errors)) {
            Response::badRequest('validation.failed', $errors);
            return;
        }

        try {
            $result = $this->userService->updateProfile($userId, $dto->toArray());
            Response::success($result, 'user.profile.updated');

        } catch (\Exception $e) {
            Logger::error('Failed to update profile', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::serverError('user.profile.update_failed');
        }
    }

    /**
     * List all users (admin only)
     * 
     * @route GET /api/users/admin/list
     * @middleware auth, permission:users:read - Requires users:read permission
     */
    #[Route('/api/v1/system/list', method: 'GET', middleware: ['auth', 'permission:users:read'], description: 'List all users (admin)')]
    public function listUsers(array $request): void
    {
        $page = (int)($request['query']['page'] ?? 1);
        $perPage = (int)($request['query']['per_page'] ?? 20);
        
        try {
            $result = $this->userService->getAllUsers($page, $perPage);
            
            Response::paginated(
                $result['users'],
                $result['total'],
                $result['page'],
                $result['per_page'],
                'user.list.retrieved'
            );

        } catch (\Exception $e) {
            Logger::error('Failed to list users', ['error' => $e->getMessage()]);
            Response::serverError('user.list.failed');
        }
    }

    /**
     * Delete a user (admin only)
     * 
     * @route DELETE /api/users/admin/{userId}
     * @middleware auth, permission:users:delete - Requires users:delete permission
     */
    #[Route('/api/v1/system/{userId}', method: 'DELETE', middleware: ['auth', 'permission:users:delete'], description: 'Delete user (admin)')]
    public function deleteUser(array $request, string $userId): void
    {
        $adminId = $request['user']['user_id'];

        try {
            $this->userService->deleteUser($userId, $adminId);
            Response::success(['deleted' => true], 'user.delete.success');

        } catch (\Exception $e) {
            Logger::error('Failed to delete user', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::serverError('user.delete.failed');
        }
    }

    /**
     * Create a new user (admin only)
     * 
     * @route POST /api/users/admin
     * @middleware auth, jsonParser, permission:users:create
     */
    #[Route('/admin', method: 'POST', middleware: ['auth', 'jsonParser', 'permission:users:create'], description: 'Create user (admin)')]
    public function createUser(array $request): void
    {
        $adminId = $request['user']['user_id'];
        $data = $request['body'];

        try {
            $result = $this->userService->createUser($data, $adminId);
            Response::created($result, 'user.create.success');

        } catch (\Exception $e) {
            Logger::error('Failed to create user', [
                'error' => $e->getMessage()
            ]);
            
            if (strpos($e->getMessage(), 'already exists') !== false) {
                Response::conflict('user.already_exists');
            } else {
                Response::serverError('user.create.failed');
            }
        }
    }

    /**
     * Update a user (admin only)
     * 
     * @route PUT /api/users/admin/{userId}
     * @middleware auth, jsonParser, permission:users:update
     */
    #[Route('/api/v1/system/{userId}', method: 'PUT', middleware: ['auth', 'jsonParser', 'permission:users:update'], description: 'Update user (admin)')]
    public function updateUser(array $request, string $userId): void
    {
        $adminId = $request['user']['user_id'];
        $data = $request['body'];

        try {
            $result = $this->userService->updateUser($userId, $data, $adminId);
            Response::success($result, 'user.update.success');

        } catch (\Exception $e) {
            Logger::error('Failed to update user', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            if ($e->getMessage() === 'User not found') {
                Response::notFound('user.not_found');
            } else {
                Response::serverError('user.update.failed');
            }
        }
    }

    /**
     * Import users from CSV/Excel file (admin only)
     * 
     * @route POST /api/users/admin/import
     * @middleware auth, permission:users:create
     */
    #[Route('/api/v1/system/import', method: 'POST', middleware: ['auth', 'permission:users:create'], description: 'Import users (admin)')]
    public function importUsers(array $request): void
    {
        $adminId = $request['user']['user_id'];

        if (empty($_FILES['file'])) {
            Response::badRequest('file.required');
            return;
        }

        $file = $_FILES['file'];
        $allowedTypes = ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        
        if (!in_array($file['type'], $allowedTypes)) {
            Response::badRequest('file.invalid_type');
            return;
        }

        try {
            $result = $this->userService->importUsers($file['tmp_name'], $file['type'], $adminId);
            Response::success($result, 'user.import.success');

        } catch (\Exception $e) {
            Logger::error('Failed to import users', [
                'error' => $e->getMessage()
            ]);
            Response::serverError('user.import.failed');
        }
    }

    /**
     * Download user import template
     * 
     * @route GET /api/users/admin/template/{format}
     * @middleware auth, permission:users:read
     */
    #[Route('/api/v1/system/template/{format}', method: 'GET', middleware: ['auth', 'permission:users:read'], description: 'Download import template')]
    public function downloadTemplate(array $request, string $format): void
    {
        if (!in_array($format, ['csv', 'xlsx'])) {
            Response::badRequest('template.invalid_format');
            return;
        }

        try {
            $this->userService->downloadTemplate($format);
        } catch (\Exception $e) {
            Logger::error('Failed to download template', [
                'format' => $format,
                'error' => $e->getMessage()
            ]);
            Response::serverError('template.download.failed');
        }
    }
}
