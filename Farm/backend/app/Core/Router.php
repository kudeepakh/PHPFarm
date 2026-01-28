<?php

namespace PHPFrarm\Core;

use PHPFrarm\Core\I18n\Translator;
use PHPFrarm\Middleware\AuthorizationMiddleware;

/**
 * Router - Handles routing and middleware execution
 */
class Router
{
    private static array $routes = [];
    private static array $middlewares = [];
    private static array $groupMiddlewares = [];
    private static array $globalMiddlewares = [];

    /**
     * Register a GET route
     * @param string $path Route path
     * @param callable|array $handler Route handler (function or [object, method])
     * @param array $middlewares Middleware names
     * @param bool $isPublic Whether route is public (no auth required)
     */
    public static function get(string $path, callable|array $handler, array $middlewares = [], bool $isPublic = false): void
    {
        self::addRoute('GET', $path, $handler, $middlewares, $isPublic);
    }

    /**
     * Register a POST route
     * @param string $path Route path
     * @param callable|array $handler Route handler (function or [object, method])
     * @param array $middlewares Middleware names
     * @param bool $isPublic Whether route is public (no auth required)
     */
    public static function post(string $path, callable|array $handler, array $middlewares = [], bool $isPublic = false): void
    {
        self::addRoute('POST', $path, $handler, $middlewares, $isPublic);
    }

    /**
     * Register a PUT route
     * @param string $path Route path
     * @param callable|array $handler Route handler (function or [object, method])
     * @param array $middlewares Middleware names
     * @param bool $isPublic Whether route is public (no auth required)
     */
    public static function put(string $path, callable|array $handler, array $middlewares = [], bool $isPublic = false): void
    {
        self::addRoute('PUT', $path, $handler, $middlewares, $isPublic);
    }

    /**
     * Register a DELETE route
     * @param string $path Route path
     * @param callable|array $handler Route handler (function or [object, method])
     * @param array $middlewares Middleware names
     * @param bool $isPublic Whether route is public (no auth required)
     */
    public static function delete(string $path, callable|array $handler, array $middlewares = [], bool $isPublic = false): void
    {
        self::addRoute('DELETE', $path, $handler, $middlewares, $isPublic);
    }

    /**
     * Register a route group with shared middleware
     */
    public static function group(string $prefix, array $middlewares, callable $callback): void
    {
        $previousGroupMiddlewares = self::$groupMiddlewares;
        self::$groupMiddlewares = array_merge(self::$groupMiddlewares, $middlewares);
        
        // Store current prefix for nested groups
        $previousPrefix = self::$currentPrefix ?? '';
        self::$currentPrefix = $previousPrefix . $prefix;
        
        // Execute the group callback
        $callback();
        
        // Restore previous state
        self::$groupMiddlewares = $previousGroupMiddlewares;
        self::$currentPrefix = $previousPrefix;
    }

    private static ?string $currentPrefix = null;

