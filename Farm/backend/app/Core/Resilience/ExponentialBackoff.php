<?php

namespace App\Core\Resilience;

/**
 * Exponential Backoff Strategy
 * 
 * Doubles the delay between each retry attempt.
 * 
 * Example with base=1000ms:
 * Attempt 1: 0ms
 * Attempt 2: 1000ms delay
 * Attempt 3: 2000ms delay
 * Attempt 4: 4000ms delay
 * Attempt 5: 8000ms delay
 */
class ExponentialBackoff implements BackoffStrategy
{
    private int $baseDelayMs;
    private int $maxDelayMs;
    private bool $useJitter;

    public function __construct(
        int $baseDelayMs = 1000,
        int $maxDelayMs = 30000,
        bool $useJitter = false
    ) {
        $this->baseDelayMs = $baseDelayMs;
        $this->maxDelayMs = $maxDelayMs;
        $this->useJitter = $useJitter;
    }

    public function getDelayMs(int $attemptNumber): int
    {
        // First attempt has no delay
        if ($attemptNumber <= 1) {
            return 0;
        }

        // Calculate exponential delay: base * 2^(attempt - 2)
        $delay = $this->baseDelayMs * pow(2, $attemptNumber - 2);

        // Cap at max delay
        $delay = min($delay, $this->maxDelayMs);

        // Add jitter if enabled (±20% randomization)
        if ($this->useJitter) {
            $jitterRange = $delay * 0.2; // 20% of delay
            $minDelay = $delay - $jitterRange;
            $maxDelay = $delay + $jitterRange;
            $delay = mt_rand((int)$minDelay, (int)$maxDelay);
        }

        return (int) $delay;
    }

    public function getName(): string
    {
        return $this->useJitter ? 'exponential_jitter' : 'exponential';
    }
}
