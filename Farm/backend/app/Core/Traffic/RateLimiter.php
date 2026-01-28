<?php

namespace PHPFrarm\Core\Traffic;

use Redis;
use PHPFrarm\Core\Logger;

/**
 * Rate Limiter
 * 
 * Implements multiple rate limiting algorithms:
 * - Token Bucket (smooth rate limiting with burst capacity)
 * - Sliding Window (accurate rate limiting)
 * - Fixed Window (simple counter-based)
 * 
 * Features:
 * - Redis-backed for distributed rate limiting
 * - Multiple algorithms support
 * - Per-client rate limiting
 * - Burst capacity support
 * - Rate limit headers (X-RateLimit-*)
 * 
 * @package Farm\Backend\App\Core\Traffic
 */
class RateLimiter
{
    private Redis $redis;
    private Logger $logger;
    private string $algorithm;
    private int $defaultLimit;
    private int $defaultWindow;
    
    // Rate limit algorithms
    public const ALGORITHM_TOKEN_BUCKET = 'token_bucket';
    public const ALGORITHM_SLIDING_WINDOW = 'sliding_window';
    public const ALGORITHM_FIXED_WINDOW = 'fixed_window';
    
    // Redis key prefixes
    private const PREFIX_TOKEN_BUCKET = 'ratelimit:token:';
    private const PREFIX_SLIDING_WINDOW = 'ratelimit:sliding:';
    private const PREFIX_FIXED_WINDOW = 'ratelimit:fixed:';
    private const PREFIX_STATS = 'ratelimit:stats:';
    
    /**
     * Constructor
     * 
     * @param Redis $redis Redis connection
     * @param Logger $logger Logger instance
     * @param array $config Configuration
     */
    public function __construct(Redis $redis, Logger $logger, array $config = [])
    {
        $this->redis = $redis;
        $this->logger = $logger;
        $this->algorithm = $config['algorithm'] ?? self::ALGORITHM_TOKEN_BUCKET;
        $this->defaultLimit = $config['default_limit'] ?? 60;
        $this->defaultWindow = $config['default_window'] ?? 60;
    }
    
    /**
     * Check if request is allowed
     * 
     * @param string $identifier Client identifier (IP, user ID, API key)
     * @param int|null $limit Maximum requests allowed
     * @param int|null $window Time window in seconds
     * @param int|null $burst Burst capacity (token bucket only)
     * @return array ['allowed' => bool, 'remaining' => int, 'reset' => int]
     */
    public function check(
        string $identifier,
        ?int $limit = null,
        ?int $window = null,
        ?int $burst = null
    ): array {
        $limit = $limit ?? $this->defaultLimit;
        $window = $window ?? $this->defaultWindow;
        $burst = $burst ?? (int)($limit * 1.5); // 50% burst capacity by default
        
        try {
            $result = match($this->algorithm) {
                self::ALGORITHM_TOKEN_BUCKET => $this->checkTokenBucket($identifier, $limit, $window, $burst),
                self::ALGORITHM_SLIDING_WINDOW => $this->checkSlidingWindow($identifier, $limit, $window),
                self::ALGORITHM_FIXED_WINDOW => $this->checkFixedWindow($identifier, $limit, $window),
                default => throw new \RuntimeException("Unknown algorithm: {$this->algorithm}")
            };
            
            // Track statistics
            $this->trackStats($identifier, $result['allowed']);
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logger->error('Rate limiter error', [
                'identifier' => $identifier,
                'error' => $e->getMessage()
            ]);
            
            // Fail open - allow request if rate limiter fails
            return [
                'allowed' => true,
                'remaining' => $limit,
                'reset' => time() + $window,
                'limit' => $limit
            ];
        }
    }
    
