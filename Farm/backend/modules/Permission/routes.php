<?php

/**
 * Permission Module Routes
 * 
 * Controllers use #[Route] attributes for route definitions
 * Register controllers here for auto-discovery
 */

use PHPFrarm\Core\ControllerRegistry;

// Register Permission controllers
ControllerRegistry::register(PHPFrarm\Modules\Permission\Controllers\PermissionController::class);
ControllerRegistry::register(PHPFrarm\Modules\Permission\Controllers\PermissionApiController::class);
