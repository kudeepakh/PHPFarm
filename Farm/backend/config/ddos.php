<?php

/**
 * DDoS & Abuse Protection Configuration
 * 
 * Multi-layer security configuration:
 * - Bot Detection
 * - IP Reputation Management
 * - Geo-Blocking
 * - WAF (Web Application Firewall)
 * - Anomaly Detection
 * 
 * All settings can be overridden via environment variables.
 */

return [
    
    /*
    |--------------------------------------------------------------------------
    | DDoS Protection Enabled
    |--------------------------------------------------------------------------
    |
    | Master switch for all DDoS protection features.
    | When disabled, all security layers are bypassed.
    |
    */
    'enabled' => env('DDOS_PROTECTION_ENABLED', true),
    
    /*
    |--------------------------------------------------------------------------
    | Bot Detection Configuration
    |--------------------------------------------------------------------------
    |
    | Configure bot detection behavior and sensitivity.
    |
    */
    'bot_detection' => [
        'enabled' => env('BOT_DETECTION_ENABLED', true),
        'strict_mode' => env('BOT_DETECTION_STRICT', false),
        'allow_good_bots' => env('BOT_ALLOW_SEARCH_ENGINES', true),
        'check_fingerprint' => env('BOT_CHECK_FINGERPRINT', true),
        'log_detections' => env('BOT_LOG_DETECTIONS', true),
        
        // Known good bots (search engines, monitoring services)
        'whitelisted_bots' => [
            'googlebot',
            'bingbot',
            'slurp',        // Yahoo
            'duckduckbot',
            'uptimerobot',
            'pingdom',
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | IP Reputation Management
    |--------------------------------------------------------------------------
    |
    | Blacklist/whitelist management and reputation scoring.
    |
    */
    'ip_reputation' => [
        'enabled' => env('IP_REPUTATION_ENABLED', true),
        
        // Auto-blocking configuration
        'auto_block_enabled' => env('IP_AUTO_BLOCK_ENABLED', true),
        'auto_block_threshold' => env('IP_AUTO_BLOCK_THRESHOLD', 10),
        'auto_block_duration' => env('IP_AUTO_BLOCK_DURATION', 3600), // 1 hour
        
        // Violation tracking
        'violation_ttl' => env('IP_VIOLATION_TTL', 1800), // 30 minutes
        
        // Reputation scoring
        'default_reputation' => env('IP_DEFAULT_REPUTATION', 50),
        'reputation_decay' => env('IP_REPUTATION_DECAY', 1),
        'reputation_recovery' => env('IP_REPUTATION_RECOVERY', 0.1),
        
        // Permanently whitelisted IPs (never blocked)
        'whitelist_ips' => array_filter(explode(',', env('IP_WHITELIST', ''))),
        
        // Permanently blacklisted IPs
        'blacklist_ips' => array_filter(explode(',', env('IP_BLACKLIST', ''))),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Geo-Blocking Configuration
    |--------------------------------------------------------------------------
    |
    | Country-based access control using IP geolocation.
    |
    */
    'geo_blocking' => [
        'enabled' => env('GEO_BLOCKING_ENABLED', false),
        'mode' => env('GEO_BLOCKING_MODE', 'blacklist'), // 'blacklist' or 'whitelist'
        
        // Blocked countries (ISO 3166-1 alpha-2 codes)
        'blocked_countries' => array_filter(explode(',', env('GEO_BLOCKED_COUNTRIES', ''))),
        
        // Allowed countries (whitelist mode only)
        'allowed_countries' => array_filter(explode(',', env('GEO_ALLOWED_COUNTRIES', 'US,CA,GB'))),
        
        // Geolocation provider
        'provider' => env('GEO_PROVIDER', 'ip-api'), // 'ip-api', 'maxmind', 'custom'
        'cache_ttl' => env('GEO_CACHE_TTL', 86400), // 24 hours
        'fallback_country' => env('GEO_FALLBACK_COUNTRY', null),
        'log_blocks' => env('GEO_LOG_BLOCKS', true),
        
        // MaxMind configuration (if using MaxMind provider)
        'maxmind' => [
            'database_path' => env('MAXMIND_DB_PATH', '/var/lib/GeoLite2-Country.mmdb'),
            'account_id' => env('MAXMIND_ACCOUNT_ID', ''),
            'license_key' => env('MAXMIND_LICENSE_KEY', ''),
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | WAF (Web Application Firewall) Configuration
    |--------------------------------------------------------------------------
    |
    | Attack signature detection for common vulnerabilities.
    |
    */
    'waf' => [
        'enabled' => env('WAF_ENABLED', true),
        'sensitivity' => env('WAF_SENSITIVITY', 'medium'), // 'low', 'medium', 'high'
        'block_on_detection' => env('WAF_BLOCK_ON_DETECTION', true),
        'log_detections' => env('WAF_LOG_DETECTIONS', true),
        
        // Detection types
        'detect_sql_injection' => env('WAF_DETECT_SQL', true),
        'detect_xss' => env('WAF_DETECT_XSS', true),
        'detect_path_traversal' => env('WAF_DETECT_PATH_TRAVERSAL', true),
        'detect_command_injection' => env('WAF_DETECT_CMD_INJECTION', true),
        'detect_ldap_injection' => env('WAF_DETECT_LDAP', true),
        'detect_xxe' => env('WAF_DETECT_XXE', true),
        'detect_ssrf' => env('WAF_DETECT_SSRF', true),
        
        // Custom WAF rules (name => regex pattern)
        'custom_rules' => [
            // Example: 'credit_card' => '/\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}/',
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Anomaly Detection Configuration
    |--------------------------------------------------------------------------
    |
    | Pattern and velocity-based anomaly detection.
    |
    */
    'anomaly_detection' => [
        'enabled' => env('ANOMALY_DETECTION_ENABLED', true),
        
        // Velocity limits
        'velocity_per_second' => env('ANOMALY_VELOCITY_PER_SECOND', 10),
        'velocity_per_minute' => env('ANOMALY_VELOCITY_PER_MINUTE', 100),
        'velocity_per_hour' => env('ANOMALY_VELOCITY_PER_HOUR', 1000),
        
        // Endpoint abuse detection
        'endpoint_abuse_threshold' => env('ANOMALY_ENDPOINT_THRESHOLD', 50),
        
        // Pattern analysis
        'pattern_threshold' => env('ANOMALY_PATTERN_THRESHOLD', 3),
        'tracking_window' => env('ANOMALY_TRACKING_WINDOW', 300), // 5 minutes
        'min_requests_for_analysis' => env('ANOMALY_MIN_REQUESTS', 10),
        
        'log_anomalies' => env('ANOMALY_LOG', true),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Middleware Configuration
    |--------------------------------------------------------------------------
    |
    | DDoS protection middleware behavior.
    |
    */
    'middleware' => [
        'check_ip_reputation' => env('DDOS_CHECK_IP_REPUTATION', true),
        'check_bots' => env('DDOS_CHECK_BOTS', true),
        'check_geo' => env('DDOS_CHECK_GEO', true),
        'check_waf' => env('DDOS_CHECK_WAF', true),
        'check_anomalies' => env('DDOS_CHECK_ANOMALIES', true),
        'log_blocked_requests' => env('DDOS_LOG_BLOCKED', true),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting (Per-Route Override)
    |--------------------------------------------------------------------------
    |
    | Default rate limits when #[BotProtection] attribute not specified.
    |
    */
    'default_rate_limits' => [
        'per_second' => env('RATE_LIMIT_PER_SECOND', null),
        'per_minute' => env('RATE_LIMIT_PER_MINUTE', null),
        'per_hour' => env('RATE_LIMIT_PER_HOUR', null),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Response Configuration
    |--------------------------------------------------------------------------
    |
    | Customize blocked request responses.
    |
    */
    'response' => [
        'status_code' => env('DDOS_BLOCK_STATUS_CODE', 403),
        'message' => env('DDOS_BLOCK_MESSAGE', 'Access denied due to security policy'),
        'error_code' => env('DDOS_BLOCK_ERROR_CODE', 'SECURITY_VIOLATION'),
        'include_reason' => env('DDOS_INCLUDE_REASON', false), // Expose block reason (for debugging)
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Challenge Configuration (Future Enhancement)
    |--------------------------------------------------------------------------
    |
    | CAPTCHA and challenge-response configuration.
    |
    */
    'challenge' => [
        'enabled' => env('CHALLENGE_ENABLED', false),
        'provider' => env('CHALLENGE_PROVIDER', 'recaptcha'), // 'recaptcha', 'hcaptcha', 'turnstile'
        'site_key' => env('CHALLENGE_SITE_KEY', ''),
        'secret_key' => env('CHALLENGE_SECRET_KEY', ''),
        'threshold' => env('CHALLENGE_THRESHOLD', 5), // Violations before challenge
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | MongoDB collection for security events.
    |
    */
    'logging' => [
        'collection' => env('SECURITY_LOG_COLLECTION', 'security_events'),
        'log_clean_requests' => env('SECURITY_LOG_CLEAN', false),
        'log_bot_detections' => env('SECURITY_LOG_BOTS', true),
        'log_waf_detections' => env('SECURITY_LOG_WAF', true),
        'log_anomalies' => env('SECURITY_LOG_ANOMALIES', true),
        'log_geo_blocks' => env('SECURITY_LOG_GEO', true),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Performance Optimization
    |--------------------------------------------------------------------------
    |
    | Caching and performance tuning.
    |
    */
    'performance' => [
        'cache_driver' => env('DDOS_CACHE_DRIVER', 'redis'),
        'cache_prefix' => env('DDOS_CACHE_PREFIX', 'ddos:'),
        'cache_ttl' => env('DDOS_CACHE_TTL', 300), // 5 minutes
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Integration with External Services
    |--------------------------------------------------------------------------
    |
    | External WAF, CDN, and threat intelligence services.
    |
    */
    'integrations' => [
        // Cloudflare
        'cloudflare' => [
            'enabled' => env('CLOUDFLARE_ENABLED', false),
            'api_token' => env('CLOUDFLARE_API_TOKEN', ''),
            'zone_id' => env('CLOUDFLARE_ZONE_ID', ''),
        ],
        
        // AWS WAF
        'aws_waf' => [
            'enabled' => env('AWS_WAF_ENABLED', false),
            'web_acl_id' => env('AWS_WAF_WEB_ACL_ID', ''),
        ],
        
        // AbuseIPDB (threat intelligence)
        'abuseipdb' => [
            'enabled' => env('ABUSEIPDB_ENABLED', false),
            'api_key' => env('ABUSEIPDB_API_KEY', ''),
        ],
    ],
];
