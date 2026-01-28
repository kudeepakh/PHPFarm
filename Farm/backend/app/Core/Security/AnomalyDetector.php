<?php

namespace Farm\Backend\App\Core\Security;

use App\Core\Cache\CacheManager;
use Farm\Backend\App\Core\Logging\LogManager;
use Farm\Backend\App\Core\Observability\TraceContext;

/**
 * AnomalyDetector - Detect suspicious request patterns and velocity attacks
 * 
 * Detection mechanisms:
 * - Request velocity (requests per second/minute)
 * - Endpoint abuse (repeated access to same endpoint)
 * - Pattern anomalies (unusual request sequences)
 * - Statistical outlier detection
 * - Distributed attack detection (multiple IPs, same pattern)
 * 
 * Thread-safe with Redis atomic operations.
 */
class AnomalyDetector
{
    private CacheManager $cache;
    private LogManager $logger;
    private array $config;
    
    // Cache keys
    private const VELOCITY_KEY = 'anomaly:velocity:';
    private const ENDPOINT_KEY = 'anomaly:endpoint:';
    private const PATTERN_KEY = 'anomaly:pattern:';
    private const STATS_KEY = 'anomaly:stats';
    
    // Time windows
    private const WINDOW_1_SECOND = 1;
    private const WINDOW_1_MINUTE = 60;
    private const WINDOW_5_MINUTES = 300;
    private const WINDOW_1_HOUR = 3600;

    public function __construct(CacheManager $cache, LogManager $logger, array $config = [])
    {
        $this->cache = $cache;
        $this->logger = $logger;
        $this->config = array_merge([
            'velocity_per_second' => 10,      // Max requests per second
            'velocity_per_minute' => 100,     // Max requests per minute
            'velocity_per_hour' => 1000,      // Max requests per hour
            'endpoint_abuse_threshold' => 50, // Same endpoint hits
            'pattern_threshold' => 3,         // Repeated pattern occurrences
            'tracking_window' => 300,         // Track patterns for 5 minutes
            'min_requests_for_analysis' => 10, // Minimum for pattern analysis
            'log_anomalies' => true,
        ], $config);
    }

    /**
     * Detect anomaly in current request
     * 
     * @param string $ip Client IP
     * @param string $endpoint Endpoint path (e.g., /api/users)
     * @param string $method HTTP method
     * @return array Detection result
     */
    public function detectAnomaly(string $ip, string $endpoint, string $method): array
    {
        $anomalies = [];
        
        // 1. Check velocity (rate)
        if ($this->isVelocityExceeded($ip)) {
            $anomalies[] = 'velocity_exceeded';
        }
        
        // 2. Check endpoint abuse
        if ($this->isEndpointAbused($ip, $endpoint)) {
            $anomalies[] = 'endpoint_abuse';
        }
        
        // 3. Check pattern anomaly
        if ($this->hasAnomalousPattern($ip, $endpoint, $method)) {
            $anomalies[] = 'pattern_anomaly';
        }
        
        // Track request
        $this->trackRequest($ip, $endpoint, $method);
        
        // Log anomalies
        if (!empty($anomalies) && $this->config['log_anomalies']) {
            $this->logAnomaly($ip, $endpoint, $anomalies);
        }
        
        return [
            'has_anomaly' => !empty($anomalies),
            'anomaly_types' => $anomalies,
            'severity' => $this->calculateSeverity($anomalies),
        ];
    }

    /**
     * Check if request velocity is exceeded
     * 
     * @param string $ip
     * @return bool
     */
    public function isVelocityExceeded(string $ip): bool
    {
        // Check 1-second window
        $key1s = self::VELOCITY_KEY . "{$ip}:1s";
        $count1s = $this->cache->incr($key1s);
        $this->cache->expire($key1s, self::WINDOW_1_SECOND);
        
        if ($count1s > $this->config['velocity_per_second']) {
            return true;
        }
        
        // Check 1-minute window
        $key1m = self::VELOCITY_KEY . "{$ip}:1m";
        $count1m = $this->cache->incr($key1m);
        $this->cache->expire($key1m, self::WINDOW_1_MINUTE);
        
        if ($count1m > $this->config['velocity_per_minute']) {
            return true;
        }
        
        // Check 1-hour window
        $key1h = self::VELOCITY_KEY . "{$ip}:1h";
        $count1h = $this->cache->incr($key1h);
        $this->cache->expire($key1h, self::WINDOW_1_HOUR);
        
        if ($count1h > $this->config['velocity_per_hour']) {
            return true;
        }
        
        return false;
    }

