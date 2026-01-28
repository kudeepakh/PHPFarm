<?php

namespace App\Core\Resilience;

/**
 * Fibonacci Backoff Strategy
 * 
 * Uses Fibonacci sequence for delay calculation.
 * Provides gradual increase that's less aggressive than exponential.
 * 
 * Example with multiplier=1000ms:
 * Attempt 1: 0ms
 * Attempt 2: 1000ms delay (1 * 1000)
 * Attempt 3: 1000ms delay (1 * 1000)
 * Attempt 4: 2000ms delay (2 * 1000)
 * Attempt 5: 3000ms delay (3 * 1000)
 * Attempt 6: 5000ms delay (5 * 1000)
 * Attempt 7: 8000ms delay (8 * 1000)
 */
class FibonacciBackoff implements BackoffStrategy
{
    private int $multiplierMs;
    private int $maxDelayMs;
    private array $fibSequence;

    public function __construct(int $multiplierMs = 1000, int $maxDelayMs = 30000)
    {
        $this->multiplierMs = $multiplierMs;
        $this->maxDelayMs = $maxDelayMs;
        $this->fibSequence = [1, 1, 2, 3, 5, 8, 13, 21, 34, 55];
    }

    public function getDelayMs(int $attemptNumber): int
    {
        // First attempt has no delay
        if ($attemptNumber <= 1) {
            return 0;
        }

        // Get Fibonacci number for this attempt
        $fibIndex = $attemptNumber - 2; // Adjust for 0-based index
        
        // If we exceed pre-calculated sequence, calculate on the fly
        if ($fibIndex >= count($this->fibSequence)) {
            $fibNumber = $this->calculateFibonacci($fibIndex);
        } else {
            $fibNumber = $this->fibSequence[$fibIndex];
        }

        $delay = $fibNumber * $this->multiplierMs;

        // Cap at max delay
        return min($delay, $this->maxDelayMs);
    }

    private function calculateFibonacci(int $n): int
    {
        if ($n <= 1) {
            return 1;
        }

        $a = 1;
        $b = 1;

        for ($i = 2; $i <= $n; $i++) {
            $temp = $a + $b;
            $a = $b;
            $b = $temp;
        }

        return $b;
    }

    public function getName(): string
    {
        return 'fibonacci';
    }
}
