<?php

namespace PHPFrarm\Modules\Auth\Services;

use PHPFrarm\Core\Database;
use PHPFrarm\Core\Logger;
use PHPFrarm\Core\TraceContext;

/**
 * Phone Authentication Service
 * 
 * Handles JWT token generation and session management for phone-based authentication.
 * Extracted from PhoneLoginController to follow service layer pattern.
 */
class PhoneAuthService
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * Generate JWT tokens for phone authentication
     * 
     * @param array $userData User data including user_id, email, phone, names, status, role
     * @return array JWT access and refresh tokens with expiry information
     */
    public function generateJWTTokens(array $userData): array
    {
        // Delegate to AuthService for token generation
        // AuthService.issueTokens is private, so we use login method which calls it
        // For phone login, we don't create session here (will be done separately)
        
        $userId = $userData['user_id'];
        $email = $userData['email'] ?? '';
        $role = $userData['role'] ?? 'user';
        $tokenVersion = (int)($userData['token_version'] ?? 0);
        $sessionId = $userData['session_id'];
        
        $now = time();
        $accessExp = $now + (int)(env('JWT_EXPIRY', 3600)); // 1 hour
        $refreshExp = $now + (int)(env('JWT_REFRESH_EXPIRY', 604800)); // 7 days
        
        // Since AuthService.issueTokens is private, we'll implement token generation here
        // This maintains consistency with existing JWT structure
        $accessToken = $this->createAccessToken([
            'user_id' => $userId,
            'email' => $email,
            'phone' => $userData['phone'] ?? '',
            'first_name' => $userData['first_name'] ?? '',
            'last_name' => $userData['last_name'] ?? '',
            'role' => $role,
            'session_id' => $sessionId,
            'token_version' => $tokenVersion,
            'login_method' => $userData['login_method'] ?? 'phone_otp'
        ], $accessExp);
        
        $refreshToken = $this->createRefreshToken([
            'user_id' => $userId,
            'session_id' => $sessionId,
            'token_version' => $tokenVersion
        ], $refreshExp);
        
        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => (int)(env('JWT_EXPIRY', 3600)),
            'access_expires_at' => date('Y-m-d H:i:s', $accessExp),
            'refresh_expires_at' => date('Y-m-d H:i:s', $refreshExp)
        ];
    }

    /**
     * Create user session record via stored procedure
     * 
     * @param array $sessionData Session data including session_id, user_id, tokens, device info
     * @return void
     */
    public function createUserSession(array $sessionData): void
    {
        try {
            // Hash tokens before storing (security best practice)
            $accessTokenHash = hash('sha256', $sessionData['access_token']);
            $refreshTokenHash = hash('sha256', $sessionData['refresh_token']);
            
            // Call stored procedure to create session
            Database::callProcedure('sp_create_user_session', [
                $sessionData['session_id'],
                $sessionData['user_id'],
                $accessTokenHash,
                $refreshTokenHash,
                $sessionData['device_info'] ?? 'unknown',
                $sessionData['ip_address'] ?? 'unknown',
                $sessionData['access_expires_at'],
                $sessionData['refresh_expires_at'],
                TraceContext::getCorrelationId()
            ]);
            
            Logger::info('User session created', [
                'session_id' => $sessionData['session_id'],
                'user_id' => $sessionData['user_id'],
                'correlation_id' => TraceContext::getCorrelationId()
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Failed to create user session', [
                'error' => $e->getMessage(),
                'user_id' => $sessionData['user_id'] ?? 'unknown',
                'session_id' => $sessionData['session_id'] ?? 'unknown',
                'correlation_id' => TraceContext::getCorrelationId()
            ]);
            
            // Don't fail the login for session creation errors
            // Session can be created on next request or manually cleaned up
        }
    }

    /**
     * Create JWT access token
     * 
     * @param array $payload Token payload
     * @param int $expiresAt Expiration timestamp
     * @return string Encoded JWT token
     */
    private function createAccessToken(array $payload, int $expiresAt): string
    {
        $now = time();
        
        $claims = [
            'typ' => 'access',
            'iat' => $now,
            'nbf' => $now,
            'exp' => $expiresAt,
            'user_id' => $payload['user_id'],
            'email' => $payload['email'],
            'phone' => $payload['phone'] ?? '',
            'first_name' => $payload['first_name'] ?? '',
            'last_name' => $payload['last_name'] ?? '',
            'role' => $payload['role'] ?? 'user',
            'session_id' => $payload['session_id'],
            'token_version' => $payload['token_version'] ?? 0,
            'login_method' => $payload['login_method'] ?? 'phone_otp'
        ];
        
        return $this->encodeToken($claims);
    }

    /**
     * Create JWT refresh token
     * 
     * @param array $payload Token payload
     * @param int $expiresAt Expiration timestamp
     * @return string Encoded JWT token
     */
    private function createRefreshToken(array $payload, int $expiresAt): string
    {
        $now = time();
        
        $claims = [
            'typ' => 'refresh',
            'iat' => $now,
            'nbf' => $now,
            'exp' => $expiresAt,
            'user_id' => $payload['user_id'],
            'session_id' => $payload['session_id'],
            'token_version' => $payload['token_version'] ?? 0
        ];
        
        return $this->encodeToken($claims);
    }

    /**
     * Encode JWT token using Firebase JWT library
     * 
     * @param array $payload Token claims
     * @return string Encoded JWT token
     */
    private function encodeToken(array $payload): string
    {
        $secret = env('JWT_SECRET', 'your-secret-key-change-in-production');
        $algorithm = env('JWT_ALGORITHM', 'HS256');
        
        return \Firebase\JWT\JWT::encode($payload, $secret, $algorithm);
    }
}
