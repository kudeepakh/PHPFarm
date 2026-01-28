<?php

/**
 * Permission Module Configuration
 * 
 * This module handles permission management and discovery
 */

return [
    'name' => 'Permission',
    'version' => '1.0.0',
    'description' => 'Permission management and auto-discovery',
    
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
            'Modules\\Permission\\' => __DIR__,
        ],
    ],
];
