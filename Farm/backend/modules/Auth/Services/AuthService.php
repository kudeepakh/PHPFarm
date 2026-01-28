<?php

namespace PHPFrarm\Modules\Auth\Services;

use Firebase\JWT\JWT;
use PHPFrarm\Modules\Auth\DAO\UserDAO;
use PHPFrarm\Modules\Auth\DAO\SessionDAO;
use PHPFrarm\Core\Logger;
use PHPFrarm\Core\Database;
use PHPFrarm\Core\Utils\UuidGenerator;
use PHPFrarm\Modules\UserManagement\Services\AuthorizationService;
use PHPFrarm\Modules\UserManagement\Services\AccountStatusService;
use PHPFrarm\Modules\UserManagement\Services\IdentifierService;
use PHPFrarm\Core\Queue\JobQueue;
use PHPFrarm\Core\Queue\Jobs\SendVerificationEmailJob;

/**
 * Auth Service - Business logic for authentication
 */
class AuthService
{
    private UserDAO $userDAO;
    private SessionDAO $sessionDAO;

    public function __construct()
    {
        $this->userDAO = new UserDAO();
        $this->sessionDAO = new SessionDAO();
    }

    /**
     * Unified registration with email AND/OR phone
     * 
     * Industry standard approach:
     * - At least one identifier required
     * - Both can be provided and linked to same user
     * - Each identifier must be globally unique
     * - Email is verified via link, phone via OTP
     */
    public function register(?string $email, ?string $phone, string $password, ?string $firstName, ?string $lastName): array
    {
        // At least one identifier required
        if (empty($email) && empty($phone)) {
            throw new \Exception('At least one identifier (email or phone) is required');
        }

        // Check if email already exists
        if (!empty($email)) {
            $existingEmail = $this->userDAO->checkIdentifierExists($email);
            if ($existingEmail) {
                throw new \Exception('Email already registered');
            }
        }

        // Check if phone already exists
        if (!empty($phone)) {
            $existingPhone = $this->userDAO->checkIdentifierExists($phone);
            if ($existingPhone) {
                throw new \Exception('Phone already registered');
            }
        }

        // Generate IDs and hash password
        $userId = bin2hex(random_bytes(16));
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        
        $response = [
            'user_id' => $userId,
            'identifiers' => []
        ];

        // Determine primary identifier (email takes precedence if both provided)
        $primaryType = !empty($email) ? 'email' : 'phone';

        // Create user with primary identifier first
        if (!empty($email)) {
            $emailIdentifierId = bin2hex(random_bytes(16));
            $result = $this->userDAO->registerWithEmail(
                $userId, 
                $emailIdentifierId, 
                $email, 
                $passwordHash, 
                $firstName, 
                $lastName
            );

            if (empty($result['success'])) {
                throw new \Exception($result['message'] ?? 'Registration failed');
            }

            // Queue verification email job (async - doesn't slow down API response)
            try {
                $job = new SendVerificationEmailJob([
                    'user_id' => $userId,
                    'email' => $email,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ]);
                
                $jobId = JobQueue::dispatch($job, 'high');
                
                if ($jobId) {
                    $response['verification_email_queued'] = true;
                    Logger::info('Verification email job queued', [
                        'user_id' => $userId,
                        'email' => $email,
                        'job_id' => $jobId,
                    ]);
                } else {
                    $response['verification_email_queued'] = false;
                    Logger::warning('Failed to queue verification email job', [
                        'user_id' => $userId,
                        'email' => $email,
                    ]);
                }
            } catch (\Exception $e) {
                Logger::error('Failed to queue verification email', [
                    'user_id' => $userId,
                    'email' => $email,
                    'error' => $e->getMessage()
                ]);
                $response['verification_email_queued'] = false;
            }

            $response['identifiers'][] = [
                'type' => 'email',
                'value' => $email,
                'is_primary' => ($primaryType === 'email'),
                'verified' => false,
                'verification_method' => 'email_link'
            ];
        }

        // If phone is also provided, add it as secondary identifier
        if (!empty($phone)) {
            $phoneIdentifierId = bin2hex(random_bytes(16));
            
            if (empty($email)) {
                // Phone is primary - use registerWithPhone which includes OTP
                $otpId = bin2hex(random_bytes(16));
                $otpCode = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                $otpHash = hash('sha256', $otpCode);
                $otpExpiresAt = new \DateTime('+15 minutes');
                
                $result = $this->userDAO->registerWithPhone(
                    $userId, 
                    $phoneIdentifierId, 
                    $phone, 
                    $passwordHash, 
                    $firstName, 
                    $lastName,
                    $otpId,
                    $otpHash,
                    $otpExpiresAt
                );

                if (empty($result['success'])) {
                    throw new \Exception($result['message'] ?? 'Registration failed');
                }

                $response['otp_id'] = $otpId;
                $response['otp_expires_at'] = $otpExpiresAt->format('Y-m-d H:i:s');
                
                // In dev mode, include OTP
                if (($_ENV['APP_ENV'] ?? 'production') !== 'production') {
                    $response['dev_otp'] = $otpCode;
                }
            } else {
                // Email is primary, phone is secondary - add phone identifier
                // Parameters: identifierId, userId, type, value, isVerified, isPrimary
                $this->userDAO->addIdentifier($phoneIdentifierId, $userId, 'phone', $phone, false, false);
            }

            $response['identifiers'][] = [
                'type' => 'phone',
                'value' => substr($phone, 0, 4) . '****' . substr($phone, -2),
                'is_primary' => ($primaryType === 'phone'),
                'verified' => false,
                'verification_method' => 'sms_otp'
            ];
        }

        Logger::audit('User registered', [
            'user_id' => $userId,
            'identifiers' => array_column($response['identifiers'], 'type'),
            'primary' => $primaryType
        ]);

        return $response;
    }

