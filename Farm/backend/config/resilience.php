<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Retry Policy Configuration
    |--------------------------------------------------------------------------
    |
    | Configure retry behavior for transient failures.
    |
    */
    'retry' => [
        'max_attempts' => 3,
        'initial_delay_ms' => 100,
        'max_delay_ms' => 5000,
        'backoff_multiplier' => 2,
        'jitter' => true,
        
        // Default retryable exceptions
        'retryable_exceptions' => [
            'ConnectionException',
            'TimeoutException',
            'ServiceUnavailableException'
        ],
        
        // Default idempotency configuration
        'idempotency' => [
            'enabled' => true,
            'ttl' => 86400, // 24 hours
            'header_name' => 'Idempotency-Key'
        ]
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Configuration
    |--------------------------------------------------------------------------
    |
    | Configure circuit breaker thresholds and timeouts.
    |
    */
    'circuit_breaker' => [
        'failure_threshold' => 5,
        'success_threshold' => 2,
        'timeout_seconds' => 60,
        'half_open_timeout_seconds' => 30
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Graceful Degradation Configuration
    |--------------------------------------------------------------------------
    |
    | Configure fallback behavior for service degradation.
    |
    */
    'degradation' => [
        'enabled' => true,
        
        // Services that support degradation
        'services' => [
            'payment-service' => [
                'fallback_strategy' => 'cache',
                'cache_ttl' => 300
            ],
            'notification-service' => [
                'fallback_strategy' => 'queue',
                'queue_name' => 'notifications_delayed'
            ],
            'search-service' => [
                'fallback_strategy' => 'simple',
                'simple_search_enabled' => true
            ],
            'recommendation-service' => [
                'fallback_strategy' => 'static',
                'static_results' => 'popular_items'
            ]
        ],
        
        // Auto-degradation thresholds
        'auto_degrade' => [
            'enabled' => false,
            'error_rate_threshold' => 50, // percentage
            'latency_threshold_ms' => 5000,
            'window_seconds' => 60
        ]
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Backpressure Configuration
    |--------------------------------------------------------------------------
    |
    | Configure concurrency limits and overload protection.
    |
    */
    'backpressure' => [
        'enabled' => true,
        
        // Concurrency limits per resource
        'limits' => [
            'global' => 1000,           // Total concurrent requests
            'api' => 500,               // API requests
            'database' => 100,          // Database operations
            'external' => 50,           // External service calls
            'heavy_computation' => 20,  // CPU-intensive operations
            'file_upload' => 30,        // File upload operations
            'report_generation' => 10   // Report generation
        ],
        
        // Overload detection
        'overload_threshold' => 90, // percentage
        
        // Backpressure strategy
        'strategy' => 'reject', // reject, queue, or throttle
        
        // Queue configuration (if strategy is 'queue')
        'queue' => [
            'max_size' => 1000,
            'timeout_seconds' => 30
        ],
        
        // Throttle configuration (if strategy is 'throttle')
        'throttle' => [
            'delay_ms' => 100,
            'max_delay_ms' => 5000
        ],
        
        // Retry-After header (seconds)
        'retry_after' => 5
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Timeout Configuration
    |--------------------------------------------------------------------------
    |
    | Configure timeouts for different operation types.
    |
    */
    'timeouts' => [
        'database_query' => 5,      // seconds
        'http_request' => 10,       // seconds
        'external_api' => 15,       // seconds
        'report_generation' => 60,  // seconds
        'file_upload' => 300,       // seconds (5 minutes)
        'batch_operation' => 600    // seconds (10 minutes)
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Health Check Configuration
    |--------------------------------------------------------------------------
    |
    | Configure health check behavior.
    |
    */
    'health_check' => [
        'enabled' => true,
        'interval_seconds' => 30,
        'timeout_seconds' => 5,
        
        'dependencies' => [
            'database' => [
                'critical' => true,
                'check' => 'database_connection'
            ],
            'redis' => [
                'critical' => false,
                'check' => 'redis_connection'
            ],
            'mongodb' => [
                'critical' => false,
                'check' => 'mongodb_connection'
            ]
        ]
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Configure monitoring and alerting.
    |
    */
    'monitoring' => [
        'enabled' => true,
        
        'metrics' => [
            'retry_attempts' => true,
            'circuit_breaker_trips' => true,
            'degradation_activations' => true,
            'backpressure_rejections' => true,
            'timeout_occurrences' => true
        ],
        
        'alerts' => [
            'high_error_rate' => [
                'enabled' => true,
                'threshold' => 10, // percentage
                'window_seconds' => 300
            ],
            'circuit_breaker_open' => [
                'enabled' => true
            ],
            'backpressure_overload' => [
                'enabled' => true,
                'threshold' => 95 // percentage
            ]
        ]
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configure state storage for resilience components.
    |
    */
    'storage' => [
        'driver' => 'file', // file, redis, or database
        'path' => __DIR__ . '/../storage/resilience',
        
        // Redis configuration (if driver is 'redis')
        'redis' => [
            'prefix' => 'resilience:',
            'ttl' => 86400
        ]
    ]
];
