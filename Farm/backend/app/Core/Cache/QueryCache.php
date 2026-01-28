<?php

namespace App\Core\Cache;

use PHPFrarm\Core\Database\DB;
use App\Core\Cache\CacheManager;
use PHPFrarm\Core\Logger;

/**
 * Query Cache Helper
 * 
 * Provides database query result caching with automatic invalidation.
 * Integrates with stored procedures pattern.
 */
class QueryCache
{
    private static ?QueryCache $instance = null;
    private CacheManager $cache;
    private array $config;

    private function __construct()
    {
        $this->cache = CacheManager::getInstance();
        $cacheConfig = require BASE_PATH . '/config/cache.php';
        $this->config = $cacheConfig['query'] ?? [];
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Cache a stored procedure call result
     * 
     * Example:
     * $users = QueryCache::call('sp_get_users', ['status' => 'active'], 600, ['users']);
     */
    public static function call(string $procedureName, array $params = [], ?int $ttl = null, array $tags = []): mixed
    {
        $instance = self::getInstance();
        
        if (!$instance->config['enabled']) {
            return self::executeStoredProcedure($procedureName, $params);
        }

        $key = $instance->generateKey($procedureName, $params);
        
        return $instance->cache->remember($key, function() use ($procedureName, $params) {
            return self::executeStoredProcedure($procedureName, $params);
        }, $ttl ?? $instance->config['ttl'], $tags);
    }

    /**
     * Cache a query result (for read-only views)
     * 
     * Example:
     * $data = QueryCache::query('SELECT * FROM vw_active_users', [], 300, ['users']);
     */
    public static function query(string $sql, array $params = [], ?int $ttl = null, array $tags = []): mixed
    {
        $instance = self::getInstance();
        
        if (!$instance->config['enabled']) {
            return DB::select($sql, $params);
        }

        $key = $instance->generateKey($sql, $params);
        
        return $instance->cache->remember($key, function() use ($sql, $params) {
            return DB::select($sql, $params);
        }, $ttl ?? $instance->config['ttl'], $tags);
    }

    /**
     * Invalidate cache by stored procedure name
     */
    public static function invalidateProcedure(string $procedureName, array $params = []): bool
    {
        $instance = self::getInstance();
        
        if (empty($params)) {
            // Invalidate all calls to this procedure
            return $instance->cache->flushPattern("query:{$procedureName}:*") > 0;
        }
        
        // Invalidate specific call
        $key = $instance->generateKey($procedureName, $params);
        return $instance->cache->delete($key);
    }

    /**
     * Invalidate cache by table name
     */
    public static function invalidateTable(string $tableName): int
    {
        $instance = self::getInstance();
        $tag = "table:{$tableName}";
        return $instance->cache->flushTags([$tag]);
    }

    /**
     * Get cache key for query
     */
    private function generateKey(string $query, array $params): string
    {
        $queryHash = md5($query);
        $paramsHash = !empty($params) ? md5(serialize($params)) : 'no-params';
        return "query:{$queryHash}:{$paramsHash}";
    }

    /**
     * Execute stored procedure
     */
    private static function executeStoredProcedure(string $procedureName, array $params): mixed
    {
        $placeholders = array_fill(0, count($params), '?');
        $sql = "CALL {$procedureName}(" . implode(', ', $placeholders) . ")";
        return DB::select($sql, array_values($params));
    }

    /**
     * Get cache statistics
     */
    public static function getStatistics(): array
    {
        $instance = self::getInstance();
        return $instance->cache->getStatistics();
    }

    /**
     * Enable query caching
     */
    public static function enable(): void
    {
        $instance = self::getInstance();
        $instance->config['enabled'] = true;
    }

    /**
     * Disable query caching
     */
    public static function disable(): void
    {
        $instance = self::getInstance();
        $instance->config['enabled'] = false;
    }

    /**
     * Check if query caching is enabled
     */
    public static function isEnabled(): bool
    {
        $instance = self::getInstance();
        return $instance->config['enabled'] ?? false;
    }
}