    /**
     * Register with phone only (requires OTP verification)
     * Kept for backward compatibility with phone registration endpoint
     */
    public function registerWithPhone(
        string $phone, 
        string $password, 
        ?string $firstName, 
        ?string $lastName,
        string $otpHash,
        \DateTime $otpExpiresAt
    ): array {
        // Check if phone identifier already exists
        $existing = $this->userDAO->checkIdentifierExists($phone);
        if ($existing) {
            throw new \Exception('Phone already registered');
        }

        // Generate IDs and hash password
        $userId = bin2hex(random_bytes(16));
        $identifierId = bin2hex(random_bytes(16));
        $otpId = bin2hex(random_bytes(16));
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        // Create user with phone identifier + OTP (atomic operation)
        $result = $this->userDAO->registerWithPhone(
            $userId, 
            $identifierId, 
            $phone, 
            $passwordHash, 
            $firstName, 
            $lastName,
            $otpId,
            $otpHash,
            $otpExpiresAt
        );

        if (empty($result['success'])) {
            throw new \Exception($result['message'] ?? 'Registration failed');
        }

        Logger::audit('User registered with phone (pending verification)', [
            'user_id' => $userId,
            'phone' => substr($phone, 0, 4) . '***' // Mask phone in logs
        ]);

        return [
            'user_id' => $userId,
            'otp_id' => $result['otp_id'] ?? $otpId,
            'message' => 'Registration initiated. Please verify your phone with OTP.'
        ];
    }

    public function login(string $identifier, string $password): array
    {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // Get user by ANY identifier (email, phone, username)
        $user = $this->userDAO->getUserByIdentifier($identifier);

        if (!$user) {
            Logger::security('Login attempt with invalid identifier', [
                'identifier' => $identifier,
                'ip' => $ipAddress
            ]);
            throw new \Exception('Invalid credentials');
        }
        
        // Check if identifier is verified
        if (empty($user['is_verified'])) {
            Logger::security('Login attempt with unverified identifier', [
                'identifier' => $identifier,
                'identifier_type' => $user['identifier_type'] ?? 'unknown',
                'ip' => $ipAddress
            ]);
            throw new \Exception('Please verify your ' . ($user['identifier_type'] ?? 'account') . ' before logging in');
        }
        
        $userId = $user['id'];
        $tokenVersion = (int)($user['token_version'] ?? 0);
        
        // Check account status
        if (isset($user['status']) && $user['status'] !== 'active') {
            Logger::security('Login blocked due to account status', [
                'user_id' => $userId,
                'identifier' => $identifier,
                'status' => $user['status'],
                'ip' => $ipAddress
            ]);
            throw new \Exception('Account is not active. Status: ' . $user['status']);
        }

        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            Logger::security('Login attempt with invalid password', [
                'user_id' => $userId,
                'identifier' => $identifier,
                'ip' => $ipAddress
            ]);
            
            throw new \Exception('Invalid credentials');
        }

        // Update last login
        $this->userDAO->updateLastLogin($userId);

        // Build primary identifier for token (prefer email, then phone)
        $primaryIdentifier = $user['identifier_value'] ?? $identifier;
        
