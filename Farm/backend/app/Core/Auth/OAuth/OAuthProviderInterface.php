<?php

namespace PHPFrarm\Core\Auth\OAuth;

/**
 * OAuth Provider Interface
 * 
 * Abstract interface for OAuth 2.0 providers (Google, Facebook, GitHub, etc.)
 * Standardizes OAuth flow across different providers.
 * 
 * OAuth 2.0 Flow:
 * 1. getAuthorizationUrl() - Redirect user to provider
 * 2. User authorizes on provider's site
 * 3. Provider redirects back with authorization code
 * 4. getAccessToken(code) - Exchange code for access token
 * 5. getUserInfo(token) - Get user profile information
 * 
 * @package PHPFrarm\Core\Auth\OAuth
 */
interface OAuthProviderInterface
{
    /**
     * Get authorization URL to redirect user
     * 
     * @param string $redirectUri Callback URL after authorization
     * @param array $scopes Requested permissions (email, profile, etc.)
     * @param string|null $state CSRF protection token
     * @return string Authorization URL
     */
    public function getAuthorizationUrl(string $redirectUri, array $scopes = [], ?string $state = null): string;

    /**
     * Exchange authorization code for access token
     * 
     * @param string $code Authorization code from provider
     * @param string $redirectUri Must match the one used in getAuthorizationUrl()
     * @return array ['access_token' => string, 'token_type' => string, 'expires_in' => int, 'refresh_token' => string|null]
     * @throws \Exception If token exchange fails
     */
    public function getAccessToken(string $code, string $redirectUri): array;

    /**
     * Get user information using access token
     * 
     * @param string $accessToken Access token from getAccessToken()
     * @return array ['id' => string, 'email' => string, 'name' => string, 'picture' => string|null, 'email_verified' => bool]
     * @throws \Exception If user info request fails
     */
    public function getUserInfo(string $accessToken): array;

    /**
     * Refresh access token using refresh token
     * 
     * @param string $refreshToken Refresh token from getAccessToken()
     * @return array ['access_token' => string, 'token_type' => string, 'expires_in' => int]
     * @throws \Exception If refresh fails
     */
    public function refreshToken(string $refreshToken): array;

    /**
     * Revoke access token (logout from provider)
     * 
     * @param string $accessToken Access token to revoke
     * @return bool True if revoked successfully
     */
    public function revokeToken(string $accessToken): bool;

    /**
     * Get provider name
     * 
     * @return string 'google', 'facebook', 'github', etc.
     */
    public function getProviderName(): string;

    /**
     * Check if provider is configured (client ID/secret present)
     * 
     * @return bool
     */
    public function isConfigured(): bool;

    /**
     * Get default scopes for this provider
     * 
     * @return array
     */
    public function getDefaultScopes(): array;
}
