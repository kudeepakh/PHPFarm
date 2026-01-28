<?php

namespace App\Core\Resilience;

/**
 * Retry Statistics
 * 
 * Tracks retry attempts, successes, and failures.
 */
class RetryStatistics
{
    private array $stats = [
        'total_attempts' => 0,
        'total_retries' => 0,
        'retry_successes' => 0,
        'retry_exhausted' => 0,
        'operations' => [],
    ];

    public function recordRetryAttempt(string $operation, int $attemptNumber): void
    {
        $this->stats['total_attempts']++;
        
        if ($attemptNumber > 1) {
            $this->stats['total_retries']++;
        }

        if (!isset($this->stats['operations'][$operation])) {
            $this->stats['operations'][$operation] = [
                'attempts' => 0,
                'retries' => 0,
                'successes' => 0,
                'exhausted' => 0,
                'last_attempt_at' => null,
            ];
        }

        $this->stats['operations'][$operation]['attempts']++;
        if ($attemptNumber > 1) {
            $this->stats['operations'][$operation]['retries']++;
        }
        $this->stats['operations'][$operation]['last_attempt_at'] = time();
    }

    public function recordRetrySuccess(string $operation, int $attemptNumber): void
    {
        $this->stats['retry_successes']++;

        if (isset($this->stats['operations'][$operation])) {
            $this->stats['operations'][$operation]['successes']++;
        }
    }

    public function recordRetryExhausted(string $operation, int $attemptNumber): void
    {
        $this->stats['retry_exhausted']++;

        if (isset($this->stats['operations'][$operation])) {
            $this->stats['operations'][$operation]['exhausted']++;
        }
    }

    public function getStats(): array
    {
        $stats = $this->stats;

        // Calculate retry success rate
        if ($stats['total_retries'] > 0) {
            $stats['retry_success_rate'] = round(
                ($stats['retry_successes'] / $stats['total_retries']) * 100,
                2
            );
        } else {
            $stats['retry_success_rate'] = 0;
        }

        return $stats;
    }

    public function getOperationStats(string $operation): ?array
    {
        return $this->stats['operations'][$operation] ?? null;
    }

    public function reset(): void
    {
        $this->stats = [
            'total_attempts' => 0,
            'total_retries' => 0,
            'retry_successes' => 0,
            'retry_exhausted' => 0,
            'operations' => [],
        ];
    }
}
