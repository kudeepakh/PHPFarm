<?php

namespace PHPFrarm\Core\Database\Attributes;

use Attribute;

/**
 * OptimisticLock Attribute
 * 
 * Route-level optimistic locking control via PHP attribute.
 * Automatically handles version checking and conflict resolution.
 * 
 * Usage Examples:
 * 
 * 1. Basic optimistic locking (3 retries):
 *    #[OptimisticLock]
 * 
 * 2. Custom max attempts:
 *    #[OptimisticLock(maxAttempts: 5)]
 * 
 * 3. Custom retry delay:
 *    #[OptimisticLock(maxAttempts: 3, baseDelayMs: 200)]
 * 
 * 4. Fail fast (no retry):
 *    #[OptimisticLock(maxAttempts: 1)]
 * 
 * 5. Conditional locking:
 *    #[OptimisticLock(when: 'config.locking.enabled')]
 * 
 * 6. With version header enforcement:
 *    #[OptimisticLock(requireIfMatch: true)]
 * 
 * 7. Full configuration:
 *    #[OptimisticLock(
 *        maxAttempts: 5,
 *        baseDelayMs: 100,
 *        requireIfMatch: true,
 *        when: 'env.production',
 *        returnVersion: true
 *    )]
 */
#[Attribute(Attribute::TARGET_METHOD)]
class OptimisticLock
{
    public int $maxAttempts;
    public int $baseDelayMs;
    public bool $requireIfMatch;
    public ?string $when;
    public bool $returnVersion;
    public bool $enabled;

    /**
     * @param int $maxAttempts Maximum retry attempts on version conflict
     * @param int $baseDelayMs Base delay in milliseconds between retries
     * @param bool $requireIfMatch Require If-Match header for updates
     * @param string|null $when Condition expression to enable locking
     * @param bool $returnVersion Return version in response (ETag header)
     * @param bool $enabled Enable/disable locking
     */
    public function __construct(
        int $maxAttempts = 3,
        int $baseDelayMs = 100,
        bool $requireIfMatch = false,
        ?string $when = null,
        bool $returnVersion = true,
        bool $enabled = true
    ) {
        $this->maxAttempts = $maxAttempts;
        $this->baseDelayMs = $baseDelayMs;
        $this->requireIfMatch = $requireIfMatch;
        $this->when = $when;
        $this->returnVersion = $returnVersion;
        $this->enabled = $enabled;
    }

    /**
     * Check if locking should be applied
     */
    public function shouldApply(array $context = []): bool
    {
        if (!$this->enabled) {
            return false;
        }

        if ($this->when === null) {
            return true;
        }

        return $this->evaluateCondition($this->when, $context);
    }

    /**
     * Evaluate condition expression
     */
    private function evaluateCondition(string $expression, array $context): bool
    {
        // Support dot notation: env.production, config.locking.enabled
        $value = $this->getNestedValue($expression, $context);
        return !empty($value) && $value !== false && $value !== 'false';
    }

    /**
     * Get nested value from context using dot notation
     */
    private function getNestedValue(string $path, array $context)
    {
        $keys = explode('.', $path);
        $value = $context;

        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } else {
                return null;
            }
        }

        return $value;
    }
}
