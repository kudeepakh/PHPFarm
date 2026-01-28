<?php

/**
 * User Module Configuration
 * 
 * Controllers are auto-discovered via route attributes
 */

use PHPFrarm\Core\ControllerRegistry;

return [
    // Module metadata
    'name' => 'User',
    'version' => '1.0.0',
    'description' => 'User management module',
    'enabled' => true,

    // Dependencies (other modules required)
    'dependencies' => [],

    // Bootstrap function (optional)
    // Runs when module is loaded
    'bootstrap' => function() {
        // Register controllers for automatic route discovery
        ControllerRegistry::register(\PHPFrarm\Modules\User\Controllers\UserController::class);
        ControllerRegistry::register(\PHPFrarm\Modules\User\Controllers\AccountStatusController::class);
        ControllerRegistry::register(\PHPFrarm\Modules\User\Controllers\VerificationController::class);
        ControllerRegistry::register(\PHPFrarm\Modules\User\Controllers\UserHealthController::class);
        
        \PHPFrarm\Core\Logger::info('User module initialized with attribute-based routing');
    },

    // Module-specific configuration
    'config' => [
        'allow_registration' => true,
        'require_email_verification' => true,
        'password_min_length' => 8,
    ],
];
