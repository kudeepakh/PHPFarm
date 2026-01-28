<?php

namespace PHPFrarm\Core\Auth\OAuth;

use PHPFrarm\Core\Logger;

/**
 * LinkedIn OAuth Provider
 * 
 * Professional networking platform authentication.
 * 
 * Features:
 * - Professional profile data
 * - Work history
 * - Company information
 * - Member permissions
 * 
 * Setup:
 * 1. Create app at developers.linkedin.com
 * 2. Get Client ID and Client Secret
 * 3. Configure redirect URI
 * 4. Request "Sign In with LinkedIn" product
 * 
 * @package PHPFrarm\Core\Auth\OAuth
 */
class LinkedInOAuthProvider implements OAuthProviderInterface
{
    private string $clientId;
    private string $clientSecret;

    public function __construct()
    {
        $this->clientId = env('LINKEDIN_CLIENT_ID', '');
        $this->clientSecret = env('LINKEDIN_CLIENT_SECRET', '');
    }

    public function getAuthorizationUrl(string $redirectUri, array $scopes = [], string $state = ''): string
    {
        $scopes = $scopes ?: $this->getDefaultScopes();
        
        $params = [
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => implode(' ', $scopes)
        ];

        return 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query($params);
    }

    public function getAccessToken(string $code, string $redirectUri): array
    {
        $endpoint = 'https://www.linkedin.com/oauth/v2/accessToken';

        $data = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
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
            throw new \Exception($error['error_description'] ?? 'Token exchange failed');
        }

        return json_decode($response, true);
    }

    public function getUserInfo(string $accessToken): array
    {
        // Get basic profile
        $profileEndpoint = 'https://api.linkedin.com/v2/userinfo';

        $ch = curl_init($profileEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new \Exception('Failed to get LinkedIn user info');
        }

        $data = json_decode($response, true);

        return [
            'id' => $data['sub'] ?? '',
            'email' => $data['email'] ?? '',
            'name' => $data['name'] ?? '',
            'given_name' => $data['given_name'] ?? '',
            'family_name' => $data['family_name'] ?? '',
            'picture' => $data['picture'] ?? '',
            'locale' => $data['locale'] ?? '',
            'email_verified' => $data['email_verified'] ?? false
        ];
    }

    public function refreshToken(string $refreshToken): array
    {
        $endpoint = 'https://www.linkedin.com/oauth/v2/accessToken';

        $data = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret
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
        $endpoint = 'https://www.linkedin.com/oauth/v2/revoke';

        $data = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'token' => $accessToken
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

    public function getProviderName(): string
    {
        return 'linkedin';
    }

    public function isConfigured(): bool
    {
        return !empty($this->clientId) && !empty($this->clientSecret);
    }

    public function getDefaultScopes(): array
    {
        return ['openid', 'profile', 'email'];
    }
}
