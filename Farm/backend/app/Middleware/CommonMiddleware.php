<?php

namespace PHPFrarm\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPFrarm\Core\Response;
use PHPFrarm\Core\Logger;
use PHPFrarm\Modules\Auth\DAO\SessionDAO;
use PHPFrarm\Modules\Auth\DAO\UserDAO;

/**
 * Built-in Middleware Collection
 */
class CommonMiddleware
{
    private static ?\Redis $redis = null;
    /**
     * Authentication middleware - Verify JWT token
     * Skips authentication for routes marked with #[PublicRoute] attribute
     */
    public static function auth(array $request, callable $next): mixed
    {
        // Check if route is marked as public
        if (isset($request['_is_public_route']) && $request['_is_public_route'] === true) {
            Logger::debug('Public route - skipping authentication', [
                'path' => $request['path'] ?? 'unknown'
            ]);
            return $next($request);
        }
        
        $authHeader = $request['headers']['Authorization'] ?? '';

        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            Response::unauthorized('auth.required');
            return null;
        }

        $token = substr($authHeader, 7);

        try {
            // Verify JWT token (simplified - use firebase/php-jwt in production)
            $decoded = self::verifyJWT($token);

            $decoded['roles'] = self::normalizeClaimList($decoded['roles'] ?? null);
            $decoded['scopes'] = self::normalizeClaimList($decoded['scopes'] ?? null);
            if (!empty($decoded['role']) && !in_array($decoded['role'], $decoded['roles'], true)) {
                $decoded['roles'][] = $decoded['role'];
            }
            
            // Add user info to request
            $request['user'] = $decoded;
            $request['session_id'] = $decoded['sid'] ?? null;
            
            Logger::debug('User authenticated', ['user_id' => $decoded['user_id'] ?? 'unknown']);
            
            return $next($request);

        } catch (\Exception $e) {
            Logger::security('Authentication failed', [
                'error' => $e->getMessage(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            Response::unauthorized('auth.invalid_token');
            return null;
        }
    }

    /**
     * Rate limiting middleware - Simple in-memory implementation
     * 
     * NOTE: For production with multiple servers, use Redis-backed RateLimiter:
     * - Farm\Backend\App\Core\Traffic\RateLimiter class is available
     * - Supports distributed rate limiting across instances
     * - Configure in config/rate_limit.php
     * - Enable via middleware: ['rate_limit'] in routes
     */
    public static function rateLimit(array $request, callable $next): mixed
    {
        $ip = $request['headers']['X-Forwarded-For']
            ?? $request['headers']['X-Real-IP']
            ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        $limit = (int)($_ENV['RATE_LIMIT_REQUESTS'] ?? 100);
        $window = (int)($_ENV['RATE_LIMIT_WINDOW'] ?? 60);

        $result = self::checkRateLimit($ip, $limit, $window);

        header('X-RateLimit-Limit: ' . $result['limit']);
        header('X-RateLimit-Remaining: ' . $result['remaining']);
        header('X-RateLimit-Reset: ' . $result['reset']);

        if (!$result['allowed']) {
            Logger::warning('Rate limit exceeded', [
                'ip' => $ip,
                'limit' => $limit,
                'window' => $window,
            ]);

            Response::tooManyRequests('error.too_many_requests');
            return null;
        }

        return $next($request);
    }

    /**
     * CORS middleware - SINGLE SOURCE OF TRUTH for all CORS headers
     * Handles both preflight (OPTIONS) and actual requests
     * Prevents header duplication by being the ONLY place CORS is set
     */
    public static function cors(array $request, callable $next): mixed
    {
        // Get origin from request
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // Debug logging
        \PHPFrarm\Core\Logger::debug('CORS middleware executing', [
            'origin' => $origin,
            'method' => $request['method'],
            'path' => $request['path']
        ]);

        // Determine allowed origins
        $allowedOrigins = array_filter(array_map('trim', explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? '')));
        if (empty($allowedOrigins)) {
            // Default allowed origins for development
            $allowedOrigins = [
                'http://localhost:3000',
                'http://localhost:3900',
                'http://127.0.0.1:3000',
                'http://127.0.0.1:3900',
            ];
        }

        $isDevelopment = ($_ENV['APP_ENV'] ?? 'production') === 'development';
        $allowCredentials = ($_ENV['CORS_ALLOW_CREDENTIALS'] ?? 'true') === 'true';
        $originAllowed = $origin !== '' && in_array($origin, $allowedOrigins, true);

        // Set Access-Control-Allow-Origin
        if ($originAllowed || ($isDevelopment && $origin !== '')) {
            header('Access-Control-Allow-Origin: ' . $origin);
            if ($allowCredentials) {
                header('Access-Control-Allow-Credentials: true');
            }
            header('Vary: Origin');
        } else {
            header('Access-Control-Allow-Origin: *');
        }

        // Allow all standard HTTP methods
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS, HEAD');

        // Handle Access-Control-Allow-Headers
        // Strategy: Echo back exactly what browser requests to avoid duplication
        $requestedHeaders = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? '';
        
        if ($requestedHeaders !== '') {
            // Browser sent specific headers in preflight - echo them back exactly
            header('Access-Control-Allow-Headers: ' . $requestedHeaders);
            header('Vary: Access-Control-Request-Headers');
        } else {
            // No specific headers requested - provide comprehensive default list
            $defaultHeaders = [
                'Content-Type',
                'Authorization',
                'Accept',
                'Accept-Language',
                'Accept-Encoding',
                'X-Correlation-Id',
                'X-Transaction-Id',
                'X-Request-Id',
                'Origin',
                'DNT',
                'User-Agent',
                'X-Requested-With',
                'If-Modified-Since',
                'Cache-Control',
                'Sec-CH-UA',
                'Sec-CH-UA-Mobile',
                'Sec-CH-UA-Platform',
                'Sec-Fetch-Dest',
                'Sec-Fetch-Mode',
                'Sec-Fetch-Site',
                'Sec-Fetch-User',
                'Referer',
            ];
            header('Access-Control-Allow-Headers: ' . implode(', ', $defaultHeaders));
        }

        // Set preflight cache duration
        // Development: 0 (no cache) - changes take effect immediately
        // Production: 86400 seconds (24 hours) - reduces preflight overhead
        $maxAge = $isDevelopment ? 0 : 86400;
        header('Access-Control-Max-Age: ' . $maxAge);

        // Expose trace IDs to JavaScript
        header('Access-Control-Expose-Headers: X-Correlation-Id, X-Transaction-Id, X-Request-Id');
        
        // Debug: Log headers set
        \PHPFrarm\Core\Logger::debug('CORS headers set', [
            'origin_header' => $origin,
            'allowed' => $originAllowed,
            'is_dev' => $isDevelopment,
            'headers_sent' => headers_sent()
        ]);

        // Handle preflight OPTIONS request
        if ($request['method'] === 'OPTIONS') {
            http_response_code(204); // No Content
            \PHPFrarm\Core\Logger::debug('CORS preflight handled - exiting with 204');
            exit;
        }

        return $next($request);
    }

    /**
     * JSON body parser middleware
     */
    public static function jsonParser(array $request, callable $next): mixed
    {
        if ($request['method'] === 'POST' || $request['method'] === 'PUT') {
            $contentType = $request['headers']['Content-Type'] ?? '';
            
            if (str_contains($contentType, 'application/json')) {
                if (!is_array($request['body'])) {
                    Response::badRequest('request.invalid_json');
                    return null;
                }
            }
        }

        return $next($request);
    }

    /**
     * Validation middleware
     */
    public static function validate(array $rules): callable
    {
        return function(array $request, callable $next) use ($rules) {
            $errors = [];
            $data = $request['body'] ?? [];

            foreach ($rules as $field => $rule) {
                if ($rule['required'] ?? false) {
                    if (!isset($data[$field]) || empty($data[$field])) {
                        $errors[] = "$field is required";
                    }
                }

                if (isset($data[$field]) && isset($rule['type'])) {
                    $value = $data[$field];
                    
                    switch ($rule['type']) {
                        case 'email':
                            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                                $errors[] = "$field must be a valid email";
                            }
                            break;
                        case 'numeric':
                            if (!is_numeric($value)) {
                                $errors[] = "$field must be numeric";
                            }
                            break;
                        case 'string':
                            if (!is_string($value)) {
                                $errors[] = "$field must be a string";
                            }
                            break;
                    }
                }

                if (isset($data[$field]) && isset($rule['min'])) {
                    if (strlen($data[$field]) < $rule['min']) {
                        $errors[] = "$field must be at least {$rule['min']} characters";
                    }
                }

                if (isset($data[$field]) && isset($rule['max'])) {
                    if (strlen($data[$field]) > $rule['max']) {
                        $errors[] = "$field must not exceed {$rule['max']} characters";
                    }
                }
            }

            if (!empty($errors)) {
                Response::badRequest('validation.failed', $errors);
                return null;
            }

            return $next($request);
        };
    }

