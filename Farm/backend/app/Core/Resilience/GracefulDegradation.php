<?php

namespace Farm\Backend\App\Core\Resilience;

/**
 * Graceful Degradation Manager
 * 
 * Provides fallback strategies when services are unavailable or overloaded.
 * Allows applications to continue functioning with reduced functionality.
 * 
 * Degradation Strategies:
 * - FAIL_OPEN: Return default/cached data instead of failing
 * - FAIL_CLOSED: Reject request immediately
 * - QUEUE: Queue request for later processing
 * - REDIRECT: Redirect to alternative service
 * 
 * Usage:
 * ```php
 * $degradation = new GracefulDegradation();
 * 
 * // Execute with fallback
 * $result = $degradation->execute(
 *     fn() => $externalService->getData(),
 *     fn() => $cache->get('fallback_data')
 * );
 * 
 * // Check if service is degraded
 * if ($degradation->isDegraded('payment-service')) {
 *     return $this->showMaintenanceMessage();
 * }
 * ```
 */
class GracefulDegradation
{
    private const STATE_FILE = __DIR__ . '/../../../storage/resilience/degradation_state.json';
    private const STATS_FILE = __DIR__ . '/../../../storage/resilience/degradation_stats.json';
    
    private array $state = [];
    private array $stats = [];
    
    public function __construct()
    {
        $this->loadState();
        $this->loadStats();
    }
    
    /**
     * Execute operation with graceful degradation
     * 
     * @param callable $primary Primary operation
     * @param callable|null $fallback Fallback operation
     * @param string $serviceName Service identifier
     * @return mixed
     * @throws \Exception
     */
    public function execute(callable $primary, ?callable $fallback = null, string $serviceName = 'default')
    {
        try {
            // Check if service is manually degraded
            if ($this->isDegraded($serviceName)) {
                $this->recordDegradation($serviceName, 'manual');
                
                if ($fallback !== null) {
                    return $fallback();
                }
                
                throw new \Exception("Service $serviceName is degraded");
            }
            
            // Execute primary operation
            $result = $primary();
            $this->recordSuccess($serviceName);
            
            return $result;
            
        } catch (\Exception $e) {
            $this->recordFailure($serviceName, $e->getMessage());
            
            // Try fallback if available
            if ($fallback !== null) {
                try {
                    return $fallback();
                } catch (\Exception $fallbackError) {
                    // Log fallback failure
                    error_log("Fallback failed for $serviceName: " . $fallbackError->getMessage());
                    throw $e; // Throw original exception
                }
            }
            
            throw $e;
        }
    }
    
    /**
     * Enable degraded mode for service
     * 
     * @param string $serviceName
     * @param string $reason
     * @param int|null $durationSeconds
     * @return void
     */
    public function degrade(string $serviceName, string $reason = 'manual', ?int $durationSeconds = null): void
    {
        $this->state[$serviceName] = [
            'degraded' => true,
            'reason' => $reason,
            'started_at' => time(),
            'expires_at' => $durationSeconds ? time() + $durationSeconds : null
        ];
        
        $this->saveState();
    }
    
    /**
     * Restore service from degraded mode
     * 
     * @param string $serviceName
     * @return void
     */
    public function restore(string $serviceName): void
    {
        if (isset($this->state[$serviceName])) {
            $this->state[$serviceName]['degraded'] = false;
            $this->state[$serviceName]['restored_at'] = time();
            $this->saveState();
        }
    }
    
    /**
     * Check if service is currently degraded
     * 
     * @param string $serviceName
     * @return bool
     */
    public function isDegraded(string $serviceName): bool
    {
        if (!isset($this->state[$serviceName])) {
            return false;
        }
        
        $state = $this->state[$serviceName];
        
        if (!$state['degraded']) {
            return false;
        }
        
        // Check if degradation has expired
        if ($state['expires_at'] !== null && time() > $state['expires_at']) {
            $this->restore($serviceName);
            return false;
        }
        
        return true;
    }
    