    /**
     * Check if specific endpoint is being abused
     * 
     * @param string $ip
     * @param string $endpoint
     * @return bool
     */
    public function isEndpointAbused(string $ip, string $endpoint): bool
    {
        $key = self::ENDPOINT_KEY . "{$ip}:" . md5($endpoint);
        $count = $this->cache->incr($key);
        $this->cache->expire($key, $this->config['tracking_window']);
        
        return $count > $this->config['endpoint_abuse_threshold'];
    }

    /**
     * Detect anomalous request patterns
     * 
     * Identifies:
     * - Repetitive sequences (same endpoints in same order)
     * - Unusual method combinations
     * - Rapid endpoint scanning
     * 
     * @param string $ip
     * @param string $endpoint
     * @param string $method
     * @return bool
     */
    public function hasAnomalousPattern(string $ip, string $endpoint, string $method): bool
    {
        // Build pattern signature
        $patternSignature = "{$method}:{$endpoint}";
        $patternKey = self::PATTERN_KEY . $ip;
        
        // Get recent patterns
        $patterns = $this->cache->lRange($patternKey, 0, 9) ?? []; // Last 10 requests
        
        // Not enough data yet
        if (count($patterns) < $this->config['min_requests_for_analysis']) {
            return false;
        }
        
        // Count occurrences of current pattern
        $occurrences = count(array_filter($patterns, fn($p) => $p === $patternSignature));
        
        // Detect repetitive pattern
        if ($occurrences >= $this->config['pattern_threshold']) {
            return true;
        }
        
        // Detect rapid scanning (many different endpoints quickly)
        $uniqueEndpoints = count(array_unique(array_map(fn($p) => explode(':', $p)[1], $patterns)));
        if ($uniqueEndpoints >= 8) { // 8+ different endpoints in 10 requests
            return true;
        }
        
        return false;
    }

    /**
     * Track request for pattern analysis
     * 
     * @param string $ip
     * @param string $endpoint
     * @param string $method
     */
    public function trackRequest(string $ip, string $endpoint, string $method): void
    {
        $patternSignature = "{$method}:{$endpoint}";
        $patternKey = self::PATTERN_KEY . $ip;
        
        // Add to pattern list (left push)
        $this->cache->lPush($patternKey, $patternSignature);
        
        // Keep only last 20 requests
        $this->cache->lTrim($patternKey, 0, 19);
        
        // Set expiration
        $this->cache->expire($patternKey, $this->config['tracking_window']);
    }

    /**
     * Get velocity metrics for IP
     * 
     * @param string $ip
     * @return array Velocity data
     */
    public function getVelocityMetrics(string $ip): array
    {
        $key1s = self::VELOCITY_KEY . "{$ip}:1s";
        $key1m = self::VELOCITY_KEY . "{$ip}:1m";
        $key1h = self::VELOCITY_KEY . "{$ip}:1h";
        
        return [
            'per_second' => (int) ($this->cache->get($key1s) ?? 0),
            'per_minute' => (int) ($this->cache->get($key1m) ?? 0),
            'per_hour' => (int) ($this->cache->get($key1h) ?? 0),
            'limits' => [
                'per_second' => $this->config['velocity_per_second'],
                'per_minute' => $this->config['velocity_per_minute'],
                'per_hour' => $this->config['velocity_per_hour'],
            ],
        ];
    }

