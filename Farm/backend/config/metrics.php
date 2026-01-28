<?php

/**
 * Metrics Configuration
 * 
 * Configure application metrics collection and export.
 * 
 * @package PHPFrarm
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Metrics
    |--------------------------------------------------------------------------
    |
    | Master switch to enable/disable metrics collection.
    |
    */
    'enabled' => (bool) ($_ENV['METRICS_ENABLED'] ?? true),

    /*
    |--------------------------------------------------------------------------
    | Metrics Namespace
    |--------------------------------------------------------------------------
    |
    | Prefix for all metric names (e.g., phpfrarm_http_requests_total).
    |
    */
    'namespace' => $_ENV['METRICS_NAMESPACE'] ?? 'phpfrarm',

    /*
    |--------------------------------------------------------------------------
    | Metrics Backend
    |--------------------------------------------------------------------------
    |
    | Where to send metrics. Options: prometheus, statsd, datadog
    |
    */
    'backend' => $_ENV['METRICS_BACKEND'] ?? 'prometheus',

    /*
    |--------------------------------------------------------------------------
    | StatsD Configuration
    |--------------------------------------------------------------------------
    */
    'statsd' => [
        'host' => $_ENV['STATSD_HOST'] ?? 'localhost',
        'port' => (int) ($_ENV['STATSD_PORT'] ?? 8125),
        'prefix' => $_ENV['STATSD_PREFIX'] ?? 'phpfrarm.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Prometheus Configuration
    |--------------------------------------------------------------------------
    */
    'prometheus' => [
        // Storage backend: redis, apc, in_memory
        'storage' => $_ENV['PROMETHEUS_STORAGE'] ?? 'redis',
        
        // Redis prefix for metrics
        'redis_prefix' => 'phpfrarm_metrics:',
        
        // Endpoint path for metrics
        'endpoint' => '/metrics',
    ],

    /*
    |--------------------------------------------------------------------------
    | DataDog Configuration
    |--------------------------------------------------------------------------
    */
    'datadog' => [
        'host' => $_ENV['DD_AGENT_HOST'] ?? 'localhost',
        'port' => (int) ($_ENV['DD_TRACE_AGENT_PORT'] ?? 8126),
        'tags' => [
            'env' => $_ENV['APP_ENV'] ?? 'development',
            'service' => $_ENV['SERVICE_NAME'] ?? 'phpfrarm-api',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Histogram Buckets
    |--------------------------------------------------------------------------
    |
    | Default buckets for histogram metrics (in seconds).
    |
    */
    'histogram_buckets' => [
        'http_request_duration' => [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0],
        'database_query_duration' => [0.001, 0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0],
        'external_call_duration' => [0.01, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0, 30.0],
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Collect Metrics
    |--------------------------------------------------------------------------
    |
    | Automatically collect these metrics.
    |
    */
    'auto_collect' => [
        'http_requests' => true,
        'http_request_duration' => true,
        'http_request_size' => true,
        'http_response_size' => true,
        'database_queries' => true,
        'database_duration' => true,
        'cache_operations' => true,
        'memory_usage' => true,
        'errors' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Labels to Include
    |--------------------------------------------------------------------------
    |
    | Default labels to add to all metrics.
    |
    */
    'default_labels' => [
        'service' => $_ENV['SERVICE_NAME'] ?? 'phpfrarm-api',
        'env' => $_ENV['APP_ENV'] ?? 'development',
        'version' => $_ENV['APP_VERSION'] ?? '1.0.0',
    ],

    /*
    |--------------------------------------------------------------------------
    | Path Normalization
    |--------------------------------------------------------------------------
    |
    | Normalize paths to prevent high cardinality.
    | Replaces dynamic segments (IDs, UUIDs) with placeholders.
    |
    */
    'normalize_paths' => true,

    /*
    |--------------------------------------------------------------------------
    | Paths to Ignore
    |--------------------------------------------------------------------------
    |
    | Don't collect metrics for these paths.
    |
    */
    'ignore_paths' => [
        '/health',
        '/health/live',
        '/health/ready',
        '/metrics',
        '/favicon.ico',
    ],
];
