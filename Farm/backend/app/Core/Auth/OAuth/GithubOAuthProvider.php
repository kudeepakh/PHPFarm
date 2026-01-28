<?php

namespace PHPFrarm\Core\Auth\OAuth;

use PHPFrarm\Core\Logger;

/**
 * GitHub OAuth 2.0 Provider
 * 
 * Implements GitHub OAuth authentication.
 * 
 * Setup:
 * 1. Go to https://github.com/settings/developers
 * 2. New OAuth App → Fill in app details
 * 3. Set Authorization callback URL
 * 4. Copy Client ID and Client Secret to .env
 * 
 * Documentation: https://docs.github.com/en/apps/oauth-apps/building-oauth-apps/authorizing-oauth-apps
 * 
 * @package PHPFrarm\Core\Auth\OAuth
 */
class GithubOAuthProvider implements OAuthProviderInterface
{
    private string $clientId;
    private string $clientSecret;
    private string $authUrl = 'https://github.com/login/oauth/authorize';
    private string $tokenUrl = 'https://github.com/login/oauth/access_token';
    private string $userInfoUrl = 'https://api.github.com/user';
    private string $userEmailUrl = 'https://api.github.com/user/emails';

    public function __construct()
    {
        $this->clientId = $_ENV['GITHUB_CLIENT_ID'] ?? '';
        $this->clientSecret = $_ENV['GITHUB_CLIENT_SECRET'] ?? '';
    }

    public function getAuthorizationUrl(string $redirectUri, array $scopes = [], ?string $state = null): string
    {
        if (empty($scopes)) {
            $scopes = $this->getDefaultScopes();
        }

        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', $scopes),
            'allow_signup' => 'true'
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
            'redirect_uri' => $redirectUri
        ];

        $response = $this->sendRequest($this->tokenUrl, 'POST', $payload);

        return [
            'access_token' => $response['access_token'],
            'token_type' => $response['token_type'] ?? 'bearer',
            'expires_in' => null, // GitHub tokens don't expire
            'refresh_token' => null, // GitHub doesn't use refresh tokens
            'scope' => $response['scope'] ?? null
        ];
    }

    public function getUserInfo(string $accessToken): array
    {
        $user = $this->sendRequest(
            $this->userInfoUrl,
            'GET',
            [],
            ['Authorization: Bearer ' . $accessToken]
        );

        // GitHub requires separate request for emails if user's email is private
        $email = $user['email'] ?? null;
        $emailVerified = false;

        if (empty($email) || !$emailVerified) {
            try {
                $emails = $this->sendRequest(
                    $this->userEmailUrl,
                    'GET',
                    [],
                    ['Authorization: Bearer ' . $accessToken]
                );

                // Find primary verified email
                foreach ($emails as $emailData) {
                    if ($emailData['primary'] && $emailData['verified']) {
                        $email = $emailData['email'];
                        $emailVerified = true;
                        break;
                    }
                }
            } catch (\Exception $e) {
                Logger::warning('GitHub email fetch failed', ['error' => $e->getMessage()]);
            }
        }

        return [
            'id' => (string)$user['id'],
            'email' => $email,
            'name' => $user['name'] ?? $user['login'],
            'given_name' => null, // GitHub doesn't provide separate first/last names
            'family_name' => null,
            'picture' => $user['avatar_url'] ?? null,
            'email_verified' => $emailVerified,
            'username' => $user['login'],
            'profile_url' => $user['html_url'],
            'bio' => $user['bio'] ?? null,
            'location' => $user['location'] ?? null,
            'company' => $user['company'] ?? null,
            'raw' => $user
        ];
    }

    public function refreshToken(string $refreshToken): array
    {
        throw new \Exception('GitHub does not support refresh tokens. Tokens do not expire.');
    }

    public function revokeToken(string $accessToken): bool
    {
        try {
            $url = "https://api.github.com/applications/{$this->clientId}/token";
            
            $payload = json_encode(['access_token' => $accessToken]);
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'DELETE',
                CURLOPT_USERPWD => $this->clientId . ':' . $this->clientSecret,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/vnd.github+json'
                ],
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_TIMEOUT => 10
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $httpCode === 204;
        } catch (\Exception $e) {
            Logger::error('GitHub token revocation failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function getProviderName(): string
    {
        return 'github';
    }

    public function isConfigured(): bool
    {
        return !empty($this->clientId) && !empty($this->clientSecret);
    }

    public function getDefaultScopes(): array
    {
        return [
            'read:user',
            'user:email'
        ];
    }

    /**
     * Send HTTP request to GitHub API
     */
    private function sendRequest(string $url, string $method = 'GET', array $data = [], array $headers = []): array
    {
        $ch = curl_init();

        $defaultHeaders = [
            'Accept: application/json',
            'User-Agent: PHPFrarm-OAuth'
        ];
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
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode < 200 || $httpCode >= 300) {
            $error = $result['error_description'] ?? $result['message'] ?? 'GitHub OAuth error';
            throw new \Exception("GitHub OAuth error: $error (HTTP $httpCode)");
        }

        return $result;
    }
}
