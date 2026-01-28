<?php

namespace App\Core\Cache\Attributes;

use Attribute;

/**
 * No Cache Attribute
 * 
 * Explicitly disable caching for a route, even if global caching is enabled.
 * Useful for sensitive data, real-time endpoints, or debugging.
 * 
 * Usage Examples:
 * 
 * Basic no-cache:
 * #[NoCache]
 * public function getCurrentUserSession() { }
 * 
 * With reason (for documentation):
 * #[NoCache(reason: 'Real-time data, must not be cached')]
 * public function getLiveStockPrices() { }
 * 
 * Conditional no-cache:
 * #[NoCache(when: 'debug.enabled')]
 * public function getApiData() { }
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class NoCache
{
    /**
     * @param string|null $reason Documentation reason for no-cache
     * @param string|null $when Condition that must be true to disable cache
     * @param bool $noStore Set Cache-Control: no-store
     * @param bool $noCache Set Cache-Control: no-cache
     * @param bool $mustRevalidate Set Cache-Control: must-revalidate
     */
    public function __construct(
        public ?string $reason = null,
        public ?string $when = null,
        public bool $noStore = true,
        public bool $noCache = true,
        public bool $mustRevalidate = true
    ) {
    }

    /**
     * Check if no-cache should be applied
     */
    public function shouldApply(array $context = []): bool
    {
        if ($this->when === null) {
            return true;
        }

        return $this->evaluateCondition($this->when, $context);
    }

    /**
     * Evaluate condition expression
     */
    private function evaluateCondition(string $condition, array $context): bool
    {
        // Handle simple boolean checks
        if (strpos($condition, '==') === false && strpos($condition, '!=') === false) {
            $value = $this->getNestedValue($condition, $context);
            return (bool) $value;
        }

        // Handle equality checks
        if (strpos($condition, '==') !== false) {
            [$key, $expectedValue] = array_map('trim', explode('==', $condition, 2));
            $actualValue = $this->getNestedValue($key, $context);
            $expectedValue = trim($expectedValue, "'\"");
            return $actualValue == $expectedValue;
        }

        return false;
    }

    /**
     * Get nested value from context
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
     * Get Cache-Control header value
     */
    public function getCacheControlHeader(): string
    {
        $directives = [];

        if ($this->noStore) {
            $directives[] = 'no-store';
        }

        if ($this->noCache) {
            $directives[] = 'no-cache';
        }

        if ($this->mustRevalidate) {
            $directives[] = 'must-revalidate';
        }

        return implode(', ', $directives);
    }
}
