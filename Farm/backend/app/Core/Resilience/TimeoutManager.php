<?php

namespace PHPFrarm\Core\Resilience;

use PHPFrarm\Core\Logger;

/**
 * Timeout Manager
 * 
 * Provides configurable timeout enforcement for operations
 * Prevents long-running operations from hanging the application
 * 
 * Features:
 * - Function execution timeout
 * - Database query timeout
 * - HTTP request timeout
 * - Async timeout support
 * 
 * Usage:
 * ```php
 * $timeout = new TimeoutManager(5); // 5 seconds
 * $result = $timeout->execute(function() {
 *     return slowOperation();
 * });
 * ```
 */
class TimeoutManager
{
    private int $timeoutSeconds;
    private ?int $startTime = null;
    
    /**
     * Constructor
     * 
     * @param int $timeoutSeconds Maximum execution time in seconds
     */
    public function __construct(int $timeoutSeconds = 30)
    {
        $this->timeoutSeconds = $timeoutSeconds;
    }
    
    /**
     * Execute a callable with timeout protection
     */
    public function execute(callable $callback): mixed
    {
        $this->startTime = time();
        
        // Set PHP execution timeout
        $originalTimeout = ini_get('max_execution_time');
        set_time_limit($this->timeoutSeconds);
        
        try {
            // Register shutdown function to detect timeout
            register_shutdown_function(function() {
                $error = error_get_last();
                if ($error && $error['type'] === E_ERROR) {
                    if (strpos($error['message'], 'Maximum execution time') !== false) {
                        Logger::error('Operation timed out', [
                            'timeout_seconds' => $this->timeoutSeconds
                        ]);
                    }
                }
            });
            
            // Execute the callback
            $result = $callback();
            
            // Log execution time
            $elapsed = time() - $this->startTime;
            
            if ($elapsed > ($this->timeoutSeconds * 0.8)) {
                Logger::warning('Operation approaching timeout', [
                    'elapsed_seconds' => $elapsed,
                    'timeout_seconds' => $this->timeoutSeconds
                ]);
            }
            
            return $result;
            
        } catch (\Throwable $e) {
            $elapsed = time() - $this->startTime;
            
            // Check if timeout was the cause
            if ($elapsed >= $this->timeoutSeconds) {
                Logger::error('Operation timed out with exception', [
                    'elapsed_seconds' => $elapsed,
                    'timeout_seconds' => $this->timeoutSeconds,
                    'exception' => get_class($e)
                ]);
                
                throw new TimeoutException(
                    "Operation timed out after {$this->timeoutSeconds} seconds",
                    0,
                    $e
                );
            }
            
            throw $e;
            
        } finally {
            // Restore original timeout
            set_time_limit((int)$originalTimeout);
        }
    }
    
    /**
     * Check if timeout has been exceeded
     */
    public function hasTimedOut(): bool
    {
        if ($this->startTime === null) {
            return false;
        }
        
        return (time() - $this->startTime) >= $this->timeoutSeconds;
    }
    
    /**
     * Get remaining time
     */
    public function getRemainingTime(): int
    {
        if ($this->startTime === null) {
            return $this->timeoutSeconds;
        }
        
        $elapsed = time() - $this->startTime;
        $remaining = $this->timeoutSeconds - $elapsed;
        
        return max(0, $remaining);
    }
    
    /**
     * Get elapsed time
     */
    public function getElapsedTime(): int
    {
        if ($this->startTime === null) {
            return 0;
        }
        
        return time() - $this->startTime;
    }
    
    /**
     * Database query timeout wrapper
     */
    public static function forDatabase(callable $callback, int $timeoutSeconds = 10): mixed
    {
        $manager = new self($timeoutSeconds);
        
        Logger::debug('Database query started with timeout', [
            'timeout_seconds' => $timeoutSeconds
        ]);
        
        try {
            return $manager->execute($callback);
        } catch (TimeoutException $e) {
            Logger::error('Database query timed out', [
                'timeout_seconds' => $timeoutSeconds
            ]);
            
            throw new \PDOException('Database query timed out', 0, $e);
        }
    }
    
    /**
     * HTTP request timeout wrapper
     */
    public static function forHttpRequest(string $url, array $options = [], int $timeoutSeconds = 30): mixed
    {
        $manager = new self($timeoutSeconds);
        
        return $manager->execute(function() use ($url, $options, $timeoutSeconds) {
            $ch = curl_init($url);
            
            // Set curl timeout
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSeconds);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            // Apply custom options
            foreach ($options as $option => $value) {
                curl_setopt($ch, $option, $value);
            }
            
            $response = curl_exec($ch);
            $error = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            curl_close($ch);
            
            if ($error) {
                throw new \Exception("HTTP request failed: $error");
            }
            
            Logger::debug('HTTP request completed', [
                'url' => $url,
                'http_code' => $httpCode
            ]);
            
            return [
                'response' => $response,
                'http_code' => $httpCode
            ];
        });
    }
    
    /**
     * Async operation timeout wrapper
     */
    public static function forAsync(callable $callback, int $timeoutSeconds = 60): mixed
    {
        $manager = new self($timeoutSeconds);
        
        // For true async, would use ext-parallel or swoole
        // This is a synchronous fallback
        
        return $manager->execute($callback);
    }
    
    /**
     * Create timeout from environment config
     */
    public static function fromConfig(string $operation): self
    {
        $configMap = [
            'database' => $_ENV['TIMEOUT_DATABASE'] ?? 10,
            'http' => $_ENV['TIMEOUT_HTTP'] ?? 30,
            'api' => $_ENV['TIMEOUT_API'] ?? 30,
            'job' => $_ENV['TIMEOUT_JOB'] ?? 300,
            'default' => $_ENV['TIMEOUT_DEFAULT'] ?? 30,
        ];
        
        $timeout = (int)($configMap[$operation] ?? $configMap['default']);
        
        return new self($timeout);
    }
}

/**
 * Timeout Exception
 */
class TimeoutException extends \RuntimeException {}
