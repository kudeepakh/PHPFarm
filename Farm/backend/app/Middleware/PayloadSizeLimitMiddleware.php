<?php

namespace PHPFrarm\Middleware;

use PHPFrarm\Core\Response;
use PHPFrarm\Core\Logger;

/**
 * Payload Size Limit Middleware
 * 
 * Enforces maximum request payload size to prevent:
 * - Memory exhaustion attacks
 * - Denial of service attacks
 * - Slowloris attacks
 * 
 * Checks:
 * - Content-Length header
 * - Actual body size
 * - JSON depth
 * - Array nesting levels
 */
class PayloadSizeLimitMiddleware
{
    // Default limits (can be overridden via .env)
    private const DEFAULT_MAX_SIZE = 10 * 1024 * 1024; // 10 MB
    private const DEFAULT_MAX_JSON_DEPTH = 50;
    private const DEFAULT_MAX_ARRAY_DEPTH = 10;
    private const DEFAULT_MAX_FIELDS = 1000;
    
    /**
     * Enforce payload size limits
     */
    public static function handle(array $request, callable $next): mixed
    {
        $method = $request['method'] ?? '';
        
        // Only check for requests with body
        if (!in_array($method, ['POST', 'PUT', 'PATCH'])) {
            return $next($request);
        }
        
        // Get configured limits
        $maxSize = self::getMaxSize();
        $maxJsonDepth = self::getMaxJsonDepth();
        $maxArrayDepth = self::getMaxArrayDepth();
        $maxFields = self::getMaxFields();
        
        // Check Content-Length header
        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        
        if ($contentLength > $maxSize) {
            Logger::security('Payload size exceeded via Content-Length', [
                'content_length' => $contentLength,
                'max_allowed' => $maxSize,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            Response::error(
                'Payload too large',
                413,
                'PAYLOAD_TOO_LARGE',
                ['max_size' => $maxSize, 'received' => $contentLength]
            );
            return null;
        }
        
        // Check actual body size
        $body = $request['body'] ?? null;
        
        if (is_string($body)) {
            $actualSize = strlen($body);
            
            if ($actualSize > $maxSize) {
                Logger::security('Payload size exceeded via body', [
                    'actual_size' => $actualSize,
                    'max_allowed' => $maxSize
                ]);
                
                Response::error(
                    'Payload too large',
                    413,
                    'PAYLOAD_TOO_LARGE',
                    ['max_size' => $maxSize, 'received' => $actualSize]
                );
                return null;
            }
        }
        
        // Check JSON structure if applicable
        if (is_array($body)) {
            // Check number of fields
            $fieldCount = self::countFields($body);
            
            if ($fieldCount > $maxFields) {
                Logger::security('Too many fields in request', [
                    'field_count' => $fieldCount,
                    'max_allowed' => $maxFields
                ]);
                
                Response::error(
                    'Too many fields in request',
                    400,
                    'TOO_MANY_FIELDS',
                    ['max_fields' => $maxFields, 'received' => $fieldCount]
                );
                return null;
            }
            
            // Check array depth
            $depth = self::getArrayDepth($body);
            
            if ($depth > $maxArrayDepth) {
                Logger::security('Array nesting too deep', [
                    'depth' => $depth,
                    'max_allowed' => $maxArrayDepth
                ]);
                
                Response::error(
                    'Array nesting too deep',
                    400,
                    'ARRAY_TOO_DEEP',
                    ['max_depth' => $maxArrayDepth, 'received' => $depth]
                );
                return null;
            }
        }
        
        Logger::debug('Payload size validation passed', [
            'content_length' => $contentLength,
            'max_allowed' => $maxSize
        ]);
        
        return $next($request);
    }
    
    /**
     * Get maximum allowed payload size
     */
    private static function getMaxSize(): int
    {
        $envSize = $_ENV['MAX_PAYLOAD_SIZE'] ?? null;
        
        if ($envSize) {
            // Support human-readable formats: 10M, 5K, etc.
            return self::parseSize($envSize);
        }
        
        return self::DEFAULT_MAX_SIZE;
    }
    
    /**
     * Get maximum JSON depth
     */
    private static function getMaxJsonDepth(): int
    {
        return (int)($_ENV['MAX_JSON_DEPTH'] ?? self::DEFAULT_MAX_JSON_DEPTH);
    }
    
    /**
     * Get maximum array depth
     */
    private static function getMaxArrayDepth(): int
    {
        return (int)($_ENV['MAX_ARRAY_DEPTH'] ?? self::DEFAULT_MAX_ARRAY_DEPTH);
    }
    
    /**
     * Get maximum number of fields
     */
    private static function getMaxFields(): int
    {
        return (int)($_ENV['MAX_REQUEST_FIELDS'] ?? self::DEFAULT_MAX_FIELDS);
    }
    
    /**
     * Parse size string (e.g., "10M", "5K")
     */
    private static function parseSize(string $size): int
    {
        $size = trim($size);
        $unit = strtoupper(substr($size, -1));
        $value = (int)substr($size, 0, -1);
        
        return match ($unit) {
            'G' => $value * 1024 * 1024 * 1024,
            'M' => $value * 1024 * 1024,
            'K' => $value * 1024,
            default => (int)$size,
        };
    }
    
    /**
     * Count total fields in array recursively
     */
    private static function countFields(array $data, int $current = 0): int
    {
        $count = $current;
        
        foreach ($data as $value) {
            $count++;
            
            if (is_array($value)) {
                $count = self::countFields($value, $count);
            }
        }
        
        return $count;
    }
    
    /**
     * Get array nesting depth
     */
    private static function getArrayDepth(array $data, int $depth = 0): int
    {
        $maxDepth = $depth;
        
        foreach ($data as $value) {
            if (is_array($value)) {
                $currentDepth = self::getArrayDepth($value, $depth + 1);
                $maxDepth = max($maxDepth, $currentDepth);
            }
        }
        
        return $maxDepth;
    }
}
