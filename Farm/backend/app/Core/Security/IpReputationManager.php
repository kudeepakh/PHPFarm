<?php

namespace Farm\Backend\App\Core\Security;

use App\Core\Cache\CacheManager;
use Farm\Backend\App\Core\Logging\LogManager;
use Farm\Backend\App\Core\Observability\TraceContext;

/**
 * IpReputationManager - Manage IP blacklists, whitelists, and reputation scores
 * 
 * Features:
 * - Blacklist management (manual & automatic)
 * - Whitelist management (trusted IPs)
 * - Reputation scoring (0-100)
 * - Auto-blocking based on violations
 * - TTL-based temporary blocks
 * - Redis-backed for performance
 * 
 * Thread-safe with atomic Redis operations.
 */
class IpReputationManager
{
    private CacheManager $cache;
    private LogManager $logger;
    private array $config;
    
    // Cache keys
    private const BLACKLIST_KEY = 'ip:blacklist';
    private const WHITELIST_KEY = 'ip:whitelist';
    private const REPUTATION_KEY = 'ip:reputation:';
    private const VIOLATIONS_KEY = 'ip:violations:';
    
    // Reputation thresholds
    private const REPUTATION_EXCELLENT = 90;
    private const REPUTATION_GOOD = 70;
    private const REPUTATION_NEUTRAL = 50;
    private const REPUTATION_POOR = 30;
    private const REPUTATION_BLOCKED = 0;

    public function __construct(CacheManager $cache, LogManager $logger, array $config = [])
    {
        $this->cache = $cache;
        $this->logger = $logger;
        $this->config = array_merge([
            'auto_block_enabled' => true,
            'auto_block_threshold' => 10,      // Violations before auto-block
            'auto_block_duration' => 3600,     // Seconds (1 hour)
            'violation_ttl' => 1800,           // Track violations for 30 minutes
            'reputation_decay' => 1,           // Points lost per violation
            'reputation_recovery' => 0.1,      // Points gained per clean request
            'default_reputation' => 50,        // Neutral reputation
        ], $config);
    }

    /**
     * Check if IP is blocked
     * 
     * @param string $ip
     * @return bool
     */
    public function isBlocked(string $ip): bool
    {
        // Check whitelist first (highest priority)
        if ($this->isWhitelisted($ip)) {
            return false;
        }
        
        // Check blacklist
        return $this->cache->sIsMember(self::BLACKLIST_KEY, $ip);
    }

    /**
     * Check if IP is whitelisted
     * 
     * @param string $ip
     * @return bool
     */
    public function isWhitelisted(string $ip): bool
    {
        return $this->cache->sIsMember(self::WHITELIST_KEY, $ip);
    }

    /**
     * Add IP to blacklist
     * 
     * @param string $ip
     * @param string $reason Reason for blocking
     * @param int|null $duration Seconds (null = permanent)
     * @return bool Success
     */
    public function addToBlacklist(string $ip, string $reason, ?int $duration = null): bool
    {
        // Add to blacklist set
        $this->cache->sAdd(self::BLACKLIST_KEY, $ip);
        
        // Set reputation to 0 (blocked)
        $this->setReputation($ip, self::REPUTATION_BLOCKED);
        
        // Log security event
        $this->logger->security('ip_blacklisted', [
            'ip' => $ip,
            'reason' => $reason,
            'duration' => $duration,
            'correlation_id' => TraceContext::getCorrelationId(),
            'timestamp' => time(),
        ]);
        
        // If temporary block, schedule removal
        if ($duration !== null) {
            $this->cache->expire(self::BLACKLIST_KEY, $duration);
        }
        
        return true;
    }

    /**
     * Remove IP from blacklist
     * 
     * @param string $ip
     * @return bool Success
     */
    public function removeFromBlacklist(string $ip): bool
    {
        $this->cache->sRem(self::BLACKLIST_KEY, $ip);
        
        // Reset reputation to neutral
        $this->setReputation($ip, $this->config['default_reputation']);
        
        $this->logger->security('ip_unblocked', [
            'ip' => $ip,
            'correlation_id' => TraceContext::getCorrelationId(),
        ]);
        
        return true;
    }

    /**
     * Add IP to whitelist (trusted)
     * 
     * @param string $ip
     * @param string $reason
     * @return bool Success
     */
    public function addToWhitelist(string $ip, string $reason): bool
    {
        $this->cache->sAdd(self::WHITELIST_KEY, $ip);
        
        // Set reputation to maximum
        $this->setReputation($ip, 100);
        
        // Remove from blacklist if present
        $this->cache->sRem(self::BLACKLIST_KEY, $ip);
        
        $this->logger->security('ip_whitelisted', [
            'ip' => $ip,
            'reason' => $reason,
            'correlation_id' => TraceContext::getCorrelationId(),
        ]);
        
        return true;
    }

    /**
     * Remove IP from whitelist
     * 
     * @param string $ip
     * @return bool Success
     */
    public function removeFromWhitelist(string $ip): bool
    {
        $this->cache->sRem(self::WHITELIST_KEY, $ip);
        
        // Reset to neutral reputation
        $this->setReputation($ip, $this->config['default_reputation']);
        
        return true;
    }

