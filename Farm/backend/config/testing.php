<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Test Environment Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration specific to the testing environment.
    | These settings override default app configuration when TESTING=true.
    |
    */

    'test_database' => [
        'driver' => 'mysql',
        'host' => env('TEST_DB_HOST', 'localhost'),
        'port' => env('TEST_DB_PORT', 3306),
        'database' => env('TEST_DB_DATABASE', 'phpfrarm_test'),
        'username' => env('TEST_DB_USERNAME', 'root'),
        'password' => env('TEST_DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci'
    ],

    'test_mongo' => [
        'host' => env('TEST_MONGO_HOST', 'localhost'),
        'port' => env('TEST_MONGO_PORT', 27017),
        'database' => env('TEST_MONGO_DATABASE', 'phpfrarm_test'),
        'username' => env('TEST_MONGO_USERNAME', ''),
        'password' => env('TEST_MONGO_PASSWORD', '')
    ],

    /*
    |--------------------------------------------------------------------------
    | Factory Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for test data factories.
    |
    */

    'factories' => [
        'default_count' => 10,
        'batch_size' => 100,
        'use_transactions' => true
    ],

    /*
    |--------------------------------------------------------------------------
    | Mock Server Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for HTTP mock server.
    |
    */

    'mock_server' => [
        'enabled' => true,
        'port' => 8888,
        'record_requests' => true,
        'default_delay' => 0 // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Contract Testing Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for OpenAPI contract testing.
    |
    */

    'contract_testing' => [
        'enabled' => true,
        'spec_path' => __DIR__ . '/../docs/openapi.json',
        'auto_generate_spec' => true,
        'validate_requests' => true,
        'validate_responses' => true,
        'fail_on_missing_endpoint' => true
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Testing Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for automated security testing.
    |
    */

    'security_testing' => [
        'enabled' => true,
        'tests' => [
            'sql_injection' => true,
            'xss' => true,
            'csrf' => true,
            'authentication' => true,
            'authorization' => true,
            'rate_limiting' => true,
            'input_validation' => true
        ],
        'payload_timeout' => 5000, // milliseconds
        'max_payload_size' => 1024 * 1024 // 1MB
    ],

    /*
    |--------------------------------------------------------------------------
    | Load Testing Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for performance and load testing.
    |
    */

    'load_testing' => [
        'enabled' => true,
        'default_concurrent_users' => 10,
        'default_requests_per_user' => 10,
        'default_ramp_up_time' => 0,
        'request_timeout' => 30000, // milliseconds
        
        'performance_criteria' => [
            'min_success_rate' => 99.0, // percent
            'max_avg_latency' => 200, // milliseconds
            'max_p95_latency' => 500, // milliseconds
            'max_p99_latency' => 1000 // milliseconds
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Test Isolation Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for test isolation and cleanup.
    |
    */

    'isolation' => [
        'use_transactions' => true,
        'clear_redis_before_test' => true,
        'clear_logs_before_test' => false,
        'reset_time_after_test' => true
    ],

    /*
    |--------------------------------------------------------------------------
    | External Service Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for mocking external services.
    |
    */

    'external_services' => [
        'stripe' => [
            'mock' => true,
            'api_key' => 'sk_test_mock'
        ],
        'sendgrid' => [
            'mock' => true,
            'api_key' => 'SG.mock'
        ],
        'twilio' => [
            'mock' => true,
            'account_sid' => 'AC_mock',
            'auth_token' => 'mock_token'
        ],
        'oauth' => [
            'google' => ['mock' => true],
            'facebook' => ['mock' => true],
            'github' => ['mock' => true]
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Test Coverage Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for code coverage reporting.
    |
    */

    'coverage' => [
        'enabled' => true,
        'minimum_threshold' => 80, // percent
        'include_paths' => [
            __DIR__ . '/../app'
        ],
        'exclude_paths' => [
            __DIR__ . '/../app/Console',
            __DIR__ . '/../app/Migrations'
        ],
        'report_formats' => ['html', 'text', 'clover']
    ],

    /*
    |--------------------------------------------------------------------------
    | Parallel Testing Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for running tests in parallel.
    |
    */

    'parallel' => [
        'enabled' => false,
        'processes' => 4,
        'recreate_databases' => true
    ],

    /*
    |--------------------------------------------------------------------------
    | Test Helpers Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for test helper utilities.
    |
    */

    'helpers' => [
        'time_mocking' => true,
        'temp_files_cleanup' => true,
        'auto_seed' => false,
        'default_password' => 'password123'
    ],

    /*
    |--------------------------------------------------------------------------
    | Assertion Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for custom assertions.
    |
    */

    'assertions' => [
        'strict_type_checking' => true,
        'float_precision' => 2,
        'database_query_timeout' => 5000 // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for test debugging.
    |
    */

    'debug' => [
        'dump_failed_responses' => true,
        'log_sql_queries' => false,
        'verbose_errors' => true,
        'save_failed_requests' => true,
        'failed_requests_path' => __DIR__ . '/../tests/failed_requests'
    ]
];
