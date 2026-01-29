<?php

namespace PHPFrarm\Core\Auth\OAuth;

use PHPFrarm\Core\Logger;

/**
 * Apple Sign In OAuth Provider
 * 
 * Implements "Sign in with Apple" (OAuth 2.0 / OpenID Connect).
 * Required for iOS apps published on App Store.
 * 
 * Features:
 * - Secure authentication via Apple ID
 * - Privacy-focused (hide email option)
 * - ID token with user data
 * - Refresh token support
 * 
 * Setup:
 * 1. Create App ID in Apple Developer Console
 * 2. Enable "Sign in with Apple" capability
 * 3. Create Service ID (identifier)
 * 4. Get Team ID, Client ID (Service ID), Key ID
 * 5. Download private key (.p8 file)
 * 6. Configure redirect URI
 * 
 * Note: Apple uses JWT for client secret (not static secret)
 * 
 * @package PHPFrarm\Core\Auth\OAuth
 */
class AppleOAuthProvider implements OAuthProviderInterface
{
    private string $clientId; // Service ID
    private string $teamId;
    private string $keyId;
    private string $privateKey;

    public function __construct()
    {
        $this->clientId = env('APPLE_CLIENT_ID', ''); // Service ID
        $this->teamId = env('APPLE_TEAM_ID', '');
        $this->keyId = env('APPLE_KEY_ID', '');
        
        // Private key from .p8 file
        $keyPath = env('APPLE_PRIVATE_KEY_PATH', '');
        if ($keyPath && file_exists($keyPath)) {
            $this->privateKey = file_get_contents($keyPath);
        } else {
            // Or load directly from env (base64 encoded)
            $this->privateKey = env('APPLE_PRIVATE_KEY', '');
            if (!empty($this->privateKey)) {
                $this->privateKey = base64_decode($this->privateKey);
            }
        }
    }

    public function getAuthorizationUrl(string $redirectUri, array $scopes = [], ?string $state = null): string
    {
        $scopes = $scopes ?: $this->getDefaultScopes();
        
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code id_token',
            'response_mode' => 'form_post', // Apple uses POST callback
            'scope' => implode(' ', $scopes),
            'state' => $state ?? ''
        ];

        return 'https://appleid.apple.com/auth/authorize?' . http_build_query($params);
    }

    public function getAccessToken(string $code, string $redirectUri): array
    {
        $endpoint = 'https://appleid.apple.com/auth/token';

        // Generate client secret (JWT signed with private key)
        $clientSecret = $this->generateClientSecret();

        $data = [
            'client_id' => $this->clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri
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
            throw new \Exception($error['error'] ?? 'Token exchange failed');
        }

        return json_decode($response, true);
    }

    public function getUserInfo(string $accessToken): array
    {
        // Apple doesn't provide a separate userinfo endpoint
        // User data is in the ID token (JWT)
        // Decode ID token to get user info
        
        // Note: In Apple's flow, user data is provided ONLY on first authorization
        // in the POST callback as "user" parameter (JSON)
        // Subsequent logins don't include user data
        
        // For now, return empty array
        // In actual implementation, decode and cache ID token
        
        Logger::warning('Apple getUserInfo called - user data is in ID token', [
            'note' => 'Parse ID token from authorization response'
        ]);

        return [
            'id' => '', // Get from ID token sub claim
            'email' => '', // Get from ID token email claim
            'name' => '', // Get from user parameter in callback
            'email_verified' => true // Apple emails are always verified
        ];
    }

    /**
     * Parse ID token (JWT) to extract user info
     * 
     * @param string $idToken ID token from authorization
     * @return array User data
     */
    public function parseIdToken(string $idToken): array
    {
        // Split JWT
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new \Exception('Invalid ID token format');
        }

        // Decode payload (second part)
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        // Verify issuer and audience
        if ($payload['iss'] !== 'https://appleid.apple.com') {
            throw new \Exception('Invalid issuer');
        }

        if ($payload['aud'] !== $this->clientId) {
            throw new \Exception('Invalid audience');
        }

        // Check expiration
        if ($payload['exp'] < time()) {
            throw new \Exception('ID token expired');
        }

        return [
            'id' => $payload['sub'] ?? '',
            'email' => $payload['email'] ?? '',
            'email_verified' => $payload['email_verified'] ?? false,
            'is_private_email' => $payload['is_private_email'] ?? false,
            'auth_time' => $payload['auth_time'] ?? null
        ];
    }

    public function refreshToken(string $refreshToken): array
    {
        $endpoint = 'https://appleid.apple.com/auth/token';
        $clientSecret = $this->generateClientSecret();

        $data = [
            'client_id' => $this->clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    public function revokeToken(string $accessToken): bool
    {
        $endpoint = 'https://appleid.apple.com/auth/revoke';
        $clientSecret = $this->generateClientSecret();

        $data = [
            'client_id' => $this->clientId,
            'client_secret' => $clientSecret,
            'token' => $accessToken,
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
     * Generate client secret (JWT)
     * 
     * Apple requires client secret to be a JWT signed with private key
     */
    private function generateClientSecret(): string
    {
        $header = [
            'alg' => 'ES256',
            'kid' => $this->keyId
        ];

        $payload = [
            'iss' => $this->teamId,
            'iat' => time(),
            'exp' => time() + 3600, // 1 hour
            'aud' => 'https://appleid.apple.com',
            'sub' => $this->clientId
        ];

        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));

        $data = "$headerEncoded.$payloadEncoded";

        // Sign with private key (ES256)
        $privateKey = openssl_pkey_get_private($this->privateKey);
        openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        $signatureEncoded = $this->base64UrlEncode($signature);

        return "$data.$signatureEncoded";
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function getProviderName(): string
    {
        return 'apple';
    }

    public function isConfigured(): bool
    {
        return !empty($this->clientId) && 
               !empty($this->teamId) && 
               !empty($this->keyId) && 
               !empty($this->privateKey);
    }

    public function getDefaultScopes(): array
    {
        return ['name', 'email'];
    }
}
