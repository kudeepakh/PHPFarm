<?php

namespace App\Core\Cache;

use App\Core\Cache\Drivers\RedisDriver;
use PHPFrarm\Core\Logger;
use Exception;

/**
 * Central Cache Manager
 * 
 * Provides unified interface for caching with tag support,
 * statistics tracking, and multiple driver support.
 */
class CacheManager
{
    private static ?CacheManager $instance = null;
    private $driver;
    private array $config;
    private CacheStatistics $statistics;
    private bool $enabled;

    private function __construct()
    {
        $this->config = require BASE_PATH . '/config/cache.php';
        $this->enabled = $this->config['enabled'] ?? true;
        $this->statistics = new CacheStatistics();
        
        if ($this->enabled) {
            $this->initializeDriver();
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initializeDriver(): void
    {
        $driver = $this->config['driver'] ?? 'redis';

        switch ($driver) {
            case 'redis':
                $this->driver = new RedisDriver($this->config['redis']);
                break;
            default:
                throw new Exception("Unsupported cache driver: {$driver}");
        }
    }

    /**
     * Get item from cache
     */
    public function get(string $key, $default = null)
    {
        if (!$this->enabled) {
            return $default;
        }

        try {
            $fullKey = $this->getFullKey($key);
            $value = $this->driver->get($fullKey);

            if ($value !== null) {
                $this->statistics->recordHit($key);
                $this->logOperation('hit', $key);
                return $this->unserialize($value);
            }

            $this->statistics->recordMiss($key);
            $this->logOperation('miss', $key);
            return $default;
        } catch (Exception $e) {
            Logger::error('Cache get failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return $default;
        }
    }

    /**
     * Store item in cache
     */
    public function set(string $key, $value, ?int $ttl = null, array $tags = []): bool
    {
        if (!$this->enabled) {
            return false;
        }

        try {
            $fullKey = $this->getFullKey($key);
            $ttl = $ttl ?? $this->config['default_ttl'];
            $serialized = $this->serialize($value);

            $result = $this->driver->set($fullKey, $serialized, $ttl);

            if ($result && !empty($tags)) {
                $this->tagKey($fullKey, $tags);
            }

            $this->statistics->recordSet($key, strlen($serialized));
            $this->logOperation('set', $key, ['ttl' => $ttl, 'tags' => $tags]);

            return $result;
        } catch (Exception $e) {
            Logger::error('Cache set failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Delete item from cache
     */
    public function delete(string $key): bool
    {
        if (!$this->enabled) {
            return false;
        }

        try {
            $fullKey = $this->getFullKey($key);
            $result = $this->driver->delete($fullKey);

            $this->statistics->recordDelete($key);
            $this->logOperation('delete', $key);

            return $result;
        } catch (Exception $e) {
            Logger::error('Cache delete failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if key exists
     */
    public function has(string $key): bool
    {
        if (!$this->enabled) {
            return false;
        }

        try {
            $fullKey = $this->getFullKey($key);
            return $this->driver->exists($fullKey);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Remember: Get from cache or execute callback and store
     */
    public function remember(string $key, callable $callback, ?int $ttl = null, array $tags = [])
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl, $tags);

        return $value;
    }

    /**
     * Remember forever (no expiration)
     */
    public function rememberForever(string $key, callable $callback, array $tags = [])
    {
        return $this->remember($key, $callback, 0, $tags);
    }

    /**
     * Flush cache by tags
     */
    public function flushTags(array $tags): int
    {
        if (!$this->enabled || empty($tags)) {
            return 0;
        }

        try {
            $deletedCount = 0;

            foreach ($tags as $tag) {
                $keys = $this->getKeysByTag($tag);
                foreach ($keys as $key) {
                    if ($this->driver->delete($key)) {
                        $deletedCount++;
                    }
                }
                // Clean up tag index
                $this->driver->delete($this->getTagKey($tag));
            }

            $this->statistics->recordFlush('tags', $deletedCount);
            $this->logOperation('flush_tags', implode(',', $tags), ['count' => $deletedCount]);

            return $deletedCount;
        } catch (Exception $e) {
            Logger::error('Cache flush tags failed', [
                'tags' => $tags,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Flush cache by pattern
     */
    public function flushPattern(string $pattern): int
    {
        if (!$this->enabled) {
            return 0;
        }

        try {
            $fullPattern = $this->getFullKey($pattern);
            $keys = $this->driver->keys($fullPattern);
            $deletedCount = 0;

            foreach ($keys as $key) {
                if ($this->driver->delete($key)) {
                    $deletedCount++;
                }
            }

            $this->statistics->recordFlush('pattern', $deletedCount);
            $this->logOperation('flush_pattern', $pattern, ['count' => $deletedCount]);

            return $deletedCount;
        } catch (Exception $e) {
            Logger::error('Cache flush pattern failed', [
                'pattern' => $pattern,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Flush all cache
     */
    public function flushAll(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        try {
            $result = $this->driver->flushAll();
            $this->statistics->recordFlush('all');
            $this->logOperation('flush_all');
            return $result;
        } catch (Exception $e) {
            Logger::error('Cache flush all failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Increment cache value
     */
    public function increment(string $key, int $value = 1): int
    {
        if (!$this->enabled) {
            return 0;
        }

        $fullKey = $this->getFullKey($key);
        return $this->driver->increment($fullKey, $value);
    }

    /**
     * Decrement cache value
     */
    public function decrement(string $key, int $value = 1): int
    {
        if (!$this->enabled) {
            return 0;
        }

        $fullKey = $this->getFullKey($key);
        return $this->driver->decrement($fullKey, $value);
    }

    /**
     * Get cache statistics
     */
    public function getStatistics(): array
    {
        return $this->statistics->getStats();
    }

    /**
     * Tag a cache key
     */
    private function tagKey(string $key, array $tags): void
    {
        foreach ($tags as $tag) {
            $tagKey = $this->getTagKey($tag);
            $this->driver->sAdd($tagKey, $key);
        }
    }

    /**
     * Get keys by tag
     */
    private function getKeysByTag(string $tag): array
    {
        $tagKey = $this->getTagKey($tag);
        return $this->driver->sMembers($tagKey) ?? [];
    }

    /**
     * Get full cache key with prefix
     */
    private function getFullKey(string $key): string
    {
        $prefix = $this->config['prefix'] ?? 'cache';
        return "{$prefix}:{$key}";
    }

    /**
     * Get tag key
     */
    private function getTagKey(string $tag): string
    {
        $prefix = $this->config['prefix'] ?? 'cache';
        return "{$prefix}:tag:{$tag}";
    }

    /**
     * Serialize value for storage
     */
    private function serialize($value): string
    {
        return serialize($value);
    }

    /**
     * Unserialize value from storage
     */
    private function unserialize(string $value)
    {
        return unserialize($value);
    }

    /**
     * Log cache operation
     */
    private function logOperation(string $operation, string $key = '', array $context = []): void
    {
        if (!($this->config['development']['log_hits'] ?? false) && $operation === 'hit') {
            return;
        }

        if (!($this->config['development']['log_misses'] ?? false) && $operation === 'miss') {
            return;
        }

        Logger::debug("Cache {$operation}", array_merge([
            'key' => $key,
            'operation' => $operation
        ], $context));
    }

    /**
     * Disable caching
     */
    public function disable(): void
    {
        $this->enabled = false;
    }

    /**
     * Enable caching
     */
    public function enable(): void
    {
        $this->enabled = true;
    }

    /**
     * Check if caching is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
