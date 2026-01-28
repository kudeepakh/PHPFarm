<?php

namespace PHPFrarm\Modules\System\Controllers;

use PHPFrarm\Core\Response;
use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Attributes\RouteGroup;
use PHPFrarm\Core\Attributes\PublicRoute;
use PHPFrarm\Modules\System\Services\HealthCheckService;

/**
 * Health Check Controller
 * 
 * Provides health and readiness endpoints for monitoring and orchestration.
 * All endpoints are public (no authentication required).
 * Business logic delegated to HealthCheckService.
 */
#[RouteGroup('/health', middleware: ['cors'])]
class HealthController
{
    private HealthCheckService $healthService;
    
    public function __construct()
    {
        $this->healthService = new HealthCheckService();
    }
    
    /**
     * Liveness probe - checks if application is running
     * Returns 200 if application can serve traffic
     * 
     * @route GET /health
     */
    #[PublicRoute(reason: 'Kubernetes liveness probe')]
    #[Route('', method: 'GET', middleware: [], description: 'Liveness probe')]
    public function index(array $request): void
    {
        Response::success([
            'status' => 'ok',
            'service' => 'PHPFrarm API',
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z')
        ], 'health.ok', 200);
    }
    
    /**
     * Readiness probe - checks if application is ready to serve traffic
     * Checks all dependencies: MySQL, MongoDB, Redis
     * 
     * @route GET /health/ready
     */
    #[PublicRoute(reason: 'Kubernetes readiness probe')]
    #[Route('/ready', method: 'GET', middleware: [], description: 'Readiness probe')]
    public function ready(array $request): void
    {
        $checks = [
            'mysql' => $this->healthService->checkMySQL(),
            'mongodb' => $this->healthService->checkMongoDB(),
            'redis' => $this->healthService->checkRedis(),
        ];
        
        $allHealthy = !in_array(false, $checks, true);
        $statusCode = $allHealthy ? 200 : 503;
        
        Response::success([
            'status' => $allHealthy ? 'ready' : 'not_ready',
            'checks' => $checks,
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z')
        ], $allHealthy ? 'health.ready' : 'health.not_ready', $statusCode);
    }
    
    /**
     * Detailed health check with dependency status
     * 
     * @route GET /health/detailed
     */
    #[PublicRoute(reason: 'Monitoring system health check')]
    #[Route('/detailed', method: 'GET', middleware: [], description: 'Detailed health check')]
    public function detailed(array $request): void
    {
        $startTime = microtime(true);
        
        $checks = [
            'mysql' => $this->healthService->checkMySQLDetailed(),
            'mongodb' => $this->healthService->checkMongoDBDetailed(),
            'redis' => $this->healthService->checkRedisDetailed(),
            'disk' => $this->healthService->checkDiskSpace(),
            'memory' => $this->healthService->checkMemory(),
        ];
        
        $allHealthy = true;
        foreach ($checks as $check) {
            if (isset($check['status']) && $check['status'] !== 'healthy') {
                $allHealthy = false;
                break;
            }
        }
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        Response::success([
            'status' => $allHealthy ? 'healthy' : 'degraded',
            'checks' => $checks,
            'response_time_ms' => $duration,
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z')
        ], $allHealthy ? 'health.healthy' : 'health.degraded', $allHealthy ? 200 : 503);
    }
}
