<?php

namespace PHPFrarm\Core\Auth\OAuth;

use PHPFrarm\Core\Logger;

/**
 * Microsoft/Azure AD OAuth Provider
 * 
 * Supports both:
 * - Microsoft Account (personal) - outlook.com, hotmail.com, live.com
 * - Azure AD (enterprise) - work/school accounts
 * 
 * Features:
 * - Single Sign-On (SSO)
 * - Multi-tenant support
 * - Conditional access policies
 * - MFA integration
 * - Extensive user profile
 * 
 * Setup:
 * 1. Register app in Azure Portal (portal.azure.com)
 * 2. Choose account type (personal, organizational, both)
 * 3. Get Application (client) ID
 * 4. Create client secret
 * 5. Configure redirect URI
 * 
 * @package PHPFrarm\Core\Auth\OAuth
 */
class MicrosoftOAuthProvider implements OAuthProviderInterface
{
    private string $clientId;
    private string $clientSecret;
    private string $tenant; // 'common', 'organizations', 'consumers', or tenant ID

    public function __construct()
    {
        $this->clientId = env('MICROSOFT_CLIENT_ID', '');
        $this->clientSecret = env('MICROSOFT_CLIENT_SECRET', '');
        $this->tenant = env('MICROSOFT_TENANT', 'common'); // common = any account
    }

    public function getAuthorizationUrl(string $redirectUri, array $scopes = [], ?string $state = null): string
    {
        $scopes = $scopes ?: $this->getDefaultScopes();
        
        $params = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'response_mode' => 'query',
            'scope' => implode(' ', $scopes),
            'state' => $state ?? ''
        ];

        return "https://login.microsoftonline.com/{$this->tenant}/oauth2/v2.0/authorize?" . http_build_query($params);
    }

    public function getAccessToken(string $code, string $redirectUri): array
    {
        $endpoint = "https://login.microsoftonline.com/{$this->tenant}/oauth2/v2.0/token";

        $data = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code'
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
        $endpoint = 'https://graph.microsoft.com/v1.0/me';

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            $error = json_decode($response, true);
            throw new \Exception($error['error']['message'] ?? 'Failed to get user info');
        }

        $data = json_decode($response, true);

        return [
            'id' => $data['id'] ?? '',
            'email' => $data['mail'] ?? $data['userPrincipalName'] ?? '',
            'name' => $data['displayName'] ?? '',
            'given_name' => $data['givenName'] ?? '',
            'family_name' => $data['surname'] ?? '',
            'job_title' => $data['jobTitle'] ?? '',
            'office_location' => $data['officeLocation'] ?? '',
            'preferred_language' => $data['preferredLanguage'] ?? '',
            'mobile_phone' => $data['mobilePhone'] ?? '',
            'business_phones' => $data['businessPhones'] ?? []
        ];
    }

    public function refreshToken(string $refreshToken): array
    {
        $endpoint = "https://login.microsoftonline.com/{$this->tenant}/oauth2/v2.0/token";

        $data = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token'
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
        // Microsoft Graph doesn't have direct revoke endpoint
        // Tokens expire automatically (1 hour for access, 90 days for refresh)
        Logger::info('Microsoft tokens expire automatically', [
            'access_token_expiry' => '1 hour',
            'refresh_token_expiry' => '90 days'
        ]);

        return true;
    }

    public function getProviderName(): string
    {
        return 'microsoft';
    }

    public function isConfigured(): bool
    {
        return !empty($this->clientId) && !empty($this->clientSecret);
    }

    public function getDefaultScopes(): array
    {
        return ['openid', 'profile', 'email', 'User.Read'];
    }
}
