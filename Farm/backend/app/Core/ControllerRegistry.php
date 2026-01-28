<?php

namespace PHPFrarm\Core;

use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Attributes\RouteGroup;
use ReflectionClass;
use ReflectionMethod;

/**
 * Controller Registry
 * 
 * Automatically discovers routes from controller attributes
 * Caches route definitions in Redis for performance
 */
class ControllerRegistry
{
    private static array $controllers = [];
    private static ?object $redis = null;
    private static string $cacheKey = 'framework:routes:registry';

    /**
     * Initialize Redis connection
     */
    private static function initRedis(): void
    {
        if (self::$redis !== null) {
            return;
        }

        try {
            if (class_exists('\Redis')) {
                self::$redis = new \Redis();
                self::$redis->connect(
                    $_ENV['REDIS_HOST'] ?? 'redis',
                    (int)($_ENV['REDIS_PORT'] ?? 6379),
                    1.0  // 1 second timeout
                );
                
                // Authenticate if password is set
                if (!empty($_ENV['REDIS_PASSWORD'])) {
                    self::$redis->auth($_ENV['REDIS_PASSWORD']);
                }
                
                // Test connection
                self::$redis->ping();
            }
        } catch (\Exception $e) {
            Logger::warning('Redis not available for route caching', [
                'error' => $e->getMessage()
            ]);
            self::$redis = null;
        }
    }

    /**
     * Register a controller class for route discovery
     */
    public static function register(string $controllerClass): void
    {
        if (!class_exists($controllerClass)) {
            Logger::error("Controller class not found: {$controllerClass}");
            return;
        }

        self::$controllers[] = $controllerClass;
    }

    /**
     * Discover and register all routes from registered controllers
     */
    public static function discoverRoutes(): array
    {
        self::initRedis();

        // Try file cache first (faster than Redis for local dev)
        $fileCache = FileCache::get('controller_routes');
        if ($fileCache !== null && self::isFileCacheValid($fileCache)) {
            Logger::info('Routes loaded from file cache');
            return $fileCache['routes'];
        }

        // Check if cache is valid (no file changes)
        $cachedData = self::loadFromCache();
        if ($cachedData !== null && self::isCacheValid($cachedData)) {
            Logger::info('Routes loaded from Redis cache');
            // Also save to file cache for next time
            FileCache::put('controller_routes', $cachedData);
            return $cachedData['routes'];
        }

        // Cache invalid or missing - rediscover routes
        Logger::info('Discovering routes from controllers', [
            'controller_count' => count(self::$controllers),
            'reason' => $cachedData === null ? 'cache_miss' : 'controller_files_changed'
        ]);

        $routes = [];
        $fileModTimes = [];
        $filePaths = [];

        foreach (self::$controllers as $controllerClass) {
            // Track file modification time and path
            $reflection = new ReflectionClass($controllerClass);
            $filePath = $reflection->getFileName();
            if ($filePath !== false) {
                $fileModTimes[$controllerClass] = filemtime($filePath);
                $filePaths[$controllerClass] = $filePath;
            }

            $discoveredRoutes = self::discoverControllerRoutes($controllerClass);
            $routes = array_merge($routes, $discoveredRoutes);
        }

        // Cache the discovered routes with file paths and modification times
        $cacheData = [
            'routes' => $routes,
            'file_mod_times' => $fileModTimes,
            'file_paths' => $filePaths,
            'cached_at' => time()
        ];
        
        self::saveToCache($routes, $fileModTimes, $filePaths);
        FileCache::put('controller_routes', $cacheData);

        Logger::info('Route discovery complete', [
            'total_routes' => count($routes),
            'tracked_files' => count($fileModTimes)
        ]);

        return $routes;
    }

