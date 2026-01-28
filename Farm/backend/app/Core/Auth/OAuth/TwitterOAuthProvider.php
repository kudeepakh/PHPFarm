<?php

namespace PHPFrarm\Core\Auth\OAuth;

use PHPFrarm\Core\Logger;

/**
 * Twitter/X OAuth Provider
 * 
 * Twitter OAuth 2.0 with PKCE (OAuth 2.0 Authorization Code Flow with PKCE).
 * 
 * Features:
 * - Secure authentication
 * - User profile access
 * - Tweet on behalf (with additional scopes)
 * - OAuth 2.0 (not OAuth 1.0a)
 * 
 * Setup:
 * 1. Create app at developer.twitter.com
 * 2. Enable OAuth 2.0
 * 3. Get Client ID (Client Secret not required for PKCE)
 * 4. Configure callback URLs
 * 5. Set app permissions
 * 
 * @package PHPFrarm\Core\Auth\OAuth
 */
class TwitterOAuthProvider implements OAuthProviderInterface
{
    private string $clientId;
    private string $clientSecret;

    public function __construct()
    {
        $this->clientId = env('TWITTER_CLIENT_ID', '');
        $this->clientSecret = env('TWITTER_CLIENT_SECRET', '');
    }

    public function getAuthorizationUrl(string $redirectUri, array $scopes = [], string $state = ''): string
    {
        $scopes = $scopes ?: $this->getDefaultScopes();
        
        // Generate PKCE code verifier and challenge
        $codeVerifier = $this->generateCodeVerifier();
        $codeChallenge = $this->generateCodeChallenge($codeVerifier);

        // Store code verifier in session for token exchange
        $_SESSION['twitter_code_verifier'] = $codeVerifier;

        $params = [
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', $scopes),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256'
        ];

        return 'https://twitter.com/i/oauth2/authorize?' . http_build_query($params);
    }

    public function getAccessToken(string $code, string $redirectUri): array
    {
        $endpoint = 'https://api.twitter.com/2/oauth2/token';

        // Retrieve code verifier from session
        $codeVerifier = $_SESSION['twitter_code_verifier'] ?? '';
        unset($_SESSION['twitter_code_verifier']);

        if (empty($codeVerifier)) {
            throw new \Exception('Code verifier not found');
        }

        $data = [
            'code' => $code,
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'code_verifier' => $codeVerifier
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            $error = json_decode($response, true);
            throw new \Exception($error['error_description'] ?? 'Token exchange failed');
        }

        return json_decode($response, true);
    }

    public function getUserInfo(string $accessToken): array
    {
        $endpoint = 'https://api.twitter.com/2/users/me';
        
        // Request user fields
        $params = [
            'user.fields' => 'id,name,username,profile_image_url,description,verified'
        ];

        $ch = curl_init($endpoint . '?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new \Exception('Failed to get Twitter user info');
        }

        $result = json_decode($response, true);
        $data = $result['data'] ?? [];

        // Note: Twitter OAuth 2.0 doesn't provide email by default
        // Need to apply for elevated access and request email scope

        return [
            'id' => $data['id'] ?? '',
            'username' => $data['username'] ?? '',
            'name' => $data['name'] ?? '',
            'profile_image_url' => $data['profile_image_url'] ?? '',
            'description' => $data['description'] ?? '',
            'verified' => $data['verified'] ?? false,
            'email' => '' // Not provided without elevated access
        ];
    }

    public function refreshToken(string $refreshToken): array
    {
        $endpoint = 'https://api.twitter.com/2/oauth2/token';

        $data = [
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
            'client_id' => $this->clientId
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    public function revokeToken(string $accessToken): bool
    {
        $endpoint = 'https://api.twitter.com/2/oauth2/revoke';

        $data = [
            'token' => $accessToken,
            'client_id' => $this->clientId,
            'token_type_hint' => 'access_token'
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }

    /**
     * Generate PKCE code verifier
     */
    private function generateCodeVerifier(): string
    {
        $randomBytes = random_bytes(32);
        return rtrim(strtr(base64_encode($randomBytes), '+/', '-_'), '=');
    }

    /**
     * Generate PKCE code challenge from verifier
     */
    private function generateCodeChallenge(string $codeVerifier): string
    {
        $hash = hash('sha256', $codeVerifier, true);
        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }

    public function getProviderName(): string
    {
        return 'twitter';
    }

    public function isConfigured(): bool
    {
        return !empty($this->clientId);
    }

    public function getDefaultScopes(): array
    {
        return ['tweet.read', 'users.read', 'offline.access'];
    }
}
