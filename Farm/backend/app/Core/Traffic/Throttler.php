<?php

namespace Farm\Backend\App\Core\Traffic;

use Redis;
use PHPFrarm\Core\Logger;

/**
 * Throttler
 * 
 * Implements request throttling with gradual slowdown.
 * Unlike rate limiting (hard block), throttling adds delays
 * to slow down excessive requests progressively.
 * 
 * Features:
 * - Progressive delay (exponential backoff)
 * - Redis-backed throttle tracking
 * - Configurable thresholds and max delays
 * - Per-client throttling
 * - Throttle status reporting
 * 
 * @package Farm\Backend\App\Core\Traffic
 */
class Throttler
{
    private Redis $redis;
    private Logger $logger;
    private int $threshold;
    private int $window;
    private float $maxDelay;
    private float $baseDelay;
    
    private const PREFIX = 'throttle:';
    private const STATS_PREFIX = 'throttle:stats:';
    
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
        $this->threshold = $config['threshold'] ?? 100; // Requests before throttling starts
        $this->window = $config['window'] ?? 60; // Time window in seconds
        $this->maxDelay = $config['max_delay'] ?? 5.0; // Maximum delay in seconds
        $this->baseDelay = $config['base_delay'] ?? 0.1; // Base delay in seconds
    }
    
    /**
     * Check if request should be throttled and apply delay
     * 
     * @param string $identifier Client identifier
     * @param int|null $threshold Custom threshold
     * @param int|null $window Custom window
     * @return array ['throttled' => bool, 'delay' => float, 'requests' => int]
     */
    public function check(
        string $identifier,
        ?int $threshold = null,
        ?int $window = null
    ): array {
        $threshold = $threshold ?? $this->threshold;
        $window = $window ?? $this->window;
        
        try {
            $key = self::PREFIX . $identifier;
            $now = microtime(true);
            $windowStart = $now - $window;
            
            // Remove old timestamps
            $this->redis->zRemRangeByScore($key, 0, $windowStart);
            
            // Add current request
            $this->redis->zAdd($key, $now, uniqid('', true));
            $this->redis->expire($key, $window);
            
            // Count requests in window
            $requestCount = $this->redis->zCard($key);
            
            // Calculate delay if over threshold
            $throttled = $requestCount > $threshold;
            $delay = 0.0;
            
            if ($throttled) {
                $excessRequests = $requestCount - $threshold;
                $delay = $this->calculateDelay($excessRequests);
                
                // Track throttle event
                $this->trackThrottle($identifier, $delay);
                
                // Apply delay
                usleep((int)($delay * 1000000));
            }
            
            return [
                'throttled' => $throttled,
                'delay' => $delay,
                'requests' => $requestCount,
                'threshold' => $threshold,
                'excess' => max(0, $requestCount - $threshold)
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Throttler error', [
                'identifier' => $identifier,
                'error' => $e->getMessage()
            ]);
            
            // Fail open - no throttling on error
            return [
                'throttled' => false,
                'delay' => 0.0,
                'requests' => 0,
                'threshold' => $threshold,
                'excess' => 0
            ];
        }
    }
    
    /**
     * Calculate delay based on excess requests
     * 
     * Uses exponential backoff formula:
     * delay = min(base_delay * (2 ^ excess_requests), max_delay)
     * 
     * @param int $excessRequests Number of requests over threshold
     * @return float Delay in seconds
     */
    private function calculateDelay(int $excessRequests): float
    {
        // Exponential backoff capped at max delay
        $delay = $this->baseDelay * pow(2, min($excessRequests, 10));
        return min($delay, $this->maxDelay);
    }
    
    /**
     * Track throttle statistics
     * 
     * @param string $identifier Client identifier
     * @param float $delay Applied delay
     * @return void
     */
    private function trackThrottle(string $identifier, float $delay): void
    {
        try {
            $key = self::STATS_PREFIX . date('Y-m-d');
            
            $this->redis->hIncrBy($key, 'total_throttled', 1);
            $this->redis->hIncrBy($key, "client:{$identifier}", 1);
            $this->redis->hIncrByFloat($key, 'total_delay', $delay);
            $this->redis->expire($key, 86400 * 7); // Keep for 7 days
            
        } catch (\Exception $e) {
            $this->logger->warning('Failed to track throttle stats', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get throttle statistics
     * 
     * @param string|null $date Date (Y-m-d), null for today
     * @param string|null $identifier Specific client
     * @return array Statistics
     */
    public function getStats(?string $date = null, ?string $identifier = null): array
    {
        $date = $date ?? date('Y-m-d');
        $key = self::STATS_PREFIX . $date;
        
        $stats = $this->redis->hGetAll($key);
        
        $totalThrottled = (int)($stats['total_throttled'] ?? 0);
        $totalDelay = (float)($stats['total_delay'] ?? 0);
        
        $clients = [];
        foreach ($stats as $k => $v) {
            if (str_starts_with($k, 'client:')) {
                $clientId = substr($k, 7);
                if ($identifier === null || $clientId === $identifier) {
                    $clients[$clientId] = (int)$v;
                }
            }
        }
        
        return [
            'date' => $date,
            'total_throttled' => $totalThrottled,
            'total_delay_seconds' => round($totalDelay, 2),
            'average_delay_seconds' => $totalThrottled > 0 
                ? round($totalDelay / $totalThrottled, 3) 
                : 0,
            'clients' => $clients,
            'top_throttled_clients' => $this->getTopThrottledClients($clients, 10)
        ];
    }
    
    /**
     * Get top throttled clients
     * 
     * @param array $clients Client throttle counts
     * @param int $limit Number of clients to return
     * @return array Top clients
     */
    private function getTopThrottledClients(array $clients, int $limit): array
    {
        arsort($clients);
        return array_slice($clients, 0, $limit, true);
    }
    
    /**
     * Reset throttle state for identifier
     * 
     * @param string $identifier Client identifier
     * @return bool Success
     */
    public function reset(string $identifier): bool
    {
        try {
            $key = self::PREFIX . $identifier;
            $this->redis->del($key);
            
            $this->logger->info('Throttle reset', ['identifier' => $identifier]);
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to reset throttle', [
                'identifier' => $identifier,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get current throttle status
     * 
     * @param string $identifier Client identifier
     * @return array Current status
     */
    public function getStatus(string $identifier): array
    {
        try {
            $key = self::PREFIX . $identifier;
            $now = microtime(true);
            $windowStart = $now - $this->window;
            
            // Clean old entries
            $this->redis->zRemRangeByScore($key, 0, $windowStart);
            
            // Count requests
            $requestCount = $this->redis->zCard($key);
            $throttled = $requestCount > $this->threshold;
            
            $delay = 0.0;
            if ($throttled) {
                $excessRequests = $requestCount - $this->threshold;
                $delay = $this->calculateDelay($excessRequests);
            }
            
            return [
                'identifier' => $identifier,
                'requests_in_window' => $requestCount,
                'threshold' => $this->threshold,
                'window_seconds' => $this->window,
                'is_throttled' => $throttled,
                'current_delay_seconds' => $delay,
                'excess_requests' => max(0, $requestCount - $this->threshold)
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get throttle status', [
                'identifier' => $identifier,
                'error' => $e->getMessage()
            ]);
            
            return [
                'identifier' => $identifier,
                'requests_in_window' => 0,
                'threshold' => $this->threshold,
                'window_seconds' => $this->window,
                'is_throttled' => false,
                'current_delay_seconds' => 0.0,
                'excess_requests' => 0
            ];
        }
    }
    
    /**
     * Generate throttle headers
     * 
     * @param array $result Throttle check result
     * @return array HTTP headers
     */
    public function getHeaders(array $result): array
    {
        return [
            'X-Throttle-Status' => $result['throttled'] ? 'throttled' : 'normal',
            'X-Throttle-Delay' => $result['throttled'] 
                ? sprintf('%.3f', $result['delay']) 
                : '0',
            'X-Throttle-Requests' => (string)$result['requests'],
            'X-Throttle-Threshold' => (string)$result['threshold']
        ];
    }
}
