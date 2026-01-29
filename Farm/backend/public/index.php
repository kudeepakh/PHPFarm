<?php

/**
 * Application Entry Point
 * 
 * Minimal entry point - all initialization happens in bootstrap
 */

require_once __DIR__ . '/../bootstrap/app.php';

// Import core classes
use PHPFrarm\Core\TraceContext;
use PHPFrarm\Core\Response;
use PHPFrarm\Core\Logger;
use PHPFrarm\Core\Router;
use PHPFrarm\Core\ModuleLoader;
use PHPFrarm\Core\ControllerRegistry;
use PHPFrarm\Core\ApiVersion;
use PHPFrarm\Core\FileCache;
use PHPFrarm\Middleware\CommonMiddleware;
use PHPFrarm\Middleware\InputValidationMiddleware;
use PHPFrarm\Middleware\SecureHeadersMiddleware;
use PHPFrarm\Middleware\XSSMiddleware;
use PHPFrarm\Middleware\CSRFMiddleware;
use PHPFrarm\Middleware\PayloadSizeLimitMiddleware;
use PHPFrarm\Middleware\ErrorSanitizationMiddleware;
use PHPFrarm\Middleware\IdempotencyMiddleware;
use PHPFrarm\Middleware\TimeoutMiddleware;
use PHPFrarm\Middleware\AuthorizationMiddleware;

// Initialize trace context (MANDATORY)
TraceContext::initialize();

// Initialize API versioning from config
$apiConfigPath = __DIR__ . '/../config/api.php';
$apiConfig = file_exists($apiConfigPath) ? require $apiConfigPath : [];
$versioning = $apiConfig['versioning'] ?? [];

$supportedVersions = $versioning['supported_versions'] ?? ['v1'];
ApiVersion::setSupportedVersions($supportedVersions);

$deprecatedVersions = $versioning['deprecated_versions'] ?? [];
foreach ($deprecatedVersions as $version => $sunsetDate) {
    ApiVersion::deprecateVersion((string) $version, (string) $sunsetDate);
}

// Global exception handler
set_exception_handler(function ($exception) {
    error_log('[PHPFrarm] Unhandled exception: ' . $exception->getMessage());
    error_log($exception->getTraceAsString());
    
    // Log full details to MongoDB with trace IDs
    Logger::error('Unhandled exception', [
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString(),
        'type' => get_class($exception)
    ]);
    
    // In development, show detailed errors for easier debugging
    $env = $_ENV['APP_ENV'] ?? 'production';
    if (in_array($env, ['dev', 'development', 'local'])) {
        Response::error([
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => explode("\n", $exception->getTraceAsString())
        ], 500, 'UNHANDLED_EXCEPTION');
    } else {
        // Production: hide internal details, use correlation ID to find logs
        Response::serverError('error.unexpected');
    }
});

// Register global middleware
Router::middleware('timeout', [TimeoutMiddleware::class, 'handle']); // Request timeout - must be very early
Router::middleware('errorSanitization', [ErrorSanitizationMiddleware::class, 'handle']); // Error handling - must be first
Router::middleware('secureHeaders', [SecureHeadersMiddleware::class, 'handle']); // Apply first
Router::middleware('payloadSizeLimit', [PayloadSizeLimitMiddleware::class, 'handle']); // Check size early
Router::middleware('xss', [XSSMiddleware::class, 'handle']); // XSS protection
Router::middleware('csrf', [CSRFMiddleware::class, 'handle']); // CSRF protection
Router::middleware('idempotency', [IdempotencyMiddleware::class, 'handle']); // Idempotency for state-changing operations
Router::middleware('auth', [CommonMiddleware::class, 'auth']);
Router::middleware('rateLimit', [CommonMiddleware::class, 'rateLimit']);
Router::middleware('cors', [CommonMiddleware::class, 'cors']);
Router::middleware('jsonParser', [CommonMiddleware::class, 'jsonParser']);
Router::middleware('logRequest', [CommonMiddleware::class, 'logRequest']);
Router::middleware('inputValidation', function(array $request, callable $next) {
    $middleware = new InputValidationMiddleware();
    return $middleware->handle(function() use ($request, $next) {
        return $next($request);
    });
});

// MANDATORY: Authentication enforced by default on all routes
// Use #[Public] attribute on controller methods to explicitly allow public access
// Error sanitization wraps all requests to prevent information disclosure
// NOTE: CORS MUST run FIRST to handle OPTIONS preflight before auth blocks it
Router::setGlobalMiddlewares(['cors', 'timeout', 'errorSanitization', 'auth', 'csrf', 'idempotency', 'payloadSizeLimit', 'inputValidation']);

// Authorization middleware (dynamic - created per permission)
// Usage in routes: middleware: ['permission:users:create']
// Handled by annotation system with dynamic middleware creation

// Core system routes are defined via controller attributes

// Performance measurement
$startTime = microtime(true);

/**
 * UNIFIED MODULE LOADING (STANDARD APPROACH)
 * 
 * All controllers (System, Admin, Auth, User, etc.) are now loaded via ModuleLoader.
 * No manual controller registration required - ModuleLoader auto-discovers controllers
 * from all modules using PSR-4 autoloading.
 * 
 * NOTE: Caching optimization was causing issues with route attributes.
 * Now using standard loading with module caching only (not route caching).
 * 
 * Benefits:
 * - No need to modify index.php when adding new modules
 * - PSR-4 autoloading ensures classes load only when needed
 * - Consistent namespace structure: PHPFrarm\Modules\{Module}\{Type}
 */
$moduleLoadStart = microtime(true);
ModuleLoader::init(__DIR__ . '/../modules');
ModuleLoader::loadAll();
$moduleLoadTime = (microtime(true) - $moduleLoadStart) * 1000;

// Auto-discover and register all module controllers
$controllerDiscoveryStart = microtime(true);
ControllerRegistry::discoverAllModuleControllers();
$controllerDiscoveryTime = (microtime(true) - $controllerDiscoveryStart) * 1000;

// Discover and register routes from controller attributes
$routeDiscoveryStart = microtime(true);
$routes = ControllerRegistry::discoverRoutes();
ControllerRegistry::registerWithRouter($routes);
$routeDiscoveryTime = (microtime(true) - $routeDiscoveryStart) * 1000;

$totalBootTime = (microtime(true) - $startTime) * 1000;
Logger::info('Performance: Bootstrap complete', [
    'total_boot_ms' => round($totalBootTime, 2),
    'module_load_ms' => round($moduleLoadTime, 2),
    'controller_discovery_ms' => round($controllerDiscoveryTime, 2),
    'route_discovery_ms' => round($routeDiscoveryTime, 2),
    'route_count' => count($routes)
]);

// Dispatch the request to appropriate route
$dispatchStart = microtime(true);
Router::dispatch();
$dispatchTime = (microtime(true) - $dispatchStart) * 1000;

$totalRequestTime = (microtime(true) - $startTime) * 1000;
Logger::info('Performance: Request complete', [
    'total_request_ms' => round($totalRequestTime, 2),
    'dispatch_ms' => round($dispatchTime, 2)
]);
