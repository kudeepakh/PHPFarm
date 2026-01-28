<?php

namespace App\Core\Cache;

use App\Core\Cache\CacheManager;
use PHPFrarm\Core\Logger;

/**
 * Cache Warmer Service
 * 
 * Proactively populates cache with frequently accessed data.
 */
class CacheWarmer
{
    private CacheManager $cache;
    private array $config;

    public function __construct()
    {
        $this->cache = CacheManager::getInstance();
        $cacheConfig = require BASE_PATH . '/config/cache.php';
        $this->config = $cacheConfig['warming'] ?? [];
    }

    /**
     * Warm all configured endpoints
     */
    public function warmAll(): array
    {
        if (!$this->config['enabled']) {
            return ['status' => 'disabled'];
        }

        $results = [];
        $endpoints = $this->config['endpoints'] ?? [];

        Logger::info('Cache warming started', ['endpoints_count' => count($endpoints)]);

        foreach ($endpoints as $endpoint) {
            $results[$endpoint] = $this->warmEndpoint($endpoint);
        }

        Logger::info('Cache warming completed', [
            'total' => count($results),
            'success' => count(array_filter($results)),
            'failed' => count(array_filter($results, fn($r) => !$r))
        ]);

        return $results;
    }

    /**
     * Warm a specific endpoint
     */
    public function warmEndpoint(string $endpoint): bool
    {
        try {
            Logger::debug('Warming endpoint', ['endpoint' => $endpoint]);

            // Make internal HTTP request to the endpoint
            $response = $this->makeRequest($endpoint);

            if ($response['status'] >= 200 && $response['status'] < 300) {
                Logger::debug('Endpoint warmed successfully', ['endpoint' => $endpoint]);
                return true;
            }

            Logger::warning('Endpoint warming failed', [
                'endpoint' => $endpoint,
                'status' => $response['status']
            ]);
            return false;
        } catch (\Exception $e) {
            Logger::error('Endpoint warming error', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Warm specific data using callback
     */
    public function warm(string $key, callable $callback, ?int $ttl = null, array $tags = []): bool
    {
        try {
            $value = $callback();
            return $this->cache->set($key, $value, $ttl, $tags);
        } catch (\Exception $e) {
            Logger::error('Cache warm error', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Warm multiple keys
     */
    public function warmBatch(array $items): array
    {
        $results = [];

        foreach ($items as $item) {
            $key = $item['key'];
            $callback = $item['callback'];
            $ttl = $item['ttl'] ?? null;
            $tags = $item['tags'] ?? [];

            $results[$key] = $this->warm($key, $callback, $ttl, $tags);
        }

        return $results;
    }

    /**
     * Make internal HTTP request
     */
    private function makeRequest(string $endpoint): array
    {
        $baseUrl = env('APP_URL', 'http://localhost');
        $url = $baseUrl . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception($error);
        }

        return [
            'status' => $statusCode,
            'body' => $response
        ];
    }

    /**
     * Schedule cache warming (for cron job)
     */
    public static function schedule(): void
    {
        $warmer = new self();
        $warmer->warmAll();
    }
}
