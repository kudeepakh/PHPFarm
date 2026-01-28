<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Retry Policy Configuration
    |--------------------------------------------------------------------------
    |
    | Configure retry behavior for handling transient failures in
    | external API calls, database operations, and other operations.
    |
    */

    /**
     * Enable/disable retry globally
     */
    'enabled' => env('RETRY_ENABLED', true),

    /**
     * Default retry configuration
     */
    'defaults' => [
        'max_attempts' => env('RETRY_MAX_ATTEMPTS', 3),
        'strategy' => env('RETRY_STRATEGY', 'exponential_jitter'),
        'base_delay_ms' => env('RETRY_BASE_DELAY_MS', 1000),
        'max_delay_ms' => env('RETRY_MAX_DELAY_MS', 30000),
    ],

    /**
     * Backoff strategies configuration
     */
    'strategies' => [
        'fixed' => [
            'delay_ms' => 1000,
        ],
        'linear' => [
            'increment_ms' => 1000,
            'max_delay_ms' => 30000,
        ],
        'exponential' => [
            'base_delay_ms' => 1000,
            'max_delay_ms' => 30000,
            'use_jitter' => false,
        ],
        'exponential_jitter' => [
            'base_delay_ms' => 1000,
            'max_delay_ms' => 30000,
            'use_jitter' => true,
        ],
        'fibonacci' => [
            'multiplier_ms' => 1000,
            'max_delay_ms' => 30000,
        ],
    ],

    /**
     * Operation-specific retry policies
     * 
     * Define custom retry behavior for specific operations.
     */
    'policies' => [
        'external_api' => [
            'max_attempts' => 5,
            'strategy' => 'exponential_jitter',
            'base_delay_ms' => 1000,
            'timeout_ms' => 10000,
            'circuit_breaker' => 'external-api',
        ],
        'payment_processing' => [
            'max_attempts' => 3,
            'strategy' => 'fixed',
            'base_delay_ms' => 2000,
            'timeout_ms' => 30000,
            'circuit_breaker' => 'payment-gateway',
            'idempotency_required' => true,
        ],
        'database_query' => [
            'max_attempts' => 3,
            'strategy' => 'exponential',
            'base_delay_ms' => 500,
            'timeout_ms' => 5000,
        ],
        'email_sending' => [
            'max_attempts' => 4,
            'strategy' => 'fibonacci',
            'base_delay_ms' => 1000,
            'timeout_ms' => 15000,
        ],
    ],

    /**
     * Exception handling
     * 
     * Define which exceptions should trigger retries.
     */
    'retry_on_exceptions' => [
        // Network errors
        'GuzzleHttp\Exception\ConnectException',
        'GuzzleHttp\Exception\RequestException',
        
        // Database errors
        'PDOException',
        'Illuminate\Database\QueryException',
        
        // Timeout errors
        'App\Core\Exceptions\TimeoutException',
        
        // Custom exceptions
        'App\Core\Exceptions\TransientException',
    ],

    /**
     * Do NOT retry on these exceptions
     * (overrides retry_on_exceptions)
     */
    'do_not_retry_on' => [
        // Client errors (4xx)
        'App\Core\Exceptions\ValidationException',
        'App\Core\Exceptions\UnauthorizedException',
        'App\Core\Exceptions\ForbiddenException',
        'App\Core\Exceptions\NotFoundException',
        
        // Business logic errors
        'App\Core\Exceptions\BusinessRuleViolationException',
        'App\Core\Exceptions\InsufficientFundsException',
    ],

    /**
     * Idempotency configuration
     */
    'idempotency' => [
        'enabled' => env('IDEMPOTENCY_ENABLED', true),
        'ttl' => env('IDEMPOTENCY_TTL', 86400), // 24 hours
        'header_name' => env('IDEMPOTENCY_HEADER', 'Idempotency-Key'),
        'auto_generate' => env('IDEMPOTENCY_AUTO_GENERATE', false),
    ],

    /**
     * Circuit breaker integration
     */
    'circuit_breaker' => [
        'enabled' => env('CIRCUIT_BREAKER_ENABLED', true),
        'failure_threshold' => env('CIRCUIT_BREAKER_FAILURE_THRESHOLD', 5),
        'success_threshold' => env('CIRCUIT_BREAKER_SUCCESS_THRESHOLD', 2),
        'timeout' => env('CIRCUIT_BREAKER_TIMEOUT', 60), // seconds
    ],

    /**
     * Timeout configuration
     */
    'timeout' => [
        'enabled' => env('TIMEOUT_ENABLED', true),
        'default_timeout_ms' => env('DEFAULT_TIMEOUT_MS', 30000),
    ],

    /**
     * Monitoring and logging
     */
    'monitoring' => [
        'log_retries' => env('LOG_RETRIES', true),
        'log_exhausted' => env('LOG_EXHAUSTED', true),
        'log_level' => env('RETRY_LOG_LEVEL', 'warning'),
        'track_statistics' => env('TRACK_RETRY_STATISTICS', true),
    ],

    /**
     * HTTP method retry rules
     */
    'http_methods' => [
        'GET' => ['retryable' => true, 'idempotent' => true],
        'POST' => ['retryable' => true, 'idempotent' => false],
        'PUT' => ['retryable' => true, 'idempotent' => true],
        'PATCH' => ['retryable' => true, 'idempotent' => false],
        'DELETE' => ['retryable' => true, 'idempotent' => true],
        'HEAD' => ['retryable' => true, 'idempotent' => true],
        'OPTIONS' => ['retryable' => true, 'idempotent' => true],
    ],

    /**
     * HTTP status codes retry rules
     */
    'http_status_codes' => [
        // Retry on these status codes
        'retry_on' => [
            408, // Request Timeout
            429, // Too Many Requests
            500, // Internal Server Error
            502, // Bad Gateway
            503, // Service Unavailable
            504, // Gateway Timeout
        ],
        // Do NOT retry on these status codes
        'do_not_retry_on' => [
            400, // Bad Request
            401, // Unauthorized
            403, // Forbidden
            404, // Not Found
            405, // Method Not Allowed
            409, // Conflict
            410, // Gone
            422, // Unprocessable Entity
        ],
    ],

    /**
     * Rate limiting integration
     */
    'rate_limiting' => [
        'respect_retry_after' => env('RESPECT_RETRY_AFTER', true),
        'max_retry_after' => env('MAX_RETRY_AFTER', 300), // seconds
    ],

    /**
     * Development/testing settings
     */
    'development' => [
        'disable_in_tests' => env('DISABLE_RETRY_IN_TESTS', true),
        'simulate_failures' => env('SIMULATE_RETRY_FAILURES', false),
        'verbose_logging' => env('RETRY_VERBOSE_LOGGING', false),
    ],

    /**
     * Performance limits
     */
    'limits' => [
        'max_attempts_global' => 10,
        'max_delay_ms' => 60000, // 1 minute
        'max_total_retry_time_ms' => 300000, // 5 minutes
    ],

    /**
     * Common use case presets
     */
    'presets' => [
        'aggressive' => [
            'max_attempts' => 5,
            'strategy' => 'exponential_jitter',
            'base_delay_ms' => 500,
        ],
        'conservative' => [
            'max_attempts' => 2,
            'strategy' => 'fixed',
            'base_delay_ms' => 2000,
        ],
        'balanced' => [
            'max_attempts' => 3,
            'strategy' => 'exponential',
            'base_delay_ms' => 1000,
        ],
    ],
];
