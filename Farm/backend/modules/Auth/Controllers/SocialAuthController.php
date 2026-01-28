<?php

namespace PHPFrarm\Modules\Auth\Controllers;

use PHPFrarm\Core\BaseController;
use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Attributes\RouteGroup;
use PHPFrarm\Core\Attributes\PublicRoute;
use PHPFrarm\Modules\Auth\Services\SocialAuthService;

/**
 * Social Authentication Controller
 * 
 * Handles OAuth login endpoints for Google, Facebook, GitHub
 * 
 * Routes:
 * - GET  /api/auth/social/{provider}             → Start OAuth flow
 * - GET  /api/auth/social/{provider}/callback    → OAuth callback
 * - POST /api/auth/social/{provider}/unlink      → Unlink provider
 * - GET  /api/auth/social/providers              → List linked providers
 * 
 * @package PHPFrarm\Modules\Auth\Controllers
 */
#[RouteGroup('/api/auth/social', middleware: ['cors'])]
class SocialAuthController extends BaseController
{
    private SocialAuthService $socialAuthService;

    public function __construct()
    {
        parent::__construct();
        $this->socialAuthService = new SocialAuthService();
    }

    /**
     * Start OAuth flow
     * 
     * GET /api/auth/social/{provider}?redirect_uri=...
     * 
     * @return void
     */
    #[PublicRoute(reason: 'OAuth initiation must be accessible without authentication')]
    #[Route('/{provider}', method: 'GET', description: 'Start OAuth flow')]
    public function authorize(array $request): void
    {
        $this->request = $request;
        $provider = $request['params']['provider'] ?? null;
        $redirectUri = $request['query']['redirect_uri'] ?? null;

        if (!$provider) {
            $this->sendError('Provider is required', 400);
            return;
        }

        if (!$redirectUri) {
            $this->sendError('redirect_uri is required', 400);
            return;
        }

        // Validate provider
        $allowedProviders = ['google', 'facebook', 'github'];
        if (!in_array($provider, $allowedProviders)) {
            $this->sendError("Invalid provider. Allowed: " . implode(', ', $allowedProviders), 400);
            return;
        }

        try {
            $result = $this->socialAuthService->getAuthorizationUrl($provider, $redirectUri);

            $this->sendSuccess([
                'authorization_url' => $result['url'],
                'state' => $result['state'],
                'provider' => $provider
            ], 'OAuth authorization URL generated');

        } catch (\Exception $e) {
            $this->sendError($e->getMessage(), 500, 'OAUTH_INIT_FAILED');
        }
    }

    /**
     * Handle OAuth callback
     * 
     * GET /api/auth/social/{provider}/callback?code=...&state=...&redirect_uri=...
     * 
     * @return void
     */
    #[PublicRoute(reason: 'OAuth callback must be accessible without authentication')]
    #[Route('/{provider}/callback', method: 'GET', description: 'OAuth callback')]
    public function callback(array $request): void
    {
        $this->request = $request;
        $provider = $request['params']['provider'] ?? null;
        $code = $request['query']['code'] ?? null;
        $state = $request['query']['state'] ?? null;
        $redirectUri = $request['query']['redirect_uri'] ?? null;

        // Check for error from provider
        $error = $request['query']['error'] ?? null;
        if ($error) {
            $errorDescription = $request['query']['error_description'] ?? 'Unknown error';
            $this->sendError("OAuth error: $errorDescription", 400, 'OAUTH_ERROR');
            return;
        }

        if (!$provider || !$code || !$state || !$redirectUri) {
            $this->sendError('Missing required parameters (code, state, redirect_uri)', 400);
            return;
        }

        try {
            $result = $this->socialAuthService->handleCallback($provider, $code, $state, $redirectUri);

            $this->sendSuccess([
                'user' => [
                    'user_id' => $result['user']['user_id'],
                    'email' => $result['user']['email'],
                    'first_name' => $result['user']['first_name'],
                    'last_name' => $result['user']['last_name'],
                    'status' => $result['user']['status']
                ],
                'tokens' => $result['tokens'],
                'oauth_provider' => $result['oauth_provider']
            ], 'OAuth login successful');

        } catch (\Exception $e) {
            $this->sendError($e->getMessage(), 500, 'OAUTH_CALLBACK_FAILED');
        }
    }

    /**
     * Unlink OAuth provider from user account
     * 
     * POST /api/auth/social/{provider}/unlink
     * Authorization: Bearer {token}
     * 
     * @return void
     */
    #[Route('/{provider}/unlink', method: 'POST', middleware: ['auth'], description: 'Unlink provider')]
    public function unlink(array $request): void
    {
        $this->request = $request;
        $provider = $request['params']['provider'] ?? null;

        if (!$provider) {
            $this->sendError('Provider is required', 400);
            return;
        }

        // Get authenticated user
        $userId = $request['user']['user_id'] ?? null;
        if (!$userId) {
            $this->sendError('Unauthorized', 401);
            return;
        }

        try {
            $this->socialAuthService->unlinkProvider($userId, $provider);

            $this->sendSuccess([
                'provider' => $provider,
                'unlinked' => true
            ], "OAuth provider $provider unlinked successfully");

        } catch (\Exception $e) {
            $this->sendError($e->getMessage(), 500, 'OAUTH_UNLINK_FAILED');
        }
    }

    /**
     * List user's linked OAuth providers
     * 
     * GET /api/auth/social/providers
     * Authorization: Bearer {token}
     * 
     * @return void
     */
    #[Route('/providers', method: 'GET', middleware: ['auth'], description: 'List linked providers')]
    public function listProviders(array $request): void
    {
        $this->request = $request;
        $userId = $request['user']['user_id'] ?? null;
        if (!$userId) {
            $this->sendError('Unauthorized', 401);
            return;
        }

        try {
            $providers = $this->socialAuthService->getLinkedProviders($userId);

            $this->sendSuccess([
                'providers' => $providers
            ], 'Linked providers retrieved');

        } catch (\Exception $e) {
            $this->sendError($e->getMessage(), 500, 'PROVIDERS_LIST_FAILED');
        }
    }
}