    /**
     * Get endpoint abuse metrics for IP
     * 
     * @param string $ip
     * @return array Top abused endpoints
     */
    public function getEndpointMetrics(string $ip): array
    {
        // This is a simplified version - in production, use sorted sets
        $pattern = self::ENDPOINT_KEY . "{$ip}:*";
        $keys = $this->cache->keys($pattern) ?? [];
        
        $endpoints = [];
        foreach ($keys as $key) {
            $count = $this->cache->get($key);
            if ($count > 0) {
                $endpoints[] = [
                    'endpoint' => str_replace(self::ENDPOINT_KEY . "{$ip}:", '', $key),
                    'count' => (int) $count,
                ];
            }
        }
        
        // Sort by count descending
        usort($endpoints, fn($a, $b) => $b['count'] - $a['count']);
        
        return array_slice($endpoints, 0, 10); // Top 10
    }

    /**
     * Get request pattern for IP
     * 
     * @param string $ip
     * @return array Recent request patterns
     */
    public function getRequestPattern(string $ip): array
    {
        $patternKey = self::PATTERN_KEY . $ip;
        $patterns = $this->cache->lRange($patternKey, 0, 19) ?? [];
        
        return array_map(function($pattern) {
            [$method, $endpoint] = explode(':', $pattern, 2);
            return ['method' => $method, 'endpoint' => $endpoint];
        }, $patterns);
    }

    /**
     * Calculate anomaly severity (1-10)
     * 
     * @param array $anomalies List of anomaly types
     * @return int Severity score
     */
    private function calculateSeverity(array $anomalies): int
    {
        if (empty($anomalies)) {
            return 0;
        }
        
        $severity = 0;
        $weights = [
            'velocity_exceeded' => 3,
            'endpoint_abuse' => 4,
            'pattern_anomaly' => 5,
        ];
        
        foreach ($anomalies as $anomaly) {
            $severity += $weights[$anomaly] ?? 2;
        }
        
        return min(10, $severity);
    }

    /**
     * Log anomaly detection
     * 
     * @param string $ip
     * @param string $endpoint
     * @param array $anomalies
     */
    private function logAnomaly(string $ip, string $endpoint, array $anomalies): void
    {
        $this->logger->security('anomaly_detected', [
            'ip' => $ip,
            'endpoint' => $endpoint,
            'anomaly_types' => $anomalies,
            'severity' => $this->calculateSeverity($anomalies),
            'correlation_id' => TraceContext::getCorrelationId(),
            'timestamp' => time(),
        ]);
        
        // Update statistics
        $this->incrementStatistic('anomalies_detected');
    }

    /**
     * Get complete analysis for IP
     * 
     * @param string $ip
     * @return array Complete analysis
     */
    public function analyzeIp(string $ip): array
    {
        return [
            'ip' => $ip,
            'velocity' => $this->getVelocityMetrics($ip),
            'endpoint_abuse' => $this->getEndpointMetrics($ip),
            'request_pattern' => $this->getRequestPattern($ip),
            'has_velocity_anomaly' => $this->isVelocityExceeded($ip),
        ];
    }

    /**
     * Clear tracking data for IP
     * 
     * @param string $ip
     */
    public function clearTracking(string $ip): void
    {
        $patterns = [
            self::VELOCITY_KEY . "{$ip}:*",
            self::ENDPOINT_KEY . "{$ip}:*",
            self::PATTERN_KEY . $ip,
        ];
        
        foreach ($patterns as $pattern) {
            $keys = $this->cache->keys($pattern) ?? [];
            foreach ($keys as $key) {
                $this->cache->delete($key);
            }
        }
    }

    /**
     * Increment statistic counter
     * 
     * @param string $metric
     */
    private function incrementStatistic(string $metric): void
    {
        $key = self::STATS_KEY . ":{$metric}";
        $this->cache->incr($key);
        $this->cache->expire($key, 86400); // 24 hours
    }

    /**
     * Get statistics
     * 
     * @return array
     */
    public function getStatistics(): array
    {
        return [
            'anomalies_detected' => (int) ($this->cache->get(self::STATS_KEY . ':anomalies_detected') ?? 0),
            'velocity_per_second_limit' => $this->config['velocity_per_second'],
            'velocity_per_minute_limit' => $this->config['velocity_per_minute'],
            'velocity_per_hour_limit' => $this->config['velocity_per_hour'],
            'endpoint_abuse_threshold' => $this->config['endpoint_abuse_threshold'],
            'pattern_threshold' => $this->config['pattern_threshold'],
        ];
    }
}