    /**
     * Discover routes from a single controller
     */
    private static function discoverControllerRoutes(string $controllerClass): array
    {
        $routes = [];
        $reflection = new ReflectionClass($controllerClass);

        // Get class-level RouteGroup attribute
        $groupAttributes = $reflection->getAttributes(RouteGroup::class);
        $groupPrefix = '';
        $groupMiddleware = [];

        if (!empty($groupAttributes)) {
            $group = $groupAttributes[0]->newInstance();
            $groupPrefix = $group->prefix;
            $groupMiddleware = $group->middleware;
        }

        // Scan all public methods for Route attributes
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $routeAttributes = $method->getAttributes(Route::class);

            foreach ($routeAttributes as $routeAttribute) {
                $route = $routeAttribute->newInstance();

                // Check for PublicRoute attribute
                $publicAttributes = $method->getAttributes(\PHPFrarm\Core\Attributes\PublicRoute::class);
                $isPublic = !empty($publicAttributes);
                
                if ($isPublic) {
                    $publicAttr = $publicAttributes[0]->newInstance();
                    Logger::info('Public route registered', [
                        'path' => $route->path,
                        'reason' => $publicAttr->reason ?? 'not specified',
                        'controller' => $controllerClass,
                        'action' => $method->getName()
                    ]);
                }

                // Combine group prefix with route path
                if ($route->path === '' || $route->path === '/') {
                    $fullPath = rtrim($groupPrefix, '/');
                } else {
                    $fullPath = rtrim($groupPrefix, '/') . '/' . ltrim($route->path, '/');
                }

                // Merge group middleware with route middleware
                $middleware = array_merge($groupMiddleware, $route->middleware);

                $routes[] = [
                    'method' => $route->method,
                    'path' => $fullPath,
                    'controller' => $controllerClass,
                    'action' => $method->getName(),
                    'middleware' => $middleware,
                    'description' => $route->description,
                    'is_public' => $isPublic
                ];

                Logger::debug('Route discovered', [
                    'method' => $route->method,
                    'path' => $fullPath,
                    'controller' => $controllerClass,
                    'action' => $method->getName()
                ]);
            }
        }

