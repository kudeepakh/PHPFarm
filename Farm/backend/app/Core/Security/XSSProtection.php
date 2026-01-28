<?php

namespace PHPFrarm\Core\Security;

/**
 * XSS Protection Utility
 * 
 * Provides input sanitization and output encoding to prevent XSS attacks
 * 
 * Usage:
 * - XSSProtection::sanitize($input) - Clean user input
 * - XSSProtection::encode($output) - Encode for HTML output
 * - XSSProtection::sanitizeArray($data) - Recursive sanitization
 */
class XSSProtection
{
    /**
     * Sanitize a single value
     */
    public static function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            return self::sanitizeArray($value);
        }
        
        if (!is_string($value)) {
            return $value;
        }
        
        // Remove null bytes
        $value = str_replace(chr(0), '', $value);
        
        // Strip HTML tags (keep only safe ones)
        $value = strip_tags($value);
        
        // Remove any remaining script tags (case-insensitive)
        $value = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/is', '', $value);
        
        // Remove javascript: and data: protocols
        $value = preg_replace('/javascript:/i', '', $value);
        $value = preg_replace('/data:text\/html/i', '', $value);
        
        // Remove event handlers
        $value = preg_replace('/on\w+\s*=\s*["\']?[^"\']*["\']?/i', '', $value);
        
        return trim($value);
    }
    
    /**
     * Sanitize an array recursively
     */
    public static function sanitizeArray(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            $sanitizedKey = self::sanitize($key);
            
            if (is_array($value)) {
                $sanitized[$sanitizedKey] = self::sanitizeArray($value);
            } else {
                $sanitized[$sanitizedKey] = self::sanitize($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * HTML entity encode for output
     */
    public static function encode(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Decode HTML entities
     */
    public static function decode(string $value): string
    {
        return htmlspecialchars_decode($value, ENT_QUOTES | ENT_HTML5);
    }
    
    /**
     * Sanitize for URL
     */
    public static function sanitizeUrl(string $url): string
    {
        // Remove any protocols except http/https
        $url = preg_replace('/^(?!https?:\/\/).*/i', '', $url);
        
        // Filter URL
        $filtered = filter_var($url, FILTER_SANITIZE_URL);
        
        // Validate URL
        if (filter_var($filtered, FILTER_VALIDATE_URL) === false) {
            return '';
        }
        
        return $filtered;
    }
    
    /**
     * Sanitize for SQL (additional layer, use prepared statements!)
     */
    public static function sanitizeSql(string $value): string
    {
        // Remove SQL injection attempts
        $patterns = [
            '/(\bUNION\b|\bSELECT\b|\bINSERT\b|\bUPDATE\b|\bDELETE\b|\bDROP\b|\bCREATE\b)/i',
            '/--/',
            '/;/',
            '/\/\*.*\*\//',
        ];
        
        foreach ($patterns as $pattern) {
            $value = preg_replace($pattern, '', $value);
        }
        
        return addslashes($value);
    }
    
    /**
     * Sanitize filename
     */
    public static function sanitizeFilename(string $filename): string
    {
        // Remove path traversal attempts
        $filename = basename($filename);
        
        // Remove any characters that aren't alphanumeric, dash, underscore, or dot
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        
        // Remove multiple dots (directory traversal)
        $filename = preg_replace('/\.+/', '.', $filename);
        
        return $filename;
    }
    
    /**
     * Check if string contains XSS patterns
     */
    public static function containsXSS(string $value): bool
    {
        $xssPatterns = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/is',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i',
            '/eval\s*\(/i',
        ];
        
        foreach ($xssPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Middleware-friendly sanitization
     */
    public static function sanitizeRequest(array $request): array
    {
        if (isset($request['body']) && is_array($request['body'])) {
            $request['body'] = self::sanitizeArray($request['body']);
        }
        
        if (isset($request['query']) && is_array($request['query'])) {
            $request['query'] = self::sanitizeArray($request['query']);
        }
        
        if (isset($request['params']) && is_array($request['params'])) {
            $request['params'] = self::sanitizeArray($request['params']);
        }
        
        return $request;
    }
}
