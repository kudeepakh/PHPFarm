<?php

namespace PHPFrarm\Core\Observability;

use PHPFrarm\Core\TraceContext;

/**
 * Metrics Collector
 * 
 * Collects and exports application metrics to Prometheus, StatsD, or other backends.
 * 
 * Features:
 * - Counter metrics (incrementing values)
 * - Gauge metrics (current values)
 * - Histogram metrics (latency distributions)
 * - Summary metrics (quantiles)
 * - Labels/tags support
 * - Prometheus exposition format
 * - StatsD integration
 * 
 * @package PHPFrarm\Core\Observability
 */
class MetricsCollector
{
    private static ?self $instance = null;
    private array $config;
    private array $counters = [];
    private array $gauges = [];
    private array $histograms = [];
    private string $namespace;
    private bool $enabled;
    
    /**
     * Histogram buckets for latency
     */
    private const DEFAULT_BUCKETS = [
        0.005, 0.01, 0.025, 0.05, 0.075, 0.1, 0.25, 0.5, 0.75, 1.0, 2.5, 5.0, 7.5, 10.0
    ];
    
    private function __construct()
    {
        $this->config = $this->loadConfig();
        $this->namespace = $this->config['namespace'] ?? 'phpfrarm';
        $this->enabled = $this->config['enabled'] ?? true;
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Load metrics configuration
     */
    private function loadConfig(): array
    {
        $configPath = dirname(__DIR__, 2) . '/config/metrics.php';
        return file_exists($configPath) ? require $configPath : [
            'enabled' => true,
            'namespace' => 'phpfrarm',
            'backend' => 'prometheus', // prometheus, statsd, datadog
            'statsd' => [
                'host' => $_ENV['STATSD_HOST'] ?? 'localhost',
                'port' => (int) ($_ENV['STATSD_PORT'] ?? 8125),
            ],
            'prometheus' => [
                'storage' => 'redis', // redis, apc, in_memory
                'redis_prefix' => 'phpfrarm_metrics:',
            ],
        ];
    }
    
    // =====================================================
    // COUNTER METRICS
    // =====================================================
    
    /**
     * Increment a counter
     * 
     * @param string $name Metric name
     * @param float $value Increment value (default: 1)
     * @param array $labels Labels/tags
     */
    public function incrementCounter(string $name, float $value = 1, array $labels = []): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $key = $this->buildKey($name, $labels);
        
        if (!isset($this->counters[$key])) {
            $this->counters[$key] = [
                'name' => $name,
                'value' => 0,
                'labels' => $labels,
            ];
        }
        
        $this->counters[$key]['value'] += $value;
        
        // Send to backend if StatsD
        if ($this->config['backend'] === 'statsd') {
            $this->sendToStatsd("{$this->namespace}.{$name}", $value, 'c', $labels);
        }
    }
    
    /**
     * Common counter shortcuts
     */
    public function countRequest(string $method, string $path, int $status): void
    {
        $this->incrementCounter('http_requests_total', 1, [
            'method' => $method,
            'path' => $this->normalizePath($path),
            'status' => (string) $status,
        ]);
    }
    
    public function countError(string $type, string $code): void
    {
        $this->incrementCounter('errors_total', 1, [
            'type' => $type,
            'code' => $code,
        ]);
    }
    
    public function countDatabaseQuery(string $operation, string $table): void
    {
        $this->incrementCounter('database_queries_total', 1, [
            'operation' => $operation,
            'table' => $table,
        ]);
    }
    
    public function countCacheHit(bool $hit): void
    {
        $this->incrementCounter($hit ? 'cache_hits_total' : 'cache_misses_total');
    }
    
    // =====================================================
    // GAUGE METRICS
    // =====================================================
    
    /**
     * Set a gauge value
     * 
     * @param string $name Metric name
     * @param float $value Current value
     * @param array $labels Labels/tags
     */
    public function setGauge(string $name, float $value, array $labels = []): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $key = $this->buildKey($name, $labels);
        
        $this->gauges[$key] = [
            'name' => $name,
            'value' => $value,
            'labels' => $labels,
        ];
        