    /**
     * Add a route
     * @param string $method HTTP method
     * @param string $path Route path
     * @param callable|array $handler Route handler
     * @param array $middlewares Middleware names
     * @param bool $isPublic Whether route is public (no auth required)
     */
    private static function addRoute(string $method, string $path, callable|array $handler, array $middlewares, bool $isPublic = false): void
    {
        // Add group prefix if exists
        if (self::$currentPrefix) {
            $path = self::$currentPrefix . $path;
        }

        // Merge group middlewares with route middlewares
        $allMiddlewares = array_merge(self::$groupMiddlewares, $middlewares);

        self::$routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middlewares' => $allMiddlewares,
            'pattern' => self::pathToPattern($path),
            'is_public' => $isPublic
        ];
    }

    /**
     * Convert path to regex pattern
     */
    private static function pathToPattern(string $path): string
    {
        // Convert {param} to named capture groups
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    /**
     * Register global middleware
     * @param string $name Middleware name
     * @param callable|array $middleware Middleware handler
     */
    public static function middleware(string $name, callable|array $middleware): void
    {
        self::$middlewares[$name] = $middleware;
    }

    /**
     * Add a global middleware by name
     */
    public static function addGlobalMiddleware(string $name): void
    {
        if (!in_array($name, self::$globalMiddlewares, true)) {
            self::$globalMiddlewares[] = $name;
        }
    }

    /**
     * Replace global middleware list
     */
    public static function setGlobalMiddlewares(array $names): void
    {
        self::$globalMiddlewares = array_values(array_unique($names));
    }

    /**
     * Dispatch the current request
     */
    public static function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        $headers = getallheaders() ?: [];
        $locale = Translator::detectLocale($headers);
        Translator::setLocale($locale);

        $request = [
            'method' => $method,
            'path' => $path,
            'params' => [],
            'query' => $_GET,
            'body' => null,
            'headers' => $headers,
            'api_version' => null,
            'locale' => $locale,
        ];

        // CORS is handled in global middleware chain - no special handling needed here
        // Global middlewares apply to all routes including OPTIONS preflight
        
        // Resolve API version
        $tempRequest = [
            'path' => $path,
            'headers' => $headers
        ];
        $apiVersion = ApiVersion::resolveVersion($tempRequest);
        
        // Strip version prefix from path for route matching
        $pathWithoutVersion = ApiVersion::stripVersionPrefix($path);
        
        // Add version headers to response
        foreach (ApiVersion::addDeprecationHeaders($apiVersion) as $header => $value) {
            header("{$header}: {$value}");
        }
        
        // Log request
        Logger::info('Incoming request', [
            'method' => $method,
            'path' => $path,
            'api_version' => $apiVersion,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        // Debug: Log route count and stripped path
        Logger::debug('Route matching', [
            'total_routes' => count(self::$routes),
            'path_with_version' => $path,
            'path_without_version' => $pathWithoutVersion,
            'method' => $method
        ]);

        foreach (self::$routes as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $pathWithoutVersion, $matches)) {
                // Extract route parameters
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Execute middlewares
                $body = self::getRequestBody();

                $request = [
                    'method' => $method,
                    'path' => $path,
                    'params' => $params,
                    'query' => $_GET,
                    'body' => $body,
                    'headers' => $headers,
                    'api_version' => $apiVersion,
                    'locale' => $locale,
                    '_is_public_route' => $route['is_public'] ?? false,
                ];

                $next = function($request) use ($route, $params) {
                    return call_user_func_array($route['handler'], [$request, ...array_values($params)]);
                };

                // Execute middleware chain
                $allMiddlewares = array_merge(self::$globalMiddlewares, $route['middlewares']);
                foreach (array_reverse($allMiddlewares) as $middlewareName) {
                    $resolved = self::resolveDynamicMiddleware($middlewareName);
                    if ($resolved !== null) {
                        $prevNext = $next;
                        $next = function($request) use ($resolved, $prevNext) {
                            return $resolved($request, $prevNext);
                        };
                        continue;
                    }

                    if (isset(self::$middlewares[$middlewareName])) {
                        $middleware = self::$middlewares[$middlewareName];
                        $prevNext = $next;
                        $next = function($request) use ($middleware, $prevNext) {
                            return $middleware($request, $prevNext);
                        };
                    }
                }

                // Execute the chain
                $next($request);
                return;
            }
        }

        // No route found
        Response::notFound('error.endpoint_not_found');
    }

    /**
     * Get request body
     */
    private static function getRequestBody(): mixed
    {
        $rawBody = file_get_contents('php://input');
        $normalizedBody = $rawBody;

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);

        // Enforce payload size guard early
        $maxBytes = (int)($_ENV['PAYLOAD_MAX_BYTES'] ?? 1048576); // default 1MB
        if ($contentLength > 0 && $contentLength > $maxBytes) {
            Logger::warning('Payload exceeds maximum size', [
                'content_length' => $contentLength,
                'max_bytes' => $maxBytes,
            ]);
            Response::error('error.payload_too_large', 413, 'ERR_PAYLOAD_TOO_LARGE');
        }

        if ($normalizedBody !== '') {
            // Strip UTF-8 BOM if present
            if (str_starts_with($normalizedBody, "\xEF\xBB\xBF")) {
                $normalizedBody = substr($normalizedBody, 3);
            }
            $normalizedBody = trim($normalizedBody);
        }
        
        if ($normalizedBody === '') {
            return $_POST;
        }

        return self::decodeBody($normalizedBody, $contentType, $contentLength);
    }

    /**
     * Decode request body based on content type. Extracted for testability.
     */
    private static function decodeBody(string $normalizedBody, string $contentType, int $contentLength): mixed
    {
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($normalizedBody, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $preview = substr($normalizedBody, 0, 200);
                $preview = preg_replace('/[^\x20-\x7E]/', '?', $preview);
                Logger::warning('Invalid JSON payload', [
                    'error' => json_last_error_msg(),
                    'preview' => $preview,
                    'length' => strlen($normalizedBody),
                ]);
                Response::badRequest('request.invalid_json');
            }

            return $decoded;
        }

        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            $parsed = [];
            if ($normalizedBody !== '') {
                parse_str($normalizedBody, $parsed);
            } else {
                $parsed = $_POST;
            }
            return $parsed;
        }

        if (str_contains($contentType, 'multipart/form-data')) {
            // PHP already parsed multipart into superglobals
            return [
                'fields' => $_POST,
                'files' => $_FILES,
            ];
        }

        // Default fallback
        return $normalizedBody;
    }

    /**
     * Get all registered routes (for debugging)
     */
    public static function getRoutes(): array
    {
        return self::$routes;
    }

    /**
     * Resolve dynamic middleware definitions like permission:* or scope:*.
     */
    private static function resolveDynamicMiddleware(string $middlewareName): ?callable
    {
        if (str_starts_with($middlewareName, 'permission:')) {
            $permission = substr($middlewareName, strlen('permission:'));
            return AuthorizationMiddleware::requirePermission($permission);
        }

        if (str_starts_with($middlewareName, 'permissionAny:')) {
            $raw = substr($middlewareName, strlen('permissionAny:'));
            $permissions = array_filter(array_map('trim', explode(',', $raw)));
            return AuthorizationMiddleware::requireAnyPermission($permissions);
        }

        if (str_starts_with($middlewareName, 'permissionAll:')) {
            $raw = substr($middlewareName, strlen('permissionAll:'));
            $permissions = array_filter(array_map('trim', explode(',', $raw)));
            return AuthorizationMiddleware::requireAllPermissions($permissions);
        }

        if (str_starts_with($middlewareName, 'scope:')) {
            $scope = substr($middlewareName, strlen('scope:'));
            return AuthorizationMiddleware::requireScope($scope);
        }

        return null;
    }
}
