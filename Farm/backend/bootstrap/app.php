<?php

/**
 * Application Bootstrap
 * 
 * Handles core initialization:
 * - Composer autoloader
 * - Environment variables
 * - Helper functions
 * - Constants
 */

// Define base path constant
define('BASE_PATH', dirname(__DIR__));

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Define env() helper function for config files
if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed {
        return $_ENV[$key] ?? $default;
    }
}
