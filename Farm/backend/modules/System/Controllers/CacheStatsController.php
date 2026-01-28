<?php

namespace PHPFrarm\Modules\System\Controllers;

use PHPFrarm\Core\Response;
use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Attributes\RouteGroup;
use PHPFrarm\Modules\System\Services\CacheManagementService;

/**
 * Cache Stats Controller
 * 
 * Provides cache statistics and management endpoints.
 * Business logic delegated to CacheManagementService.
 */
#[RouteGroup('/api/v1/cache', middleware: ['cors', 'auth'])]
class CacheStatsController
{
    private CacheManagementService $cacheService;
    
    public function __construct()
    {
        $this->cacheService = new CacheManagementService();
    }
    
    /**
     * Get cache statistics for dashboard
     */
    #[Route('/stats', method: 'GET', middleware: ['auth', 'permission:cache:read|system:read'])]
    public function stats(array $request): void
    {
        $stats = $this->cacheService->getRedisStats();
        Response::success($stats);
    }

    /**
     * Get cache keys with search and prefix filtering
     */
    #[Route('/keys', method: 'GET', middleware: ['auth', 'permission:cache:read|system:read'])]
    public function keys(array $request): void
    {
        $search = $request['query']['search'] ?? '';
        $prefix = $request['query']['prefix'] ?? '';
        $page = (int)($request['query']['page'] ?? 1);
        $perPage = (int)($request['query']['per_page'] ?? 50);

        $keys = $this->cacheService->getRedisKeys($prefix, $search);
        
        $total = count($keys);
        $offset = ($page - 1) * $perPage;
        $paginatedKeys = array_slice($keys, $offset, $perPage);

        Response::success([
            'keys' => $paginatedKeys,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
        ]);
    }

    /**
     * Delete a specific cache key
     */
    #[Route('/keys/{key}', method: 'DELETE', middleware: ['auth', 'permission:cache:manage'])]
    public function deleteKey(array $request, string $key): void
    {
        $deleted = $this->cacheService->deleteRedisKey($key);
        Response::success(['deleted' => $deleted, 'key' => $key], 'Cache key deleted');
    }

    /**
     * Clear all cache
     */
    #[Route('/clear', method: 'POST', middleware: ['auth', 'permission:cache:manage'])]
    public function clear(array $request): void
    {
        $result = $this->cacheService->clearRedisCache();
        Response::success(['cleared' => $result], 'Cache cleared');
    }

}
