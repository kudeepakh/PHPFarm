<?php

/**
 * Auth Module Configuration
 * 
 * Controllers are auto-discovered via route attributes
 */

use PHPFrarm\Core\ControllerRegistry;

return [
    'name' => 'Auth',
    'version' => '1.0.0',
    'description' => 'Authentication and authorization module',
    'enabled' => true,
    'dependencies' => [],

    'bootstrap' => function() {
        // Register controllers for automatic route discovery
        ControllerRegistry::register(\PHPFrarm\Modules\Auth\Controllers\AuthController::class);
        ControllerRegistry::register(\PHPFrarm\Modules\Auth\Controllers\OTPController::class);
        ControllerRegistry::register(\PHPFrarm\Modules\Auth\Controllers\OTPAdminController::class);
        ControllerRegistry::register(\PHPFrarm\Modules\Auth\Controllers\SocialAuthController::class);
        ControllerRegistry::register(\PHPFrarm\Modules\Auth\Controllers\UserContextController::class);
        
        \PHPFrarm\Core\Logger::info('Auth module initialized with attribute-based routing');
    },

    'config' => [
        'jwt_expiry' => (int)($_ENV['JWT_EXPIRY'] ?? 3600),
        'refresh_token_expiry' => (int)($_ENV['JWT_REFRESH_EXPIRY'] ?? 604800),
        'otp_expiry' => (int)($_ENV['OTP_EXPIRY'] ?? 300),
        'otp_max_attempts' => (int)($_ENV['OTP_MAX_ATTEMPTS'] ?? 3),
    ],
];
