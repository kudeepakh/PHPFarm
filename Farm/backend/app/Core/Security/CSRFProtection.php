<?php

namespace PHPFrarm\Core\Security;

use PHPFrarm\Core\Logger;

/**
 * CSRF Protection Manager
 * 
 * Provides token-based CSRF protection for state-changing operations
 * 
 * Usage:
 * 1. Generate token: CSRFProtection::generateToken()
 * 2. Validate token: CSRFProtection::validateToken($token)
 * 3. Add to forms: <input type="hidden" name="csrf_token" value="<?= CSRFProtection::getToken() ?>">
 * 
 * Token Storage: Session-based (can be extended to Redis)
 */
class CSRFProtection
{
    private static string $tokenKey = '_csrf_token';
    private static int $tokenLifetime = 3600; // 1 hour
    
    /**
     * Generate a new CSRF token
     */
    public static function generateToken(): string
    {
        // Ensure session is started
        self::ensureSession();
        
        // Generate cryptographically secure token
        $token = bin2hex(random_bytes(32));
        
        // Store token with timestamp
        $_SESSION[self::$tokenKey] = [
            'token' => $token,
            'timestamp' => time()
        ];
        
        Logger::debug('CSRF token generated', [
            'session_id' => session_id()
        ]);
        
        return $token;
    }
    
    /**
     * Get the current CSRF token (generates if not exists)
     */
    public static function getToken(): string
    {
        self::ensureSession();
        
        if (!isset($_SESSION[self::$tokenKey]) || self::isTokenExpired()) {
            return self::generateToken();
        }
        
        return $_SESSION[self::$tokenKey]['token'];
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateToken(?string $token): bool
    {
        self::ensureSession();
        
        // No token provided
        if (empty($token)) {
            Logger::security('CSRF validation failed: No token provided');
            return false;
        }
        
        // No stored token
        if (!isset($_SESSION[self::$tokenKey])) {
            Logger::security('CSRF validation failed: No stored token');
            return false;
        }
        
        $storedData = $_SESSION[self::$tokenKey];
        
        // Token expired
        if (self::isTokenExpired()) {
            Logger::security('CSRF validation failed: Token expired');
            self::clearToken();
            return false;
        }
        
        // Compare tokens (timing-safe)
        $isValid = hash_equals($storedData['token'], $token);
        
        if (!$isValid) {
            Logger::security('CSRF validation failed: Token mismatch');
        }
        
        return $isValid;
    }
    
    /**
     * Clear CSRF token
     */
    public static function clearToken(): void
    {
        self::ensureSession();
        unset($_SESSION[self::$tokenKey]);
    }
    
    /**
     * Rotate CSRF token (use after successful validation)
     */
    public static function rotateToken(): string
    {
        self::clearToken();
        return self::generateToken();
    }
    
    /**
     * Check if token has expired
     */
    private static function isTokenExpired(): bool
    {
        if (!isset($_SESSION[self::$tokenKey]['timestamp'])) {
            return true;
        }
        
        $age = time() - $_SESSION[self::$tokenKey]['timestamp'];
        return $age > self::$tokenLifetime;
    }
    
    /**
     * Ensure session is started
     */
    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Configure secure session
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_secure', '1'); // HTTPS only
            ini_set('session.cookie_samesite', 'Strict');
            
            session_start();
        }
    }
    
    /**
     * Set token lifetime
     */
    public static function setTokenLifetime(int $seconds): void
    {
        self::$tokenLifetime = $seconds;
    }
    
    /**
     * Extract token from request
     */
    public static function extractTokenFromRequest(array $request): ?string
    {
        // Check body (JSON or form data)
        if (isset($request['body']['csrf_token'])) {
            return $request['body']['csrf_token'];
        }
        
        // Check headers (common for SPA)
        $headers = $request['headers'] ?? [];
        if (isset($headers['X-CSRF-Token'])) {
            return $headers['X-CSRF-Token'];
        }
        if (isset($headers['x-csrf-token'])) {
            return $headers['x-csrf-token'];
        }
        
        return null;
    }
    
    /**
     * Middleware for automatic CSRF validation
     */
    public static function middleware(array $request, callable $next): mixed
    {
        $method = $request['method'] ?? '';
        
        // Only validate state-changing methods
        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            return $next($request);
        }
        
        // Extract token from request
        $token = self::extractTokenFromRequest($request);
        
        // Validate token
        if (!self::validateToken($token)) {
            Logger::security('CSRF attack prevented', [
                'method' => $method,
                'path' => $request['path'] ?? 'unknown',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'CSRF_TOKEN_INVALID',
                'message' => 'CSRF token validation failed',
            ]);
            exit;
        }
        
        // Token valid - rotate for next request (optional, security best practice)
        // self::rotateToken();
        
        return $next($request);
    }
}