    /**
     * Token Bucket Algorithm
     * 
     * Allows burst traffic while maintaining average rate.
     * Tokens are added at fixed rate, requests consume tokens.
     * 
     * @param string $identifier Client identifier
     * @param int $limit Rate limit (tokens per window)
     * @param int $window Time window in seconds
     * @param int $burst Maximum burst capacity
     * @return array Rate limit result
     */
    private function checkTokenBucket(string $identifier, int $limit, int $window, int $burst): array
    {
        $key = self::PREFIX_TOKEN_BUCKET . $identifier;
        $now = microtime(true);
        
        // Get current bucket state
        $bucket = $this->redis->hGetAll($key);
        
        if (empty($bucket)) {
            // Initialize new bucket
            $bucket = [
                'tokens' => $burst,
                'last_refill' => $now,
                'capacity' => $burst
            ];
        } else {
            $bucket['tokens'] = (float)$bucket['tokens'];
            $bucket['last_refill'] = (float)$bucket['last_refill'];
            $bucket['capacity'] = (int)$bucket['capacity'];
        }
        
        // Calculate tokens to add based on elapsed time
        $elapsed = $now - $bucket['last_refill'];
        $refillRate = $limit / $window; // tokens per second
        $tokensToAdd = $elapsed * $refillRate;
        
        // Refill bucket (capped at capacity)
        $bucket['tokens'] = min($bucket['capacity'], $bucket['tokens'] + $tokensToAdd);
        $bucket['last_refill'] = $now;
        
        // Check if we have enough tokens
        $allowed = $bucket['tokens'] >= 1;
        
        if ($allowed) {
            // Consume one token
            $bucket['tokens'] -= 1;
        }
        
        // Save bucket state
        $this->redis->hMSet($key, [
            'tokens' => $bucket['tokens'],
            'last_refill' => $bucket['last_refill'],
            'capacity' => $bucket['capacity']
        ]);
        $this->redis->expire($key, $window * 2);
        
        // Calculate reset time (when bucket will be full again)
        $tokensNeeded = $bucket['capacity'] - $bucket['tokens'];
        $secondsToFill = $tokensNeeded / $refillRate;
        $reset = (int)($now + $secondsToFill);
        
        return [
            'allowed' => $allowed,
            'remaining' => (int)max(0, floor($bucket['tokens'])),
            'reset' => $reset,
            'limit' => $limit
        ];
    }
    
    /**
     * Sliding Window Algorithm
     * 
     * Most accurate algorithm, tracks individual request timestamps.
     * Uses sorted sets in Redis for efficient window management.
     * 
     * @param string $identifier Client identifier
     * @param int $limit Rate limit
     * @param int $window Time window in seconds
     * @return array Rate limit result
     */
    private function checkSlidingWindow(string $identifier, int $limit, int $window): array
    {
        $key = self::PREFIX_SLIDING_WINDOW . $identifier;
        $now = microtime(true);
        $windowStart = $now - $window;
        
        // Remove old requests outside the window
        $this->redis->zRemRangeByScore($key, 0, $windowStart);
        
        // Count requests in current window
        $count = $this->redis->zCard($key);
        
        $allowed = $count < $limit;
        
        if ($allowed) {
            // Add current request timestamp
            $this->redis->zAdd($key, $now, uniqid('', true));
            $count++;
        }
        
        // Set expiry
        $this->redis->expire($key, $window);
        
        // Get oldest request timestamp for reset calculation
        $oldest = $this->redis->zRange($key, 0, 0, true);
        $oldestTime = !empty($oldest) ? (float)array_values($oldest)[0] : $now;
        $reset = (int)($oldestTime + $window);
        
        return [
            'allowed' => $allowed,
            'remaining' => max(0, $limit - $count),
            'reset' => $reset,
            'limit' => $limit
        ];
    }
    
    /**
     * Fixed Window Algorithm
     * 
     * Simple counter-based approach.
     * Window resets at fixed intervals.
     * 
     * @param string $identifier Client identifier
     * @param int $limit Rate limit
     * @param int $window Time window in seconds
     * @return array Rate limit result
     */
    private function checkFixedWindow(string $identifier, int $limit, int $window): array
    {
        $now = time();
        $windowId = (int)($now / $window);
        $key = self::PREFIX_FIXED_WINDOW . $identifier . ':' . $windowId;
        
        // Increment counter
        $count = $this->redis->incr($key);
        
        if ($count === 1) {
            // First request in this window, set expiry
            $this->redis->expire($key, $window);
        }
        
        $allowed = $count <= $limit;
        $windowEnd = ($windowId + 1) * $window;
        
        return [
            'allowed' => $allowed,
            'remaining' => max(0, $limit - $count),
            'reset' => $windowEnd,
            'limit' => $limit
        ];
    }
    
