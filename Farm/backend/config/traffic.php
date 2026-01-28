<?php

return [
    
    /*
    |--------------------------------------------------------------------------
    | Traffic Management Configuration
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting, throttling, and quota management for the API.
    | These settings can be overridden per route using #[RateLimit] attribute.
    |
    */
    
    // Enable/disable traffic management globally
    'enabled' => env('TRAFFIC_ENABLED', true),
    
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    */
    'rate_limit' => [
        // Rate limiting algorithm
        // Options: 'token_bucket', 'sliding_window', 'fixed_window'
        'algorithm' => env('RATE_LIMIT_ALGORITHM', 'token_bucket'),
        
        // Default rate limit (requests per window)
        'default_limit' => env('RATE_LIMIT_DEFAULT', 60),
        
        // Default time window (seconds)
        'default_window' => env('RATE_LIMIT_WINDOW', 60),
        
        // Burst capacity multiplier (token bucket only)
        // Burst = limit * burst_multiplier
        'burst_multiplier' => 1.5,
        
        // Fail open (allow requests if Redis is down)
        'fail_open' => true,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Throttling Configuration
    |--------------------------------------------------------------------------
    |
    | Throttling applies progressive delays to excessive requests instead of
    | blocking them completely. Useful for graceful degradation.
    |
    */
    'throttle' => [
        // Threshold before throttling starts (requests per window)
        'threshold' => env('THROTTLE_THRESHOLD', 100),
        
        // Time window for counting requests (seconds)
        'window' => env('THROTTLE_WINDOW', 60),
        
        // Base delay applied to first excess request (seconds)
        'base_delay' => 0.1,
        
        // Maximum delay applied to requests (seconds)
        'max_delay' => 5.0,
        
        // Exponential backoff enabled
        'exponential_backoff' => true,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Quota Management Configuration
    |--------------------------------------------------------------------------
    |
    | Manage client-level quotas (daily/monthly API usage limits).
    |
    */
    'quota' => [
        // Allow requests over quota (with tracking)
        'allow_overage' => env('QUOTA_ALLOW_OVERAGE', false),
        
        // Default quota tier for new clients
        'default_tier' => env('QUOTA_DEFAULT_TIER', 'free'),
        
        // Quota tiers configuration
        'tiers' => [
            'free' => [
                'limit' => 1000,
                'period' => 'daily', // 'hourly', 'daily', 'monthly'
                'description' => 'Free tier - 1,000 requests per day'
            ],
            
            'basic' => [
                'limit' => 10000,
                'period' => 'daily',
                'description' => 'Basic plan - 10,000 requests per day'
            ],
            
            'premium' => [
                'limit' => 100000,
                'period' => 'daily',
                'description' => 'Premium plan - 100,000 requests per day'
            ],
            
            'enterprise' => [
                'limit' => 1000000,
                'period' => 'daily',
                'description' => 'Enterprise plan - 1M requests per day'
            ],
            
            'unlimited' => [
                'limit' => PHP_INT_MAX,
                'period' => 'daily',
                'description' => 'Unlimited access'
            ],
        ],
        
        // Quota warning threshold (percentage)
        // Alert when client reaches this % of quota
        'warning_threshold' => 80,
        
        // Quota reset schedule (cron expression for scheduled resets)
        'reset_schedule' => '0 0 * * *', // Daily at midnight
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Client Identification Strategy
    |--------------------------------------------------------------------------
    |
    | Define how clients are identified for traffic management.
    | Priority: User ID > API Key > IP Address
    |
    */
    'identification' => [
        // Use authenticated user ID if available
        'use_user_id' => true,
        
        // Use API key from header if available
        'use_api_key' => true,
        'api_key_header' => 'X-API-Key',
        
        // Fall back to IP address
        'use_ip_address' => true,
        
        // Trust proxy headers for IP detection
        'trust_proxy' => env('TRUST_PROXY', false),
        'proxy_header' => 'X-Forwarded-For',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Response Headers
    |--------------------------------------------------------------------------
    |
    | Configure which traffic management headers to include in responses.
    |
    */
    'headers' => [
        // Rate limit headers
        'rate_limit' => true, // X-RateLimit-Limit, X-RateLimit-Remaining, etc.
        
        // Throttle headers
        'throttle' => true, // X-Throttle-Status, X-Throttle-Delay, etc.
        
        // Quota headers
        'quota' => true, // X-Quota-Limit, X-Quota-Remaining, etc.
        
        // Retry-After header (for 429 responses)
        'retry_after' => true,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Error Messages
    |--------------------------------------------------------------------------
    */
    'messages' => [
        'rate_limit_exceeded' => 'Rate limit exceeded. Please try again later.',
        'quota_exceeded' => 'API quota exceeded. Please upgrade your plan or try again later.',
        'throttled' => 'Too many requests. Your requests are being throttled.',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Statistics & Monitoring
    |--------------------------------------------------------------------------
    */
    'statistics' => [
        // Enable statistics tracking
        'enabled' => true,
        
        // Statistics retention (days)
        'retention_days' => 30,
        
        // Alert on high block rates
        'alert_on_high_block_rate' => true,
        'high_block_rate_threshold' => 50, // percentage
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Route-Specific Overrides
    |--------------------------------------------------------------------------
    |
    | Define route patterns with custom traffic limits.
    | These override global defaults but are overridden by #[RateLimit] attribute.
    |
    */
    'routes' => [
        // Public endpoints - strict limits
        'POST /api/v1/auth/login' => [
            'limit' => 5,
            'window' => 300, // 5 minutes
            'throttle' => false,
        ],
        
        'POST /api/v1/auth/register' => [
            'limit' => 3,
            'window' => 3600, // 1 hour
            'throttle' => false,
        ],
        
        // Search endpoints - throttle enabled
        'GET /api/v1/search' => [
            'limit' => 30,
            'window' => 60,
            'throttle' => true,
            'throttle_threshold' => 20,
        ],
        
        // Data export endpoints - expensive operations
        'GET /api/v1/*/export' => [
            'limit' => 10,
            'window' => 3600,
            'quota_cost' => 10, // Count as 10 requests
        ],
        
        // Admin endpoints - higher limits
        'GET /admin/*' => [
            'limit' => 300,
            'window' => 60,
        ],
        
        // Health check - unlimited
        'GET /health' => [
            'enabled' => false, // Disable traffic management
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Whitelisting
    |--------------------------------------------------------------------------
    |
    | Clients in whitelist bypass all traffic management.
    |
    */
    'whitelist' => [
        // Whitelisted client identifiers
        'clients' => env('TRAFFIC_WHITELIST_CLIENTS', [
            // 'user:admin',
            // 'api:internal-service',
            // 'ip:127.0.0.1',
        ]),
        
        // Whitelisted IP addresses/ranges
        'ips' => env('TRAFFIC_WHITELIST_IPS', [
            '127.0.0.1',
            '::1',
        ]),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Redis Configuration
    |--------------------------------------------------------------------------
    |
    | Traffic management uses Redis for distributed state management.
    |
    */
    'redis' => [
        'connection' => env('REDIS_CONNECTION', 'default'),
        
        // Key prefix for traffic data
        'key_prefix' => env('REDIS_TRAFFIC_PREFIX', 'traffic:'),
        
        // Connection timeout (seconds)
        'timeout' => 2.0,
    ],
    
];
