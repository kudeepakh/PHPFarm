<?php

/**
 * PHPFrarm CLI Bootstrap
 * 
 * Common bootstrap functionality for CLI commands.
 * Loads autoloader, environment, and defines helper functions.
 */

// Load autoloader (vendor is at backend root, 2 levels up from app/bootstrap)
require_once __DIR__ . '/../../vendor/autoload.php';

// Load environment variables (at backend root)
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, '"\'');
        
        if (!getenv($name)) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
        }
    }
}

// Define env() helper if not exists
if (!function_exists('env')) {
    /**
     * Get environment variable with default value
     */
    function env(string $key, mixed $default = null): mixed {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }
        // Handle boolean-like values
        switch (strtolower((string)$value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'null':
            case '(null)':
                return null;
        }
        return $value;
    }
}

// Define config() helper if not exists
if (!function_exists('config')) {
    /**
     * Get configuration value with dot notation
     */
    function config(string $key, mixed $default = null): mixed {
        static $configs = [];
        
        $parts = explode('.', $key);
        $file = array_shift($parts);
        
        if (!isset($configs[$file])) {
            $path = __DIR__ . '/../../config/' . $file . '.php';
            if (file_exists($path)) {
                $configs[$file] = require $path;
            } else {
                return $default;
            }
        }
        
        $value = $configs[$file];
        foreach ($parts as $part) {
            if (!is_array($value) || !isset($value[$part])) {
                return $default;
            }
            $value = $value[$part];
        }
        
        return $value;
    }
}
