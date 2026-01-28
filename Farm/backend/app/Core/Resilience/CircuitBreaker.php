<?php

namespace PHPFrarm\Core\Resilience;

use PHPFrarm\Core\Logger;

/**
 * Circuit Breaker Pattern Implementation
 * 
 * Prevents cascading failures by stopping requests to failing services
 * 
 * States:
 * - CLOSED: Normal operation, requests pass through
 * - OPEN: Service failing, all requests fail fast
 * - HALF_OPEN: Testing if service recovered
 * 
 * Configuration:
 * - Failure threshold: Number of failures before opening
 * - Timeout: How long to wait in OPEN state
 * - Success threshold: Successes needed in HALF_OPEN to close
 * 
 * Usage:
 * ```php
 * $breaker = new CircuitBreaker('external_api');
 * $result = $breaker->call(function() {
 *     return callExternalAPI();
 * });
 * ```
 */
class CircuitBreaker
{
    const STATE_CLOSED = 'closed';
    const STATE_OPEN = 'open';
    const STATE_HALF_OPEN = 'half_open';
    
    private string $name;
    private string $storageKey;
    private int $failureThreshold;
    private int $successThreshold;
    private int $timeout;
    private string $cacheDir;
    
    /**
     * Constructor
     * 
     * @param string $name Unique identifier for this circuit breaker
     * @param int $failureThreshold Failures before opening (default: 5)
     * @param int $timeout Seconds to wait before HALF_OPEN (default: 60)
     * @param int $successThreshold Successes in HALF_OPEN to close (default: 2)
     */
    public function __construct(
        string $name,
        int $failureThreshold = 5,
        int $timeout = 60,
        int $successThreshold = 2
    ) {
        $this->name = $name;
        $this->failureThreshold = $failureThreshold;
        $this->timeout = $timeout;
        $this->successThreshold = $successThreshold;
        $this->cacheDir = sys_get_temp_dir() . '/phpfrarm/circuit_breakers';
        $this->storageKey = $this->cacheDir . '/' . md5($name) . '.json';
        
        // Ensure cache directory exists
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Execute a callable with circuit breaker protection
     */
    public function call(callable $callback): mixed
    {
        $state = $this->getState();
        
        // If circuit is OPEN, fail fast
        if ($state === self::STATE_OPEN) {
            if ($this->shouldAttemptReset()) {
                $this->setState(self::STATE_HALF_OPEN);
                Logger::info('Circuit breaker transitioning to HALF_OPEN', [
                    'circuit' => $this->name
                ]);
            } else {
                Logger::warning('Circuit breaker is OPEN, request blocked', [
                    'circuit' => $this->name
                ]);
                
                throw new CircuitBreakerException(
                    "Circuit breaker '{$this->name}' is OPEN. Service unavailable."
                );
            }
        }
        
        try {
            // Execute the callback
            $result = $callback();
            
            // Success - record it
            $this->recordSuccess();
            
            return $result;
            
        } catch (\Throwable $e) {
            // Failure - record it
            $this->recordFailure($e);
            
            throw $e;
        }
    }
    
    /**
     * Get current circuit state
     */
    public function getState(): string
    {
        $data = $this->loadState();
        return $data['state'] ?? self::STATE_CLOSED;
    }
    
    /**
     * Set circuit state
     */
    private function setState(string $state): void
    {
        $data = $this->loadState();
        $data['state'] = $state;
        $data['state_changed_at'] = time();
        
        $this->saveState($data);
    }
    
    /**
     * Record successful call
     */
    private function recordSuccess(): void
    {
        $data = $this->loadState();
        $state = $data['state'] ?? self::STATE_CLOSED;
        
        if ($state === self::STATE_HALF_OPEN) {
            $data['consecutive_successes'] = ($data['consecutive_successes'] ?? 0) + 1;
            
            // Check if we can close the circuit
            if ($data['consecutive_successes'] >= $this->successThreshold) {
                Logger::info('Circuit breaker closing after successful recovery', [
                    'circuit' => $this->name,
                    'successes' => $data['consecutive_successes']
                ]);
                
                $this->reset();
                return;
            }
        } elseif ($state === self::STATE_CLOSED) {
            // Reset failure count on success
            $data['failure_count'] = 0;
            $data['consecutive_failures'] = 0;
        }
        
        $data['last_success_time'] = time();
        $data['total_successes'] = ($data['total_successes'] ?? 0) + 1;
        
        $this->saveState($data);
    }
    
    /**
     * Record failed call
     */
    private function recordFailure(\Throwable $exception): void
    {
        $data = $this->loadState();
        $state = $data['state'] ?? self::STATE_CLOSED;
        
        $data['failure_count'] = ($data['failure_count'] ?? 0) + 1;
        $data['consecutive_failures'] = ($data['consecutive_failures'] ?? 0) + 1;
        $data['last_failure_time'] = time();
        $data['last_exception'] = get_class($exception) . ': ' . $exception->getMessage();
        $data['total_failures'] = ($data['total_failures'] ?? 0) + 1;
        
        // Check if we should open the circuit
        if ($state === self::STATE_CLOSED && 
            $data['consecutive_failures'] >= $this->failureThreshold) {
            
            Logger::error('Circuit breaker opening due to failures', [
                'circuit' => $this->name,
                'failures' => $data['consecutive_failures'],
                'threshold' => $this->failureThreshold,
                'last_exception' => $data['last_exception']
            ]);
            
            $data['state'] = self::STATE_OPEN;
            $data['state_changed_at'] = time();
        }
        
        // If in HALF_OPEN and still failing, go back to OPEN
        if ($state === self::STATE_HALF_OPEN) {
            Logger::warning('Circuit breaker reopening after failed recovery attempt', [
                'circuit' => $this->name
            ]);
            
            $data['state'] = self::STATE_OPEN;
            $data['state_changed_at'] = time();
            $data['consecutive_successes'] = 0;
        }
        
        $this->saveState($data);
    }
    
    /**
     * Check if enough time has passed to attempt reset
     */
    private function shouldAttemptReset(): bool
    {
        $data = $this->loadState();
        $stateChangedAt = $data['state_changed_at'] ?? 0;
        
        return (time() - $stateChangedAt) >= $this->timeout;
    }
    
    /**
     * Reset circuit breaker to CLOSED state
     */
    public function reset(): void
    {
        $data = [
            'state' => self::STATE_CLOSED,
            'state_changed_at' => time(),
            'failure_count' => 0,
            'consecutive_failures' => 0,
            'consecutive_successes' => 0,
            'total_successes' => 0,
            'total_failures' => 0,
        ];
        
        $this->saveState($data);
        
        Logger::info('Circuit breaker reset', ['circuit' => $this->name]);
    }
    
    /**
     * Get circuit breaker statistics
     */
    public function getStats(): array
    {
        $data = $this->loadState();
        
        return [
            'name' => $this->name,
            'state' => $data['state'] ?? self::STATE_CLOSED,
            'failure_count' => $data['failure_count'] ?? 0,
            'consecutive_failures' => $data['consecutive_failures'] ?? 0,
            'consecutive_successes' => $data['consecutive_successes'] ?? 0,
            'total_successes' => $data['total_successes'] ?? 0,
            'total_failures' => $data['total_failures'] ?? 0,
            'last_failure_time' => $data['last_failure_time'] ?? null,
            'last_success_time' => $data['last_success_time'] ?? null,
            'last_exception' => $data['last_exception'] ?? null,
            'state_changed_at' => $data['state_changed_at'] ?? null,
        ];
    }
    
    /**
     * Load circuit state from storage
     */
    private function loadState(): array
    {
        if (!file_exists($this->storageKey)) {
            return [];
        }
        
        $json = file_get_contents($this->storageKey);
        return json_decode($json, true) ?? [];
    }
    
    /**
     * Save circuit state to storage
     */
    private function saveState(array $data): void
    {
        file_put_contents($this->storageKey, json_encode($data, JSON_PRETTY_PRINT));
    }
}

/**
 * Circuit Breaker Exception
 */
class CircuitBreakerException extends \Exception {}
