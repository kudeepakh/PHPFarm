<?php

namespace Farm\Backend\App\Core\Traffic;

use Redis;
use PHPFrarm\Core\Logger;

/**
 * Quota Manager
 * 
 * Manages client-level quotas for API usage.
 * Tracks usage against allocated quotas and enforces limits.
 * 
 * Features:
 * - Client-specific quotas (requests per day/month)
 * - Usage tracking with Redis
 * - Quota tiers (free, basic, premium, enterprise)
 * - Quota reset schedules (daily, monthly)
 * - Usage alerts and notifications
 * - Overage handling (block or allow with tracking)
 * 
 * @package Farm\Backend\App\Core\Traffic
 */
class QuotaManager
{
    private Redis $redis;
    private Logger $logger;
    private array $tiers;
    private bool $allowOverage;
    
    private const PREFIX_USAGE = 'quota:usage:';
    private const PREFIX_TIER = 'quota:tier:';
    private const PREFIX_CUSTOM = 'quota:custom:';
    private const PREFIX_STATS = 'quota:stats:';
    
    // Quota periods
    public const PERIOD_DAILY = 'daily';
    public const PERIOD_MONTHLY = 'monthly';
    public const PERIOD_HOURLY = 'hourly';
    
    // Default quota tiers
    private const DEFAULT_TIERS = [
        'free' => ['limit' => 1000, 'period' => self::PERIOD_DAILY],
        'basic' => ['limit' => 10000, 'period' => self::PERIOD_DAILY],
        'premium' => ['limit' => 100000, 'period' => self::PERIOD_DAILY],
        'enterprise' => ['limit' => 1000000, 'period' => self::PERIOD_DAILY],
        'unlimited' => ['limit' => PHP_INT_MAX, 'period' => self::PERIOD_DAILY]
    ];
    
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
        $this->tiers = $config['tiers'] ?? self::DEFAULT_TIERS;
        $this->allowOverage = $config['allow_overage'] ?? false;
    }
    
    /**
     * Check if client has quota available
     * 
     * @param string $clientId Client identifier
     * @param int $cost Request cost (default 1)
     * @return array ['allowed' => bool, 'remaining' => int, 'limit' => int, 'reset' => int]
     */
    public function check(string $clientId, int $cost = 1): array
    {
        try {
            // Get client quota configuration
            $quota = $this->getClientQuota($clientId);
            $period = $quota['period'];
            $limit = $quota['limit'];
            
            // Get current usage
            $usageKey = $this->getUsageKey($clientId, $period);
            $current = (int)$this->redis->get($usageKey) ?: 0;
            
            // Check if within quota
            $allowed = ($current + $cost) <= $limit;
            
            if (!$allowed && $this->allowOverage) {
                // Allow overage but track it
                $this->trackOverage($clientId, $cost);
                $allowed = true;
            }
            
            if ($allowed) {
                // Increment usage
                $newUsage = $this->redis->incrBy($usageKey, $cost);
                
                // Set expiry if first request in period
                if ($newUsage === $cost) {
                    $ttl = $this->getPeriodTTL($period);
                    $this->redis->expire($usageKey, $ttl);
                }
                
                $current = $newUsage;
            }
            
            // Track statistics
            $this->trackUsage($clientId, $cost, $allowed);
            
            $reset = $this->getResetTime($period);
            
            return [
                'allowed' => $allowed,
                'remaining' => max(0, $limit - $current),
                'limit' => $limit,
                'used' => $current,
                'reset' => $reset,
                'period' => $period,
                'tier' => $quota['tier']
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Quota manager error', [
                'client_id' => $clientId,
                'error' => $e->getMessage()
            ]);
            
            // Fail open
            return [
                'allowed' => true,
                'remaining' => PHP_INT_MAX,
                'limit' => PHP_INT_MAX,
                'used' => 0,
                'reset' => time() + 86400,
                'period' => self::PERIOD_DAILY,
                'tier' => 'unknown'
            ];
        }
    }
    
    /**
     * Get client quota configuration
     * 
     * @param string $clientId Client identifier
     * @return array Quota configuration
     */
    private function getClientQuota(string $clientId): array
    {
        // Check for custom quota
        $customKey = self::PREFIX_CUSTOM . $clientId;
        $custom = $this->redis->hGetAll($customKey);
        
        if (!empty($custom)) {
            return [
                'limit' => (int)$custom['limit'],
                'period' => $custom['period'] ?? self::PERIOD_DAILY,
                'tier' => 'custom'
            ];
        }
        
        // Check assigned tier
        $tierKey = self::PREFIX_TIER . $clientId;
        $tierName = $this->redis->get($tierKey) ?: 'free';
        
        $tierConfig = $this->tiers[$tierName] ?? $this->tiers['free'];
        
        return [
            'limit' => $tierConfig['limit'],
            'period' => $tierConfig['period'],
            'tier' => $tierName
        ];
    }
    
    /**
     * Get usage key for client and period
     * 
     * @param string $clientId Client identifier
     * @param string $period Quota period
     * @return string Redis key
     */
    private function getUsageKey(string $clientId, string $period): string
    {
        $suffix = match($period) {
            self::PERIOD_HOURLY => date('Y-m-d-H'),
            self::PERIOD_DAILY => date('Y-m-d'),
            self::PERIOD_MONTHLY => date('Y-m'),
            default => date('Y-m-d')
        };
        
        return self::PREFIX_USAGE . $clientId . ':' . $suffix;
    }
    
    /**
     * Get TTL for quota period
     * 
     * @param string $period Quota period
     * @return int TTL in seconds
     */
    private function getPeriodTTL(string $period): int
    {
        return match($period) {
            self::PERIOD_HOURLY => 3600,
            self::PERIOD_DAILY => 86400,
            self::PERIOD_MONTHLY => 86400 * 31,
            default => 86400
        };
    }
    
    /**
     * Get quota reset timestamp
     * 
     * @param string $period Quota period
     * @return int Unix timestamp
     */
    private function getResetTime(string $period): int
    {
        return match($period) {
            self::PERIOD_HOURLY => strtotime('+1 hour', strtotime(date('Y-m-d H:00:00'))),
            self::PERIOD_DAILY => strtotime('tomorrow 00:00:00'),
            self::PERIOD_MONTHLY => strtotime('first day of next month 00:00:00'),
            default => strtotime('tomorrow 00:00:00')
        };
    }
    
    /**
     * Set client quota tier
     * 
     * @param string $clientId Client identifier
     * @param string $tier Tier name
     * @return bool Success
     */
    public function setTier(string $clientId, string $tier): bool
    {
        if (!isset($this->tiers[$tier])) {
            $this->logger->warning('Invalid quota tier', [
                'client_id' => $clientId,
                'tier' => $tier
            ]);
            return false;
        }
        
        try {
            $key = self::PREFIX_TIER . $clientId;
            $this->redis->set($key, $tier);
            
            $this->logger->info('Quota tier set', [
                'client_id' => $clientId,
                'tier' => $tier
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to set quota tier', [
                'client_id' => $clientId,
                'tier' => $tier,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Set custom quota for client
     * 
     * @param string $clientId Client identifier
     * @param int $limit Request limit
     * @param string $period Quota period
     * @return bool Success
     */
    public function setCustomQuota(string $clientId, int $limit, string $period = self::PERIOD_DAILY): bool
    {
        try {
            $key = self::PREFIX_CUSTOM . $clientId;
            $this->redis->hMSet($key, [
                'limit' => $limit,
                'period' => $period
            ]);
            
            $this->logger->info('Custom quota set', [
                'client_id' => $clientId,
                'limit' => $limit,
                'period' => $period
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to set custom quota', [
                'client_id' => $clientId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Track quota usage statistics
     * 
     * @param string $clientId Client identifier
     * @param int $cost Request cost
     * @param bool $allowed Whether request was allowed
     * @return void
     */
    private function trackUsage(string $clientId, int $cost, bool $allowed): void
    {
        try {
            $key = self::PREFIX_STATS . date('Y-m-d');
            
            $this->redis->hIncrBy($key, 'total_requests', 1);
            $this->redis->hIncrBy($key, 'total_cost', $cost);
            $this->redis->hIncrBy($key, $allowed ? 'allowed' : 'blocked', 1);
            $this->redis->hIncrBy($key, "client:{$clientId}:requests", 1);
            $this->redis->hIncrBy($key, "client:{$clientId}:cost", $cost);
            $this->redis->expire($key, 86400 * 30); // Keep for 30 days
            
        } catch (\Exception $e) {
            $this->logger->warning('Failed to track quota usage', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Track quota overage
     * 
     * @param string $clientId Client identifier
     * @param int $cost Overage amount
     * @return void
     */
    private function trackOverage(string $clientId, int $cost): void
    {
        try {
            $key = 'quota:overage:' . date('Y-m-d');
            
            $this->redis->hIncrBy($key, "client:{$clientId}", $cost);
            $this->redis->expire($key, 86400 * 30);
            
            $this->logger->warning('Quota overage', [
                'client_id' => $clientId,
                'overage' => $cost
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to track overage', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get quota statistics
     * 
     * @param string|null $date Date (Y-m-d)
     * @param string|null $clientId Specific client
     * @return array Statistics
     */
    public function getStats(?string $date = null, ?string $clientId = null): array
    {
        $date = $date ?? date('Y-m-d');
        $key = self::PREFIX_STATS . $date;
        
        $stats = $this->redis->hGetAll($key);
        
        $clients = [];
        foreach ($stats as $k => $v) {
            if (preg_match('/^client:([^:]+):(.+)$/', $k, $matches)) {
                $id = $matches[1];
                $metric = $matches[2];
                
                if ($clientId === null || $id === $clientId) {
                    if (!isset($clients[$id])) {
                        $clients[$id] = ['requests' => 0, 'cost' => 0];
                    }
                    $clients[$id][$metric] = (int)$v;
                }
            }
        }
        
        return [
            'date' => $date,
            'total_requests' => (int)($stats['total_requests'] ?? 0),
            'total_cost' => (int)($stats['total_cost'] ?? 0),
            'allowed' => (int)($stats['allowed'] ?? 0),
            'blocked' => (int)($stats['blocked'] ?? 0),
            'clients' => $clients,
            'top_users' => $this->getTopUsers($clients, 10)
        ];
    }
    
    /**
     * Get top quota users
     * 
     * @param array $clients Client usage data
     * @param int $limit Number to return
     * @return array Top users
     */
    private function getTopUsers(array $clients, int $limit): array
    {
        uasort($clients, function($a, $b) {
            return $b['cost'] <=> $a['cost'];
        });
        
        return array_slice($clients, 0, $limit, true);
    }
    
    /**
     * Reset quota for client
     * 
     * @param string $clientId Client identifier
     * @return bool Success
     */
    public function reset(string $clientId): bool
    {
        try {
            $patterns = [
                self::PREFIX_USAGE . $clientId . ':*'
            ];
            
            foreach ($patterns as $pattern) {
                $keys = $this->redis->keys($pattern);
                if (!empty($keys)) {
                    $this->redis->del($keys);
                }
            }
            
            $this->logger->info('Quota reset', ['client_id' => $clientId]);
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to reset quota', [
                'client_id' => $clientId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get quota status for client
     * 
     * @param string $clientId Client identifier
     * @return array Status information
     */
    public function getStatus(string $clientId): array
    {
        $quota = $this->getClientQuota($clientId);
        $usageKey = $this->getUsageKey($clientId, $quota['period']);
        $current = (int)($this->redis->get($usageKey) ?: 0);
        
        return [
            'client_id' => $clientId,
            'tier' => $quota['tier'],
            'limit' => $quota['limit'],
            'used' => $current,
            'remaining' => max(0, $quota['limit'] - $current),
            'period' => $quota['period'],
            'reset' => $this->getResetTime($quota['period']),
            'usage_percent' => $quota['limit'] > 0 
                ? round(($current / $quota['limit']) * 100, 2) 
                : 0
        ];
    }
    
    /**
     * Get available tiers
     * 
     * @return array Tier configurations
     */
    public function getTiers(): array
    {
        return $this->tiers;
    }
    
    /**
     * Generate quota headers
     * 
     * @param array $result Quota check result
     * @return array HTTP headers
     */
    public function getHeaders(array $result): array
    {
        return [
            'X-Quota-Limit' => (string)$result['limit'],
            'X-Quota-Remaining' => (string)$result['remaining'],
            'X-Quota-Used' => (string)$result['used'],
            'X-Quota-Reset' => (string)$result['reset'],
            'X-Quota-Tier' => $result['tier']
        ];
    }
}
