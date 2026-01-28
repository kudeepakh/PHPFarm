<?php

namespace PHPFrarm\Middleware;

use PHPFrarm\Core\Response;
use PHPFrarm\Core\Logger;
use PHPFrarm\Core\TraceContext;

/**
 * Request Timeout Middleware
 * 
 * Enforces maximum execution time for requests to prevent resource exhaustion
 * 
 * Features:
 * - Configurable timeout per route type
 * - Graceful timeout handling
 * - Cleanup on timeout
 * - Monitoring and alerting
 */
class TimeoutMiddleware
{
    /**
     * Default timeout values (in seconds)
     */
    private static array $defaultTimeouts = [
        'default' => 30,          // Default for most operations
        'upload' => 120,          // File upload operations
        'report' => 180,          // Report generation
        'bulk' => 300,           // Bulk operations
        'admin' => 60,           // Admin operations
        'auth' => 15,            // Authentication operations
        'health' => 5            // Health checks
    ];
    
    /**
     * Route patterns and their timeout categories
     */
    private static array $routeTimeouts = [
        '/health' => 'health',
        '/auth/' => 'auth',
        '/upload' => 'upload',
        '/report' => 'report',
        '/bulk' => 'bulk',
        '/system/' => 'admin',
    ];
    
    /**
     * Handle request with timeout enforcement
     */
    public static function handle(array $request, callable $next): mixed
    {
        $timeout = self::getTimeoutForRequest($request);
        $startTime = microtime(true);
        
        // Set PHP execution timeout
        $oldTimeLimit = ini_get('max_execution_time');
        set_time_limit($timeout);
        
        // Register shutdown function to detect timeouts
        $timeoutDetected = false;
        register_shutdown_function(function() use (&$timeoutDetected, $timeout, $startTime, $request, $oldTimeLimit) {
            $error = error_get_last();
            $executionTime = microtime(true) - $startTime;
            
            // Check if timeout occurred
            if ($executionTime >= $timeout || 
                ($error && strpos($error['message'], 'Maximum execution time') !== false)) {
                
                $timeoutDetected = true;
                
                self::logTimeout($request, $timeout, $executionTime);
                
                // Restore original time limit
                set_time_limit($oldTimeLimit);
                
                // Send timeout response if not already sent
                if (!headers_sent()) {
                    self::sendTimeoutResponse($timeout, $executionTime);
                }
            }
        });
        
        try {
            // Execute the request
            $result = $next($request);
            
            $executionTime = microtime(true) - $startTime;
            
            // Log slow requests (>50% of timeout)
            if ($executionTime > ($timeout * 0.5)) {
                Logger::warning('Slow request detected', [
                    'execution_time' => $executionTime,
                    'timeout' => $timeout,
                    'threshold' => $timeout * 0.5,
                    'uri' => $request['uri'] ?? 'unknown',
                    'method' => $request['method'] ?? 'unknown',
                    'correlation_id' => TraceContext::getCorrelationId()
                ]);
            }
            
            // Restore original time limit
            set_time_limit($oldTimeLimit);
            
            return $result;
            
        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;
            
            // Check if this was a timeout
            if ($executionTime >= $timeout) {
                self::logTimeout($request, $timeout, $executionTime);
                self::sendTimeoutResponse($timeout, $executionTime);
                return null;
            }
            
            // Restore original time limit and re-throw
            set_time_limit($oldTimeLimit);
            throw $e;
        }
    }
    
    /**
     * Determine timeout for the current request
     */
    private static function getTimeoutForRequest(array $request): int
    {
        $uri = $request['uri'] ?? '';
        $method = strtoupper($request['method'] ?? 'GET');
        
        // Check for custom timeout header
        $customTimeout = self::getCustomTimeout($request);
        if ($customTimeout > 0) {
            return min($customTimeout, 600); // Max 10 minutes
        }
        
        // Match against route patterns
        foreach (self::$routeTimeouts as $pattern => $category) {
            if (strpos($uri, $pattern) !== false) {
                return self::$defaultTimeouts[$category];
            }
        }
        
        // Adjust based on HTTP method
        return match($method) {
            'POST', 'PUT', 'PATCH' => self::$defaultTimeouts['default'] + 10, // Extra time for write operations
            'DELETE' => self::$defaultTimeouts['default'] + 5,
            default => self::$defaultTimeouts['default']
        };
    }
    
    /**
     * Get custom timeout from request headers
     */
    private static function getCustomTimeout(array $request): int
    {
        $headers = $request['headers'] ?? [];
        
        $customTimeout = $headers['X-Request-Timeout'] ?? 
                        $headers['Request-Timeout'] ?? 
                        0;
        
        return (int) $customTimeout;
    }
    
    /**
     * Log timeout occurrence
     */
    private static function logTimeout(array $request, int $timeout, float $executionTime): void
    {
        Logger::error('Request timeout occurred', [
            'timeout' => $timeout,
            'execution_time' => $executionTime,
            'uri' => $request['uri'] ?? 'unknown',
            'method' => $request['method'] ?? 'unknown',
            'user_agent' => $request['headers']['User-Agent'] ?? 'unknown',
            'ip_address' => $request['headers']['X-Forwarded-For'] ?? 
                           $request['headers']['X-Real-IP'] ?? 
                           $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'correlation_id' => TraceContext::getCorrelationId(),
            'transaction_id' => TraceContext::getTransactionId(),
            'request_id' => TraceContext::getRequestId(),
        ]);
        
        // Also log to security log for potential DoS detection
        Logger::security('Request timeout detected', [
            'timeout' => $timeout,
            'execution_time' => $executionTime,
            'uri' => $request['uri'] ?? 'unknown',
            'ip_address' => $request['headers']['X-Forwarded-For'] ?? 
                           $request['headers']['X-Real-IP'] ?? 
                           $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'correlation_id' => TraceContext::getCorrelationId()
        ]);
    }
    
    /**
     * Send timeout response to client
     */
    private static function sendTimeoutResponse(int $timeout, float $executionTime): void
    {
        // Clear any existing output
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Set appropriate status code and headers
        http_response_code(504); // Gateway Timeout
        header('Content-Type: application/json');
        header('X-Timeout: true');
        header("X-Timeout-Limit: $timeout");
        header("X-Execution-Time: " . number_format($executionTime, 3));
        
        // Add trace context headers
        TraceContext::setResponseHeaders();
        
        // Send timeout response
        $response = [
            'success' => false,
            'message' => 'Request timeout',
            'error_code' => 'ERR_REQUEST_TIMEOUT',
            'timeout_seconds' => $timeout,
            'execution_time' => number_format($executionTime, 3),
            'trace' => TraceContext::getAll(),
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
        
        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        // Force output and exit
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        
        exit;
    }
    
    /**
     * Get timeout configuration for a specific operation type
     */
    public static function getTimeout(string $operation = 'default'): int
    {
        return self::$defaultTimeouts[$operation] ?? self::$defaultTimeouts['default'];
    }
    
    /**
     * Set timeout for a specific operation type
     */
    public static function setTimeout(string $operation, int $seconds): void
    {
        if ($seconds > 0 && $seconds <= 600) { // Max 10 minutes
            self::$defaultTimeouts[$operation] = $seconds;
        }
    }
}