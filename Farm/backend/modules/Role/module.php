<?php

/**
 * Role Module Configuration
 * 
 * This module handles role management and RBAC
 */

return [
    'name' => 'Role',
    'version' => '1.0.0',
    'description' => 'Role-Based Access Control (RBAC) management',
    
    'providers' => [
        // Service providers will be auto-loaded
    ],
    
    'middleware' => [
        // Module-specific middleware
    ],
    
    'routes' => [
        // Routes are auto-discovered via #[Route] attributes in Controllers
    ],
    
    'dependencies' => [
        // Other modules this module depends on
    ],
    
    'autoload' => [
        'psr-4' => [
            'Modules\\Role\\' => __DIR__,
        ],
    ],
];
