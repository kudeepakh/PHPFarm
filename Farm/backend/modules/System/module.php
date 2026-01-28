<?php

/**
 * System Module
 * 
 * Provides core infrastructure and system monitoring functionality:
 * - Health checks (liveness/readiness probes)
 * - API documentation
 * - Security monitoring (DDoS, WAF, bot detection)
 * - Traffic management (rate limiting, throttling)
 * - Resilience monitoring (circuit breakers, retries)
 * - Cache management
 * - Locking management (optimistic locking)
 * - System metrics and observability
 * 
 * This module is required for production deployments.
 */

return [
    'name' => 'System',
    'version' => '1.0.0',
    'description' => 'Core infrastructure: health, monitoring, security, traffic, cache, and resilience management',
    'author' => 'PHPFrarm Framework',
    
    'requires' => [
        'php' => '>=8.1'
    ],
    
    'config' => [
        'health_checks' => [
            'mysql' => true,
            'mongodb' => true,
            'redis' => true,
            'disk_space' => true,
            'memory' => true
        ],
        'metrics_enabled' => true,
        'docs_enabled' => true,
        'security_monitoring' => true,
        'traffic_monitoring' => true
    ],
    
    'bootstrap' => function() {
        // Initialize system monitoring
        \PHPFrarm\Core\Logger::info('System module initialized', [
            'health_checks_enabled' => true,
            'metrics_enabled' => true,
            'security_monitoring' => true
        ]);
        
        // Register controllers for automatic route discovery
        \PHPFrarm\Core\ControllerRegistry::register(\PHPFrarm\Modules\System\Controllers\SystemController::class);
        \PHPFrarm\Core\ControllerRegistry::register(\PHPFrarm\Modules\System\Controllers\CacheStatsController::class);
        \PHPFrarm\Core\ControllerRegistry::register(\PHPFrarm\Modules\System\Controllers\SecurityEventsController::class);
    }
];