    /**
     * Get degradation status for service
     * 
     * @param string $serviceName
     * @return array|null
     */
    public function getStatus(string $serviceName): ?array
    {
        if (!isset($this->state[$serviceName])) {
            return null;
        }
        
        return $this->state[$serviceName];
    }
    
    /**
     * Get all degraded services
     * 
     * @return array
     */
    public function getAllDegraded(): array
    {
        return array_filter($this->state, fn($state) => $state['degraded'] ?? false);
    }
    
    /**
     * Get statistics for service
     * 
     * @param string $serviceName
     * @return array
     */
    public function getStats(string $serviceName): array
    {
        return $this->stats[$serviceName] ?? [
            'total_calls' => 0,
            'success_count' => 0,
            'failure_count' => 0,
            'degradation_count' => 0,
            'last_success' => null,
            'last_failure' => null
        ];
    }
    
    /**
     * Clear all degraded states
     * 
     * @return void
     */
    public function clearAll(): void
    {
        foreach (array_keys($this->state) as $service) {
            $this->restore($service);
        }
    }
    
    /**
     * Record successful operation
     * 
     * @param string $serviceName
     * @return void
     */
    private function recordSuccess(string $serviceName): void
    {
        if (!isset($this->stats[$serviceName])) {
            $this->stats[$serviceName] = [
                'total_calls' => 0,
                'success_count' => 0,
                'failure_count' => 0,
                'degradation_count' => 0
            ];
        }
        
        $this->stats[$serviceName]['total_calls']++;
        $this->stats[$serviceName]['success_count']++;
        $this->stats[$serviceName]['last_success'] = time();
        
        $this->saveStats();
    }
    
    /**
     * Record failed operation
     * 
     * @param string $serviceName
     * @param string $error
     * @return void
     */
    private function recordFailure(string $serviceName, string $error): void
    {
        if (!isset($this->stats[$serviceName])) {
            $this->stats[$serviceName] = [
                'total_calls' => 0,
                'success_count' => 0,
                'failure_count' => 0,
                'degradation_count' => 0
            ];
        }
        
        $this->stats[$serviceName]['total_calls']++;
        $this->stats[$serviceName]['failure_count']++;
        $this->stats[$serviceName]['last_failure'] = time();
        $this->stats[$serviceName]['last_error'] = $error;
        
        $this->saveStats();
    }
    
    /**
     * Record degradation usage
     * 
     * @param string $serviceName
     * @param string $reason
     * @return void
     */
    private function recordDegradation(string $serviceName, string $reason): void
    {
        if (!isset($this->stats[$serviceName])) {
            $this->stats[$serviceName] = [
                'total_calls' => 0,
                'success_count' => 0,
                'failure_count' => 0,
                'degradation_count' => 0
            ];
        }
        
        $this->stats[$serviceName]['degradation_count']++;
        $this->stats[$serviceName]['last_degradation'] = time();
        $this->stats[$serviceName]['last_degradation_reason'] = $reason;
        
        $this->saveStats();
    }
    
    /**
     * Load state from file
     * 
     * @return void
     */
    private function loadState(): void
    {
        if (file_exists(self::STATE_FILE)) {
            $this->state = json_decode(file_get_contents(self::STATE_FILE), true) ?? [];
        }
    }
    
    /**
     * Save state to file
     * 
     * @return void
     */
    private function saveState(): void
    {
        $dir = dirname(self::STATE_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents(self::STATE_FILE, json_encode($this->state, JSON_PRETTY_PRINT));
    }
    
    /**
     * Load stats from file
     * 
     * @return void
     */
    private function loadStats(): void
    {
        if (file_exists(self::STATS_FILE)) {
            $this->stats = json_decode(file_get_contents(self::STATS_FILE), true) ?? [];
        }
    }
    
    /**
     * Save stats to file
     * 
     * @return void
     */
    private function saveStats(): void
    {
        $dir = dirname(self::STATS_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents(self::STATS_FILE, json_encode($this->stats, JSON_PRETTY_PRINT));
    }
}
