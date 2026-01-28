<?php

namespace PHPFrarm\Core\Database;

use App\Core\Exceptions\OptimisticLockException;
use App\Core\Resilience\RetryPolicy;
use App\Core\Resilience\ExponentialBackoff;
use PHPFrarm\Core\Logger;

/**
 * OptimisticLockManager
 * 
 * Manages optimistic locking operations with automatic retry logic.
 * Tracks conflict statistics and provides conflict resolution strategies.
 */
class OptimisticLockManager
{
    private static ?OptimisticLockManager $instance = null;
    private OptimisticLockStatistics $statistics;

    private function __construct()
    {
        $this->statistics = new OptimisticLockStatistics();
    }

    public static function getInstance(): OptimisticLockManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Execute operation with automatic retry on optimistic lock conflicts
     * 
     * @param callable $operation The operation to execute (should throw OptimisticLockException on conflict)
     * @param int $maxAttempts Maximum retry attempts
     * @param int $baseDelayMs Base delay in milliseconds between retries
     * @return mixed Result of the operation
     * @throws OptimisticLockException If all retries are exhausted
     */
    public function executeWithRetry(
        callable $operation,
        int $maxAttempts = 3,
        int $baseDelayMs = 100
    ) {
        $retryPolicy = new RetryPolicy(
            $maxAttempts,
            new ExponentialBackoff($baseDelayMs, 5000, true), // Max 5 seconds delay
            [OptimisticLockException::class]
        );

        $attemptNumber = 1;

        try {
            return $retryPolicy->execute(function() use ($operation, &$attemptNumber) {
                try {
                    $result = $operation();
                    
                    if ($attemptNumber > 1) {
                        $this->statistics->recordRetrySuccess($attemptNumber);
                        Logger::info('Optimistic lock retry succeeded', [
                            'attempts' => $attemptNumber
                        ]);
                    }
                    
                    return $result;
                    
                } catch (OptimisticLockException $e) {
                    $this->statistics->recordConflict(
                        $e->getEntityType(),
                        $e->getEntityId(),
                        $attemptNumber
                    );

                    Logger::warning('Optimistic lock conflict detected', [
                        'entity_type' => $e->getEntityType(),
                        'entity_id' => $e->getEntityId(),
                        'expected_version' => $e->getExpectedVersion(),
                        'current_version' => $e->getCurrentVersion(),
                        'attempt' => $attemptNumber,
                        'max_attempts' => $maxAttempts
                    ]);

                    $attemptNumber++;
                    throw $e;
                }
            }, [
                'operation_name' => 'optimistic_lock_operation'
            ]);

        } catch (OptimisticLockException $e) {
            $this->statistics->recordRetryExhausted($attemptNumber - 1);
            
            Logger::error('Optimistic lock retry exhausted', [
                'entity_type' => $e->getEntityType(),
                'entity_id' => $e->getEntityId(),
                'attempts' => $attemptNumber - 1
            ]);

            throw $e;
        }
    }

    /**
     * Execute operation without retry (fail fast)
     * 
     * @param callable $operation
     * @return mixed
     * @throws OptimisticLockException
     */
    public function execute(callable $operation)
    {
        try {
            return $operation();
        } catch (OptimisticLockException $e) {
            $this->statistics->recordConflict(
                $e->getEntityType(),
                $e->getEntityId(),
                1
            );
            throw $e;
        }
    }

    /**
     * Get conflict statistics
     */
    public function getStatistics(): array
    {
        return $this->statistics->getStats();
    }

    /**
     * Reset statistics
     */
    public function resetStatistics(): void
    {
        $this->statistics->reset();
    }
}

/**
 * OptimisticLockStatistics
 * 
 * Tracks optimistic lock conflict statistics.
 */
class OptimisticLockStatistics
{
    private array $stats = [
        'total_conflicts' => 0,
        'retry_successes' => 0,
        'retry_exhausted' => 0,
        'entities' => [],
    ];

    public function recordConflict(string $entityType, $entityId, int $attemptNumber): void
    {
        $this->stats['total_conflicts']++;

        $key = "{$entityType}:{$entityId}";
        if (!isset($this->stats['entities'][$key])) {
            $this->stats['entities'][$key] = [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'conflict_count' => 0,
                'last_conflict_at' => null,
            ];
        }

        $this->stats['entities'][$key]['conflict_count']++;
        $this->stats['entities'][$key]['last_conflict_at'] = time();
    }

    public function recordRetrySuccess(int $attemptNumber): void
    {
        $this->stats['retry_successes']++;
    }

    public function recordRetryExhausted(int $attemptNumber): void
    {
        $this->stats['retry_exhausted']++;
    }

    public function getStats(): array
    {
        $stats = $this->stats;

        // Calculate success rate
        if ($stats['total_conflicts'] > 0) {
            $stats['retry_success_rate'] = round(
                ($stats['retry_successes'] / $stats['total_conflicts']) * 100,
                2
            );
        } else {
            $stats['retry_success_rate'] = 0;
        }

        // Sort entities by conflict count
        uasort($stats['entities'], function($a, $b) {
            return $b['conflict_count'] <=> $a['conflict_count'];
        });

        return $stats;
    }

    public function reset(): void
    {
        $this->stats = [
            'total_conflicts' => 0,
            'retry_successes' => 0,
            'retry_exhausted' => 0,
            'entities' => [],
        ];
    }
}
