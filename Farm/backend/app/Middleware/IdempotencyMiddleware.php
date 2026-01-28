<?php

namespace PHPFrarm\Middleware;

use PHPFrarm\Core\Database;
use PHPFrarm\Core\Response;
use PHPFrarm\Core\Logger;
use PHPFrarm\Core\TraceContext;

/**
 * Idempotency Middleware
 * 
 * Prevents duplicate processing of requests using Idempotency-Key header
 * 
 * Usage:
 * - Client sends "Idempotency-Key: <unique-key>" header
 * - Middleware checks if request was already processed
 * - Returns cached response for duplicate requests
 * - Stores new responses for future duplicate detection
 * 
 * Applies only to: POST, PUT, PATCH requests (state-changing operations)
 */
class IdempotencyMiddleware
{
    /**
     * HTTP methods that support idempotency
     */
    private static array $idempotentMethods = ['POST', 'PUT', 'PATCH'];
    
    /**
     * Default TTL for idempotency keys (24 hours)
     */
    private static int $defaultTtlHours = 24;
    
    /**
     * Maximum size of response to cache (1MB)
     */
    private static int $maxResponseSize = 1048576;
    
    /**
     * Handle idempotency for state-changing requests
     */
    public static function handle(array $request, callable $next): mixed
    {
        $method = strtoupper($request['method'] ?? 'GET');
        
        // Only apply to state-changing methods
        if (!in_array($method, self::$idempotentMethods, true)) {
            return $next($request);
        }
        
        // Get idempotency key from header
        $idempotencyKey = self::getIdempotencyKey($request);
        
        // If no key provided, proceed without idempotency
        if (empty($idempotencyKey)) {
            return $next($request);
        }
        
        // Validate key format (must be UUID or similar)
        if (!self::isValidIdempotencyKey($idempotencyKey)) {
            Response::badRequest('validation.failed', [
                'idempotency_key' => 'Idempotency key must be a valid UUID or alphanumeric string (max 255 chars)'
            ]);
            return null;
        }
        
        // Create request hash for duplicate detection
        $requestHash = self::createRequestHash($request);
        
        // Check if request was already processed
        $existingResponse = self::getExistingResponse($idempotencyKey, $requestHash);
        
        if ($existingResponse) {
            // Return cached response
            self::returnCachedResponse($existingResponse);
            return null;
        }
        
        // Process new request
        ob_start();
        $response = $next($request);
        $responseBody = ob_get_clean();
        
        // Store response for future idempotency checks
        self::storeResponse($idempotencyKey, $requestHash, $responseBody);
        
        // Return the response
        echo $responseBody;
        return $response;
    }
    
    /**
     * Get idempotency key from request headers
     */
    private static function getIdempotencyKey(array $request): ?string
    {
        $headers = $request['headers'] ?? [];
        
        // Check various header formats
        return $headers['Idempotency-Key'] ?? 
               $headers['X-Idempotency-Key'] ?? 
               $headers['idempotency-key'] ?? 
               null;
    }
    
    /**
     * Validate idempotency key format
     */
    private static function isValidIdempotencyKey(string $key): bool
    {
        // Must be 1-255 characters, alphanumeric with dashes/underscores
        if (strlen($key) < 1 || strlen($key) > 255) {
            return false;
        }
        
        // Allow UUID format or alphanumeric with common separators
        return preg_match('/^[a-zA-Z0-9\-_]+$/', $key) === 1;
    }
    
    /**
     * Create deterministic hash of request for duplicate detection
     */
    private static function createRequestHash(array $request): string
    {
        $hashData = [
            'method' => strtoupper($request['method'] ?? 'GET'),
            'uri' => $request['uri'] ?? '',
            'query' => $request['query'] ?? [],
            'body' => $request['body'] ?? '',
            'content_type' => $request['headers']['Content-Type'] ?? '',
        ];
        
        // Normalize and sort query parameters
        if (is_array($hashData['query'])) {
            ksort($hashData['query']);
        }
        
        // Create deterministic JSON representation
        $normalized = json_encode($hashData, JSON_SORT_KEYS);
        
        return hash('sha256', $normalized);
    }
    
    /**
     * Get existing response for idempotency key
     */
    private static function getExistingResponse(string $idempotencyKey, string $requestHash): ?array
    {
        try {
            $result = Database::callProcedure('sp_get_idempotent_request', [
                $idempotencyKey,
                $requestHash
            ]);
            
            if (empty($result) || $result[0]['status'] !== 'valid') {
                return null;
            }
            
            return $result[0];
        } catch (\Exception $e) {
            Logger::error('Failed to get idempotent request', [
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Return cached response to client
     */
    private static function returnCachedResponse(array $cachedResponse): void
    {
        $statusCode = $cachedResponse['status_code'] ?? 200;
        $headers = json_decode($cachedResponse['response_headers'] ?? '{}', true) ?: [];
        $body = $cachedResponse['response_body'] ?? '';
        
        // Set status code
        http_response_code($statusCode);
        
        // Add idempotency header
        header('X-Idempotent: true');
        header('X-Idempotent-Replayed: true');
        
        // Set cached headers (excluding some that shouldn't be replayed)
        $excludedHeaders = ['date', 'server', 'x-powered-by'];
        foreach ($headers as $name => $value) {
            if (!in_array(strtolower($name), $excludedHeaders, true)) {
                header("$name: $value");
            }
        }
        
        // Set trace context headers for correlation
        TraceContext::setResponseHeaders();
        
        echo $body;
        
        Logger::info('Idempotent request replayed', [
            'idempotency_key' => $cachedResponse['idempotency_key'],
            'original_created_at' => $cachedResponse['created_at'],
            'status_code' => $statusCode
        ]);
    }
    
    /**
     * Store response for future idempotency checks
     */
    private static function storeResponse(string $idempotencyKey, string $requestHash, string $responseBody): void
    {
        // Don't store responses that are too large
        if (strlen($responseBody) > self::$maxResponseSize) {
            Logger::warning('Response too large for idempotency storage', [
                'idempotency_key' => $idempotencyKey,
                'response_size' => strlen($responseBody),
                'max_size' => self::$maxResponseSize
            ]);
            return;
        }
        
        // Get response headers
        $responseHeaders = [];
        foreach (headers_list() as $header) {
            if (strpos($header, ':') !== false) {
                list($name, $value) = explode(':', $header, 2);
                $responseHeaders[trim($name)] = trim($value);
            }
        }
        
        try {
            Database::callProcedure('sp_store_idempotent_request', [
                $idempotencyKey,
                $requestHash,
                $responseBody,
                json_encode($responseHeaders),
                http_response_code(),
                TraceContext::getCorrelationId(),
                TraceContext::getTransactionId(),
                TraceContext::getRequestId(),
                self::$defaultTtlHours
            ]);
            
            Logger::debug('Idempotent response stored', [
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'response_size' => strlen($responseBody),
                'ttl_hours' => self::$defaultTtlHours
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to store idempotent response', [
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'error' => $e->getMessage()
            ]);
        }
    }
}