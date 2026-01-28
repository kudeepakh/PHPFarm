<?php

namespace PHPFrarm\Middleware;

use PHPFrarm\Core\Logger;

/**
 * Secure Headers Middleware
 * 
 * Automatically adds security headers to all HTTP responses
 * 
 * Headers Applied:
 * - X-Frame-Options: DENY (prevent clickjacking)
 * - X-Content-Type-Options: nosniff (prevent MIME sniffing)
 * - X-XSS-Protection: 1; mode=block (XSS filter for older browsers)
 * - Strict-Transport-Security: HSTS for HTTPS
 * - Content-Security-Policy: Control resource loading
 * - Referrer-Policy: Control referer information
 * - Permissions-Policy: Control browser features
 */
class SecureHeadersMiddleware
{
    /**
     * Apply security headers
     */
    public static function handle(array $request, callable $next): mixed
    {
        // Add security headers before passing to next middleware
        self::applyHeaders();
        
        Logger::debug('Security headers applied');
        
        return $next($request);
    }
    
    /**
     * Apply all security headers
     */
    private static function applyHeaders(): void
    {
        // Prevent clickjacking attacks
        header('X-Frame-Options: DENY');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Enable XSS protection (legacy browsers)
        header('X-XSS-Protection: 1; mode=block');
        
        // Force HTTPS (if enabled)
        if (self::isHttpsEnabled()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
        
        // Content Security Policy
        $csp = self::getCSP();
        if ($csp) {
            header("Content-Security-Policy: {$csp}");
        }
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Permissions Policy (formerly Feature-Policy)
        $permissionsPolicy = self::getPermissionsPolicy();
        if ($permissionsPolicy) {
            header("Permissions-Policy: {$permissionsPolicy}");
        }
        
        // Remove server signature
        header_remove('X-Powered-By');
        header('Server: PHPFrarm');
        
        // Prevent browser caching of sensitive data (API responses)
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
    
    /**
     * Check if HTTPS is enabled
     */
    private static function isHttpsEnabled(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
            || $_SERVER['SERVER_PORT'] == 443
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }
    
    /**
     * Get Content Security Policy
     */
    private static function getCSP(): string
    {
        // Default CSP for API (very restrictive)
        $cspDirectives = [
            "default-src 'none'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
        ];
        
        // Load from environment if available
        $customCSP = $_ENV['CSP_POLICY'] ?? null;
        if ($customCSP) {
            return $customCSP;
        }
        
        return implode('; ', $cspDirectives);
    }
    
    /**
     * Get Permissions Policy
     */
    private static function getPermissionsPolicy(): string
    {
        // Disable dangerous browser features
        $policies = [
            'geolocation=()',
            'microphone=()',
            'camera=()',
            'payment=()',
            'usb=()',
            'magnetometer=()',
            'gyroscope=()',
            'accelerometer=()',
        ];
        
        // Load from environment if available
        $customPolicy = $_ENV['PERMISSIONS_POLICY'] ?? null;
        if ($customPolicy) {
            return $customPolicy;
        }
        
        return implode(', ', $policies);
    }
    
    /**
     * NOTE: CORS headers are now handled exclusively in CommonMiddleware::cors()
     * This prevents header duplication and browser conflicts.
     * SecureHeadersMiddleware focuses only on security headers.
     */
}
