<?php

namespace PHPFrarm\Modules\Auth\Controllers;

use PHPFrarm\Core\Response;
use PHPFrarm\Core\Logger;
use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Attributes\RouteGroup;
use PHPFrarm\Core\Attributes\PublicRoute;
use PHPFrarm\Modules\Auth\Services\AuthService;
use PHPFrarm\Modules\Auth\DTO\RegisterRequestDTO;
use PHPFrarm\Modules\Auth\DTO\LoginRequestDTO;
use PHPFrarm\Modules\UserManagement\Services\EmailVerificationService;

/**
 * Auth Controller - Handles HTTP requests for authentication
 * 
 * Routes are automatically discovered via attributes
 * No need to manually register in routes.php
 */
#[RouteGroup('/api/v1/auth', middleware: ['cors', 'rateLimit'])]
class AuthController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * Register a new user
     * 
     * @route POST /api/auth/register
     * @middleware jsonParser - Parse and validate JSON body
     */
    #[PublicRoute(reason: 'User registration must be accessible without authentication')]
    #[Route('/register', method: 'POST', middleware: ['jsonParser'], description: 'Register new user')]
    public function register(array $request): void
    {
        $dto = new RegisterRequestDTO($request['body']);

        // Validate DTO
        $errors = $dto->validate();
        if (!empty($errors)) {
            Response::badRequest('validation.failed', $errors);
            return;
        }

        try {
            // Unified registration: email AND/OR phone
            $result = $this->authService->register(
                $dto->email,
                $dto->phone,
                $dto->password,
                $dto->firstName,
                $dto->lastName
            );

            Response::success($result, 'auth.register.success', 201);

        } catch (\Exception $e) {
            Logger::error('Registration failed', [
                'email' => $dto->email,
                'phone' => $dto->phone ? substr($dto->phone, 0, 4) . '***' : null,
                'error' => $e->getMessage()
            ]);

            if (str_contains($e->getMessage(), 'already registered')) {
                Response::badRequest($e->getMessage());
            } else {
                Response::serverError('auth.register.failed');
            }
        }
    }

    /**
    * Login with identifier and password
     * 
     * @route POST /api/auth/login
     * @middleware jsonParser - Parse and validate JSON body
     */
    #[PublicRoute(reason: 'Login must be accessible without authentication')]
    #[Route('/login', method: 'POST', middleware: ['jsonParser'], description: 'User login')]
    public function login(array $request): void
    {
        $dto = new LoginRequestDTO($request['body']);

        // Validate DTO
        $errors = $dto->validate();
        if (!empty($errors)) {
            Response::badRequest('validation.failed', $errors);
            return;
        }

        try {
            $result = $this->authService->login($dto->identifier, $dto->password);
            Response::success($result, 'auth.login.success');

        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            
            Logger::error('Login failed', [
                'identifier' => $dto->identifier,
                'error' => $errorMessage
            ]);
            
            // Check if this is a verification-required error
            if (str_contains($errorMessage, 'verify your')) {
                Response::error($errorMessage, 403, 'ERR_VERIFICATION_REQUIRED');
                return;
            }
            
            // Check if this is an account status error
            if (str_contains($errorMessage, 'Account is not active')) {
                Response::error($errorMessage, 403, 'ERR_ACCOUNT_INACTIVE');
                return;
            }
            
            // Generic invalid credentials
            Response::unauthorized('auth.login.failed');
        }
    }

    /**
     * Get current user context
     * 
     * @route GET /api/auth/me
     * @middleware auth - Requires authentication
     */
    #[Route('/me', method: 'GET', middleware: ['auth'], description: 'Get current user context')]
    public function me(array $request): void
    {
        $userId = $request['user']['user_id'] ?? null;
        if (!$userId) {
            Response::unauthorized('error.not_authenticated');
            return;
        }

        try {
            $authz = \PHPFrarm\Services\AuthorizationService::getUserAuthorizationData($userId);
            $user = Database::callProcedure('sp_get_user_by_id', [$userId]);
            
            if (empty($user)) {
                Response::notFound('error.user_not_found');
                return;
            }

            Response::success([
                'user' => [
                    'id' => $user[0]['id'],
                    'email' => $user[0]['email'],
                    'first_name' => $user[0]['first_name'] ?? '',
                    'last_name' => $user[0]['last_name'] ?? '',
                    'roles' => array_column($authz['roles'] ?? [], 'name'),
                    'permissions' => $authz['permission_names'] ?? []
                ]
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to get user context', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::internalServerError('error.failed_to_get_user');
        }
    }

    /**
     * Logout current user
     * 
     * @route POST /api/auth/logout
     * @middleware auth - Requires authentication
     */
    #[Route('/logout', method: 'POST', middleware: ['auth'], description: 'User logout')]
    public function logout(array $request): void
    {
        $userId = $request['user']['user_id'] ?? null;
        $sessionId = $request['user']['sid'] ?? null;

        $this->authService->logout($sessionId);
        
        Logger::audit('User logged out', ['user_id' => $userId]);
        
        Response::success(['logged_out' => true], 'auth.logout.success');
    }

    /**
     * Refresh JWT access token
     * 
     * @route POST /api/auth/refresh
     * @middleware auth - Requires authentication
     */
    #[PublicRoute(reason: 'Token refresh must be accessible with just refresh token')]
    #[Route('/refresh', method: 'POST', middleware: ['jsonParser'], description: 'Refresh JWT token')]
    public function refreshToken(array $request): void
    {
        try {
            $refreshToken = $request['body']['refresh_token'] ?? '';
            if ($refreshToken === '') {
                Response::badRequest('validation.failed', ['refresh_token' => 'Refresh token is required']);
                return;
            }

            $tokens = $this->authService->refreshToken($refreshToken);
            Response::success([
                'token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'expires_at' => $tokens['access_expires_at'],
                'refresh_expires_at' => $tokens['refresh_expires_at']
            ], 'auth.refresh.success');

        } catch (\Exception $e) {
            Logger::error('Token refresh failed', ['error' => $e->getMessage()]);
            Response::serverError('auth.refresh.failed');
        }
    }

    /**
     * Verify email with token (GET - for email links)
     * Redirects to frontend after verification
     */
    #[PublicRoute(reason: 'Email verification links must work without login')]
    #[Route('/verify-email', method: 'GET', description: 'Verify email from link')]
    public function verifyEmailGet(array $request): void
    {
        $token = $request['query']['token'] ?? null;
        $frontendUrl = rtrim($_ENV['FRONTEND_URL'] ?? 'http://localhost:3900', '/');
        
        if (!$token) {
            header('Location: ' . $frontendUrl . '/verify-email?error=missing_token');
            exit;
        }
        
        try {
            $result = EmailVerificationService::verifyToken($token);
            header('Location: ' . $frontendUrl . '/verify-email?success=true&email=' . urlencode($result['email']));
            exit;
        } catch (\Exception $e) {
            Logger::warning('Email verification failed', ['error' => $e->getMessage()]);
            header('Location: ' . $frontendUrl . '/verify-email?error=' . urlencode($e->getMessage()));
            exit;
        }
    }

    /**
     * Verify email with token (POST - for API clients)
     */
    #[PublicRoute(reason: 'Email verification must work without login')]
    #[Route('/verify-email', method: 'POST', middleware: ['jsonParser'], description: 'Verify email with token')]
    public function verifyEmailPost(array $request): void
    {
        $token = $request['body']['token'] ?? null;
        
        if (!$token) {
            Response::badRequest('verification.token_required');
            return;
        }
        
        try {
            $result = EmailVerificationService::verifyToken($token);
            Response::success([
                'verified' => true,
                'email' => $result['email'],
                'user_id' => $result['user_id']
            ], 'verification.email.success');
        } catch (\Exception $e) {
            Logger::warning('Email verification failed', ['error' => $e->getMessage()]);
            Response::badRequest($e->getMessage());
        }
    }
}
