<?php

namespace App\Core\Resilience\Attributes;

use Attribute;

/**
 * Retry Attribute
 * 
 * Route-level retry control via PHP attribute.
 * 
 * Usage Examples:
 * 
 * 1. Basic retry (3 attempts, exponential backoff with jitter):
 *    #[Retry]
 * 
 * 2. Custom max attempts:
 *    #[Retry(maxAttempts: 5)]
 * 
 * 3. Fixed backoff:
 *    #[Retry(maxAttempts: 3, strategy: 'fixed', baseDelayMs: 2000)]
 * 
 * 4. Exponential without jitter:
 *    #[Retry(maxAttempts: 5, strategy: 'exponential', baseDelayMs: 1000)]
 * 
 * 5. Exponential with jitter (default):
 *    #[Retry(maxAttempts: 5, strategy: 'exponential_jitter', baseDelayMs: 1000)]
 * 
 * 6. Fibonacci backoff:
 *    #[Retry(maxAttempts: 4, strategy: 'fibonacci', baseDelayMs: 500)]
 * 
 * 7. Linear backoff:
 *    #[Retry(maxAttempts: 3, strategy: 'linear', baseDelayMs: 1000)]
 * 
 * 8. Retry only specific exceptions:
 *    #[Retry(maxAttempts: 3, retryOn: [ConnectionException::class, TimeoutException::class])]
 * 
 * 9. With circuit breaker:
 *    #[Retry(maxAttempts: 3, circuitBreaker: 'payment-service')]
 * 
 * 10. With timeout:
 *     #[Retry(maxAttempts: 3, timeoutMs: 5000)]
 * 
 * 11. Conditional retry:
 *     #[Retry(maxAttempts: 3, when: 'env.production')]
 * 
 * 12. Full configuration:
 *     #[Retry(
 *         maxAttempts: 5,
 *         strategy: 'exponential_jitter',
 *         baseDelayMs: 1000,
 *         maxDelayMs: 30000,
 *         retryOn: [ApiException::class],
 *         circuitBreaker: 'external-api',
 *         timeoutMs: 10000,
 *         when: 'config.retry.enabled',
 *         idempotencyKey: 'request.idempotency_key'
 *     )]
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Retry
{
    public int $maxAttempts;
    public string $strategy;
    public int $baseDelayMs;
    public int $maxDelayMs;
    public array $retryOn;
    public ?string $circuitBreaker;
    public ?int $timeoutMs;
    public ?string $when;
    public ?string $idempotencyKey;
    public bool $enabled;

    /**
     * @param int $maxAttempts Maximum number of retry attempts (including initial try)
     * @param string $strategy Backoff strategy: 'fixed', 'linear', 'exponential', 'exponential_jitter', 'fibonacci'
     * @param int $baseDelayMs Base delay in milliseconds
     * @param int $maxDelayMs Maximum delay cap in milliseconds
     * @param array $retryOn Array of exception classes to retry on (empty = retry all)
     * @param string|null $circuitBreaker Circuit breaker name to check before retrying
     * @param int|null $timeoutMs Timeout in milliseconds for each attempt
     * @param string|null $when Condition expression (dot notation) to enable retry
     * @param string|null $idempotencyKey Expression to extract idempotency key from request
     * @param bool $enabled Enable/disable retry (useful for testing)
     */
    public function __construct(
        int $maxAttempts = 3,
        string $strategy = 'exponential_jitter',
        int $baseDelayMs = 1000,
        int $maxDelayMs = 30000,
        array $retryOn = [],
        ?string $circuitBreaker = null,
        ?int $timeoutMs = null,
        ?string $when = null,
        ?string $idempotencyKey = null,
        bool $enabled = true
    ) {
        $this->maxAttempts = $maxAttempts;
        $this->strategy = $strategy;
        $this->baseDelayMs = $baseDelayMs;
        $this->maxDelayMs = $maxDelayMs;
        $this->retryOn = $retryOn;
        $this->circuitBreaker = $circuitBreaker;
        $this->timeoutMs = $timeoutMs;
        $this->when = $when;
        $this->idempotencyKey = $idempotencyKey;
        $this->enabled = $enabled;
    }

    /**
     * Check if retry should be applied based on condition
     */
    public function shouldRetry(array $context = []): bool
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
        // Support dot notation: env.production, config.retry.enabled
        $value = $this->getNestedValue($expression, $context);

        // Truthy check
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

    /**
     * Get idempotency key from request
     */
    public function getIdempotencyKey(array $context): ?string
    {
        if ($this->idempotencyKey === null) {
            return null;
        }

        // Extract from request using dot notation
        return $this->getNestedValue($this->idempotencyKey, $context);
    }
}
