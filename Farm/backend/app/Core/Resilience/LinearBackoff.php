<?php

namespace App\Core\Resilience;

/**
 * Linear Backoff Strategy
 * 
 * Increases delay linearly with each retry attempt.
 * 
 * Example with increment=1000ms:
 * Attempt 1: 0ms
 * Attempt 2: 1000ms delay
 * Attempt 3: 2000ms delay
 * Attempt 4: 3000ms delay
 * Attempt 5: 4000ms delay
 */
class LinearBackoff implements BackoffStrategy
{
    private int $incrementMs;
    private int $maxDelayMs;

    public function __construct(int $incrementMs = 1000, int $maxDelayMs = 30000)
    {
        $this->incrementMs = $incrementMs;
        $this->maxDelayMs = $maxDelayMs;
    }

    public function getDelayMs(int $attemptNumber): int
    {
        // First attempt has no delay
        if ($attemptNumber <= 1) {
            return 0;
        }

        // Linear increase: increment * (attempt - 1)
        $delay = $this->incrementMs * ($attemptNumber - 1);

        // Cap at max delay
        return min($delay, $this->maxDelayMs);
    }

    public function getName(): string
    {
        return 'linear';
    }
}
