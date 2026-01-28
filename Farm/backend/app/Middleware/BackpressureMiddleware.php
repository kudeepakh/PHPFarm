<?php

namespace Farm\Backend\App\Middleware;

use Farm\Backend\App\Core\Resilience\BackpressureHandler;
use Farm\Backend\App\Core\Http\Request;
use Farm\Backend\App\Core\Http\Response;

/**
 * Backpressure Middleware
 * 
 * Prevents system overload by limiting concurrent requests.
 * Rejects excess requests with 503 Service Unavailable.
 * 
 * Features:
 * - Per-endpoint concurrency limits
 * - Global concurrency limits
 * - Graceful rejection with Retry-After header
 * - Real-time load monitoring
 * 
 * Usage in routes:
 * ```php
 * #[Route('/api/heavy-operation', methods: ['POST'])]
 * #[Middleware(BackpressureMiddleware::class, ['resource' => 'api', 'limit' => 100])]
 * public function heavyOperation() { }
 * ```
 */
class BackpressureMiddleware
{
    private BackpressureHandler $handler;
    private string $resource = 'global';
    private int $timeout = 0;
    private float $overloadThreshold = 90.0;
    
    public function __construct(array $options = [])
    {
        $config = require __DIR__ . '/../../config/resilience.php';
        
        $this->handler = new BackpressureHandler($config['backpressure']['limits'] ?? []);
        $this->resource = $options['resource'] ?? 'global';
        $this->timeout = $options['timeout'] ?? 0;
        $this->overloadThreshold = $options['overload_threshold'] ?? 90.0;
    }
    
    /**
     * Handle request with backpressure control
     * 
     * @param Request $request
     * @param callable $next
     * @return Response
     */
    public function handle(Request $request, callable $next): Response
    {
        // Try to acquire permit
        if (!$this->handler->acquire($this->resource, $this->timeout)) {
            return $this->rejectRequest();
        }
        
        try {
            // Process request
            $response = $next($request);
            
            // Add load headers
            $this->addLoadHeaders($response);
            
            return $response;
            
        } finally {
            // Always release permit
            $this->handler->release($this->resource);
        }
    }
    
    /**
     * Reject request due to overload
     * 
     * @return Response
     */
    private function rejectRequest(): Response
    {
        $usage = $this->handler->getUsage($this->resource);
        
        return Response::json([
            'success' => false,
            'error' => [
                'code' => 'SERVICE_OVERLOAD',
                'message' => 'Service temporarily overloaded. Please retry later.',
                'details' => [
                    'resource' => $this->resource,
                    'current_load' => $usage['utilization'] . '%',
                    'retry_after' => 5
                ]
            ],
            'trace' => [
                'correlation_id' => $GLOBALS['correlation_id'] ?? null,
                'transaction_id' => $GLOBALS['transaction_id'] ?? null,
                'request_id' => $GLOBALS['request_id'] ?? null
            ]
        ], 503, [
            'Retry-After' => '5',
            'X-RateLimit-Resource' => $this->resource,
            'X-RateLimit-Limit' => (string)$usage['limit'],
            'X-RateLimit-Current' => (string)$usage['current']
        ]);
    }
    
    /**
     * Add load monitoring headers to response
     * 
     * @param Response $response
     * @return void
     */
    private function addLoadHeaders(Response $response): void
    {
        $usage = $this->handler->getUsage($this->resource);
        $systemLoad = $this->handler->getSystemLoad();
        
        $response->addHeader('X-Backpressure-Resource', $this->resource);
        $response->addHeader('X-Backpressure-Limit', (string)$usage['limit']);
        $response->addHeader('X-Backpressure-Current', (string)$usage['current']);
        $response->addHeader('X-Backpressure-Available', (string)$usage['available']);
        $response->addHeader('X-System-Load', (string)$systemLoad . '%');
        
        // Warn if approaching limit
        if ($usage['utilization'] >= $this->overloadThreshold) {
            $response->addHeader('X-Backpressure-Warning', 'System approaching capacity');
        }
    }
}
