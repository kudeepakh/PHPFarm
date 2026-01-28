<?php

namespace PHPFrarm\Modules\System\Controllers;

use PHPFrarm\Core\Response;
use PHPFrarm\Core\Logger;
use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Attributes\RouteGroup;
use PHPFrarm\Modules\System\Services\SystemMetricsService;

/**
 * System Dashboard Controller
 * 
 * Provides system metrics and statistics for the dashboard.
 * Delegates business logic to SystemMetricsService.
 */
#[RouteGroup('/api/v1/system', middleware: ['cors', 'auth'])]
class SystemController
{
    private SystemMetricsService $metricsService;

    public function __construct()
    {
        $this->metricsService = new SystemMetricsService();
    }
    /**
     * Get system health metrics
     * 
     * @route GET /api/v1/system/health
     */
    #[Route('/health', method: 'GET', middleware: ['permission:system:read'], description: 'Get system health metrics')]
    public function health(array $request): void
    {
        try {
            $health = $this->metricsService->getHealthMetrics();
            Response::success($health);
        } catch (\Exception $e) {
            Logger::error('System health check failed', [
                'error' => $e->getMessage()
            ]);
            
            Response::serverError('system.health.error', 500);
        }
    }
    
    /**
     * Get system statistics
     * 
     * @route GET /api/v1/system/stats
     */
    #[Route('/stats', method: 'GET', middleware: ['permission:system:read'], description: 'Get system statistics')]
    public function stats(array $request): void
    {
        try {
            $stats = $this->metricsService->getSystemStats();
            Response::success($stats);
        } catch (\Exception $e) {
            Logger::error('System stats check failed', [
                'error' => $e->getMessage()
            ]);
            
            Response::serverError('system.stats.error', 500);
        }
    }
}
