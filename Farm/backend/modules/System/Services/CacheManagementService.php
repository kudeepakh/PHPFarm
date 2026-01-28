<?php

namespace PHPFrarm\Modules\System\Services;

use PHPFrarm\Core\Logger;
use Redis;

/**
 * Cache Management Service
 * 
 * Business logic for cache operations and statistics.
 * Extracted from CacheStatsController to follow service layer pattern.
 * 
 * @package PHPFrarm\Modules\System\Services
 */
class CacheManagementService
{
    /**
     * Get Redis statistics
     * 
     * @return array Redis stats or sample data if Redis unavailable
     */
    public function getRedisStats(): array
    {
        try {
            $redis = new Redis();
            $config = $this->getRedisConfig();
            
            $connected = @$redis->connect($config['host'], $config['port']);
            
            if (!$connected) {
                return $this->getSampleStats();
            }
            
            $info = $redis->info();
            $totalKeys = $redis->dbSize();
            $redis->close();
            
            return [
                'connected' => true,
                'version' => $info['redis_version'] ?? 'unknown',
                'uptime_days' => isset($info['uptime_in_days']) ? (int)$info['uptime_in_days'] : 0,
                'connected_clients' => isset($info['connected_clients']) ? (int)$info['connected_clients'] : 0,
                'used_memory' => $info['used_memory_human'] ?? 'unknown',
                'total_keys' => $totalKeys,
                'hits' => isset($info['keyspace_hits']) ? (int)$info['keyspace_hits'] : 0,
                'misses' => isset($info['keyspace_misses']) ? (int)$info['keyspace_misses'] : 0,
            ];
        } catch (\Exception $e) {
            Logger::warning('Redis stats fetch failed', ['error' => $e->getMessage()]);
            return $this->getSampleStats();
        }
    }
    
    /**
     * Get sample stats when Redis is unavailable
     * 
     * @return array Sample statistics
     */
    public function getSampleStats(): array
    {
        return [
            'connected' => false,
            'version' => 'N/A',
            'uptime_days' => 0,
            'connected_clients' => 0,
            'used_memory' => 'N/A',
            'total_keys' => 0,
            'hits' => 0,
            'misses' => 0,
        ];
    }
    
    /**
     * Get Redis keys with search and prefix filtering
     * 
     * @param string $prefix Key prefix filter
     * @param string $search Search pattern
     * @return array List of keys with metadata
     */
    public function getRedisKeys(string $prefix, string $search): array
    {
        try {
            $redis = new Redis();
            $config = $this->getRedisConfig();
            
            if (!@$redis->connect($config['host'], $config['port'])) {
                return $this->getSampleKeys();
            }
            
            $pattern = $prefix . '*' . $search . '*';
            $keys = $redis->keys($pattern);
            
            // Handle case where keys() returns false
            if ($keys === false) {
                $keys = [];
            }
            
            $result = [];
            foreach ($keys as $key) {
                $ttl = $redis->ttl($key);
                $type = $redis->type($key);
                $result[] = [
                    'key' => $key,
                    'ttl' => $ttl,
                    'ttl_formatted' => $this->formatTtl($ttl),
                    'type' => $this->getRedisTypeString($type),
                ];
            }
            
            $redis->close();
            return $result;
        } catch (\Exception $e) {
            Logger::warning('Redis keys fetch failed', ['error' => $e->getMessage()]);
            return $this->getSampleKeys();
        }
    }
    
    /**
     * Get sample keys when Redis is unavailable
     * 
     * @return array Sample keys
     */
    public function getSampleKeys(): array
    {
        return [
            ['key' => 'sample:key:1', 'ttl' => 3600, 'ttl_formatted' => '1 hour', 'type' => 'string'],
            ['key' => 'sample:key:2', 'ttl' => -1, 'ttl_formatted' => 'no expiry', 'type' => 'hash'],
        ];
    }
    
    /**
     * Delete a specific Redis key
     * 
     * @param string $key Key to delete
     * @return bool True if deleted successfully
     */
    public function deleteRedisKey(string $key): bool
    {
        try {
            $redis = new Redis();
            $config = $this->getRedisConfig();
            
            if (!@$redis->connect($config['host'], $config['port'])) {
                return false;
            }
            
            $result = $redis->del($key) > 0;
            $redis->close();
            
            return $result;
        } catch (\Exception $e) {
            Logger::error('Redis key deletion failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Clear all Redis cache
     * 
     * @return bool True if cleared successfully
     */
    public function clearRedisCache(): bool
    {
        try {
            $redis = new Redis();
            $config = $this->getRedisConfig();
            
            if (!@$redis->connect($config['host'], $config['port'])) {
                return false;
            }
            
            $result = $redis->flushDB();
            $redis->close();
            
            return $result;
        } catch (\Exception $e) {
            Logger::error('Redis cache clear failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Format TTL seconds to human readable format
     * 
     * @param int $seconds TTL in seconds
     * @return string Formatted TTL
     */
    public function formatTtl(int $seconds): string
    {
        if ($seconds === -1) {
            return 'no expiry';
        }
        
        if ($seconds === -2) {
            return 'expired';
        }
        
        if ($seconds < 60) {
            return $seconds . ' seconds';
        }
        
        if ($seconds < 3600) {
            return round($seconds / 60) . ' minutes';
        }
        
        if ($seconds < 86400) {
            return round($seconds / 3600) . ' hours';
        }
        
        return round($seconds / 86400) . ' days';
    }
    
    /**
     * Convert Redis type constant to readable string
     * 
     * @param int $type Redis type constant
     * @return string Readable type name
     */
    private function getRedisTypeString(int $type): string
    {
        $types = [
            Redis::REDIS_STRING => 'string',
            Redis::REDIS_SET => 'set',
            Redis::REDIS_LIST => 'list',
            Redis::REDIS_ZSET => 'zset',
            Redis::REDIS_HASH => 'hash',
            Redis::REDIS_NOT_FOUND => 'not_found',
        ];
        
        return $types[$type] ?? 'unknown';
    }
    
    /**
     * Get Redis configuration from config file
     * 
     * @return array Redis configuration
     */
    private function getRedisConfig(): array
    {
        return [
            'host' => $_ENV['REDIS_HOST'] ?? 'redis',
            'port' => (int)($_ENV['REDIS_PORT'] ?? 6379),
        ];
    }
}