        return $routes;
    }

    /**
     * Register discovered routes with the Router
     */
    public static function registerWithRouter(array $routes): void
    {
        foreach ($routes as $route) {
            $handler = self::createControllerHandler(
                $route['controller'],
                $route['action']
            );

            // Strip /api/v1 prefix before registering (Router matches against stripped paths)
            $routePath = ApiVersion::stripVersionPrefix($route['path']);
            $isPublic = $route['is_public'] ?? false;

            // Register with Router based on HTTP method
            switch ($route['method']) {
                case 'GET':
                    Router::get($routePath, $handler, $route['middleware'], $isPublic);
                    break;
                case 'POST':
                    Router::post($routePath, $handler, $route['middleware'], $isPublic);
                    break;
                case 'PUT':
                    Router::put($routePath, $handler, $route['middleware'], $isPublic);
                    break;
                case 'DELETE':
                    Router::delete($routePath, $handler, $route['middleware'], $isPublic);
                    break;
                default:
                    Logger::warning("Unsupported HTTP method: {$route['method']}");
            }
        }
    }

    /**
     * Create a callable handler for a controller method
     * Uses singleton pattern to reuse controller instances
     * Automatically converts array requests to Request objects for new-style controllers
     */
    private static function createControllerHandler(string $controllerClass, string $method): callable
    {
        return function(...$args) use ($controllerClass, $method) {
            static $instances = [];

            // Reuse controller instance (singleton per class)
            if (!isset($instances[$controllerClass])) {
                $instances[$controllerClass] = new $controllerClass();
            }

            // Check method signature to determine if it expects Request object
            $reflection = new \ReflectionMethod($controllerClass, $method);
            $parameters = $reflection->getParameters();
            
            if (!empty($parameters)) {
                $firstParam = $parameters[0];
                $paramType = $firstParam->getType();
                
                // If first parameter expects Request object and we have an array, convert it
                if ($paramType && 
                    $paramType->getName() === 'PHPFrarm\\Core\\Request' && 
                    isset($args[0]) && 
                    is_array($args[0])) {
                    
                    $requestArray = $args[0];
                    $requestObject = Request::fromArray($requestArray);
                    $args[0] = $requestObject;
                    
                    // Add path parameters as additional arguments
                    if (isset($requestArray['params']) && is_array($requestArray['params'])) {
                        foreach ($requestArray['params'] as $paramValue) {
                            $args[] = $paramValue;
                        }
                    }
                }
            }

            return call_user_func_array(
                [$instances[$controllerClass], $method],
                $args
            );
        };
    }

    /**
     * Check if cached routes are still valid (no file changes)
     */
    private static function isCacheValid(?array $cachedData): bool
    {
        // No cache data
        if ($cachedData === null || !isset($cachedData['routes'])) {
            return false;
        }

        // Check if number of registered controllers has changed
        if (isset($cachedData['file_paths']) && count($cachedData['file_paths']) !== count(self::$controllers)) {
            Logger::info('Number of registered controllers changed, invalidating cache', [
                'cached_count' => count($cachedData['file_paths']),
                'current_count' => count(self::$controllers)
            ]);
            return false;
        }

        // Fast file modification check using cached paths (no class loading needed!)
        // This works in both dev and production - only invalidate if files actually change
        if (isset($cachedData['file_paths'], $cachedData['file_mod_times'])) {
            foreach ($cachedData['file_paths'] as $controllerClass => $filePath) {
                // Check file exists and modification time matches
                if (!file_exists($filePath)) {
                    Logger::info('Controller file no longer exists, invalidating cache', [
                        'class' => $controllerClass,
                        'file' => $filePath
                    ]);
                    return false;
                }

                $currentModTime = filemtime($filePath);
                $cachedModTime = $cachedData['file_mod_times'][$controllerClass] ?? 0;
                
                if ($currentModTime !== $cachedModTime) {
                    Logger::info('Controller file modified, invalidating cache', [
                        'class' => $controllerClass,
                        'file' => $filePath,
                        'cached_time' => $cachedModTime,
                        'current_time' => $currentModTime
                    ]);
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check if file cache is valid
     */
    private static function isFileCacheValid(?array $cachedData): bool
    {
        return self::isCacheValid($cachedData);
    }

    /**
     * Load routes from Redis cache
     */
    private static function loadFromCache(): ?array
    {
        if (self::$redis === null) {
            return null;
        }

        try {
            $cached = self::$redis->get(self::$cacheKey);
            if ($cached === false) {
                return null;
            }

            return json_decode($cached, true);
        } catch (\Exception $e) {
            Logger::warning('Failed to load routes from cache', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Save routes to Redis cache with file paths and modification times
     */
    private static function saveToCache(array $routes, array $fileModTimes, array $filePaths = []): void
    {
        if (self::$redis === null) {
            return;
        }

        try {
            $cacheData = [
                'routes' => $routes,
                'file_mod_times' => $fileModTimes,
                'file_paths' => $filePaths,
                'cached_at' => time()
            ];

            $ttl = (int)($_ENV['ROUTE_CACHE_TTL'] ?? 3600); // 1 hour default
            self::$redis->setex(
                self::$cacheKey,
                $ttl,
                json_encode($cacheData)
            );

            Logger::info('Routes cached in Redis with file tracking', [
                'ttl' => $ttl,
                'route_count' => count($routes),
                'tracked_files' => count($fileModTimes)
            ]);
        } catch (\Exception $e) {
            Logger::warning('Failed to cache routes', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear route cache (useful during development)
     */
    public static function clearCache(): bool
    {
        self::initRedis();

        if (self::$redis === null) {
            return false;
        }

        try {
            self::$redis->del(self::$cacheKey);
            Logger::info('Route cache cleared');
            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to clear route cache', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get all registered controller classes
     */
    public static function getRegisteredControllers(): array
    {
        return self::$controllers;
    }

    /**
     * Auto-discover and register controllers from all loaded modules
     * This scans all module directories and registers controllers automatically
     */
    public static function discoverAllModuleControllers(): void
    {
        $moduleBasePath = dirname(__DIR__, 2) . '/modules';
        
        if (!is_dir($moduleBasePath)) {
            Logger::warning('Modules directory not found', ['path' => $moduleBasePath]);
            return;
        }
        
        // Get all loaded modules from ModuleLoader
        $loadedModules = ModuleLoader::getLoadedModules();
        $totalControllers = 0;
        
        foreach ($loadedModules as $moduleName => $moduleInfo) {
            $modulePath = $moduleInfo['path'] ?? null;
            
            if (!$modulePath || !is_dir($modulePath)) {
                continue;
            }
            
            $controllersPath = $modulePath . '/Controllers';
            
            if (!is_dir($controllersPath)) {
                Logger::debug("No Controllers directory in module", ['module' => $moduleName]);
                continue;
            }
            
            // Auto-discover controllers using PSR-4 namespace
            // PHPFrarm\Modules\{ModuleName}\Controllers
            $namespace = "PHPFrarm\\Modules\\{$moduleName}\\Controllers";
            
            // Scan controller files
            $controllerFiles = glob($controllersPath . '/*.php');
            
            foreach ($controllerFiles as $file) {
                $className = basename($file, '.php');
                $fullClass = $namespace . '\\' . $className;
                
                if (class_exists($fullClass)) {
                    self::register($fullClass);
                    $totalControllers++;
                    
                    Logger::debug("Controller registered", [
                        'module' => $moduleName,
                        'controller' => $fullClass
                    ]);
                }
            }
        }
        
        Logger::info('All module controllers discovered', [
            'total_modules' => count($loadedModules),
            'total_controllers' => $totalControllers
        ]);
    }

    /**
     * Auto-discover controllers in a directory
     */
    public static function autoDiscoverControllers(string $directory, string $namespace, bool $recursive = false): void
    {
        // Cache key for controller discovery
        $cacheKey = 'framework:controllers:' . md5($directory . $namespace . ($recursive ? '1' : '0'));
        
        // Try to load from cache first
        self::initRedis();
        if (self::$redis !== null) {
            try {
                $cached = self::$redis->get($cacheKey);
                if ($cached !== false) {
                    $controllers = json_decode($cached, true);
                    if (is_array($controllers)) {
                        foreach ($controllers as $controllerClass) {
                            if (class_exists($controllerClass)) {
                                self::register($controllerClass);
                            }
                        }
                        return;
                    }
                }
            } catch (\Exception $e) {
                // Continue to manual discovery
            }
        }

        // Manual discovery
        $discoveredControllers = [];
        
        if ($recursive) {
            // Recursive scan for nested directories (e.g., modules/Module/Controllers/)
            if (!is_dir($directory)) {
                Logger::warning('Controller discovery directory not found', ['directory' => $directory]);
                return;
            }

            try {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getExtension() === 'php') {
                        // Normalize paths to use forward slashes
                        $filePath = str_replace('\\', '/', $file->getPath());
                        $baseDir = str_replace('\\', '/', $directory);
                        
                        // Calculate namespace from directory structure
                        $relativePath = str_replace($baseDir . '/', '', $filePath);
                        $relativeNamespace = str_replace('/', '\\', $relativePath);
                        
                        $className = basename($file->getFilename(), '.php');
                        $fullClass = rtrim($namespace . '\\' . $relativeNamespace, '\\') . '\\' . $className;

                        if (class_exists($fullClass)) {
                            self::register($fullClass);
                            $discoveredControllers[] = $fullClass;
                        }
                    }
                }
            } catch (\Exception $e) {
                Logger::error('Error during recursive controller discovery', [
                    'directory' => $directory,
                    'error' => $e->getMessage()
                ]);
            }
        } else {
            // Original single-level scan
            $files = glob($directory . '/*.php');

            foreach ($files as $file) {
                $className = basename($file, '.php');
                $fullClass = $namespace . '\\' . $className;

                if (class_exists($fullClass)) {
                    self::register($fullClass);
                    $discoveredControllers[] = $fullClass;
                }
            }
        }

        // Cache the discovered controllers for 1 hour
        if (self::$redis !== null && !empty($discoveredControllers)) {
            try {
                self::$redis->setex($cacheKey, 3600, json_encode($discoveredControllers));
            } catch (\Exception $e) {
                // Fail silently
            }
        }
    }
}
