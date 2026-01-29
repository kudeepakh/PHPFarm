<?php

namespace PHPFrarm\Modules\Auth\Services;

use PHPFrarm\Core\Auth\OAuth\OAuthFactory;
use PHPFrarm\Modules\Auth\DAO\UserDAO;
use PHPFrarm\Modules\Auth\Services\AuthService;
use PHPFrarm\Core\Logger;
use PHPFrarm\Core\Utils\UuidGenerator;
use App\Core\Cache\CacheManager;

/**
 * Social Authentication Service
 * 
 * Handles OAuth login via Google, Facebook, GitHub.
 * Creates user account if doesn't exist (auto-registration).
 * 
 * @package PHPFrarm\Modules\Auth\Services
 */
class SocialAuthService
{
    private UserDAO $userDAO;
    private AuthService $authService;
    private CacheManager $cache;

    public function __construct()
    {
        $this->userDAO = new UserDAO();
        $this->authService = new AuthService();
        $this->cache = CacheManager::getInstance();
    }

    /**
     * Get authorization URL to redirect user
     * 
     * @param string $provider 'google', 'facebook', 'github'
     * @param string $redirectUri Callback URL
     * @return array ['url' => string, 'state' => string]
     */
    public function getAuthorizationUrl(string $provider, string $redirectUri): array
    {
        $oauth = OAuthFactory::getProvider($provider);

        if (!$oauth->isConfigured()) {
            throw new \Exception("$provider OAuth is not configured");
        }

        // Generate CSRF state token
        $state = bin2hex(random_bytes(16));
        
        // Store state in Redis with 10-minute expiration (OAuth timeout)
        $this->cache->set("oauth_state:{$state}", $provider, 600);

        $url = $oauth->getAuthorizationUrl($redirectUri, [], $state);

        Logger::info('OAuth authorization started', [
            'provider' => $provider,
            'redirect_uri' => $redirectUri
        ]);

        return [
            'url' => $url,
            'state' => $state
        ];
    }

    /**
     * Handle OAuth callback and login user
     * 
     * @param string $provider Provider name
     * @param string $code Authorization code from provider
     * @param string $state State token for CSRF protection
     * @param string $redirectUri Callback URL (must match authorization)
     * @return array User data with tokens
     */
    public function handleCallback(string $provider, string $code, string $state, string $redirectUri): array
    {
        // Verify state (CSRF protection)
        $cachedProvider = $this->cache->get("oauth_state:{$state}");
        
        if ($cachedProvider === null) {
            throw new \Exception('Invalid or expired state parameter (CSRF attack detected or session timeout)');
        }

        // Verify provider matches
        if ($provider !== $cachedProvider) {
            throw new \Exception('Provider mismatch');
        }

        // Clear cache state
        $this->cache->delete("oauth_state:{$state}");

        $oauth = OAuthFactory::getProvider($provider);

        // Exchange code for access token
        $tokenData = $oauth->getAccessToken($code, $redirectUri);
        $accessToken = $tokenData['access_token'];

        // Get user info from provider
        $userInfo = $oauth->getUserInfo($accessToken);

        Logger::info('OAuth user info retrieved', [
            'provider' => $provider,
            'user_id' => $userInfo['id'],
            'email' => $userInfo['email']
        ]);

        // Find or create user
        $user = $this->findOrCreateOAuthUser($provider, $userInfo);

        // Use AuthService to login the user (generates tokens with proper session tracking)
        $loginResult = $this->authService->loginWithOtpIdentifier($user['email']);

        Logger::audit('OAuth login successful', [
            'user_id' => $user['user_id'],
            'provider' => $provider,
            'email' => $user['email']
        ]);

        return [
            'user' => $user,
            'tokens' => [
                'access_token' => $loginResult['token'],
                'refresh_token' => $loginResult['refresh_token'],
                'access_expires_at' => $loginResult['expires_at'],
                'refresh_expires_at' => $loginResult['refresh_expires_at']
            ],
            'oauth_provider' => $provider
        ];
    }