        // Generate access and refresh tokens with session tracking
        $authClaims = $this->buildAuthClaims($userId);
        $role = $authClaims['primary_role'] ?? 'user';
        $tokens = $this->issueTokens(
            $userId,
            $primaryIdentifier,
            $role,
            $tokenVersion,
            null,
            true,
            $authClaims['roles'],
            $authClaims['scopes']
        );

        Logger::audit('User logged in', [
            'user_id' => $userId,
            'identifier_type' => $user['identifier_type'] ?? 'email'
        ]);

        return [
            'token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_at' => $tokens['access_expires_at'],
            'refresh_expires_at' => $tokens['refresh_expires_at'],
            'user' => [
                'id' => $userId,
                'identifier' => $primaryIdentifier,
                'identifier_type' => $user['identifier_type'] ?? 'email',
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'roles' => $authClaims['roles'],
                'permissions' => $authClaims['scopes']
            ]
        ];
    }

    public function loginWithOtpIdentifier(string $identifier): array
    {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Get user by any identifier
        $user = $this->userDAO->getUserByIdentifier($identifier);

        if (!$user) {
            Logger::security('OTP login attempt with invalid identifier', [
                'identifier' => $identifier,
                'ip' => $ipAddress
            ]);
            throw new \Exception('User not found');
        }

        $userId = $user['id'];
        $tokenVersion = (int)($user['token_version'] ?? 0);

        if (isset($user['status']) && $user['status'] !== 'active') {
            Logger::security('OTP login blocked due to account status', [
                'user_id' => $userId,
                'identifier' => $identifier,
                'status' => $user['status'],
                'ip' => $ipAddress
            ]);
            throw new \Exception('Account is not active. Status: ' . $user['status']);
        }

        $this->userDAO->updateLastLogin($userId);

        $primaryIdentifier = $user['identifier_value'] ?? $identifier;
        
        $authClaims = $this->buildAuthClaims($userId);
        $role = $authClaims['primary_role'] ?? 'user';
        $tokens = $this->issueTokens(
            $userId,
            $primaryIdentifier,
            $role,
            $tokenVersion,
            null,
            true,
            $authClaims['roles'],
            $authClaims['scopes']
        );

        Logger::audit('User logged in via OTP', [
            'user_id' => $userId,
            'identifier_type' => $user['identifier_type'] ?? 'email'
        ]);

        return [
            'token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_at' => $tokens['access_expires_at'],
            'refresh_expires_at' => $tokens['refresh_expires_at'],
            'user' => [
                'id' => $userId,
                'identifier' => $primaryIdentifier,
                'identifier_type' => $user['identifier_type'] ?? 'email',
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name']
            ]
        ];
    }

    public function refreshToken(string $refreshToken): array
    {
        $decoded = $this->decodeToken($refreshToken, 'refresh');

        $userId = $decoded['user_id'] ?? null;
        $sessionId = $decoded['sid'] ?? null;
        if (!$userId || !$sessionId) {
            throw new \Exception('Invalid refresh token');
        }

        $user = $this->userDAO->getUserById($userId);
        if (!$user) {
            throw new \Exception('User not found');
        }

        $tokenVersion = (int)($user['token_version'] ?? 0);
        if (isset($decoded['tv']) && (int)$decoded['tv'] !== $tokenVersion) {
            throw new \Exception('Token version mismatch');
        }

        $refreshHash = hash('sha256', $refreshToken);
        $session = $this->sessionDAO->getActiveSessionByRefreshHash($refreshHash);
        if (!$session || $session['id'] !== $sessionId) {
            throw new \Exception('Refresh token revoked or expired');
        }

        // Get primary identifier for the user (email or phone)
        $identifiers = $this->userDAO->getUserIdentifiers($userId);
        $primaryIdentifier = null;
        foreach ($identifiers as $identifier) {
            if ($identifier['is_primary']) {
                $primaryIdentifier = $identifier['value'];
                break;
            }
        }

        // Fallback to first identifier if no primary found
        if (!$primaryIdentifier && !empty($identifiers)) {
            $primaryIdentifier = $identifiers[0]['value'];
        }

        // If still no identifier, use user_id as fallback
        if (!$primaryIdentifier) {
            $primaryIdentifier = $userId;
            Logger::warning('No identifier found for user during token refresh', [
                'user_id' => $userId
            ]);
        }

        $role = $decoded['role'] ?? 'user';
        $roles = $decoded['roles'] ?? [];
        $scopes = $decoded['scopes'] ?? [];
        $tokens = $this->issueTokens($userId, $primaryIdentifier, $role, $tokenVersion, $sessionId, false, $roles, $scopes);

        // Rotate stored session hashes
        $this->sessionDAO->updateSessionTokens(
            $sessionId,
            $tokens['access_token_hash'],
            $tokens['refresh_token_hash'],
            $tokens['access_expires_at'],
            $tokens['refresh_expires_at']
        );

        return $tokens;
    }

    /**
     * Get user by ID
     */
    public function getUserById(string $userId): ?array
    {
        return $this->userDAO->getUserById($userId);
    }

    public function logout(?string $sessionId): void
    {
        if ($sessionId) {
            $this->sessionDAO->revokeSession($sessionId);
        }
    }

    private function issueTokens(
        string $userId,
        string $email,
        string $role,
        int $tokenVersion,
        ?string $sessionId = null,
        bool $createSession = false,
        array $roles = [],
        array $scopes = []
    ): array {
        $now = time();
        $accessExp = $now + (int)($_ENV['JWT_EXPIRY'] ?? 3600);
        $refreshExp = $now + (int)($_ENV['JWT_REFRESH_EXPIRY'] ?? 604800);

        $sessionId = $sessionId ?? UuidGenerator::v4();
        $accessJti = UuidGenerator::v4();
        $refreshJti = UuidGenerator::v4();

        $accessPayload = [
            'typ' => 'access',
            'jti' => $accessJti,
            'sid' => $sessionId,
            'tv' => $tokenVersion,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $accessExp,
            'user_id' => $userId,
            'email' => $email,
            'role' => $role,
            'roles' => array_values(array_unique($roles)),
            'scopes' => array_values(array_unique($scopes))
        ];

        $refreshPayload = [
            'typ' => 'refresh',
            'jti' => $refreshJti,
            'sid' => $sessionId,
            'tv' => $tokenVersion,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $refreshExp,
            'user_id' => $userId,
            'email' => $email,
            'role' => $role,
            'roles' => array_values(array_unique($roles)),
            'scopes' => array_values(array_unique($scopes))
        ];

        $accessToken = $this->encodeToken($accessPayload);
        $refreshToken = $this->encodeToken($refreshPayload);

        $accessHash = hash('sha256', $accessToken);
        $refreshHash = hash('sha256', $refreshToken);

        $accessExpiresAt = date('Y-m-d H:i:s', $accessExp);
        $refreshExpiresAt = date('Y-m-d H:i:s', $refreshExp);

        if ($createSession) {
            $this->sessionDAO->createSession(
                $sessionId,
                $userId,
                $accessHash,
                $refreshHash,
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                $accessExpiresAt,
                $refreshExpiresAt
            );
        }

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'access_token_hash' => $accessHash,
            'refresh_token_hash' => $refreshHash,
            'access_expires_at' => $accessExpiresAt,
            'refresh_expires_at' => $refreshExpiresAt,
            'session_id' => $sessionId
        ];
    }

    private function encodeToken(array $payload): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? 'secret';
        $algorithm = $_ENV['JWT_ALGORITHM'] ?? 'HS256';
        return JWT::encode($payload, $secret, $algorithm);
    }

    private function decodeToken(string $token, string $expectedType): array
    {
        $secret = $_ENV['JWT_SECRET'] ?? 'secret';
        $algorithm = $_ENV['JWT_ALGORITHM'] ?? 'HS256';

        $decoded = (array) JWT::decode($token, new \Firebase\JWT\Key($secret, $algorithm));
        if (($decoded['typ'] ?? '') !== $expectedType) {
            throw new \Exception('Invalid token type');
        }

        return $decoded;
    }

    private function buildAuthClaims(string $userId): array
    {
        $authz = AuthorizationService::getUserAuthorizationData($userId);
        $roles = array_map(
            fn($role) => $role['name'] ?? $role['role_name'] ?? null,
            $authz['roles'] ?? []
        );
        $roles = array_values(array_filter($roles));

        // Ensure every user has at least 'user' role if no roles assigned
        if (empty($roles)) {
            $roles = ['user'];
        }

        $scopes = [];
        $defaultScopes = array_filter(array_map('trim', explode(',', $_ENV['JWT_DEFAULT_SCOPES'] ?? '')));
        if (!empty($defaultScopes)) {
            $scopes = array_merge($scopes, $defaultScopes);
        }

        $usePermissionScopes = ($_ENV['JWT_SCOPE_FROM_PERMISSIONS'] ?? 'true') === 'true';
        if ($usePermissionScopes) {
            $permissionScopes = $authz['permission_names'] ?? [];
            $scopes = array_merge($scopes, $permissionScopes);
        }

        return [
            'roles' => $roles,
            'scopes' => array_values(array_unique($scopes)),
            'primary_role' => $roles[0] ?? 'user'
        ];
    }
}
