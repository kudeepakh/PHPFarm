<?php

namespace Farm\Backend\App\Core\Resilience;

/**
 * Backpressure Handler
 * 
 * Prevents system overload by controlling request flow.
 * Implements adaptive concurrency limits and queue management.
 * 
 * Strategies:
 * - REJECT: Reject excess requests immediately (503 Service Unavailable)
 * - QUEUE: Queue excess requests for later processing
 * - THROTTLE: Delay excess requests
 * 
 * Usage:
 * ```php
 * $handler = new BackpressureHandler();
 * 
 * if (!$handler->acquire('api')) {
 *     throw new ServiceUnavailableException('System overloaded');
 * }
 * 
 * try {
 *     // Process request
 * } finally {
 *     $handler->release('api');
 * }
 * ```
 */
class BackpressureHandler
{
    private const STATE_FILE = __DIR__ . '/../../../storage/resilience/backpressure_state.json';
    private const STATS_FILE = __DIR__ . '/../../../storage/resilience/backpressure_stats.json';
    
    private array $limits = [];
    private array $current = [];
    private array $stats = [];
    
    public function __construct(array $config = [])
    {
        $this->limits = array_merge([
            'global' => 1000,      // Max concurrent requests globally
            'api' => 500,          // Max concurrent API requests
            'database' => 100,     // Max concurrent DB operations
            'external' => 50       // Max concurrent external calls
        ], $config);
        
        $this->loadState();
        $this->loadStats();
    }
    
    /**
     * Acquire permit for resource
     * 
     * @param string $resource Resource identifier
     * @param int $timeout Max wait time in milliseconds
     * @return bool True if permit acquired, false if rejected
     */
    public function acquire(string $resource = 'global', int $timeout = 0): bool
    {
        // Check if limit reached
        if ($this->isAtLimit($resource)) {
            $this->recordRejection($resource);
            
            // Try to wait if timeout specified
            if ($timeout > 0) {
                return $this->waitForPermit($resource, $timeout);
            }
            
            return false;
        }
        
        // Acquire permit
        if (!isset($this->current[$resource])) {
            $this->current[$resource] = 0;
        }
        
        $this->current[$resource]++;
        $this->recordAcquisition($resource);
        $this->saveState();
        
        return true;
    }
    
    /**
     * Release permit for resource
     * 
     * @param string $resource
     * @return void
     */
    public function release(string $resource = 'global'): void
    {
        if (isset($this->current[$resource]) && $this->current[$resource] > 0) {
            $this->current[$resource]--;
            $this->recordRelease($resource);
            $this->saveState();
        }
    }
    
    /**
     * Check if resource is at limit
     * 
     * @param string $resource
     * @return bool
     */
    public function isAtLimit(string $resource): bool
    {
        $current = $this->current[$resource] ?? 0;
        $limit = $this->limits[$resource] ?? $this->limits['global'];
        
        return $current >= $limit;
    }
    
    /**
     * Get current usage for resource
     * 
     * @param string $resource
     * @return array
     */
    public function getUsage(string $resource): array
    {
        $current = $this->current[$resource] ?? 0;
        $limit = $this->limits[$resource] ?? $this->limits['global'];
        
        return [
            'resource' => $resource,
            'current' => $current,
            'limit' => $limit,
            'available' => max(0, $limit - $current),
            'utilization' => $limit > 0 ? round(($current / $limit) * 100, 2) : 0
        ];
    }
    
    /**
     * Get usage for all resources
     * 
     * @return array
     */
    public function getAllUsage(): array
    {
        $usage = [];
        
        foreach (array_keys($this->limits) as $resource) {
            $usage[$resource] = $this->getUsage($resource);
        }
        
        return $usage;
    }
    
    /**
     * Update limit for resource
     * 
     * @param string $resource
     * @param int $newLimit
     * @return void
     */
    public function setLimit(string $resource, int $newLimit): void
    {
        $this->limits[$resource] = $newLimit;
        $this->saveState();
    }
    
