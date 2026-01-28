<?php

namespace PHPFrarm\Middleware;

use PHPFrarm\Core\Security\CSRFProtection;

/**
 * CSRF Protection Middleware
 * 
 * Validates CSRF tokens for state-changing requests (POST, PUT, PATCH, DELETE)
 * Automatically skips validation for:
 * - GET, HEAD, OPTIONS requests (safe methods)
 * - Public API routes (they use JWT tokens for protection)
 * - API routes using Bearer token authentication
 */
class CSRFMiddleware
{
    /**
     * State-changing HTTP methods that require CSRF protection
     */
    private static array $protectedMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];
    
    /**
     * Validate CSRF token for state-changing requests
     */
    public static function handle(array $request, callable $next): mixed
    {
        $method = strtoupper($request['method'] ?? 'GET');
        
        // Skip CSRF validation for safe methods (GET, HEAD, OPTIONS)
        if (!in_array($method, self::$protectedMethods, true)) {
            return $next($request);
        }
        
        // Skip CSRF for public API routes (they don't have session-based auth)
        if (isset($request['_is_public_route']) && $request['_is_public_route'] === true) {
            return $next($request);
        }
        
        // Skip CSRF for Bearer token authenticated requests (APIs use JWT, not sessions)
        $authHeader = $request['headers']['Authorization'] ?? '';
        if (str_starts_with($authHeader, 'Bearer ')) {
            return $next($request);
        }
        
        // Apply CSRF protection for session-based state-changing methods
        return CSRFProtection::middleware($request, $next);
    }
}
