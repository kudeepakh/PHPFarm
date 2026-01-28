<?php

/**
 * Role Module Routes
 * 
 * Controllers use #[Route] attributes for route definitions
 * Register controllers here for auto-discovery
 */

use PHPFrarm\Core\ControllerRegistry;

// Register Role controllers
ControllerRegistry::register(PHPFrarm\Modules\Role\Controllers\RoleController::class);
ControllerRegistry::register(PHPFrarm\Modules\Role\Controllers\RoleApiController::class);