    /**
     * Admin role middleware
     */
    public static function adminOnly(array $request, callable $next): mixed
    {
        $user = $request['user'] ?? null;

        if (!$user) {
            Response::unauthorized('auth.required');
            return null;
        }

        $authz = new \PHPFrarm\Core\Authorization\AuthorizationManager($user);
        $roles = $user['roles'] ?? [];
        if (is_string($roles)) {
            $roles = [$roles];
        }
        $roleNames = [];
        foreach ($roles as $role) {
            if (is_string($role)) {
                $roleNames[] = $role;
                continue;
            }
            if (is_array($role)) {
                $name = $role['name'] ?? $role['role_name'] ?? null;
                if ($name) {
                    $roleNames[] = $name;
                }
            }
        }

        $isAdminRole = in_array('admin', $roleNames, true) || in_array('superadmin', $roleNames, true);
        $hasAdminPermissions = $authz->canAny(['users:*', 'roles:*', 'permissions:*', 'settings:*']);

        if (!$isAdminRole && !$hasAdminPermissions) {
            Logger::security('Unauthorized admin access attempt', [
                'user_id' => $user['user_id'] ?? 'unknown',
                'path' => $request['path']
            ]);
            
            Response::forbidden('auth.admin_required');
            return null;
        }

        return $next($request);
    }

