<?php

namespace App\Middleware;

use App\Core\Cache\CacheManager;
use App\Core\Cache\Attributes\Cache;
use App\Core\Cache\Attributes\NoCache;
use App\Core\Cache\Attributes\CacheInvalidate;
use PHPFrarm\Core\Logger;
use ReflectionMethod;
use ReflectionException;

/**
 * Response Cache Middleware
 * 
 * Automatically caches HTTP responses based on route annotations.
 * Supports conditional caching, ETags, and Cache-Control headers.
 */
class ResponseCacheMiddleware
{
    private CacheManager $cache;
    private array $config;
    private array $context;

    public function __construct()
    {
        $this->cache = CacheManager::getInstance();
        $cacheConfig = require BASE_PATH . '/config/cache.php';
        $this->config = $cacheConfig['response'] ?? [];
        $this->context = $this->buildContext();
    }

    /**
     * Handle the request
     */
    public function handle($request, $next, $routeInfo = null)
    {
        // Check if response caching is enabled
        if (!$this->config['enabled'] || !$this->cache->isEnabled()) {
            return $next($request);
        }

        // Check if path is excluded
        if ($this->isPathExcluded($request->getUri())) {
            return $next($request);
        }

        // Get route handler attributes
        $attributes = $this->getRouteAttributes($routeInfo);

        // Check for NoCache attribute
        $noCacheAttr = $this->findAttribute($attributes, NoCache::class);
        if ($noCacheAttr && $noCacheAttr->shouldApply($this->context)) {
            $response = $next($request);
            return $this->addNoCacheHeaders($response, $noCacheAttr);
        }

        // Check for Cache attribute
        $cacheAttr = $this->findAttribute($attributes, Cache::class);

        // If no Cache attribute and not a cacheable method, skip caching
        if (!$cacheAttr && !$this->isCacheableMethod($request->getMethod())) {
            return $this->handleInvalidation($request, $next, $attributes);
        }

        // If Cache attribute exists, validate caching conditions
        if ($cacheAttr) {
            if (!$cacheAttr->shouldCache($this->context)) {
                return $next($request);
            }

            if (!$cacheAttr->isCacheableMethod($request->getMethod())) {
                return $next($request);
            }
        }

        // Generate cache key
        $cacheKey = $this->generateCacheKey($request, $cacheAttr);

        // Check for cached response
        $cachedResponse = $this->cache->get($cacheKey);
        if ($cachedResponse !== null) {
            return $this->serveCachedResponse($request, $cachedResponse, $cacheAttr);
        }

        // Execute request
        $response = $next($request);

        // Cache the response if status code is cacheable
        if ($this->isCacheableResponse($response, $cacheAttr)) {
            $this->cacheResponse($cacheKey, $response, $cacheAttr);
        }

        // Add cache headers
        return $this->addCacheHeaders($response, $cacheAttr);
    }

    /**
     * Handle cache invalidation for write operations
     */
    private function handleInvalidation($request, $next, array $attributes)
    {
        $invalidateAttrs = $this->findAttributes($attributes, CacheInvalidate::class);
        
        if (empty($invalidateAttrs)) {
            return $next($request);
        }

        // Execute 'before' invalidations
        foreach ($invalidateAttrs as $attr) {
            if ($attr->timing === 'before' && $attr->shouldInvalidate($this->context)) {
                $this->performInvalidation($attr, $request);
            }
        }

        // Execute request
        $response = $next($request);

        // Execute 'after' invalidations
        foreach ($invalidateAttrs as $attr) {
            if ($attr->timing === 'after' && $attr->shouldInvalidate($this->context)) {
                $this->performInvalidation($attr, $request);
            }
        }

        return $response;
    }

    /**
     * Perform cache invalidation
     */
    private function performInvalidation(CacheInvalidate $attr, $request): void
    {
        if ($attr->all) {
            $this->cache->flushAll();
            Logger::info('Cache invalidated: ALL', ['route' => $request->getUri()]);
            return;
        }

        if (!empty($attr->tags)) {
            $tags = $attr->getAllTags();
            $this->cache->flushTags($tags);
            Logger::info('Cache invalidated by tags', ['tags' => $tags, 'route' => $request->getUri()]);
        }

        if ($attr->pattern !== null) {
            $pattern = $this->replacePlaceholders($attr->pattern, $request);
            $this->cache->flushPattern($pattern);
            Logger::info('Cache invalidated by pattern', ['pattern' => $pattern, 'route' => $request->getUri()]);
        }

        if (!empty($attr->keys)) {
            foreach ($attr->keys as $key) {
                $resolvedKey = $this->replacePlaceholders($key, $request);
                $this->cache->delete($resolvedKey);
            }
            Logger::info('Cache invalidated by keys', ['keys' => $attr->keys, 'route' => $request->getUri()]);
        }
    }