    /**
     * Track rate limit statistics
     * 
     * @param string $identifier Client identifier
     * @param bool $allowed Whether request was allowed
     * @return void
     */
    private function trackStats(string $identifier, bool $allowed): void
    {
        try {
            $key = self::PREFIX_STATS . date('Y-m-d');
            $field = $allowed ? 'allowed' : 'blocked';
            
            $this->redis->hIncrBy($key, $field, 1);
            $this->redis->hIncrBy($key, "client:{$identifier}:{$field}", 1);
            $this->redis->expire($key, 86400 * 7); // Keep stats for 7 days
            
        } catch (\Exception $e) {
            // Don't fail on stats tracking errors
            $this->logger->warning('Failed to track rate limit stats', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get rate limit statistics
     * 
     * @param string|null $date Date (Y-m-d format), null for today
     * @param string|null $identifier Specific client identifier
     * @return array Statistics
     */
    public function getStats(?string $date = null, ?string $identifier = null): array
    {
        $date = $date ?? date('Y-m-d');
        $key = self::PREFIX_STATS . $date;
        
        $stats = $this->redis->hGetAll($key);
        
        if ($identifier !== null) {
            // Filter for specific client
            $prefix = "client:{$identifier}:";
            $stats = array_filter($stats, function($key) use ($prefix) {
                return str_starts_with($key, $prefix);
            }, ARRAY_FILTER_USE_KEY);
        }
        
        return [
            'date' => $date,
            'total_allowed' => (int)($stats['allowed'] ?? 0),
            'total_blocked' => (int)($stats['blocked'] ?? 0),
            'clients' => $this->extractClientStats($stats),
            'block_rate' => $this->calculateBlockRate($stats)
        ];
    }
    
    /**
     * Extract client-specific statistics
     * 
     * @param array $stats Raw statistics data
     * @return array Client statistics
     */
    private function extractClientStats(array $stats): array
    {
        $clients = [];
        
        foreach ($stats as $key => $value) {
            if (preg_match('/^client:([^:]+):(.+)$/', $key, $matches)) {
                $clientId = $matches[1];
                $metric = $matches[2];
                
                if (!isset($clients[$clientId])) {
                    $clients[$clientId] = ['allowed' => 0, 'blocked' => 0];
                }
                
                $clients[$clientId][$metric] = (int)$value;
            }
        }
        
        return $clients;
    }
    
    /**
     * Calculate block rate percentage
     * 
     * @param array $stats Statistics data
     * @return float Block rate (0-100)
     */
    private function calculateBlockRate(array $stats): float
    {
        $allowed = (int)($stats['allowed'] ?? 0);
        $blocked = (int)($stats['blocked'] ?? 0);
        $total = $allowed + $blocked;
        
        if ($total === 0) {
            return 0.0;
        }
        
        return round(($blocked / $total) * 100, 2);
    }
    
    /**
     * Reset rate limit for identifier
     * 
     * @param string $identifier Client identifier
     * @return bool Success
     */
    public function reset(string $identifier): bool
    {
        try {
            $keys = [
                self::PREFIX_TOKEN_BUCKET . $identifier,
                self::PREFIX_SLIDING_WINDOW . $identifier,
                self::PREFIX_FIXED_WINDOW . $identifier . ':*'
            ];
            
            foreach ($keys as $pattern) {
                if (str_contains($pattern, '*')) {
                    // Delete all matching keys
                    $matchingKeys = $this->redis->keys($pattern);
                    if (!empty($matchingKeys)) {
                        $this->redis->del($matchingKeys);
                    }
                } else {
                    $this->redis->del($pattern);
                }
            }
            
            $this->logger->info('Rate limit reset', ['identifier' => $identifier]);
            
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to reset rate limit', [
                'identifier' => $identifier,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Get current rate limit status for identifier
     * 
     * @param string $identifier Client identifier
     * @return array Current status
     */
    public function getStatus(string $identifier): array
    {
        return $this->check($identifier, PHP_INT_MAX, 1, PHP_INT_MAX);
    }
    
    /**
     * Generate rate limit headers
     * 
     * @param array $result Rate limit check result
     * @return array HTTP headers
     */
    public function getHeaders(array $result): array
    {
        return [
            'X-RateLimit-Limit' => (string)$result['limit'],
            'X-RateLimit-Remaining' => (string)$result['remaining'],
            'X-RateLimit-Reset' => (string)$result['reset'],
            'Retry-After' => $result['allowed'] ? null : (string)($result['reset'] - time())
        ];
    }
}
