<?php

namespace App\Core\Resilience;

use App\Core\Cache\CacheManager;
use PHPFrarm\Core\Logger;

/**
 * Idempotency Key Manager
 * 
 * Provides idempotency support for safe retries.
 * Prevents duplicate processing of the same request.
 */
class IdempotencyKey
{
    private const CACHE_PREFIX = 'idempotency:';
    private const DEFAULT_TTL = 86400; // 24 hours

    private CacheManager $cache;

    public function __construct()
    {
        $this->cache = CacheManager::getInstance();
    }

    /**
     * Generate idempotency key from request
     */
    public static function generate(string $method, string $uri, array $body = []): string
    {
        $data = [
            'method' => $method,
            'uri' => $uri,
            'body' => $body,
        ];

        return hash('sha256', json_encode($data));
    }

    /**
     * Check if request has been processed before
     * 
     * @return array|null Previous response if exists, null otherwise
     */
    public function check(string $key): ?array
    {
        $cacheKey = self::CACHE_PREFIX . $key;
        
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            Logger::info('Idempotency key found', [
                'key' => $key,
                'cached_at' => $cached['cached_at'] ?? null
            ]);
        }

        return $cached;
    }

    /**
     * Store response for idempotency checking
     * 
     * @param string $key Idempotency key
     * @param mixed $response Response to cache
     * @param int $statusCode HTTP status code
     * @param int $ttl Time to live in seconds
     */
    public function store(string $key, $response, int $statusCode, int $ttl = self::DEFAULT_TTL): void
    {
        $cacheKey = self::CACHE_PREFIX . $key;

        $data = [
            'response' => $response,
            'status_code' => $statusCode,
            'cached_at' => time(),
            'idempotency_key' => $key,
        ];

        $this->cache->set($cacheKey, $data, $ttl);

        Logger::info('Idempotency key stored', [
            'key' => $key,
            'status_code' => $statusCode,
            'ttl' => $ttl
        ]);
    }

    /**
     * Check if key exists (without returning data)
     */
    public function exists(string $key): bool
    {
        $cacheKey = self::CACHE_PREFIX . $key;
        return $this->cache->has($cacheKey);
    }

    /**
     * Delete idempotency key
     */
    public function delete(string $key): void
    {
        $cacheKey = self::CACHE_PREFIX . $key;
        $this->cache->delete($cacheKey);

        Logger::debug('Idempotency key deleted', ['key' => $key]);
    }

    /**
     * Extract idempotency key from request headers
     */
    public static function fromHeaders(array $headers): ?string
    {
        // Standard header: Idempotency-Key
        if (isset($headers['Idempotency-Key'])) {
            return $headers['Idempotency-Key'];
        }

        // Alternative: X-Idempotency-Key
        if (isset($headers['X-Idempotency-Key'])) {
            return $headers['X-Idempotency-Key'];
        }

        return null;
    }

    /**
     * Validate idempotency key format
     */
    public static function isValid(string $key): bool
    {
        // Must be non-empty and reasonable length
        if (empty($key) || strlen($key) > 255) {
            return false;
        }

        // Must contain only alphanumeric, dash, underscore
        return (bool) preg_match('/^[a-zA-Z0-9_-]+$/', $key);
    }

    /**
     * Get singleton instance
     */
    private static ?IdempotencyKey $instance = null;

    public static function getInstance(): IdempotencyKey
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