    /**
     * Generate cache key for request
     */
    private function generateCacheKey($request, ?Cache $cacheAttr = null): string
    {
        // Use custom key if provided
        if ($cacheAttr && $cacheAttr->key !== null) {
            return $this->replacePlaceholders($cacheAttr->key, $request);
        }

        $parts = [
            'response',
            $request->getMethod(),
            $request->getUri()
        ];

        // Add query parameters if configured
        if (($cacheAttr && $cacheAttr->varyByQuery) || $this->config['vary_by_query']) {
            $queryParams = $request->getQueryParams();
            if (!empty($queryParams)) {
                ksort($queryParams);
                $parts[] = http_build_query($queryParams);
            }
        }

        // Add vary by parameters
        if ($cacheAttr && !empty($cacheAttr->varyBy)) {
            foreach ($cacheAttr->varyBy as $param) {
                $value = $request->getParam($param) ?? $this->context[$param] ?? null;
                if ($value !== null) {
                    $parts[] = "{$param}:{$value}";
                }
            }
        }

        // Add vary by headers
        if ($cacheAttr && $cacheAttr->varyByHeaders && !empty($cacheAttr->headers)) {
            foreach ($cacheAttr->headers as $header) {
                $value = $request->getHeader($header);
                if ($value !== null) {
                    $parts[] = "{$header}:{$value}";
                }
            }
        }

        $key = implode(':', $parts);
        return $cacheAttr && $cacheAttr->prefix ? $cacheAttr->prefix . ':' . $key : $key;
    }

    /**
     * Replace placeholders in key template
     */
    private function replacePlaceholders(string $template, $request): string
    {
        // Replace {param} placeholders
        return preg_replace_callback('/{(\w+)}/', function ($matches) use ($request) {
            $param = $matches[1];
            return $request->getParam($param) ?? $this->context[$param] ?? $matches[0];
        }, $template);
    }

    /**
     * Check if response is cacheable
     */
    private function isCacheableResponse($response, ?Cache $cacheAttr = null): bool
    {
        $statusCode = $response->getStatusCode();

        if ($cacheAttr) {
            return $cacheAttr->isCacheableStatus($statusCode);
        }

        return in_array($statusCode, $this->config['status_codes']);
    }

    /**
     * Cache the response
     */
    private function cacheResponse(string $key, $response, ?Cache $cacheAttr = null): void
    {
        $ttl = $cacheAttr ? $cacheAttr->getEffectiveTtl($this->config['ttl']) : $this->config['ttl'];
        $tags = $cacheAttr ? $cacheAttr->tags : [];

        $data = [
            'status' => $response->getStatusCode(),
            'headers' => $response->getHeaders(),
            'body' => (string) $response->getBody(),
            'cached_at' => time(),
        ];

        // Compress if configured
        if (($cacheAttr && $cacheAttr->compress) || $this->config['compress']) {
            $data['body'] = gzcompress($data['body']);
            $data['compressed'] = true;
        }

        $this->cache->set($key, $data, $ttl, $tags);

        Logger::debug('Response cached', [
            'key' => $key,
            'ttl' => $ttl,
            'tags' => $tags,
            'size' => strlen($data['body'])
        ]);
    }

    /**
     * Serve cached response
     */
    private function serveCachedResponse($request, array $data, ?Cache $cacheAttr = null)
    {
        // Decompress if needed
        if (!empty($data['compressed'])) {
            $data['body'] = gzuncompress($data['body']);
        }

        // Check for conditional request (ETag)
        if (($cacheAttr && $cacheAttr->etag) || $this->config['etag']) {
            $etag = $this->generateETag($data['body']);
            $ifNoneMatch = $request->getHeader('If-None-Match');
            
            if ($ifNoneMatch === $etag) {
                return $this->create304Response($etag);
            }

            $data['headers']['ETag'] = $etag;
        }

        // Check for conditional request (Last-Modified)
        if (($cacheAttr && $cacheAttr->lastModified) || $this->config['last_modified']) {
            $lastModified = gmdate('D, d M Y H:i:s', $data['cached_at']) . ' GMT';
            $ifModifiedSince = $request->getHeader('If-Modified-Since');
            
            if ($ifModifiedSince === $lastModified) {
                return $this->create304Response();
            }

            $data['headers']['Last-Modified'] = $lastModified;
        }

        // Add cache hit header
        $data['headers']['X-Cache'] = 'HIT';
        $data['headers']['X-Cache-Age'] = time() - $data['cached_at'];

        Logger::debug('Serving cached response', ['cached_at' => $data['cached_at']]);

        return $this->createResponse($data['status'], $data['body'], $data['headers']);
    }

