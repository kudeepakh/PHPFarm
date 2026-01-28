<?php

namespace Farm\Backend\App\Middleware;

use Farm\Backend\App\Core\Request;
use Farm\Backend\App\Core\Response;
use Farm\Backend\App\Core\Traffic\RateLimiter;
use Farm\Backend\App\Core\Traffic\Throttler;
use Farm\Backend\App\Core\Traffic\QuotaManager;
use Farm\Backend\App\Core\Traffic\Attributes\RateLimit;
use PHPFrarm\Core\Logger;
use Redis;

/**
 * Traffic Middleware
 * 
 * Orchestrates traffic management:
 * - Rate limiting (token bucket, sliding window, fixed window)
 * - Request throttling (progressive delay)
 * - Client quotas (daily/monthly limits)
 * 
 * Supports route-level configuration via #[RateLimit] attribute.
 * 
 * @package Farm\Backend\App\Middleware
 */
class TrafficMiddleware implements MiddlewareInterface
{
    private RateLimiter $rateLimiter;
    private Throttler $throttler;
    private QuotaManager $quotaManager;
    private Logger $logger;
    private bool $enabled;
    
    /**
     * Constructor
     * 
     * @param Redis $redis Redis connection
     * @param Logger $logger Logger instance
     * @param array $config Configuration
     */
    public function __construct(Redis $redis, Logger $logger, array $config = [])
    {
        $this->logger = $logger;
        $this->enabled = $config['enabled'] ?? true;
        
        // Initialize traffic management components
        $this->rateLimiter = new RateLimiter($redis, $logger, $config['rate_limit'] ?? []);
        $this->throttler = new Throttler($redis, $logger, $config['throttle'] ?? []);
        $this->quotaManager = new QuotaManager($redis, $logger, $config['quota'] ?? []);
    }
    
    /**
     * Handle request
     * 
     * @param Request $request HTTP request
     * @param callable $next Next middleware
     * @return Response HTTP response
     */
    public function handle(Request $request, callable $next): Response
    {
        if (!$this->enabled) {
            return $next($request);
        }
        
        // Get traffic configuration from route attribute
        $config = $this->getRouteConfig($request);
        
        if (!$config['enabled']) {
            return $next($request);
        }
        
        // Identify client
        $identifier = $this->resolveIdentifier($request, $config);
        
        $headers = [];
        
        // 1. Check quota (if enabled)
        if ($config['quota']) {
            $quotaResult = $this->quotaManager->check($identifier, $config['quota_cost']);
            $headers = array_merge($headers, $this->quotaManager->getHeaders($quotaResult));
            
            if (!$quotaResult['allowed']) {
                return $this->quotaExceededResponse($quotaResult, $config);
            }
        }
        
        // 2. Check rate limit (if configured)
        if ($config['limit'] !== null) {
            $rateLimitResult = $this->rateLimiter->check(
                $identifier,
                $config['limit'],
                $config['window'],
                $config['burst']
            );
            $headers = array_merge($headers, $this->rateLimiter->getHeaders($rateLimitResult));
            
            if (!$rateLimitResult['allowed']) {
                return $this->rateLimitExceededResponse($rateLimitResult, $config);
            }
        }
        
        // 3. Apply throttling (if enabled)
        if ($config['throttle']) {
            $throttleResult = $this->throttler->check(
                $identifier,
                $config['throttle_threshold'],
                $config['window']
            );
            $headers = array_merge($headers, $this->throttler->getHeaders($throttleResult));
            
            // Throttler already applied delay during check()
        }
        
        // Add traffic headers to response
        $response = $next($request);
        
        foreach ($headers as $name => $value) {
            if ($value !== null) {
                $response->header($name, $value);
            }
        }
        
        return $response;
    }
    