        // Send to backend if StatsD
        if ($this->config['backend'] === 'statsd') {
            $this->sendToStatsd("{$this->namespace}.{$name}", $value, 'g', $labels);
        }
    }
    
    /**
     * Increment a gauge
     */
    public function incrementGauge(string $name, float $value = 1, array $labels = []): void
    {
        $key = $this->buildKey($name, $labels);
        $current = $this->gauges[$key]['value'] ?? 0;
        $this->setGauge($name, $current + $value, $labels);
    }
    
    /**
     * Decrement a gauge
     */
    public function decrementGauge(string $name, float $value = 1, array $labels = []): void
    {
        $this->incrementGauge($name, -$value, $labels);
    }
    
    /**
     * Common gauge shortcuts
     */
    public function setActiveConnections(int $count): void
    {
        $this->setGauge('active_connections', $count);
    }
    
    public function setQueueSize(string $queue, int $size): void
    {
        $this->setGauge('queue_size', $size, ['queue' => $queue]);
    }
    
    public function setMemoryUsage(): void
    {
        $this->setGauge('memory_usage_bytes', memory_get_usage(true));
        $this->setGauge('memory_peak_bytes', memory_get_peak_usage(true));
    }
    
    // =====================================================
    // HISTOGRAM METRICS
    // =====================================================
    
    /**
     * Observe a histogram value
     * 
     * @param string $name Metric name
     * @param float $value Observed value
     * @param array $labels Labels/tags
     * @param array $buckets Custom buckets (optional)
     */
    public function observeHistogram(string $name, float $value, array $labels = [], array $buckets = []): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $buckets = $buckets ?: self::DEFAULT_BUCKETS;
        $key = $this->buildKey($name, $labels);
        
        if (!isset($this->histograms[$key])) {
            $this->histograms[$key] = [
                'name' => $name,
                'labels' => $labels,
                'buckets' => array_fill_keys($buckets, 0),
                'sum' => 0,
                'count' => 0,
            ];
        }
        
        // Increment bucket counts
        foreach ($buckets as $bucket) {
            if ($value <= $bucket) {
                $this->histograms[$key]['buckets'][$bucket]++;
            }
        }
        
        $this->histograms[$key]['sum'] += $value;
        $this->histograms[$key]['count']++;
        
        // Send to backend if StatsD (as timing)
        if ($this->config['backend'] === 'statsd') {
            $this->sendToStatsd("{$this->namespace}.{$name}", $value * 1000, 'ms', $labels);
        }
    }
    
    /**
     * Common histogram shortcuts
     */
    public function observeRequestDuration(float $seconds, string $method, string $path, int $status): void
    {
        $this->observeHistogram('http_request_duration_seconds', $seconds, [
            'method' => $method,
            'path' => $this->normalizePath($path),
            'status' => (string) $status,
        ]);
    }
    
    public function observeDatabaseDuration(float $seconds, string $operation): void
    {
        $this->observeHistogram('database_query_duration_seconds', $seconds, [
            'operation' => $operation,
        ]);
    }
    
    public function observeExternalCallDuration(float $seconds, string $service): void
    {
        $this->observeHistogram('external_call_duration_seconds', $seconds, [
            'service' => $service,
        ]);
    }
    
    // =====================================================
    // TIMING HELPER
    // =====================================================
    
    /**
     * Time a callable and record as histogram
     * 
     * @param string $name Metric name
     * @param callable $callback Code to time
     * @param array $labels Labels/tags
     * @return mixed Result of callback
     */
    public function time(string $name, callable $callback, array $labels = []): mixed
    {
        $start = microtime(true);
        
        try {
            return $callback();
        } finally {
            $duration = microtime(true) - $start;
            $this->observeHistogram($name, $duration, $labels);
        }
    }
    
    /**
     * Start a timer
     */
    public function startTimer(): float
    {
        return microtime(true);
    }
    
    /**
     * Record timer duration
     */
    public function recordTimer(string $name, float $startTime, array $labels = []): void
    {
        $duration = microtime(true) - $startTime;
        $this->observeHistogram($name, $duration, $labels);
    }
    
    // =====================================================
    // LATENCY PERCENTILES
    // =====================================================
    
    /**
     * Calculate latency percentiles
     */
    public function calculatePercentiles(string $name, array $labels = []): array
    {
        $key = $this->buildKey($name, $labels);
        
        if (!isset($this->histograms[$key])) {
            return [];
        }
        
        $histogram = $this->histograms[$key];
        $total = $histogram['count'];
        
        if ($total === 0) {
            return ['p50' => 0, 'p75' => 0, 'p90' => 0, 'p95' => 0, 'p99' => 0];
        }
        
        $percentiles = ['p50' => 0.5, 'p75' => 0.75, 'p90' => 0.9, 'p95' => 0.95, 'p99' => 0.99];
        $result = [];
        
        foreach ($percentiles as $name => $target) {
            $targetCount = $total * $target;
            $cumulativeCount = 0;
            
            foreach ($histogram['buckets'] as $bucket => $count) {
                $cumulativeCount += $count;
                if ($cumulativeCount >= $targetCount) {
                    $result[$name] = $bucket;
                    break;
                }
            }
        }
        
        $result['avg'] = $total > 0 ? $histogram['sum'] / $total : 0;
        
        return $result;
    }
    
    // =====================================================
    // EXPORT / OUTPUT
    // =====================================================
    
    /**
     * Export metrics in Prometheus format
     */
    public function exportPrometheus(): string
    {
        $output = [];
        
        // Counters
        foreach ($this->counters as $counter) {
            $name = "{$this->namespace}_{$counter['name']}";
            $labels = $this->formatLabels($counter['labels']);
            $output[] = "# TYPE {$name} counter";
            $output[] = "{$name}{$labels} {$counter['value']}";
        }
        
        // Gauges
        foreach ($this->gauges as $gauge) {
            $name = "{$this->namespace}_{$gauge['name']}";
            $labels = $this->formatLabels($gauge['labels']);
            $output[] = "# TYPE {$name} gauge";
            $output[] = "{$name}{$labels} {$gauge['value']}";
        }
        
        // Histograms
        foreach ($this->histograms as $histogram) {
            $name = "{$this->namespace}_{$histogram['name']}";
            $baseLabels = $histogram['labels'];
            
            $output[] = "# TYPE {$name} histogram";
            
            $cumulativeCount = 0;
            foreach ($histogram['buckets'] as $bucket => $count) {
                $cumulativeCount += $count;
                $labels = $this->formatLabels(array_merge($baseLabels, ['le' => (string) $bucket]));
                $output[] = "{$name}_bucket{$labels} {$cumulativeCount}";
            }
            
            // +Inf bucket
            $labels = $this->formatLabels(array_merge($baseLabels, ['le' => '+Inf']));
            $output[] = "{$name}_bucket{$labels} {$histogram['count']}";
            
            // Sum and count
            $labels = $this->formatLabels($baseLabels);
            $output[] = "{$name}_sum{$labels} {$histogram['sum']}";
            $output[] = "{$name}_count{$labels} {$histogram['count']}";
        }
        
        return implode("\n", $output);
    }
    
    /**
     * Get all metrics as array
     */
    public function getAllMetrics(): array
    {
        return [
            'counters' => $this->counters,
            'gauges' => $this->gauges,
            'histograms' => array_map(function ($h) {
                return [
                    'name' => $h['name'],
                    'labels' => $h['labels'],
                    'count' => $h['count'],
                    'sum' => $h['sum'],
                    'avg' => $h['count'] > 0 ? $h['sum'] / $h['count'] : 0,
                ];
            }, $this->histograms),
        ];
    }
    
    /**
     * Get request statistics
     */
    public function getRequestStats(): array
    {
        $totalRequests = 0;
        $errorRequests = 0;
        $statusCodes = [];
        
        foreach ($this->counters as $counter) {
            if ($counter['name'] === 'http_requests_total') {
                $totalRequests += $counter['value'];
                $status = $counter['labels']['status'] ?? '200';
                $statusCodes[$status] = ($statusCodes[$status] ?? 0) + $counter['value'];
                
                if ((int) $status >= 400) {
                    $errorRequests += $counter['value'];
                }
            }
        }
        
        return [
            'total_requests' => $totalRequests,
            'error_requests' => $errorRequests,
            'error_rate' => $totalRequests > 0 ? $errorRequests / $totalRequests : 0,
            'status_codes' => $statusCodes,
            'latency_percentiles' => $this->calculatePercentiles('http_request_duration_seconds'),
        ];
    }
    
    // =====================================================
    // STATSD INTEGRATION
    // =====================================================
    
    /**
     * Send metric to StatsD
     */
    private function sendToStatsd(string $name, float $value, string $type, array $labels = []): void
    {
        $host = $this->config['statsd']['host'] ?? 'localhost';
        $port = $this->config['statsd']['port'] ?? 8125;
        
        // Format: metric.name:value|type|#tags
        $metric = "{$name}:{$value}|{$type}";
        
        if (!empty($labels)) {
            $tags = array_map(fn($k, $v) => "{$k}:{$v}", array_keys($labels), array_values($labels));
            $metric .= "|#" . implode(',', $tags);
        }
        
        try {
            $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            socket_sendto($socket, $metric, strlen($metric), 0, $host, $port);
            socket_close($socket);
        } catch (\Exception $e) {
            // Fail silently - metrics shouldn't break the app
        }
    }
    
    // =====================================================
    // HELPERS
    // =====================================================
    
    /**
     * Build unique key for metric
     */
    private function buildKey(string $name, array $labels): string
    {
        ksort($labels);
        return $name . ':' . json_encode($labels);
    }
    
    /**
     * Format labels for Prometheus
     */
    private function formatLabels(array $labels): string
    {
        if (empty($labels)) {
            return '';
        }
        
        $parts = [];
        foreach ($labels as $key => $value) {
            $escapedValue = str_replace(['\\', '"', "\n"], ['\\\\', '\\"', '\\n'], $value);
            $parts[] = "{$key}=\"{$escapedValue}\"";
        }
        
        return '{' . implode(',', $parts) . '}';
    }
    
    /**
     * Normalize path for metrics (remove IDs)
     */
    private function normalizePath(string $path): string
    {
        // Replace UUIDs
        $path = preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', ':id', $path);
        
        // Replace ULIDs
        $path = preg_replace('/[0-9A-Z]{26}/i', ':id', $path);
        
        // Replace numeric IDs
        $path = preg_replace('/\/\d+/', '/:id', $path);
        
        return $path;
    }
    
    /**
     * Reset all metrics
     */
    public function reset(): void
    {
        $this->counters = [];
        $this->gauges = [];
        $this->histograms = [];
    }
}
