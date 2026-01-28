<?php

namespace PHPFrarm\Core\Auth\OAuth;

use PHPFrarm\Core\Logger;

/**
 * Facebook OAuth 2.0 Provider
 * 
 * Implements Facebook Login using OAuth 2.0.
 * 
 * Setup:
 * 1. Go to https://developers.facebook.com/
 * 2. Create App → Add Facebook Login product
 * 3. Settings → Basic → Copy App ID and App Secret
 * 4. Facebook Login → Settings → Add Valid OAuth Redirect URIs
 * 5. Add credentials to .env
 * 
 * Documentation: https://developers.facebook.com/docs/facebook-login/manually-build-a-login-flow
 * 
 * @package PHPFrarm\Core\Auth\OAuth
 */
class FacebookOAuthProvider implements OAuthProviderInterface
{
    private string $appId;
    private string $appSecret;
    private string $graphVersion = 'v18.0';
    private string $authUrl = 'https://www.facebook.com/v18.0/dialog/oauth';
    private string $tokenUrl = 'https://graph.facebook.com/v18.0/oauth/access_token';
    private string $userInfoUrl = 'https://graph.facebook.com/v18.0/me';

    public function __construct()
    {
        $this->appId = $_ENV['FACEBOOK_APP_ID'] ?? '';
        $this->appSecret = $_ENV['FACEBOOK_APP_SECRET'] ?? '';
    }

    public function getAuthorizationUrl(string $redirectUri, array $scopes = [], ?string $state = null): string
    {
        if (empty($scopes)) {
            $scopes = $this->getDefaultScopes();
        }

        $params = [
            'client_id' => $this->appId,
            'redirect_uri' => $redirectUri,
            'scope' => implode(',', $scopes),
            'response_type' => 'code'
        ];

        if ($state) {
            $params['state'] = $state;
        }

        return $this->authUrl . '?' . http_build_query($params);
    }

    public function getAccessToken(string $code, string $redirectUri): array
    {
        $params = [
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri
        ];

        $url = $this->tokenUrl . '?' . http_build_query($params);
        $response = $this->sendRequest($url, 'GET');

        return [
            'access_token' => $response['access_token'],
            'token_type' => $response['token_type'] ?? 'bearer',
            'expires_in' => $response['expires_in'] ?? 5184000, // Facebook default: 60 days
            'refresh_token' => null // Facebook doesn't use refresh tokens
        ];
    }

    public function getUserInfo(string $accessToken): array
    {
        $fields = 'id,name,email,first_name,last_name,picture.type(large)';
        $url = $this->userInfoUrl . '?fields=' . $fields . '&access_token=' . $accessToken;

        $response = $this->sendRequest($url, 'GET');

        return [
            'id' => $response['id'],
            'email' => $response['email'] ?? null,
            'name' => $response['name'] ?? null,
            'given_name' => $response['first_name'] ?? null,
            'family_name' => $response['last_name'] ?? null,
            'picture' => $response['picture']['data']['url'] ?? null,
            'email_verified' => true, // Facebook verifies emails
            'raw' => $response
        ];
    }

    public function refreshToken(string $refreshToken): array
    {
        throw new \Exception('Facebook does not support refresh tokens. Use long-lived tokens instead.');
    }

    /**
     * Exchange short-lived token for long-lived token (60 days)
     */
    public function getLongLivedToken(string $shortLivedToken): array
    {
        $params = [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'fb_exchange_token' => $shortLivedToken
        ];

        $url = $this->tokenUrl . '?' . http_build_query($params);
        $response = $this->sendRequest($url, 'GET');

        return [
            'access_token' => $response['access_token'],
            'token_type' => 'bearer',
            'expires_in' => $response['expires_in'] ?? 5184000
        ];
    }

    public function revokeToken(string $accessToken): bool
    {
        try {
            $url = "https://graph.facebook.com/{$this->graphVersion}/me/permissions?access_token={$accessToken}";
            $this->sendRequest($url, 'DELETE');
            return true;
        } catch (\Exception $e) {
            Logger::error('Facebook token revocation failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function getProviderName(): string
    {
        return 'facebook';
    }

    public function isConfigured(): bool
    {
        return !empty($this->appId) && !empty($this->appSecret);
    }

    public function getDefaultScopes(): array
    {
        return [
            'email',
            'public_profile'
        ];
    }

    /**
     * Send HTTP request to Facebook Graph API
     */
    private function sendRequest(string $url, string $method = 'GET', array $headers = []): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode < 200 || $httpCode >= 300) {
            $error = $result['error']['message'] ?? 'Facebook OAuth error';
            throw new \Exception("Facebook OAuth error: $error (HTTP $httpCode)");
        }

        return $result;
    }

    /**
     * Debug access token (verify validity and expiration)
     */
    public function debugToken(string $accessToken): array
    {
        $url = "https://graph.facebook.com/{$this->graphVersion}/debug_token";
        $url .= "?input_token={$accessToken}&access_token={$this->appId}|{$this->appSecret}";

        $response = $this->sendRequest($url, 'GET');

        return $response['data'] ?? [];
    }
}
