<?php

namespace PHPFrarm\Modules\System\Controllers;

use App\Core\Cache\CacheManager;
use App\Core\Cache\QueryCache;
use App\Core\Cache\CacheWarmer;
use PHPFrarm\Core\Response;
use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Attributes\RouteGroup;
use App\Core\Validation\Validator;
use App\Core\Cache\Attributes\NoCache;

/**
 * Cache Admin Controller
 * 
 * Provides admin APIs for cache management, statistics, and invalidation.
 * All endpoints require 'permission:cache:manage' authorization.
 */
#[RouteGroup('/api/v1/system/cache', middleware: ['cors', 'auth'])]
class CacheController
{
    private CacheManager $cache;

    public function __construct()
    {
        $this->cache = CacheManager::getInstance();
    }

    /**
     * Get cache statistics
     * 
     * GET /api/v1/system/cache/statistics
     * Permissions: cache:read
     */
    #[NoCache(reason: 'Real-time statistics')]
    #[Route('/statistics', method: 'GET', middleware: ['auth', 'permission:cache:read'])]
    public function getStatistics(array $request): void
    {
        $stats = $this->cache->getStatistics();

        Response::success([
            'statistics' => $stats,
            'enabled' => $this->cache->isEnabled(),
            'query_cache_enabled' => QueryCache::isEnabled(),
        ], 'admin.cache.statistics_retrieved');
    }

    /**
     * Clear all cache
     * 
     * POST /api/v1/system/cache/clear
     * Permissions: cache:manage
     */
    #[Route('/clear', method: 'POST', middleware: ['auth', 'permission:cache:manage'])]
    public function clearAll(array $request): void
    {
        $result = $this->cache->flushAll();

        Response::success([
            'cleared' => $result
        ], 'admin.cache.cleared_all');
    }

    /**
     * Clear cache by tags
     * 
     * POST /api/v1/system/cache/clear-tags
     * Body: { "tags": ["users", "posts"] }
     * Permissions: cache:manage
     */
    #[Route('/clear-tags', method: 'POST', middleware: ['auth', 'permission:cache:manage'])]
    public function clearTags(array $request): void
    {
        $data = Validator::validate($request['body'] ?? [], [
            'tags' => 'required|array',
            'tags.*' => 'required|string'
        ]);

        $count = $this->cache->flushTags($data['tags']);
        
        Response::success([
            'tags' => $data['tags'],
            'keys_cleared' => $count
        ], 'admin.cache.keys_cleared');
    }

    /**
     * Clear cache by pattern
     * 
     * POST /api/v1/system/cache/clear-pattern
     * Body: { "pattern": "user:*" }
     * Permissions: cache:manage
     */
    #[Route('/clear-pattern', method: 'POST', middleware: ['auth', 'permission:cache:manage'])]
    public function clearPattern(array $request): void
    {
        $data = Validator::validate($request['body'] ?? [], [
            'pattern' => 'required|string'
        ]);

        $count = $this->cache->flushPattern($data['pattern']);
        
        Response::success([
            'pattern' => $data['pattern'],
            'keys_cleared' => $count
        ], 'admin.cache.keys_cleared_pattern');
    }

    /**
     * Clear cache by key
     * 
     * DELETE /api/v1/system/cache/keys/{key}
     * Permissions: cache:manage
     */
    #[Route('/keys/{key}', method: 'DELETE', middleware: ['auth', 'permission:cache:manage'])]
    public function clearKey(array $request, string $key): void
    {
        $result = $this->cache->delete($key);

        Response::success([
            'key' => $key,
            'cleared' => $result
        ], $result ? 'admin.cache.key_cleared' : 'admin.cache.key_not_found');
    }

