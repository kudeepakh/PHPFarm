<?php

/**
 * Cache Configuration
 * 
 * Controls caching behavior across the application.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Cache Enabled
    |--------------------------------------------------------------------------
    |
    | Master switch for caching. When false, all caching is disabled.
    | Useful for debugging or development environments.
    |
    */
    'enabled' => env('CACHE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default Cache Driver
    |--------------------------------------------------------------------------
    |
    | Supported drivers: 'redis', 'file', 'array', 'null'
    |
    */
    'driver' => env('CACHE_DRIVER', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix for all cache keys to avoid collisions with other applications.
    |
    */
    'prefix' => env('CACHE_PREFIX', 'phpfrarm'),

    /*
    |--------------------------------------------------------------------------
    | Default TTL (Time To Live)
    |--------------------------------------------------------------------------
    |
    | Default cache duration in seconds.
    |
    */
    'default_ttl' => (int) env('CACHE_DEFAULT_TTL', 3600), // 1 hour

    /*
    |--------------------------------------------------------------------------
    | Response Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for HTTP response caching middleware.
    |
    */
    'response' => [
        'enabled' => env('RESPONSE_CACHE_ENABLED', true),
        'ttl' => (int) env('RESPONSE_CACHE_TTL', 300), // 5 minutes
        'methods' => explode(',', env('RESPONSE_CACHE_METHODS', 'GET,HEAD')),
        'status_codes' => [200, 203, 204, 206, 300, 301],
        'vary_by_query' => true,
        'vary_by_headers' => [],
        'exclude_paths' => [
            '/api/v1/auth/*',
            '/api/v1/admin/cache/*',
        ],
        'compress' => env('RESPONSE_CACHE_COMPRESS', false),
        'etag' => true,
        'last_modified' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for database query result caching.
    |
    */
    'query' => [
        'enabled' => env('QUERY_CACHE_ENABLED', true),
        'ttl' => (int) env('QUERY_CACHE_TTL', 600), // 10 minutes
        'tables' => [
            // Table-specific TTLs
            'users' => 1800,          // 30 minutes
            'roles' => 3600,          // 1 hour
            'permissions' => 3600,    // 1 hour
            'config' => 7200,         // 2 hours
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Store Configurations
    |--------------------------------------------------------------------------
    |
    | Configuration for different cache drivers.
    |
    */
    'stores' => [
        'redis' => [
            'host' => env('REDIS_HOST', 'redis'),
            'port' => (int) env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', null),
            'database' => (int) env('REDIS_CACHE_DB', 0),
            'timeout' => 2.0,
            'read_timeout' => 2.0,
        ],
        
        'file' => [
            'path' => __DIR__ . '/../storage/cache',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Warming Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for cache preloading.
    |
    */
    'warming' => [
        'enabled' => env('CACHE_WARMING_ENABLED', true),
        'schedule' => '*/30 * * * *', // Every 30 minutes
        'endpoints' => [
            // Endpoints to warm on deployment
            '/api/v1/config',
            '/api/v1/roles',
            '/api/v1/permissions',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Statistics
    |--------------------------------------------------------------------------
    |
    | Enable cache hit/miss tracking and metrics.
    |
    */
    'statistics' => [
        'enabled' => env('CACHE_STATS_ENABLED', true),
        'sample_rate' => 1.0, // 100% - track all cache operations
        'store_in' => 'redis', // Where to store stats
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Stampede Prevention
    |--------------------------------------------------------------------------
    |
    | Prevent multiple processes from regenerating the same cache simultaneously.
    |
    */
    'stampede_prevention' => [
        'enabled' => true,
        'lock_timeout' => 10, // seconds
        'probabilistic_early_expiration' => true,
        'beta' => 1.0, // Early expiration factor
    ],

    /*
    |--------------------------------------------------------------------------
    | Tag Management
    |--------------------------------------------------------------------------
    |
    | Configuration for cache tagging system.
    |
    */
    'tags' => [
        'enabled' => true,
        'separator' => ':', // Tag separator in Redis keys
        'max_tags_per_key' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Connection
    |--------------------------------------------------------------------------
    |
    | Redis connection settings (when driver is 'redis').
    |
    */
    'redis' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD', null),
        'database' => env('REDIS_CACHE_DB', 1),
        'timeout' => 2.0,
        'persistent' => true,
        'prefix' => env('CACHE_PREFIX', 'phpfrarm'),
    ],

    /*
    |--------------------------------------------------------------------------
    | File Cache Settings
    |--------------------------------------------------------------------------
    |
    | File cache directory (when driver is 'file').
    |
    */
    'file' => [
        'path' => BASE_PATH . '/storage/cache',
        'permissions' => 0755,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Cleanup
    |--------------------------------------------------------------------------
    |
    | Automatic cleanup of expired cache entries.
    |
    */
    'cleanup' => [
        'enabled' => true,
        'schedule' => '0 */6 * * *', // Every 6 hours
        'max_age' => 86400 * 7, // 7 days - delete entries older than this
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Invalidation Rules
    |--------------------------------------------------------------------------
    |
    | Define automatic cache invalidation rules for specific events.
    |
    */
    'invalidation_rules' => [
        'user.updated' => ['users', 'user:{id}', 'user:{id}:*'],
        'user.deleted' => ['users', 'user:{id}:*'],
        'role.updated' => ['roles', 'role:{id}', 'users'], // Cascade to users
        'permission.updated' => ['permissions', 'roles', 'users'], // Cascade
        'post.created' => ['posts', 'user:{user_id}:posts'],
        'post.updated' => ['posts', 'post:{id}'],
        'post.deleted' => ['posts', 'post:{id}:*', 'user:{user_id}:posts'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Development & Debugging
    |--------------------------------------------------------------------------
    |
    | Cache behavior in development environments.
    |
    */
    'development' => [
        'disable_in_debug' => env('CACHE_DISABLE_IN_DEBUG', false),
        'log_hits' => env('CACHE_LOG_HITS', false),
        'log_misses' => env('CACHE_LOG_MISSES', false),
        'show_headers' => env('CACHE_SHOW_HEADERS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Predefined Tag Groups
    |--------------------------------------------------------------------------
    |
    | Common tag groups for easy bulk invalidation.
    |
    */
    'tag_groups' => [
        'auth' => ['users', 'roles', 'permissions', 'sessions'],
        'api' => ['api:v1', 'api:v2'],
        'public' => ['public:posts', 'public:pages'],
        'admin' => ['admin:*'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Memory Limits
    |--------------------------------------------------------------------------
    |
    | Maximum cache size limits.
    |
    */
    'limits' => [
        'max_item_size' => 1048576, // 1MB per cache item
        'max_memory' => 536870912, // 512MB total cache memory
        'eviction_policy' => 'lru', // Least Recently Used
    ],
];