    /**
     * Add cache headers to response
     */
    private function addCacheHeaders($response, ?Cache $cacheAttr = null)
    {
        if ($cacheAttr) {
            $cacheControl = $cacheAttr->getCacheControlHeader();
            if ($cacheControl) {
                $response = $response->withHeader('Cache-Control', $cacheControl);
            }

            if ($cacheAttr->etag) {
                $etag = $this->generateETag((string) $response->getBody());
                $response = $response->withHeader('ETag', $etag);
            }

            if ($cacheAttr->lastModified) {
                $response = $response->withHeader('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT');
            }
        }

        $response = $response->withHeader('X-Cache', 'MISS');
        
        return $response;
    }

    /**
     * Add no-cache headers
     */
    private function addNoCacheHeaders($response, NoCache $noCacheAttr)
    {
        $cacheControl = $noCacheAttr->getCacheControlHeader();
        return $response->withHeader('Cache-Control', $cacheControl);
    }

    /**
     * Generate ETag for content
     */
    private function generateETag(string $content): string
    {
        return '"' . md5($content) . '"';
    }

    /**
     * Create 304 Not Modified response
     */
    private function create304Response(?string $etag = null)
    {
        $headers = ['X-Cache' => 'HIT'];
        if ($etag) {
            $headers['ETag'] = $etag;
        }
        return $this->createResponse(304, '', $headers);
    }

    /**
     * Check if method is cacheable
     */
    private function isCacheableMethod(string $method): bool
    {
        return in_array(strtoupper($method), array_map('strtoupper', $this->config['methods']));
    }

    /**
     * Check if path is excluded from caching
     */
    private function isPathExcluded(string $path): bool
    {
        foreach ($this->config['exclude_paths'] ?? [] as $pattern) {
            if (fnmatch($pattern, $path)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get route attributes using reflection
     */
    private function getRouteAttributes($routeInfo): array
    {
        if (!$routeInfo || !isset($routeInfo['handler'])) {
            return [];
        }

        try {
            $handler = $routeInfo['handler'];
            
            if (is_array($handler)) {
                [$class, $method] = $handler;
                $reflection = new ReflectionMethod($class, $method);
            } elseif (is_callable($handler)) {
                $reflection = new \ReflectionFunction($handler);
            } else {
                return [];
            }

            return $reflection->getAttributes();
        } catch (ReflectionException $e) {
            Logger::warning('Failed to get route attributes', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Find specific attribute
     */
    private function findAttribute(array $attributes, string $attributeClass): ?object
    {
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === $attributeClass) {
                return $attribute->newInstance();
            }
        }
        return null;
    }

    /**
     * Find all instances of specific attribute
     */
    private function findAttributes(array $attributes, string $attributeClass): array
    {
        $found = [];
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === $attributeClass) {
                $found[] = $attribute->newInstance();
            }
        }
        return $found;
    }

    /**
     * Build context for condition evaluation
     */
    private function buildContext(): array
    {
        return [
            'user' => $_SESSION['user'] ?? null,
            'auth' => [
                'isAuthenticated' => !empty($_SESSION['user']),
                'isGuest' => empty($_SESSION['user']),
            ],
            'debug' => [
                'enabled' => env('APP_DEBUG', false),
            ],
            'user_id' => $_SESSION['user']['id'] ?? null,
            'locale' => $_SESSION['locale'] ?? 'en',
        ];
    }

    /**
     * Create response object (framework-specific)
     */
    private function createResponse(int $status, string $body, array $headers)
    {
        // This should be adapted to your framework's response object
        $response = new \stdClass();
        $response->status = $status;
        $response->body = $body;
        $response->headers = $headers;
        return $response;
    }
}
