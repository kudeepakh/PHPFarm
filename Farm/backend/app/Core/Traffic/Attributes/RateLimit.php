<?php

namespace Farm\Backend\App\Core\Traffic\Attributes;

use Attribute;

/**
 * RateLimit Attribute
 * 
 * Applies rate limiting, throttling, and quota rules to routes.
 * Can be used on controller classes or individual methods.
 * 
 * Usage:
 * ```php
 * #[RateLimit(limit: 100, window: 60)]
 * public function getUsers() { ... }
 * 
 * #[RateLimit(limit: 10, window: 60, throttle: true)]
 * public function createUser() { ... }
 * 
 * #[RateLimit(quota: true, quotaCost: 5)]
 * public function expensiveOperation() { ... }
 * ```
 * 
 * @package Farm\Backend\App\Core\Traffic\Attributes
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class RateLimit
{
    /**
     * Constructor
     * 
     * @param int|null $limit Maximum requests allowed in window
     * @param int|null $window Time window in seconds
     * @param int|null $burst Burst capacity (token bucket only)
     * @param string|null $algorithm Rate limiting algorithm (token_bucket, sliding_window, fixed_window)
     * @param bool $throttle Enable throttling (progressive delay)
     * @param int|null $throttleThreshold Throttle activation threshold
     * @param bool $quota Enable quota checking
     * @param int $quotaCost Cost to deduct from quota (default 1)
     * @param string|null $identifier Custom identifier resolver (defaults to IP/user ID)
     * @param string|null $key Custom Redis key suffix
     * @param bool $enabled Whether rate limiting is enabled (for easy disable)
     * @param string|null $message Custom error message when rate limited
     */
    public function __construct(
        public ?int $limit = null,
        public ?int $window = null,
        public ?int $burst = null,
        public ?string $algorithm = null,
        public bool $throttle = false,
        public ?int $throttleThreshold = null,
        public bool $quota = true,
        public int $quotaCost = 1,
        public ?string $identifier = null,
        public ?string $key = null,
        public bool $enabled = true,
        public ?string $message = null
    ) {}
    
    /**
     * Get rate limit configuration
     * 
     * @return array Configuration array
     */
    public function getConfig(): array
    {
        return [
            'limit' => $this->limit,
            'window' => $this->window,
            'burst' => $this->burst,
            'algorithm' => $this->algorithm,
            'throttle' => $this->throttle,
            'throttle_threshold' => $this->throttleThreshold,
            'quota' => $this->quota,
            'quota_cost' => $this->quotaCost,
            'identifier' => $this->identifier,
            'key' => $this->key,
            'enabled' => $this->enabled,
            'message' => $this->message
        ];
    }
    
    /**
     * Check if rate limiting is enabled
     * 
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
    
    /**
     * Check if throttling is enabled
     * 
     * @return bool
     */
    public function isThrottleEnabled(): bool
    {
        return $this->throttle;
    }
    
    /**
     * Check if quota checking is enabled
     * 
     * @return bool
     */
    public function isQuotaEnabled(): bool
    {
        return $this->quota;
    }
    
    /**
     * Get custom error message
     * 
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }
}
