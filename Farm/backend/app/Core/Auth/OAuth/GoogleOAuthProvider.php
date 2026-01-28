<?php

namespace PHPFrarm\Core\Auth\OAuth;

use PHPFrarm\Core\Logger;

/**
 * Google OAuth 2.0 Provider
 * 
 * Implements Google Sign-In using OAuth 2.0.
 * 
 * Setup:
 * 1. Go to https://console.cloud.google.com/
 * 2. Create project → Enable Google+ API
 * 3. Credentials → Create OAuth 2.0 Client ID
 * 4. Add authorized redirect URI
 * 5. Copy Client ID and Client Secret to .env
 * 
 * Documentation: https://developers.google.com/identity/protocols/oauth2
 * 
 * @package PHPFrarm\Core\Auth\OAuth
 */
class GoogleOAuthProvider implements OAuthProviderInterface
{
    private string $clientId;
    private string $clientSecret;
    private string $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth';
    private string $tokenUrl = 'https://oauth2.googleapis.com/token';
    private string $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
    private string $revokeUrl = 'https://oauth2.googleapis.com/revoke';

    public function __construct()
    {
        $this->clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? '';
        $this->clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? '';
    }

    public function getAuthorizationUrl(string $redirectUri, array $scopes = [], ?string $state = null): string
    {
        if (empty($scopes)) {
            $scopes = $this->getDefaultScopes();
        }

        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'access_type' => 'offline', // Get refresh token
            'prompt' => 'consent' // Force consent screen for refresh token
        ];

        if ($state) {
            $params['state'] = $state;
        }

        return $this->authUrl . '?' . http_build_query($params);
    }

    public function getAccessToken(string $code, string $redirectUri): array
    {
        $payload = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri
        ];

        $response = $this->sendRequest($this->tokenUrl, 'POST', $payload);

        return [
            'access_token' => $response['access_token'],
            'token_type' => $response['token_type'] ?? 'Bearer',
            'expires_in' => $response['expires_in'] ?? 3600,
            'refresh_token' => $response['refresh_token'] ?? null,
            'scope' => $response['scope'] ?? null
        ];
    }

    public function getUserInfo(string $accessToken): array
    {
        $response = $this->sendRequest(
            $this->userInfoUrl,
            'GET',
            [],
            ['Authorization: Bearer ' . $accessToken]
        );

        return [
            'id' => $response['id'],
            'email' => $response['email'] ?? null,
            'name' => $response['name'] ?? null,
            'given_name' => $response['given_name'] ?? null,
            'family_name' => $response['family_name'] ?? null,
            'picture' => $response['picture'] ?? null,
            'email_verified' => $response['verified_email'] ?? false,
            'locale' => $response['locale'] ?? null,
            'raw' => $response
        ];
    }

    public function refreshToken(string $refreshToken): array
    {
        $payload = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token'
        ];

        $response = $this->sendRequest($this->tokenUrl, 'POST', $payload);

        return [
            'access_token' => $response['access_token'],
            'token_type' => $response['token_type'] ?? 'Bearer',
            'expires_in' => $response['expires_in'] ?? 3600,
            'scope' => $response['scope'] ?? null
        ];
    }

    public function revokeToken(string $accessToken): bool
    {
        try {
            $this->sendRequest(
                $this->revokeUrl . '?token=' . $accessToken,
                'POST'
            );
            return true;
        } catch (\Exception $e) {
            Logger::error('Google token revocation failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function getProviderName(): string
    {
        return 'google';
    }

    public function isConfigured(): bool
    {
        return !empty($this->clientId) && !empty($this->clientSecret);
    }

    public function getDefaultScopes(): array
    {
        return [
            'openid',
            'email',
            'profile'
        ];
    }

    /**
     * Send HTTP request to Google API
     */
    private function sendRequest(string $url, string $method = 'GET', array $data = [], array $headers = []): array
    {
        $ch = curl_init();

        $defaultHeaders = ['Content-Type: application/x-www-form-urlencoded'];
        $headers = array_merge($defaultHeaders, $headers);

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode < 200 || $httpCode >= 300) {
            $error = $result['error_description'] ?? $result['error'] ?? 'Google OAuth error';
            throw new \Exception("Google OAuth error: $error (HTTP $httpCode)");
        }

        return $result;
    }

    /**
     * Validate ID token (JWT) from Google
     * For enhanced security when using implicit flow
     */
    public function validateIdToken(string $idToken): array
    {
        $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . $idToken;
        
        $response = $this->sendRequest($url, 'GET');

        // Verify audience (client ID)
        if ($response['aud'] !== $this->clientId) {
            throw new \Exception('Invalid ID token audience');
        }

        // Verify issuer
        if (!in_array($response['iss'], ['accounts.google.com', 'https://accounts.google.com'])) {
            throw new \Exception('Invalid ID token issuer');
        }

        // Check expiration
        if ($response['exp'] < time()) {
            throw new \Exception('ID token expired');
        }

        return $response;
    }
}