    /**
     * Logging middleware
     */
    public static function logRequest(array $request, callable $next): mixed
    {
        $startTime = microtime(true);

        Logger::access('API request', [
            'method' => $request['method'],
            'path' => $request['path'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);

        $response = $next($request);

        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        Logger::info('API request completed', [
            'method' => $request['method'],
            'path' => $request['path'],
            'duration_ms' => $duration
        ]);

        return $response;
    }

    // Helper methods

    private static function verifyJWT(string $token): array
    {
        $secret = $_ENV['JWT_SECRET'] ?? '';
        if ($secret === '') {
            throw new \Exception('JWT secret not configured');
        }

        $algorithm = $_ENV['JWT_ALGORITHM'] ?? 'HS256';

        $decoded = (array) JWT::decode($token, new Key($secret, $algorithm));

        if (($decoded['typ'] ?? '') !== 'access') {
            throw new \Exception('Invalid token type');
        }

        $issuer = $_ENV['JWT_ISSUER'] ?? null;
        if ($issuer && (($decoded['iss'] ?? null) !== $issuer)) {
            throw new \Exception('Invalid token issuer');
        }

        $audience = $_ENV['JWT_AUDIENCE'] ?? null;
        if ($audience) {
            $aud = $decoded['aud'] ?? null;
            $audList = is_array($aud) ? $aud : ($aud !== null ? [$aud] : []);
            if (!in_array($audience, $audList, true)) {
                throw new \Exception('Invalid token audience');
            }
        }

        $requireScopes = ($_ENV['JWT_REQUIRE_SCOPES'] ?? 'false') === 'true';
        if ($requireScopes) {
            $scopes = $decoded['scopes'] ?? [];
            if (empty($scopes)) {
                throw new \Exception('Missing scopes');
            }
        }

        // Verify session not revoked
        $tokenHash = hash('sha256', $token);
        $sessionDAO = new SessionDAO();
        $session = $sessionDAO->getActiveSessionByTokenHash($tokenHash);
        if (!$session) {
            throw new \Exception('Token revoked or expired');
        }

        if (!empty($decoded['sid']) && $session['id'] !== $decoded['sid']) {
            throw new \Exception('Invalid session');
        }

        // Verify token version
        if (!empty($decoded['user_id'])) {
            $userDAO = new UserDAO();
            $user = $userDAO->getUserById($decoded['user_id']);
            if (!$user) {
                throw new \Exception('User not found');
            }
            $tokenVersion = (int)($user['token_version'] ?? 0);
            if (isset($decoded['tv']) && (int)$decoded['tv'] !== $tokenVersion) {
                throw new \Exception('Token version mismatch');
            }
        }

        return $decoded;
    }

    public static function checkRateLimit(string $ip, int $limit, int $window): array
    {
        $now = time();
        $redis = self::getRedis();

        if ($redis) {
            $windowId = (int)($now / $window);
            $key = "rate_limit:{$ip}:{$windowId}";

            $count = $redis->incr($key);
            if ($count === 1) {
                $redis->expire($key, $window);
            }

            $remaining = max(0, $limit - $count);
            return [
                'allowed' => $count <= $limit,
                'remaining' => $remaining,
                'reset' => ($windowId + 1) * $window,
                'limit' => $limit,
            ];
        }

        // Fallback to file-based counters
        $cacheKey = "rate_limit_$ip";
        $cacheFile = sys_get_temp_dir() . "/$cacheKey.json";

        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true) ?: [];

            if (($data['reset_at'] ?? 0) > $now) {
                $data['count'] = ($data['count'] ?? 0) + 1;
            } else {
                $data = ['count' => 1, 'reset_at' => $now + $window];
            }
        } else {
            $data = ['count' => 1, 'reset_at' => $now + $window];
        }

        file_put_contents($cacheFile, json_encode($data));

        $remaining = max(0, $limit - $data['count']);

        return [
            'allowed' => $data['count'] <= $limit,
            'remaining' => $remaining,
            'reset' => $data['reset_at'],
            'limit' => $limit,
        ];
    }

    private static function getRedis(): ?\Redis
    {
        if (self::$redis !== null) {
            return self::$redis;
        }

        try {
            if (class_exists('\Redis')) {
                $redis = new \Redis();
                $redis->connect($_ENV['REDIS_HOST'] ?? 'redis', (int)($_ENV['REDIS_PORT'] ?? 6379));
                $redis->ping();
                self::$redis = $redis;
                return self::$redis;
            }
        } catch (\Exception $e) {
            self::$redis = null;
        }

        return null;
    }

    private static function normalizeClaimList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value)));
        }

        if (is_string($value)) {
            return array_values(array_filter(array_map('trim', explode(',', $value))));
        }

        return [];
    }
}