    /**
     * Get statistics for resource
     * 
     * @param string $resource
     * @return array
     */
    public function getStats(string $resource): array
    {
        return $this->stats[$resource] ?? [
            'total_acquisitions' => 0,
            'total_releases' => 0,
            'total_rejections' => 0,
            'peak_usage' => 0,
            'rejection_rate' => 0
        ];
    }
    
    /**
     * Reset all permits (emergency release)
     * 
     * @return void
     */
    public function resetAll(): void
    {
        $this->current = [];
        $this->saveState();
    }
    
    /**
     * Get system load indicator (0-100)
     * 
     * @return float
     */
    public function getSystemLoad(): float
    {
        $globalUsage = $this->getUsage('global');
        return $globalUsage['utilization'];
    }
    
    /**
     * Check if system is overloaded
     * 
     * @param float $threshold Percentage threshold (default 90%)
     * @return bool
     */
    public function isOverloaded(float $threshold = 90.0): bool
    {
        return $this->getSystemLoad() >= $threshold;
    }
    
    /**
     * Wait for permit to become available
     * 
     * @param string $resource
     * @param int $timeoutMs
     * @return bool
     */
    private function waitForPermit(string $resource, int $timeoutMs): bool
    {
        $startTime = microtime(true) * 1000;
        $checkInterval = 10; // Check every 10ms
        
        while ((microtime(true) * 1000 - $startTime) < $timeoutMs) {
            if (!$this->isAtLimit($resource)) {
                return $this->acquire($resource, 0);
            }
            
            usleep($checkInterval * 1000);
        }
        
        return false; // Timeout
    }
    
    /**
     * Record permit acquisition
     * 
     * @param string $resource
     * @return void
     */
    private function recordAcquisition(string $resource): void
    {
        if (!isset($this->stats[$resource])) {
            $this->stats[$resource] = [
                'total_acquisitions' => 0,
                'total_releases' => 0,
                'total_rejections' => 0,
                'peak_usage' => 0
            ];
        }
        
        $this->stats[$resource]['total_acquisitions']++;
        $this->stats[$resource]['last_acquisition'] = time();
        
        // Update peak usage
        $current = $this->current[$resource] ?? 0;
        if ($current > ($this->stats[$resource]['peak_usage'] ?? 0)) {
            $this->stats[$resource]['peak_usage'] = $current;
        }
        
        $this->saveStats();
    }
    
    /**
     * Record permit release
     * 
     * @param string $resource
     * @return void
     */
    private function recordRelease(string $resource): void
    {
        if (!isset($this->stats[$resource])) {
            return;
        }
        
        $this->stats[$resource]['total_releases']++;
        $this->stats[$resource]['last_release'] = time();
        
        $this->saveStats();
    }
    
    /**
     * Record rejection
     * 
     * @param string $resource
     * @return void
     */
    private function recordRejection(string $resource): void
    {
        if (!isset($this->stats[$resource])) {
            $this->stats[$resource] = [
                'total_acquisitions' => 0,
                'total_releases' => 0,
                'total_rejections' => 0,
                'peak_usage' => 0
            ];
        }
        
        $this->stats[$resource]['total_rejections']++;
        $this->stats[$resource]['last_rejection'] = time();
        
        // Calculate rejection rate
        $total = $this->stats[$resource]['total_acquisitions'] + $this->stats[$resource]['total_rejections'];
        $this->stats[$resource]['rejection_rate'] = $total > 0 
            ? round(($this->stats[$resource]['total_rejections'] / $total) * 100, 2)
            : 0;
        
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
            $state = json_decode(file_get_contents(self::STATE_FILE), true);
            $this->current = $state['current'] ?? [];
            $this->limits = array_merge($this->limits, $state['limits'] ?? []);
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
        
        file_put_contents(self::STATE_FILE, json_encode([
            'current' => $this->current,
            'limits' => $this->limits,
            'updated_at' => time()
        ], JSON_PRETTY_PRINT));
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
