<?php

namespace App\Core\Resilience;

/**
 * Backoff Strategy Interface
 * 
 * Defines the contract for retry backoff strategies.
 */
interface BackoffStrategy
{
    /**
     * Calculate delay in milliseconds for a given attempt number
     * 
     * @param int $attemptNumber The attempt number (1-based)
     * @return int Delay in milliseconds
     */
    public function getDelayMs(int $attemptNumber): int;

    /**
     * Get strategy name
     * 
     * @return string
     */
    public function getName(): string;
}
