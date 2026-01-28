<?php

namespace App\Middleware;

use App\Core\Resilience\Attributes\Retry;
use App\Core\Resilience\RetryPolicy;
use App\Core\Resilience\ExponentialBackoff;
use App\Core\Resilience\FixedBackoff;
use App\Core\Resilience\LinearBackoff;
use App\Core\Resilience\FibonacciBackoff;
use App\Core\Resilience\IdempotencyKey;
use PHPFrarm\Core\Logger;
use ReflectionMethod;
use Exception;

/**
 * Retry Middleware
 * 
 * Reads #[Retry] attributes from controller methods and
 * wraps execution with intelligent retry logic.
 */
class RetryMiddleware
{
    public function handle($request, $next)
    {
        $route = $request->getAttribute('route');
        
        if (!$route) {
            return $next($request);
        }

        // Get controller and method from route
        [$controller, $method] = $this->parseRoute($route);

        if (!$controller || !$method) {
            return $next($request);
        }

        // Get Retry attribute via reflection
        try {
            $reflection = new ReflectionMethod($controller, $method);
            $attributes = $reflection->getAttributes(Retry::class);

            if (empty($attributes)) {
                // No retry attribute, proceed normally
                return $next($request);
            }

            $retryAttr = $attributes[0]->newInstance();

            // Build context for condition evaluation
            $context = $this->buildContext($request);

            // Check if retry should be applied
            if (!$retryAttr->shouldRetry($context)) {
                Logger::debug('Retry disabled by condition', [
                    'controller' => $controller,
                    'method' => $method,
                    'condition' => $retryAttr->when
                ]);
                return $next($request);
            }

            // Check idempotency
            $idempotencyKey = $retryAttr->getIdempotencyKey($context);
            
            if ($idempotencyKey) {
                $idempotency = IdempotencyKey::getInstance();
                
                // Validate key format
                if (!IdempotencyKey::isValid($idempotencyKey)) {
                    Logger::warning('Invalid idempotency key format', [
                        'key' => $idempotencyKey
                    ]);
                    return $next($request);
                }

                // Check if already processed
                $cachedResponse = $idempotency->check($idempotencyKey);
                
                if ($cachedResponse !== null) {
                    Logger::info('Returning cached response for idempotency key', [
                        'key' => $idempotencyKey,
                        'status_code' => $cachedResponse['status_code']
                    ]);

                    return $this->createResponse(
                        $cachedResponse['response'],
                        $cachedResponse['status_code'],
                        ['X-Idempotency-Replay' => 'true']
                    );
                }
            }

            // Create retry policy from attribute
            $retryPolicy = $this->createRetryPolicy($retryAttr);

            // Execute with retry
            $response = $retryPolicy->execute(function() use ($next, $request) {
                return $next($request);
            }, [
                'operation_name' => "$controller::$method",
                'request_uri' => $request->getUri()->getPath(),
            ]);

            // Store response for idempotency if configured
            if ($idempotencyKey && $response->getStatusCode() < 500) {
                $idempotency = IdempotencyKey::getInstance();
                $idempotency->store(
                    $idempotencyKey,
                    (string) $response->getBody(),
                    $response->getStatusCode()
                );
            }

            return $response;

        } catch (Exception $e) {
            Logger::error('Retry middleware error', [
                'controller' => $controller ?? 'unknown',
                'method' => $method ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            // On middleware error, proceed without retry
            return $next($request);
        }
    }

    /**
     * Create retry policy from attribute
     */
    private function createRetryPolicy(Retry $attr): RetryPolicy
    {
        // Create backoff strategy based on configuration
        $backoff = match($attr->strategy) {
            'fixed' => new FixedBackoff($attr->baseDelayMs),
            'linear' => new LinearBackoff($attr->baseDelayMs, $attr->maxDelayMs),
            'exponential' => new ExponentialBackoff($attr->baseDelayMs, $attr->maxDelayMs, false),
            'exponential_jitter' => new ExponentialBackoff($attr->baseDelayMs, $attr->maxDelayMs, true),
            'fibonacci' => new FibonacciBackoff($attr->baseDelayMs, $attr->maxDelayMs),
            default => new ExponentialBackoff($attr->baseDelayMs, $attr->maxDelayMs, true),
        };

        // Create retry policy
        $policy = new RetryPolicy(
            $attr->maxAttempts,
            $backoff,
            $attr->retryOn,
            $attr->circuitBreaker,
            $attr->timeoutMs
        );

        return $policy;
    }

    /**
     * Build context for condition evaluation
     */
    private function buildContext($request): array
    {
        return [
            'env' => [
                'production' => env('APP_ENV') === 'production',
                'staging' => env('APP_ENV') === 'staging',
                'development' => env('APP_ENV') === 'development',
            ],
            'config' => [
                'retry' => [
                    'enabled' => config('retry.enabled', true),
                ],
            ],
            'request' => [
                'method' => $request->getMethod(),
                'uri' => $request->getUri()->getPath(),
                'idempotency_key' => IdempotencyKey::fromHeaders($request->getHeaders()),
            ],
            'auth' => [
                'authenticated' => $request->getAttribute('user') !== null,
                'isGuest' => $request->getAttribute('user') === null,
            ],
            'user' => $request->getAttribute('user'),
        ];
    }

    /**
     * Parse route to extract controller and method
     */
    private function parseRoute($route): array
    {
        // This depends on your routing implementation
        // Adjust based on your actual route structure
        
        $callable = $route->getCallable();

        if (is_array($callable) && count($callable) === 2) {
            return [$callable[0], $callable[1]];
        }

        if (is_string($callable) && strpos($callable, '::') !== false) {
            return explode('::', $callable);
        }

        return [null, null];
    }

    /**
     * Create HTTP response
     */
    private function createResponse($body, int $statusCode, array $headers = [])
    {
        // This depends on your HTTP library (PSR-7, etc.)
        // Adjust based on your actual implementation
        
        $response = new \App\Core\Http\Response($statusCode);
        $response->getBody()->write($body);

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
