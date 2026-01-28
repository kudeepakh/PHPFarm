<?php

namespace App\Core\Cache\Drivers;

use Redis;
use Exception;

/**
 * Redis Cache Driver
 * 
 * Handles all Redis cache operations.
 */
class RedisDriver
{
    private Redis $redis;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->connect();
    }

    private function connect(): void
    {
        $this->redis = new Redis();
        
        $connected = $this->redis->connect(
            $this->config['host'],
            $this->config['port'],
            $this->config['timeout'] ?? 2.0
        );

        if (!$connected) {
            throw new Exception('Failed to connect to Redis');
        }

        if (!empty($this->config['password'])) {
            $this->redis->auth($this->config['password']);
        }

        if (isset($this->config['database'])) {
            $this->redis->select($this->config['database']);
        }
    }

    public function get(string $key): ?string
    {
        $value = $this->redis->get($key);
        return $value !== false ? $value : null;
    }

    public function set(string $key, string $value, int $ttl = 0): bool
    {
        if ($ttl > 0) {
            return $this->redis->setex($key, $ttl, $value);
        }

        return $this->redis->set($key, $value);
    }

    public function delete(string $key): bool
    {
        return $this->redis->del($key) > 0;
    }

    public function exists(string $key): bool
    {
        return $this->redis->exists($key) > 0;
    }

    public function increment(string $key, int $value = 1): int
    {
        return $this->redis->incrBy($key, $value);
    }

    public function decrement(string $key, int $value = 1): int
    {
        return $this->redis->decrBy($key, $value);
    }

    public function keys(string $pattern): array
    {
        return $this->redis->keys($pattern);
    }

    public function flushAll(): bool
    {
        return $this->redis->flushDB();
    }

    // Set operations for tag management
    public function sAdd(string $key, string $value): int
    {
        return $this->redis->sAdd($key, $value);
    }

    public function sMembers(string $key): array
    {
        return $this->redis->sMembers($key);
    }

    public function sRem(string $key, string $value): int
    {
        return $this->redis->sRem($key, $value);
    }

    public function ttl(string $key): int
    {
        return $this->redis->ttl($key);
    }

    public function expire(string $key, int $ttl): bool
    {
        return $this->redis->expire($key, $ttl);
    }
}
