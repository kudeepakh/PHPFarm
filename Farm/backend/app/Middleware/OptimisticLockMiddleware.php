<?php

namespace App\Middleware;

use PHPFrarm\Core\Database\Attributes\OptimisticLock;
use PHPFrarm\Core\Database\OptimisticLockManager;
use App\Core\Exceptions\OptimisticLockException;
use PHPFrarm\Core\Logger;
use ReflectionMethod;
use Exception;

/**
 * OptimisticLockMiddleware
 * 
 * Reads #[OptimisticLock] attributes from controller methods and:
 * 1. Validates If-Match header if required
 * 2. Wraps execution with retry logic on conflicts
 * 3. Adds ETag header to response
 * 4. Handles 409 Conflict responses
 */
class OptimisticLockMiddleware
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

        // Get OptimisticLock attribute via reflection
        try {
            $reflection = new ReflectionMethod($controller, $method);
            $attributes = $reflection->getAttributes(OptimisticLock::class);

            if (empty($attributes)) {
                // No optimistic lock attribute, proceed normally
                return $next($request);
            }

            $lockAttr = $attributes[0]->newInstance();

            // Build context for condition evaluation
            $context = $this->buildContext($request);

            // Check if locking should be applied
            if (!$lockAttr->shouldApply($context)) {
                Logger::debug('Optimistic locking disabled by condition', [
                    'controller' => $controller,
                    'method' => $method,
                    'condition' => $lockAttr->when
                ]);
                return $next($request);
            }

            // Check If-Match header if required (for PUT/PATCH/DELETE)
            if ($lockAttr->requireIfMatch && $this->isWriteMethod($request->getMethod())) {
                $ifMatch = $request->getHeaderLine('If-Match');
                
                if (empty($ifMatch)) {
                    Logger::warning('If-Match header required but missing', [
                        'method' => $request->getMethod(),
                        'uri' => $request->getUri()->getPath()
                    ]);

                    return $this->createErrorResponse(
                        'If-Match header is required for this operation',
                        428, // Precondition Required
                        [
                            'error_code' => 'IF_MATCH_REQUIRED',
                            'required_header' => 'If-Match'
                        ]
                    );
                }
            }

            // Execute with optimistic lock retry
            $lockManager = OptimisticLockManager::getInstance();

            try {
                $response = $lockManager->executeWithRetry(
                    function() use ($next, $request) {
                        return $next($request);
                    },
                    $lockAttr->maxAttempts,
                    $lockAttr->baseDelayMs
                );

                // Add version header if configured
                if ($lockAttr->returnVersion) {
                    $response = $this->addVersionHeaders($response, $request);
                }

                return $response;

            } catch (OptimisticLockException $e) {
                Logger::error('Optimistic lock conflict unresolved', [
                    'controller' => $controller,
                    'method' => $method,
                    'entity_type' => $e->getEntityType(),
                    'entity_id' => $e->getEntityId(),
                    'expected_version' => $e->getExpectedVersion(),
                    'current_version' => $e->getCurrentVersion()
                ]);

                return $this->createConflictResponse($e);
            }

        } catch (Exception $e) {
            Logger::error('OptimisticLock middleware error', [
                'controller' => $controller ?? 'unknown',
                'method' => $method ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            // On middleware error, proceed without locking
            return $next($request);
        }
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
                'locking' => [
                    'enabled' => config('locking.enabled', true),
                ],
            ],
            'request' => [
                'method' => $request->getMethod(),
                'uri' => $request->getUri()->getPath(),
                'has_if_match' => !empty($request->getHeaderLine('If-Match')),
            ],
        ];
    }

    /**
     * Parse route to extract controller and method
     */
    private function parseRoute($route): array
    {
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
     * Check if HTTP method is a write operation
     */
    private function isWriteMethod(string $method): bool
    {
        return in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE']);
    }

    /**
     * Add version headers to response
     */
    private function addVersionHeaders($response, $request)
    {
        // Extract version from response body if available
        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        if (isset($data['data']['version'])) {
            $version = $data['data']['version'];
            $id = $data['data']['id'] ?? 'unknown';
            
            // Generate ETag
            $etag = sprintf('W/"%s-%d"', $id, $version);
            $response = $response->withHeader('ETag', $etag);
            
            // Add Last-Modified if timestamp available
            if (isset($data['data']['updated_at'])) {
                $response = $response->withHeader(
                    'Last-Modified',
                    gmdate('D, d M Y H:i:s', strtotime($data['data']['updated_at'])) . ' GMT'
                );
            }
        }

        return $response;
    }

    /**
     * Create 409 Conflict response
     */
    private function createConflictResponse(OptimisticLockException $e)
    {
        $response = new \App\Core\Http\Response(409);
        
        $body = [
            'success' => false,
            'error' => [
                'code' => 'OPTIMISTIC_LOCK_CONFLICT',
                'message' => $e->getMessage(),
                'details' => $e->getConflictDetails(),
                'action_required' => 'Please refetch the resource and retry your operation',
            ],
            'timestamp' => time(),
        ];

        $response->getBody()->write(json_encode($body));
        $response = $response->withHeader('Content-Type', 'application/json');
        
        // Add Retry-After header (suggest retry in 1 second)
        $response = $response->withHeader('Retry-After', '1');

        return $response;
    }

    /**
     * Create error response
     */
    private function createErrorResponse(string $message, int $statusCode, array $extraData = [])
    {
        $response = new \App\Core\Http\Response($statusCode);
        
        $body = array_merge([
            'success' => false,
            'error' => [
                'message' => $message,
            ],
            'timestamp' => time(),
        ], $extraData);

        $response->getBody()->write(json_encode($body));
        $response = $response->withHeader('Content-Type', 'application/json');

        return $response;
    }
}
