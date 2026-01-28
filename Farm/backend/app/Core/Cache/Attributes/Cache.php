<?php

namespace App\Core\Cache\Attributes;

use Attribute;

/**
 * Cache Attribute for Route-Level Cache Control
 * 
 * Usage Examples:
 * 
 * Basic caching:
 * #[Cache(ttl: 3600)]
 * public function getUser(int $id) { }
 * 
 * With tags:
 * #[Cache(ttl: 1800, tags: ['users', 'profiles'])]
 * public function getUserProfile(int $id) { }
 * 
 * Conditional caching:
 * #[Cache(ttl: 3600, when: 'auth.isGuest', unless: 'debug.enabled')]
 * public function getPublicData() { }
 * 
 * Always cache (permanent):
 * #[Cache(always: true)]
 * public function getAppConfig() { }
 * 
 * Cache with vary conditions:
 * #[Cache(ttl: 600, varyBy: ['user_id', 'locale'])]
 * public function getLocalizedContent() { }
 * 
 * Custom cache key:
 * #[Cache(ttl: 3600, key: 'user:{user_id}:profile')]
 * public function getUserData(int $user_id) { }
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class Cache
{
    /**
     * @param int|null $ttl Time to live in seconds (null = default, 0 = forever)
     * @param array $tags Cache tags for bulk invalidation
     * @param string|null $key Custom cache key template (supports placeholders)
     * @param array $varyBy Parameters to include in cache key
     * @param string|null $when Condition that must be true to cache (expression)
     * @param string|null $unless Condition that prevents caching (expression)
     * @param bool $always Always cache (ignores conditions, permanent storage)
     * @param bool $authenticated Cache only for authenticated users
     * @param bool $guest Cache only for guests
     * @param array $methods HTTP methods to cache (default: ['GET', 'HEAD'])
     * @param array $statusCodes Status codes to cache (default: [200, 203, 204])
     * @param bool $varyByQuery Include query parameters in cache key
     * @param bool $varyByHeaders Include specific headers in cache key
     * @param array $headers Headers to vary by (e.g., ['Accept-Language', 'User-Agent'])
     * @param string|null $prefix Cache key prefix
     * @param bool $compress Compress cached response
     * @param int|null $maxAge Client-side max-age (Cache-Control header)
     * @param bool $public Set Cache-Control: public
     * @param bool $private Set Cache-Control: private
     * @param bool $mustRevalidate Set Cache-Control: must-revalidate
     * @param bool $etag Generate ETag for conditional requests
     * @param bool $lastModified Generate Last-Modified header
     */
    public function __construct(
        public ?int $ttl = null,
        public array $tags = [],
        public ?string $key = null,
        public array $varyBy = [],
        public ?string $when = null,
        public ?string $unless = null,
        public bool $always = false,
        public bool $authenticated = false,
        public bool $guest = false,
        public array $methods = ['GET', 'HEAD'],
        public array $statusCodes = [200, 203, 204, 206, 300, 301],
        public bool $varyByQuery = true,
        public bool $varyByHeaders = false,
        public array $headers = [],
        public ?string $prefix = null,
        public bool $compress = false,
        public ?int $maxAge = null,
        public bool $public = true,
        public bool $private = false,
        public bool $mustRevalidate = false,
        public bool $etag = true,
        public bool $lastModified = true
    ) {
        // Validation
        if ($this->authenticated && $this->guest) {
            throw new \InvalidArgumentException('Cannot set both authenticated and guest to true');
        }
        
        if ($this->public && $this->private) {
            throw new \InvalidArgumentException('Cannot set both public and private to true');
        }
    }

    /**
     * Check if caching should be applied based on conditions
     */
    public function shouldCache(array $context = []): bool
    {
        // Always cache overrides all conditions
        if ($this->always) {
            return true;
        }

        // Check authentication conditions
        if ($this->authenticated && empty($context['user'])) {
            return false;
        }

        if ($this->guest && !empty($context['user'])) {
            return false;
        }

        // Check 'when' condition
        if ($this->when !== null && !$this->evaluateCondition($this->when, $context)) {
            return false;
        }

        // Check 'unless' condition
        if ($this->unless !== null && $this->evaluateCondition($this->unless, $context)) {
            return false;
        }

        return true;
    }

    /**
     * Evaluate simple condition expressions
     */
    private function evaluateCondition(string $condition, array $context): bool
    {
        // Simple dot notation support: auth.isGuest, debug.enabled, user.role == 'admin'
        // This is a basic implementation - can be extended with expression parser
        
        // Handle simple boolean checks: auth.isGuest
        if (strpos($condition, '==') === false && strpos($condition, '!=') === false) {
            $value = $this->getNestedValue($condition, $context);
            return (bool) $value;
        }

        // Handle equality checks: user.role == 'admin'
        if (strpos($condition, '==') !== false) {
            [$key, $expectedValue] = array_map('trim', explode('==', $condition, 2));
            $actualValue = $this->getNestedValue($key, $context);
            $expectedValue = trim($expectedValue, "'\"");
            return $actualValue == $expectedValue;
        }

        // Handle inequality checks: user.role != 'guest'
        if (strpos($condition, '!=') !== false) {
            [$key, $expectedValue] = array_map('trim', explode('!=', $condition, 2));
            $actualValue = $this->getNestedValue($key, $context);
            $expectedValue = trim($expectedValue, "'\"");
            return $actualValue != $expectedValue;
        }

        return false;
    }

    /**
     * Get nested value from context using dot notation
     */
    private function getNestedValue(string $key, array $context)
    {
        $keys = explode('.', $key);
        $value = $context;

        foreach ($keys as $k) {
            if (is_array($value) && isset($value[$k])) {
                $value = $value[$k];
            } elseif (is_object($value) && isset($value->$k)) {
                $value = $value->$k;
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Get effective TTL (considering 'always' flag)
     */
    public function getEffectiveTtl(?int $defaultTtl = null): ?int
    {
        if ($this->always) {
            return 0; // 0 means forever
        }

        return $this->ttl ?? $defaultTtl;
    }

    /**
     * Check if method is cacheable
     */
    public function isCacheableMethod(string $method): bool
    {
        return in_array(strtoupper($method), array_map('strtoupper', $this->methods));
    }

    /**
     * Check if status code is cacheable
     */
    public function isCacheableStatus(int $statusCode): bool
    {
        return in_array($statusCode, $this->statusCodes);
    }

    /**
     * Get Cache-Control header value
     */
    public function getCacheControlHeader(): string
    {
        $directives = [];

        if ($this->public) {
            $directives[] = 'public';
        }

        if ($this->private) {
            $directives[] = 'private';
        }

        if ($this->maxAge !== null) {
            $directives[] = 'max-age=' . $this->maxAge;
        }

        if ($this->mustRevalidate) {
            $directives[] = 'must-revalidate';
        }

        if ($this->always) {
            $directives[] = 'immutable';
        }

        return implode(', ', $directives);
    }
}