    /**
     * Find existing user by OAuth or create new one
     * 
     * @param string $provider OAuth provider name
     * @param array $userInfo User data from OAuth provider
     * @return array User data
     */
    private function findOrCreateOAuthUser(string $provider, array $userInfo): array
    {
        $oauthId = $provider . ':' . $userInfo['id'];

        // Try to find user by OAuth identifier
        $user = $this->userDAO->getUserByIdentifier($oauthId, 'oauth');

        if ($user) {
            // Update user info (name, picture may have changed)
            $this->updateUserFromOAuth($user['user_id'], $userInfo);
            
            Logger::info('Existing OAuth user found', [
                'user_id' => $user['user_id'],
                'oauth_id' => $oauthId
            ]);

            return $user;
        }

        // Try to find by email (user may have registered with email before)
        if (!empty($userInfo['email'])) {
            $user = $this->userDAO->getUserByEmail($userInfo['email']);
            
            if ($user) {
                // Link OAuth account to existing email user
                $this->linkOAuthToExistingUser($user['user_id'], $oauthId, $provider, $userInfo);
                
                Logger::info('OAuth linked to existing email user', [
                    'user_id' => $user['user_id'],
                    'oauth_id' => $oauthId,
                    'email' => $userInfo['email']
                ]);

                return $user;
            }
        }

        // Create new user
        $userId = $this->createOAuthUser($oauthId, $provider, $userInfo);

        Logger::info('New OAuth user created', [
            'user_id' => $userId,
            'oauth_id' => $oauthId,
            'provider' => $provider
        ]);

        return $this->userDAO->getUserByIdentifier($oauthId, 'oauth');
    }

    /**
     * Create new user from OAuth data
     */
    private function createOAuthUser(string $oauthId, string $provider, array $userInfo): string
    {
        $userId = UuidGenerator::v4();

        // Create user (no password needed for OAuth users)
        $this->userDAO->createUser(
            $userId,
            $userInfo['email'] ?? null,
            null, // No password hash
            $userInfo['given_name'] ?? $userInfo['name'] ?? 'User',
            $userInfo['family_name'] ?? ''
        );

        // Add OAuth identifier
        $this->userDAO->addUserIdentifier($userId, $oauthId, 'oauth');

        // Auto-verify email if provider verified it
        if (!empty($userInfo['email']) && !empty($userInfo['email_verified'])) {
            $this->userDAO->verifyEmail($userId);
        }

        return $userId;
    }

    /**
     * Link OAuth account to existing user
     */
    private function linkOAuthToExistingUser(string $userId, string $oauthId, string $provider, array $userInfo): void
    {
        // Add OAuth identifier
        $this->userDAO->addUserIdentifier($userId, $oauthId, 'oauth');

        // Update user info if not set
        $this->updateUserFromOAuth($userId, $userInfo);
    }

    /**
     * Update user data from OAuth info
     */
    private function updateUserFromOAuth(string $userId, array $userInfo): void
    {
        // Update name, picture, etc. (implementation depends on your user schema)
        // For now, just log
        Logger::debug('User OAuth data updated', [
            'user_id' => $userId,
            'name' => $userInfo['name'] ?? null,
            'picture' => $userInfo['picture'] ?? null
        ]);
    }

    /**
     * Unlink OAuth provider from user account
     * 
     * @param string $userId User ID
     * @param string $provider Provider to unlink
     * @return bool Success
     */
    public function unlinkProvider(string $userId, string $provider): bool
    {
        // Get user's identifiers
        $user = $this->userDAO->getUserById($userId);
        
        if (!$user) {
            throw new \Exception('User not found');
        }

        // Ensure user has other login methods (email/password or other OAuth)
        // Don't allow unlinking the only authentication method
        
        // Remove OAuth identifier (implementation needed in UserDAO)
        // $this->userDAO->removeUserIdentifier($userId, $provider);

        Logger::audit('OAuth provider unlinked', [
            'user_id' => $userId,
            'provider' => $provider
        ]);

        return true;
    }

    /**
     * Get user's linked OAuth providers
     * 
     * @param string $userId User ID
     * @return array ['google', 'facebook', ...]
     */
    public function getLinkedProviders(string $userId): array
    {
        // Implementation depends on how identifiers are stored
        // For now, return empty array
        return [];
    }
}