    /**
     * Get IP reputation score (0-100)
     * 
     * @param string $ip
     * @return int Reputation score
     */
    public function getReputation(string $ip): int
    {
        $reputation = $this->cache->get(self::REPUTATION_KEY . $ip);
        
        if ($reputation === null) {
            return $this->config['default_reputation'];
        }
        
        return (int) $reputation;
    }

    /**
     * Set IP reputation score
     * 
     * @param string $ip
     * @param int $score 0-100
     */
    private function setReputation(string $ip, int $score): void
    {
        $score = max(0, min(100, $score)); // Clamp to 0-100
        $this->cache->set(self::REPUTATION_KEY . $ip, $score, 86400 * 30); // 30 days TTL
    }

    /**
     * Record a violation for IP (rate limit, abuse, etc.)
     * 
     * Auto-blocks IP if threshold exceeded.
     * 
     * @param string $ip
     * @param string $violationType
     * @return bool True if auto-blocked
     */
    public function recordViolation(string $ip, string $violationType): bool
    {
        // Whitelist never gets violations
        if ($this->isWhitelisted($ip)) {
            return false;
        }
        
        // Increment violation counter
        $violationKey = self::VIOLATIONS_KEY . $ip;
        $violations = $this->cache->incr($violationKey);
        $this->cache->expire($violationKey, $this->config['violation_ttl']);
        
        // Decrease reputation
        $currentReputation = $this->getReputation($ip);
        $newReputation = max(0, $currentReputation - $this->config['reputation_decay']);
        $this->setReputation($ip, $newReputation);
        
        // Log violation
        $this->logger->security('ip_violation', [
            'ip' => $ip,
            'violation_type' => $violationType,
            'total_violations' => $violations,
            'reputation' => $newReputation,
            'correlation_id' => TraceContext::getCorrelationId(),
        ]);
        
        // Auto-block if threshold exceeded
        if ($this->config['auto_block_enabled'] && $violations >= $this->config['auto_block_threshold']) {
            $this->addToBlacklist(
                $ip,
                "Auto-blocked: {$violations} violations ({$violationType})",
                $this->config['auto_block_duration']
            );
            return true;
        }
        
        return false;
    }

    /**
     * Record successful clean request (improves reputation)
     * 
     * @param string $ip
     */
    public function recordCleanRequest(string $ip): void
    {
        if ($this->isBlocked($ip) || $this->isWhitelisted($ip)) {
            return; // No reputation change for blocked/whitelisted
        }
        
        $currentReputation = $this->getReputation($ip);
        $newReputation = min(100, $currentReputation + $this->config['reputation_recovery']);
        $this->setReputation($ip, (int) $newReputation);
    }

    /**
     * Get violation count for IP
     * 
     * @param string $ip
     * @return int Violation count
     */
    public function getViolationCount(string $ip): int
    {
        $count = $this->cache->get(self::VIOLATIONS_KEY . $ip);
        return $count !== null ? (int) $count : 0;
    }

    /**
     * Get reputation level label
     * 
     * @param int $reputation
     * @return string Level label
     */
    public function getReputationLevel(int $reputation): string
    {
        if ($reputation >= self::REPUTATION_EXCELLENT) return 'Excellent';
        if ($reputation >= self::REPUTATION_GOOD) return 'Good';
        if ($reputation >= self::REPUTATION_NEUTRAL) return 'Neutral';
        if ($reputation >= self::REPUTATION_POOR) return 'Poor';
        return 'Blocked';
    }

    /**
     * Get complete IP status
     * 
     * @param string $ip
     * @return array Status information
     */
    public function getIpStatus(string $ip): array
    {
        $reputation = $this->getReputation($ip);
        
        return [
            'ip' => $ip,
            'blocked' => $this->isBlocked($ip),
            'whitelisted' => $this->isWhitelisted($ip),
            'reputation' => $reputation,
            'reputation_level' => $this->getReputationLevel($reputation),
            'violations' => $this->getViolationCount($ip),
            'auto_block_threshold' => $this->config['auto_block_threshold'],
        ];
    }

    /**
     * Get all blacklisted IPs
     * 
     * @return array
     */
    public function getBlacklist(): array
    {
        return $this->cache->sMembers(self::BLACKLIST_KEY) ?? [];
    }

    /**
     * Get all whitelisted IPs
     * 
     * @return array
     */
    public function getWhitelist(): array
    {
        return $this->cache->sMembers(self::WHITELIST_KEY) ?? [];
    }

    /**
     * Clear all blacklist entries
     * 
     * @return bool Success
     */
    public function clearBlacklist(): bool
    {
        return $this->cache->delete(self::BLACKLIST_KEY);
    }

    /**
     * Get statistics
     * 
     * @return array
     */
    public function getStatistics(): array
    {
        return [
            'blacklisted_count' => count($this->getBlacklist()),
            'whitelisted_count' => count($this->getWhitelist()),
            'auto_block_enabled' => $this->config['auto_block_enabled'],
            'auto_block_threshold' => $this->config['auto_block_threshold'],
            'auto_block_duration' => $this->config['auto_block_duration'],
        ];
    }
}
