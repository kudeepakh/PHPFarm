<?php

/**
 * Storage Module Configuration
 * 
 * Controllers are auto-discovered via route attributes
 * 
 * @package PHPFrarm\Modules\Storage
 */

use PHPFrarm\Core\ControllerRegistry;

return [
    'name' => 'Storage',
    'version' => '1.0.0',
    'description' => 'Blob/Object storage management with multi-provider support',
    'enabled' => true,
    'dependencies' => [],

    'bootstrap' => function() {
        // Register controllers for automatic route discovery
        ControllerRegistry::register(\PHPFrarm\Modules\Storage\Controllers\StorageController::class);
        
        \PHPFrarm\Core\Logger::info('Storage module initialized');
    },

    'config' => [
        'default_disk' => $_ENV['STORAGE_DISK'] ?? 'local',
        'max_upload_size' => (int)($_ENV['STORAGE_MAX_UPLOAD_SIZE'] ?? 104857600),
        'signed_url_expiry' => (int)($_ENV['STORAGE_SIGNED_URL_EXPIRY'] ?? 3600),
    ],
];
