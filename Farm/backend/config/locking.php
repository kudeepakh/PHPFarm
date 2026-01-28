<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Optimistic Locking Configuration
    |--------------------------------------------------------------------------
    |
    | Configure optimistic locking behavior for handling concurrent updates.
    | Prevents lost updates by detecting version conflicts.
    |
    */

    /**
     * Enable/disable optimistic locking globally
     */
    'enabled' => env('OPTIMISTIC_LOCKING_ENABLED', true),

    /**
     * Default retry configuration
     */
    'retry' => [
        'max_attempts' => env('OPTIMISTIC_LOCK_MAX_ATTEMPTS', 3),
        'base_delay_ms' => env('OPTIMISTIC_LOCK_BASE_DELAY_MS', 100),
        'max_delay_ms' => env('OPTIMISTIC_LOCK_MAX_DELAY_MS', 5000),
    ],

    /**
     * Version column configuration
     */
    'version_column' => [
        'name' => env('VERSION_COLUMN_NAME', 'version'),
        'type' => env('VERSION_COLUMN_TYPE', 'integer'),
        'default_value' => 1,
    ],

    /**
     * HTTP header configuration
     */
    'headers' => [
        'if_match' => [
            'enabled' => env('IF_MATCH_ENABLED', true),
            'required_for_updates' => env('IF_MATCH_REQUIRED', false),
        ],
        'etag' => [
            'enabled' => env('ETAG_ENABLED', true),
            'format' => 'W/"{id}-{version}"', // Weak ETag format
        ],
    ],

    /**
     * Conflict resolution strategies
     */
    'conflict_resolution' => [
        'default_strategy' => env('CONFLICT_RESOLUTION_STRATEGY', 'retry'), // retry, fail_fast, merge
        'auto_retry_enabled' => env('AUTO_RETRY_ENABLED', true),
        'notify_on_conflict' => env('NOTIFY_ON_CONFLICT', false),
    ],

    /**
     * Entity-specific configurations
     * 
     * Override default settings for specific entity types.
     */
    'entities' => [
        'Product' => [
            'max_attempts' => 5,
            'base_delay_ms' => 200,
            'require_if_match' => true,
        ],
        'Order' => [
            'max_attempts' => 3,
            'base_delay_ms' => 100,
            'require_if_match' => true,
        ],
        'Inventory' => [
            'max_attempts' => 5,
            'base_delay_ms' => 50,
            'require_if_match' => false,
        ],
    ],

    /**
     * Monitoring and logging
     */
    'monitoring' => [
        'log_conflicts' => env('LOG_LOCK_CONFLICTS', true),
        'log_retries' => env('LOG_LOCK_RETRIES', true),
        'log_level' => env('LOCK_LOG_LEVEL', 'warning'),
        'track_statistics' => env('TRACK_LOCK_STATISTICS', true),
    ],

    /**
     * Conflict alerts
     */
    'alerts' => [
        'enabled' => env('LOCK_ALERTS_ENABLED', false),
        'high_conflict_threshold' => env('HIGH_CONFLICT_THRESHOLD', 100), // conflicts per hour
        'notification_channels' => ['email', 'slack'],
    ],

    /**
     * Performance optimization
     */
    'performance' => [
        'cache_version_checks' => env('CACHE_VERSION_CHECKS', false),
        'batch_conflict_detection' => env('BATCH_CONFLICT_DETECTION', false),
    ],

    /**
     * Development/testing settings
     */
    'development' => [
        'disable_in_tests' => env('DISABLE_LOCKING_IN_TESTS', true),
        'simulate_conflicts' => env('SIMULATE_LOCK_CONFLICTS', false),
        'verbose_logging' => env('LOCK_VERBOSE_LOGGING', false),
    ],

    /**
     * HTTP methods that require version checking
     */
    'http_methods' => [
        'PUT' => ['versioning_required' => true, 'auto_retry' => true],
        'PATCH' => ['versioning_required' => true, 'auto_retry' => true],
        'DELETE' => ['versioning_required' => true, 'auto_retry' => false],
        'POST' => ['versioning_required' => false, 'auto_retry' => false],
    ],

    /**
     * Stored procedure support
     */
    'stored_procedures' => [
        'use_version_check' => env('SP_VERSION_CHECK', true),
        'return_current_version' => env('SP_RETURN_VERSION', true),
        'naming_convention' => 'sp_{table}_update_versioned',
    ],

    /**
     * Common use case presets
     */
    'presets' => [
        'high_contention' => [
            'max_attempts' => 10,
            'base_delay_ms' => 50,
            'require_if_match' => true,
        ],
        'low_contention' => [
            'max_attempts' => 2,
            'base_delay_ms' => 200,
            'require_if_match' => false,
        ],
        'critical_data' => [
            'max_attempts' => 5,
            'base_delay_ms' => 100,
            'require_if_match' => true,
        ],
    ],

    /**
     * Conflict statistics retention
     */
    'statistics' => [
        'retention_days' => env('LOCK_STATS_RETENTION_DAYS', 30),
        'aggregate_interval' => env('LOCK_STATS_INTERVAL', 'hourly'), // hourly, daily
        'top_conflicts_limit' => env('TOP_CONFLICTS_LIMIT', 20),
    ],
];
