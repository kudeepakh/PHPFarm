<?php

namespace Farm\Backend\App\Core\Security\Attributes;

use Attribute;

/**
 * #[BotProtection] - Route-level DDoS & bot protection configuration
 * 
 * Apply to controller methods to configure multi-layer protection:
 * - Bot detection (block automated traffic)
 * - Geo-blocking (country-based restrictions)
 * - Rate limiting (requests per time window)
 * - WAF (attack signature detection)
 * - Anomaly detection (pattern analysis)
 * 
 * Usage:
 * ```php
 * #[BotProtection(blockBots: true, allowedCountries: ['US', 'CA'])]
 * public function sensitiveEndpoint() { }
 * ```
 * 
 * Processed by DDoSProtectionMiddleware via reflection.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class BotProtection
{
    /**
     * @param bool $enabled Enable bot protection for this route
     * @param bool $blockBots Block all detected bots (except whitelisted)
     * @param bool $allowGoodBots Allow search engine bots (Google, Bing, etc.)
     * @param bool $strictMode Strict browser fingerprint validation
     * @param bool $requireCaptcha Require CAPTCHA verification (future enhancement)
     * @param int|null $maxRequestsPerSecond Rate limit per second (null = use default)
     * @param int|null $maxRequestsPerMinute Rate limit per minute (null = use default)
     * @param int|null $maxRequestsPerHour Rate limit per hour (null = use default)
     * @param array<string> $allowedCountries Whitelist countries (ISO codes: ['US', 'CA', 'GB'])
     * @param array<string> $blockedCountries Blacklist countries (ISO codes: ['CN', 'RU'])
     * @param string $geoMode Geo-blocking mode: 'whitelist' or 'blacklist'
     * @param bool $enableWaf Enable Web Application Firewall scanning
     * @param bool $enableAnomalyDetection Enable pattern/velocity anomaly detection
     * @param int $anomalySensitivity Anomaly sensitivity (1-10, higher = more sensitive)
     * @param bool $logViolations Log security violations to MongoDB
     * @param string $onViolation Action on violation: 'block' or 'log'
     */
    public function __construct(
        public bool $enabled = true,
        public bool $blockBots = false,
        public bool $allowGoodBots = true,
        public bool $strictMode = false,
        public bool $requireCaptcha = false,
        public ?int $maxRequestsPerSecond = null,
        public ?int $maxRequestsPerMinute = null,
        public ?int $maxRequestsPerHour = null,
        public array $allowedCountries = [],
        public array $blockedCountries = [],
        public string $geoMode = 'blacklist',
        public bool $enableWaf = true,
        public bool $enableAnomalyDetection = true,
        public int $anomalySensitivity = 5,
        public bool $logViolations = true,
        public string $onViolation = 'block', // 'block' or 'log'
    ) {
        // Validate geo mode
        if (!in_array($geoMode, ['whitelist', 'blacklist'], true)) {
            throw new \InvalidArgumentException("geoMode must be 'whitelist' or 'blacklist'");
        }
        
        // Validate on violation action
        if (!in_array($onViolation, ['block', 'log'], true)) {
            throw new \InvalidArgumentException("onViolation must be 'block' or 'log'");
        }
        
        // Validate anomaly sensitivity
        if ($anomalySensitivity < 1 || $anomalySensitivity > 10) {
            throw new \InvalidArgumentException("anomalySensitivity must be between 1 and 10");
        }
    }

    /**
     * Check if bot blocking is enabled
     * 
     * @return bool
     */
    public function shouldBlockBots(): bool
    {
        return $this->enabled && $this->blockBots;
    }

    /**
     * Check if geo-blocking is configured
     * 
     * @return bool
     */
    public function hasGeoRestrictions(): bool
    {
        return $this->enabled && (!empty($this->allowedCountries) || !empty($this->blockedCountries));
    }

    /**
     * Check if rate limiting is configured
     * 
     * @return bool
     */
    public function hasRateLimits(): bool
    {
        return $this->enabled && (
            $this->maxRequestsPerSecond !== null ||
            $this->maxRequestsPerMinute !== null ||
            $this->maxRequestsPerHour !== null
        );
    }

    /**
     * Check if WAF scanning is enabled
     * 
     * @return bool
     */
    public function shouldScanWithWaf(): bool
    {
        return $this->enabled && $this->enableWaf;
    }

    /**
     * Check if anomaly detection is enabled
     * 
     * @return bool
     */
    public function shouldDetectAnomalies(): bool
    {
        return $this->enabled && $this->enableAnomalyDetection;
    }

    /**
     * Get rate limit configuration
     * 
     * @return array{perSecond: int|null, perMinute: int|null, perHour: int|null}
     */
    public function getRateLimits(): array
    {
        return [
            'perSecond' => $this->maxRequestsPerSecond,
            'perMinute' => $this->maxRequestsPerMinute,
            'perHour' => $this->maxRequestsPerHour,
        ];
    }

    /**
     * Get geo-blocking configuration
     * 
     * @return array{mode: string, allowed: array, blocked: array}
     */
    public function getGeoConfig(): array
    {
        return [
            'mode' => $this->geoMode,
            'allowed' => $this->allowedCountries,
            'blocked' => $this->blockedCountries,
        ];
    }

    /**
     * Create minimal protection (log only)
     * 
     * @return self
     */
    public static function minimal(): self
    {
        return new self(
            enabled: true,
            blockBots: false,
            enableWaf: false,
            enableAnomalyDetection: false,
            onViolation: 'log',
        );
    }

    /**
     * Create standard protection (block bots, basic WAF)
     * 
     * @return self
     */
    public static function standard(): self
    {
        return new self(
            enabled: true,
            blockBots: true,
            allowGoodBots: true,
            enableWaf: true,
            enableAnomalyDetection: true,
            onViolation: 'block',
        );
    }

    /**
     * Create maximum protection (all features enabled)
     * 
     * @return self
     */
    public static function maximum(): self
    {
        return new self(
            enabled: true,
            blockBots: true,
            allowGoodBots: false,      // Block ALL bots
            strictMode: true,
            maxRequestsPerSecond: 5,
            maxRequestsPerMinute: 50,
            maxRequestsPerHour: 500,
            enableWaf: true,
            enableAnomalyDetection: true,
            anomalySensitivity: 8,
            onViolation: 'block',
        );
    }

    /**
     * Convert to array (for debugging)
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'blockBots' => $this->blockBots,
            'allowGoodBots' => $this->allowGoodBots,
            'strictMode' => $this->strictMode,
            'requireCaptcha' => $this->requireCaptcha,
            'rateLimits' => $this->getRateLimits(),
            'geoConfig' => $this->getGeoConfig(),
            'enableWaf' => $this->enableWaf,
            'enableAnomalyDetection' => $this->enableAnomalyDetection,
            'anomalySensitivity' => $this->anomalySensitivity,
            'logViolations' => $this->logViolations,
            'onViolation' => $this->onViolation,
        ];
    }
}
