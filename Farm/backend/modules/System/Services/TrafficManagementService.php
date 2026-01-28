<?php

declare(strict_types=1);

namespace PHPFrarm\Modules\System\Services;

use Farm\Backend\App\Core\Traffic\RateLimiter;
use Farm\Backend\App\Core\Traffic\Throttler;
use Farm\Backend\App\Core\Traffic\QuotaManager;
use PHPFrarm\Core\Logger;
use Redis;

/**
 * Traffic Management Service
 * 
 * Handles business logic for traffic control operations:
 * - Rate limiting
 * - Throttling
 * - Quota management
 */
class TrafficManagementService
{
    private RateLimiter $rateLimiter;
    private Throttler $throttler;
    private QuotaManager $quotaManager;
    private Logger $logger;
    
    /**
     * Constructor
     * 
     * @param Redis $redis Redis connection
     * @param Logger $logger Logger instance
     * @param array $config Configuration
     */
    public function __construct(Redis $redis, Logger $logger, array $config = [])
    {
        $this->rateLimiter = new RateLimiter($redis, $logger, $config['rate_limit'] ?? []);
        $this->throttler = new Throttler($redis, $logger, $config['throttle'] ?? []);
        $this->quotaManager = new QuotaManager($redis, $logger, $config['quota'] ?? []);
        $this->logger = $logger;
    }
    
    // ==================== RATE LIMITER ====================
    
    /**
     * Get rate limit status for client
     */
    public function getRateLimitStatus(string $identifier): array
    {
        return $this->rateLimiter->getStatus($identifier);
    }
    
    /**
     * Get rate limit statistics
     */
    public function getRateLimitStats(?string $date = null, ?string $identifier = null): array
    {
        return $this->rateLimiter->getStats($date, $identifier);
    }
    
    /**
     * Reset rate limit for client
     */
    public function resetRateLimit(string $identifier): bool
    {
        return $this->rateLimiter->reset($identifier);
    }
    
    // ==================== THROTTLER ====================
    
    /**
     * Get throttle status for client
     */
    public function getThrottleStatus(string $identifier): array
    {
        return $this->throttler->getStatus($identifier);
    }
    
    /**
     * Get throttle statistics
     */
    public function getThrottleStats(?string $date = null, ?string $identifier = null): array
    {
        return $this->throttler->getStats($date, $identifier);
    }
    
    /**
     * Reset throttle for client
     */
    public function resetThrottle(string $identifier): bool
    {
        return $this->throttler->reset($identifier);
    }
    
    // ==================== QUOTA MANAGER ====================
    
    /**
     * Get quota tiers
     */
    public function getQuotaTiers(): array
    {
        return $this->quotaManager->getTiers();
    }
    
    /**
     * Get client quota status
     */
    public function getQuotaStatus(string $identifier): array
    {
        return $this->quotaManager->getStatus($identifier);
    }
    
    /**
     * Set custom quota for client
     */
    public function setCustomQuota(string $identifier, int $limit, string $period): bool
    {
        return $this->quotaManager->setCustomQuota($identifier, $limit, $period);
    }
    
    /**
     * Reset quota for client
     */
    public function resetQuota(string $identifier): bool
    {
        return $this->quotaManager->reset($identifier);
    }
    
    /**
     * Get quota statistics
     */
    public function getQuotaStats(?string $date = null, ?string $identifier = null): array
    {
        return $this->quotaManager->getStats($date, $identifier);
    }
}