    /**
     * Get route-level traffic configuration
     * 
     * @param Request $request HTTP request
     * @return array Configuration
     */
    private function getRouteConfig(Request $request): array
    {
        $default = [
            'enabled' => true,
            'limit' => null,
            'window' => null,
            'burst' => null,
            'algorithm' => null,
            'throttle' => false,
            'throttle_threshold' => null,
            'quota' => true,
            'quota_cost' => 1,
            'identifier' => null,
            'key' => null,
            'message' => null
        ];
        
        // Check for #[RateLimit] attribute on route handler
        $handler = $request->getAttribute('handler');
        if (!$handler) {
            return $default;
        }
        
        try {
            // Get attributes from method
            $reflection = new \ReflectionMethod($handler[0], $handler[1]);
            $attributes = $reflection->getAttributes(RateLimit::class);
            
            if (!empty($attributes)) {
                $rateLimit = $attributes[0]->newInstance();
                return array_merge($default, $rateLimit->getConfig());
            }
            
            // Check class-level attribute
            $classReflection = new \ReflectionClass($handler[0]);
            $classAttributes = $classReflection->getAttributes(RateLimit::class);
            
            if (!empty($classAttributes)) {
                $rateLimit = $classAttributes[0]->newInstance();
                return array_merge($default, $rateLimit->getConfig());
            }
            
        } catch (\Exception $e) {
            $this->logger->warning('Failed to get route traffic config', [
                'error' => $e->getMessage()
            ]);
        }
        
        return $default;
    }
    
    /**
     * Resolve client identifier
     * 
     * @param Request $request HTTP request
     * @param array $config Configuration
     * @return string Client identifier
     */
    private function resolveIdentifier(Request $request, array $config): string
    {
        // Custom identifier resolver
        if ($config['identifier'] !== null) {
            if (is_callable($config['identifier'])) {
                return call_user_func($config['identifier'], $request);
            }
            return $config['identifier'];
        }
        
        // Try authenticated user ID
        $userId = $request->getAttribute('user_id');
        if ($userId) {
            return 'user:' . $userId;
        }
        
        // Try API key
        $apiKey = $request->header('X-API-Key');
        if ($apiKey) {
            return 'api:' . $apiKey;
        }
        
        // Fall back to IP address
        $ip = $request->ip();
        return 'ip:' . $ip;
    }
    
    /**
     * Generate rate limit exceeded response
     * 
     * @param array $result Rate limit result
     * @param array $config Configuration
     * @return Response 429 response
     */
    private function rateLimitExceededResponse(array $result, array $config): Response
    {
        $message = $config['message'] ?? 'rate_limit.exceeded';
        $retryAfter = max(1, $result['reset'] - time());
        
        $this->logger->warning('Rate limit exceeded', [
            'limit' => $result['limit'],
            'reset' => $result['reset']
        ]);

        foreach ($this->rateLimiter->getHeaders($result) as $name => $value) {
            if ($value !== null) {
                header("{$name}: {$value}");
            }
        }
        header('Retry-After: ' . $retryAfter);

        return Response::tooManyRequests($message);
    }
    
    /**
     * Generate quota exceeded response
     * 
     * @param array $result Quota result
     * @param array $config Configuration
     * @return Response 429 response
     */
    private function quotaExceededResponse(array $result, array $config): Response
    {
        $message = $config['message'] ?? 'rate_limit.exceeded';
        $retryAfter = max(1, $result['reset'] - time());
        
        $this->logger->warning('Quota exceeded', [
            'tier' => $result['tier'],
            'limit' => $result['limit'],
            'reset' => $result['reset']
        ]);

        foreach ($this->quotaManager->getHeaders($result) as $name => $value) {
            if ($value !== null) {
                header("{$name}: {$value}");
            }
        }
        header('Retry-After: ' . $retryAfter);

        return Response::tooManyRequests($message);
    }
    
    /**
     * Get rate limiter instance
     * 
     * @return RateLimiter
     */
    public function getRateLimiter(): RateLimiter
    {
        return $this->rateLimiter;
    }
    
    /**
     * Get throttler instance
     * 
     * @return Throttler
     */
    public function getThrottler(): Throttler
    {
        return $this->throttler;
    }
    
    /**
     * Get quota manager instance
     * 
     * @return QuotaManager
     */
    public function getQuotaManager(): QuotaManager
    {
        return $this->quotaManager;
    }
}