    /**
     * Warm cache
     * 
     * POST /api/v1/system/cache/warm
     * Body: { "endpoints": ["/api/v1/config", "/api/v1/roles"] } (optional)
     * Permissions: cache:manage
     */
    #[Route('/warm', method: 'POST', middleware: ['auth', 'permission:cache:manage'])]
    public function warm(array $request): void
    {
        $warmer = new CacheWarmer();

        $endpoints = $request['body']['endpoints'] ?? null;
        
        if ($endpoints) {
            $results = [];
            foreach ($endpoints as $endpoint) {
                $results[$endpoint] = $warmer->warmEndpoint($endpoint);
            }
        } else {
            $results = $warmer->warmAll();
        }
        
        Response::success([
            'results' => $results
        ], 'admin.cache.warming_completed');
    }

    /**
     * Get cache keys
     * 
     * GET /api/v1/system/cache/keys?pattern=user:*&limit=100
     * Permissions: cache:read
     */
    #[NoCache]
    #[Route('/keys', method: 'GET', middleware: ['auth', 'permission:cache:read'])]
    public function getKeys(array $request): void
    {
        $pattern = $request['query']['pattern'] ?? '*';
        $limit = (int) ($request['query']['limit'] ?? 100);
        
        // This would need to be implemented in RedisDriver
        // For now, return a message
        Response::success([
            'message' => 'Key listing requires Redis SCAN implementation',
            'pattern' => $pattern,
            'limit' => $limit
        ], 'admin.cache.keys_query');
    }

    /**
     * Check if key exists
     * 
     * GET /api/v1/system/cache/check/{key}
     * Permissions: cache:read
     */
    #[NoCache]
    #[Route('/check/{key}', method: 'GET', middleware: ['auth', 'permission:cache:read'])]
    public function checkKey(array $request, string $key): void
    {
        $exists = $this->cache->has($key);
        
        $data = [
            'key' => $key,
            'exists' => $exists
        ];
        
        if ($exists) {
            $value = $this->cache->get($key);
            $data['value_type'] = gettype($value);
            $data['value_size'] = is_string($value) ? strlen($value) : null;
        }
        
        Response::success($data, $exists ? 'admin.cache.key_exists' : 'admin.cache.key_not_found');
    }

    /**
     * Invalidate table cache
     * 
     * POST /api/v1/system/cache/invalidate-table
     * Body: { "table": "users" }
     * Permissions: cache:manage
     */
    #[Route('/invalidate-table', method: 'POST', middleware: ['auth', 'permission:cache:manage'])]
    public function invalidateTable(array $request): void
    {
        $data = Validator::validate($request['body'] ?? [], [
            'table' => 'required|string'
        ]);

        $count = QueryCache::invalidateTable($data['table']);
        
        Response::success([
            'table' => $data['table'],
            'keys_invalidated' => $count
        ], 'admin.cache.table_invalidated');
    }

    /**
     * Toggle cache
     * 
     * POST /api/v1/system/cache/toggle
     * Body: { "enabled": true }
     * Permissions: cache:manage
     */
    #[Route('/toggle', method: 'POST', middleware: ['auth', 'permission:cache:manage'])]
    public function toggle(array $request): void
    {
        $data = Validator::validate($request['body'] ?? [], [
            'enabled' => 'required|boolean'
        ]);

        if ($data['enabled']) {
            $this->cache->enable();
            $message = 'admin.cache.enabled';
        } else {
            $this->cache->disable();
            $message = 'admin.cache.disabled';
        }
        
        Response::success([
            'enabled' => $data['enabled']
        ], $message);
    }

    /**
     * Get cache configuration
     * 
     * GET /api/v1/system/cache/config
     * Permissions: cache:read
     */
    #[NoCache]
    #[Route('/config', method: 'GET', middleware: ['auth', 'permission:cache:read'])]
    public function getConfig(array $request): void
    {
        $config = require BASE_PATH . '/config/cache.php';
        
        // Remove sensitive information
        unset($config['redis']['password']);
        
        Response::success([
            'config' => $config
        ], 'admin.cache.config_retrieved');
    }
}
