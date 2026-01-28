<?php

namespace PHPFrarm\Modules\System\Controllers;

use PHPFrarm\Core\Database\OptimisticLockManager;
use App\Core\Cache\Attributes\NoCache;
use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Attributes\RouteGroup;
use PHPFrarm\Core\Response;

/**
 * LockingController
 * 
 * Admin APIs for monitoring optimistic lock conflicts and statistics.
 */
#[RouteGroup('/api/v1/system/locking', middleware: ['cors', 'auth'])]
class LockingController
{
    /**
     * Get optimistic lock statistics
     * 
     * GET /api/v1/system/locking/statistics
     */
    #[NoCache]
    #[Route('/statistics', method: 'GET')]
    public function getStatistics(array $request): void
    {
        $lockManager = OptimisticLockManager::getInstance();
        $stats = $lockManager->getStatistics();

        Response::success($stats, 'locking.statistics');
    }

    /**
     * Get top conflicting entities
     * 
     * GET /api/v1/system/locking/conflicts/top
     */
    #[NoCache]
    #[Route('/conflicts/top', method: 'GET')]
    public function getTopConflicts(array $request): void
    {
        $lockManager = OptimisticLockManager::getInstance();
        $stats = $lockManager->getStatistics();

        $topEntities = array_slice($stats['entities'], 0, 10);

        Response::success([
            'top_conflicts' => array_values($topEntities),
            'total_entities' => count($stats['entities']),
        ], 'locking.conflicts.top');
    }

    /**
     * Get conflict details for specific entity
     * 
     * GET /api/v1/system/locking/conflicts/{entityType}/{entityId}
     */
    #[NoCache]
    #[Route('/conflicts/{entityType}/{entityId}', method: 'GET')]
    public function getEntityConflicts(array $request, string $entityType, string $entityId): void
    {
        $lockManager = OptimisticLockManager::getInstance();
        $stats = $lockManager->getStatistics();

        $key = "{$entityType}:{$entityId}";
        $entityStats = $stats['entities'][$key] ?? null;

        if ($entityStats === null) {
            Response::notFound('No conflict data found for this entity');
            return;
        }

        Response::success($entityStats, 'locking.conflicts.entity');
    }

    /**
     * Reset conflict statistics
     * 
     * POST /api/v1/system/locking/statistics/reset
     */
    #[NoCache]
    #[Route('/statistics/reset', method: 'POST')]
    public function resetStatistics(array $request): void
    {
        $lockManager = OptimisticLockManager::getInstance();
        $lockManager->resetStatistics();

        Response::success(['reset' => true], 'locking.statistics.reset');
    }

    /**
     * Get conflict rate over time
     * 
     * GET /api/v1/system/locking/conflicts/rate
     */
    #[NoCache]
    #[Route('/conflicts/rate', method: 'GET')]
    public function getConflictRate(array $request): void
    {
        $lockManager = OptimisticLockManager::getInstance();
        $stats = $lockManager->getStatistics();

        // Calculate rate (would need time-series data in production)
        $rate = [
            'total_conflicts' => $stats['total_conflicts'],
            'retry_successes' => $stats['retry_successes'],
            'retry_exhausted' => $stats['retry_exhausted'],
            'success_rate' => $stats['retry_success_rate'] ?? 0,
            'conflict_resolution_rate' => $stats['total_conflicts'] > 0
                ? round(($stats['retry_successes'] / $stats['total_conflicts']) * 100, 2)
                : 0,
        ];

        Response::success($rate, 'locking.conflicts.rate');
    }

    /**
     * Get locking configuration
     * 
     * GET /api/v1/system/locking/config
     */
    #[NoCache]
    #[Route('/config', method: 'GET')]
    public function getConfiguration(array $request): void
    {
        $config = config('locking', []);

        Response::success($config, 'locking.config');
    }

    /**
     * Get locking health status
     * 
     * GET /api/v1/system/locking/health
     */
    #[NoCache]
    #[Route('/health', method: 'GET')]
    public function getHealthStatus(array $request): void
    {
        $lockManager = OptimisticLockManager::getInstance();
        $stats = $lockManager->getStatistics();

        $health = [
            'status' => 'healthy',
            'optimistic_locking' => [
                'enabled' => config('locking.enabled', true),
                'total_conflicts' => $stats['total_conflicts'],
                'retry_success_rate' => $stats['retry_success_rate'] ?? 0,
            ],
            'alerts' => [],
            'timestamp' => time(),
        ];

        // Check for high conflict rate
        if ($stats['total_conflicts'] > 1000 && $stats['retry_success_rate'] < 50) {
            $health['status'] = 'warning';
            $health['alerts'][] = [
                'level' => 'warning',
                'message' => 'High conflict rate with low retry success',
                'recommendation' => 'Review conflicting entities and consider increasing retry attempts',
            ];
        }

        // Check for retry exhaustion
        if ($stats['retry_exhausted'] > 100) {
            $health['status'] = 'warning';
            $health['alerts'][] = [
                'level' => 'warning',
                'message' => 'High number of retry exhausted conflicts',
                'recommendation' => 'Consider increasing max retry attempts or base delay',
            ];
        }

        Response::success($health, 'locking.health');
    }
}
