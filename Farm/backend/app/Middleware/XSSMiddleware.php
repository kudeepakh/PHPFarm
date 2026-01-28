<?php

namespace PHPFrarm\Middleware;

use PHPFrarm\Core\Security\XSSProtection;
use PHPFrarm\Core\Logger;

/**
 * XSS Protection Middleware
 * 
 * Automatically sanitizes all incoming request data
 */
class XSSMiddleware
{
    /**
     * Sanitize request data
     */
    public static function handle(array $request, callable $next): mixed
    {
        // Sanitize all request data
        $request = XSSProtection::sanitizeRequest($request);
        
        Logger::debug('XSS sanitization applied');
        
        return $next($request);
    }
}
