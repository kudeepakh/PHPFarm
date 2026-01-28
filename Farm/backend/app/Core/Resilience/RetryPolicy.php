<?php

namespace App\Core\Resilience;

use PHPFrarm\Core\Logger;
use Exception;
use Throwable;

/**
 * Retry Policy
 * 
 * Provides intelligent retry logic with configurable backoff strategies,
 * conditional retries, and integration with circuit breakers.
 */
class RetryPolicy
{
    private int $maxAttempts;
    private BackoffStrategy $backoffStrategy;
    private array $retryOnExceptions;
    private ?string $circuitBreakerName;
    private ?int $timeoutMs;
    private bool $enabled;
    private RetryStatistics $statistics;

    public function __construct(
        int $maxAttempts = 3,
        ?BackoffStrategy $backoffStrategy = null,
        array $retryOnExceptions = [],
        ?string $circuitBreakerName = null,
        ?int $timeoutMs = null
    ) {
        $this->maxAttempts = $maxAttempts;
        $this->backoffStrategy = $backoffStrategy ?? new ExponentialBackoff(1000, 30000, true);
        $this->retryOnExceptions = $retryOnExceptions;
        $this->circuitBreakerName = $circuitBreakerName;
        $this->timeoutMs = $timeoutMs;
        $this->enabled = true;
        $this->statistics = new RetryStatistics();
    }

    /**
     * Execute operation with retry logic
     * 
     * @param callable $operation The operation to execute
     * @param array $context Additional context for logging
     * @return mixed Result of the operation
     * @throws Exception If all retries are exhausted
     */
    public function execute(callable $operation, array $context = [])
    {
        if (!$this->enabled) {
            return $operation();
        }

        $attemptNumber = 1;
        $lastException = null;
        $operationName = $context['operation_name'] ?? 'anonymous';

        // Check circuit breaker before attempting
        if ($this->circuitBreakerName && $this->isCircuitBreakerOpen()) {
            Logger::warning('Circuit breaker is open, skipping retry', [
                'circuit_breaker' => $this->circuitBreakerName,
                'operation' => $operationName
            ]);
            throw new Exception("Circuit breaker '{$this->circuitBreakerName}' is open");
        }

        while ($attemptNumber <= $this->maxAttempts) {
            try {
                Logger::debug('Executing operation', [
                    'operation' => $operationName,
                    'attempt' => $attemptNumber,
                    'max_attempts' => $this->maxAttempts
                ]);

                // Execute with timeout if configured
                if ($this->timeoutMs) {
                    $result = $this->executeWithTimeout($operation, $this->timeoutMs);
                } else {
                    $result = $operation();
                }

                // Success - record statistics
                if ($attemptNumber > 1) {
                    $this->statistics->recordRetrySuccess($operationName, $attemptNumber);
                    Logger::info('Operation succeeded after retry', [
                        'operation' => $operationName,
                        'attempts' => $attemptNumber
                    ]);
                }

                return $result;

            } catch (Throwable $e) {
                $lastException = $e;

                // Check if we should retry this exception
                if (!$this->shouldRetry($e, $attemptNumber)) {
                    Logger::warning('Exception not retryable', [
                        'operation' => $operationName,
                        'exception' => get_class($e),
                        'message' => $e->getMessage(),
                        'attempt' => $attemptNumber
                    ]);
                    throw $e;
                }

                // Check if we've exhausted retries
                if ($attemptNumber >= $this->maxAttempts) {
                    $this->statistics->recordRetryExhausted($operationName, $attemptNumber);
                    Logger::error('Retry exhausted', [
                        'operation' => $operationName,
                        'attempts' => $attemptNumber,
                        'exception' => get_class($e),
                        'message' => $e->getMessage()
                    ]);
                    throw $e;
                }

                // Calculate delay for next attempt
                $delayMs = $this->backoffStrategy->getDelayMs($attemptNumber + 1);

                $this->statistics->recordRetryAttempt($operationName, $attemptNumber);

                Logger::warning('Operation failed, retrying', [
                    'operation' => $operationName,
                    'attempt' => $attemptNumber,
                    'max_attempts' => $this->maxAttempts,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'next_delay_ms' => $delayMs,
                    'backoff_strategy' => $this->backoffStrategy->getName()
                ]);

                // Sleep before next attempt
                if ($delayMs > 0) {
                    usleep($delayMs * 1000); // Convert ms to microseconds
                }

                $attemptNumber++;
            }
        }

        // This should never be reached due to throw in catch block
        throw $lastException ?? new Exception('Retry failed with unknown error');
    }

    /**
     * Check if exception should trigger a retry
     */
    private function shouldRetry(Throwable $e, int $attemptNumber): bool
    {
        // If no specific exceptions configured, retry all
        if (empty($this->retryOnExceptions)) {
            return true;
        }

        // Check if exception matches any configured type
        foreach ($this->retryOnExceptions as $exceptionClass) {
            if ($e instanceof $exceptionClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * Execute operation with timeout
     */
    private function executeWithTimeout(callable $operation, int $timeoutMs)
    {
        $timeoutManager = TimeoutManager::getInstance();
        return $timeoutManager->execute($operation, $timeoutMs / 1000); // Convert to seconds
    }

    /**
     * Check if circuit breaker is open
     */
    private function isCircuitBreakerOpen(): bool
    {
        if (!$this->circuitBreakerName) {
            return false;
        }

        $circuitBreaker = CircuitBreaker::getInstance($this->circuitBreakerName);
        return $circuitBreaker->getState() === CircuitBreaker::STATE_OPEN;
    }

    /**
     * Builder methods for fluent configuration
     */
    public function withMaxAttempts(int $max): self
    {
        $this->maxAttempts = $max;
        return $this;
    }

    public function withBackoff(BackoffStrategy $strategy): self
    {
        $this->backoffStrategy = $strategy;
        return $this;
    }

    public function onlyOn(array $exceptions): self
    {
        $this->retryOnExceptions = $exceptions;
        return $this;
    }

    public function withCircuitBreaker(string $name): self
    {
        $this->circuitBreakerName = $name;
        return $this;
    }

    public function withTimeout(int $timeoutMs): self
    {
        $this->timeoutMs = $timeoutMs;
        return $this;
    }

    public function disable(): self
    {
        $this->enabled = false;
        return $this;
    }

    public function enable(): self
    {
        $this->enabled = true;
        return $this;
    }

    /**
     * Get retry statistics
     */
    public function getStatistics(): array
    {
        return $this->statistics->getStats();
    }

    /**
     * Static factory methods for common configurations
     */
    public static function fixed(int $maxAttempts = 3, int $delayMs = 1000): self
    {
        return new self($maxAttempts, new FixedBackoff($delayMs));
    }

    public static function exponential(int $maxAttempts = 3, int $baseDelayMs = 1000): self
    {
        return new self($maxAttempts, new ExponentialBackoff($baseDelayMs, 30000, false));
    }

    public static function exponentialWithJitter(int $maxAttempts = 3, int $baseDelayMs = 1000): self
    {
        return new self($maxAttempts, new ExponentialBackoff($baseDelayMs, 30000, true));
    }

    public static function fibonacci(int $maxAttempts = 3, int $multiplierMs = 1000): self
    {
        return new self($maxAttempts, new FibonacciBackoff($multiplierMs));
    }

    public static function linear(int $maxAttempts = 3, int $incrementMs = 1000): self
    {
        return new self($maxAttempts, new LinearBackoff($incrementMs));
    }
}
