<?php

namespace App\Core\Resilience;

/**
 * Fixed Backoff Strategy
 * 
 * Returns a constant delay between retry attempts.
 * 
 * Example:
 * Attempt 1: 0ms
 * Attempt 2: 1000ms delay
 * Attempt 3: 1000ms delay
 * Attempt 4: 1000ms delay
 */
class FixedBackoff implements BackoffStrategy
{
    private int $delayMs;

    public function __construct(int $delayMs = 1000)
    {
        $this->delayMs = $delayMs;
    }

    public function getDelayMs(int $attemptNumber): int
    {
        // First attempt has no delay
        if ($attemptNumber <= 1) {
            return 0;
        }

        return $this->delayMs;
    }

    public function getName(): string
    {
        return 'fixed';
    }
}
