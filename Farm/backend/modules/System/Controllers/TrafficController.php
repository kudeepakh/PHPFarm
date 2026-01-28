<?php

namespace PHPFrarm\Modules\System\Controllers;

use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Attributes\RouteGroup;
use PHPFrarm\Core\Response;
use PHPFrarm\Modules\System\Services\TrafficManagementService;
use PHPFrarm\Core\Logger;
use Redis;
use Farm\Backend\App\Core\Traffic\QuotaManager;

/**
 * Traffic Controller
 * 
 * Admin APIs for managing traffic control:
 * - Rate limit status, statistics, and reset
 * - Throttle status, statistics, and reset
 * - Quota management (tiers, custom quotas, status)
 * - Client-level traffic management
 * 
 * All endpoints require admin authentication.
 * Business logic delegated to TrafficManagementService.
 */
#[RouteGroup('/api/v1/system/traffic', middleware: ['cors', 'auth'])]
class TrafficController
{
    private TrafficManagementService $trafficService;
    
    /**
     * Constructor
     */
    public function __construct(Redis $redis, Logger $logger, array $config = [])
    {
        $this->trafficService = new TrafficManagementService($redis, $logger, $config);
    }
    
    // ==================== RATE LIMITER ENDPOINTS ====================
    
    /**
     * Get rate limit status for client
     * 
     * GET /api/v1/system/traffic/rate-limit/status/{identifier}
     * 
     * @param Request $request HTTP request
     * @return Response JSON response
     */
    #[Route('/rate-limit/status/{identifier}', method: 'GET')]
    public function getRateLimitStatus(array $request): void
    {
        $identifier = $request['params']['identifier'] ?? null;
        
        if (empty($identifier)) {
            Response::badRequest('traffic.identifier_required');
            return;
        }
        
        $status = $this->trafficService->getRateLimitStatus($identifier);
        
        Response::success($status, 'traffic.rate_limit.status_retrieved');
    }
    
    /**
     * Get rate limit statistics
     * 
     * GET /api/v1/system/traffic/rate-limit/stats
     * Query params: date (Y-m-d), identifier
     * 
     * @param Request $request HTTP request
     * @return Response JSON response
     */
    #[Route('/rate-limit/stats', method: 'GET')]
    public function getRateLimitStats(array $request): void
    {
        $date = $request['query']['date'] ?? null;
        $identifier = $request['query']['identifier'] ?? null;
        
        $stats = $this->trafficService->getRateLimitStats($date, $identifier);
        
        Response::success($stats, 'traffic.rate_limit.stats_retrieved');
    }
    
    /**
     * Reset rate limit for client
     * 
     * POST /api/v1/system/traffic/rate-limit/reset/{identifier}
     * 
     * @param Request $request HTTP request
     * @return Response JSON response
     */
    #[Route('/rate-limit/reset/{identifier}', method: 'POST')]
    public function resetRateLimit(array $request): void
    {
        $identifier = $request['params']['identifier'] ?? null;
        
        if (empty($identifier)) {
            Response::badRequest('traffic.identifier_required');
            return;
        }
        
        $success = $this->trafficService->resetRateLimit($identifier);
        
        if ($success) {
            Response::success(['identifier' => $identifier], 'traffic.rate_limit.reset_success');
            return;
        }

        Response::serverError('traffic.rate_limit.reset_failed');
    }
    
    // ==================== THROTTLER ENDPOINTS ====================
    
    /**
     * Get throttle status for client
     * 
     * GET /api/v1/system/traffic/throttle/status/{identifier}
     * 
     * @param Request $request HTTP request
     * @return Response JSON response
     */
    #[Route('/throttle/status/{identifier}', method: 'GET')]
    public function getThrottleStatus(array $request): void
    {
        $identifier = $request['params']['identifier'] ?? null;
        
        if (empty($identifier)) {
            Response::badRequest('traffic.identifier_required');
            return;
        }
        
        $status = $this->trafficService->getThrottleStatus($identifier);
        
        Response::success($status, 'traffic.throttle.status_retrieved');
    }
    
    /**
     * Get throttle statistics
     * 
     * GET /api/v1/system/traffic/throttle/stats
     * Query params: date (Y-m-d), identifier
     * 
     * @param Request $request HTTP request
     * @return Response JSON response
     */
    #[Route('/throttle/stats', method: 'GET')]
    public function getThrottleStats(array $request): void
    {
        $date = $request['query']['date'] ?? null;
        $identifier = $request['query']['identifier'] ?? null;
        
        $stats = $this->trafficService->getThrottleStats($date, $identifier);
        
        Response::success($stats, 'traffic.throttle.stats_retrieved');
    }
    
    /**
     * Reset throttle for client
     * 
     * POST /api/v1/system/traffic/throttle/reset/{identifier}
     * 
     * @param Request $request HTTP request
     * @return Response JSON response
     */
    #[Route('/throttle/reset/{identifier}', method: 'POST')]
    public function resetThrottle(array $request): void
    {
        $identifier = $request['params']['identifier'] ?? null;
        
        if (empty($identifier)) {
            Response::badRequest('traffic.identifier_required');
            return;
        }
        
        $success = $this->trafficService->resetThrottle($identifier);
        
        if ($success) {
            Response::success(['identifier' => $identifier], 'traffic.throttle.reset_success');
            return;
        }

        Response::serverError('traffic.throttle.reset_failed');
    }
    
    // ==================== QUOTA ENDPOINTS ====================
    
    /**
     * Get quota status for client
     * 
     * GET /api/v1/system/traffic/quota/status/{clientId}
     * 
     * @param Request $request HTTP request
     * @return Response JSON response
     */
    #[Route('/quota/status/{clientId}', method: 'GET')]
    public function getQuotaStatus(array $request): void
    {
        $clientId = $request['params']['clientId'] ?? null;
        
        if (empty($clientId)) {
            Response::badRequest('traffic.client_id_required');
            return;
        }
        
        $status = $this->trafficService->getQuotaStatus($clientId);
        
        Response::success($status, 'traffic.quota.status_retrieved');
    }
    
    /**
     * Get quota statistics
     * 
     * GET /api/v1/system/traffic/quota/stats
     * Query params: date (Y-m-d), clientId
     * 
     * @param Request $request HTTP request
     * @return Response JSON response
     */
    #[Route('/quota/stats', method: 'GET')]
    public function getQuotaStats(array $request): void
    {
        $date = $request['query']['date'] ?? null;
        $clientId = $request['query']['clientId'] ?? null;
        
        $stats = $this->trafficService->getQuotaStats($date, $clientId);
        
        Response::success($stats, 'traffic.quota.stats_retrieved');
    }
    
    /**
     * Get available quota tiers
     * 
     * GET /api/v1/system/traffic/quota/tiers
     * 
     * @param Request $request HTTP request
     * @return Response JSON response
     */
    #[Route('/quota/tiers', method: 'GET')]
    public function getQuotaTiers(array $request): void
    {
        $tiers = $this->trafficService->getQuotaTiers();

        Response::success(['tiers' => $tiers], 'traffic.quota.tiers_retrieved');
    }
    
    /**
     * Set quota tier for client
     * 
     * POST /api/v1/system/traffic/quota/tier
     * Body: { "clientId": "...", "tier": "basic" }
     * 
     * @param Request $request HTTP request
     * @return Response JSON response
     */
    #[Route('/quota/tier', method: 'POST')]
    public function setQuotaTier(array $request): void
    {
        $data = $request['body'] ?? [];
        
        if (empty($data['clientId']) || empty($data['tier'])) {
            Response::badRequest('traffic.client_id_and_tier_required');
            return;
        }
        
        $success = $this->quotaManager->setTier($data['clientId'], $data['tier']);
        
        if ($success) {
            Response::success([
                'clientId' => $data['clientId'],
                'tier' => $data['tier']
            ], 'traffic.quota.tier_set_success');
            return;
        }

        Response::badRequest('traffic.quota.tier_set_failed');
    }
    
    /**
     * Set custom quota for client
     * 
     * POST /api/v1/system/traffic/quota/custom
     * Body: { "clientId": "...", "limit": 50000, "period": "daily" }
     * 
     * @param Request $request HTTP request
     * @return Response JSON response
     */
    #[Route('/quota/custom', method: 'POST')]
    public function setCustomQuota(array $request): void
    {
        $data = $request['body'] ?? [];
        
        if (empty($data['clientId']) || !isset($data['limit'])) {
            Response::badRequest('traffic.client_id_and_limit_required');
            return;
        }
        
        $period = $data['period'] ?? QuotaManager::PERIOD_DAILY;
        
        $success = $this->trafficService->setCustomQuota(
            $data['clientId'],
            (int)$data['limit'],
            $period
        );
        
        if ($success) {
            Response::success([
                'clientId' => $data['clientId'],
                'limit' => (int)$data['limit'],
                'period' => $period
            ], 'traffic.quota.custom_set_success');
            return;
        }

        Response::serverError('traffic.quota.custom_set_failed');
    }
    
    /**
     * Reset quota for client
     * 
     * POST /api/v1/system/traffic/quota/reset/{clientId}
     * 
     * @param Request $request HTTP request
     * @return Response JSON response
     */
    #[Route('/quota/reset/{clientId}', method: 'POST')]
    public function resetQuota(array $request): void
    {
        $clientId = $request['params']['clientId'] ?? null;
        
        if (empty($clientId)) {
            Response::badRequest('traffic.client_id_required');
            return;
        }
        
        $success = $this->trafficService->resetQuota($clientId);
        
        if ($success) {
            Response::success(['clientId' => $clientId], 'traffic.quota.reset_success');
            return;
        }

        Response::serverError('traffic.quota.reset_failed');
    }
    
    // ==================== COMBINED ENDPOINTS ====================
    
    /**
     * Get comprehensive traffic status for client
     * 
     * GET /api/v1/system/traffic/status/{identifier}
     * 
     * @param Request $request HTTP request
     * @return Response JSON response
     */
    #[Route('/status/{identifier}', method: 'GET')]
    public function getTrafficStatus(array $request): void
    {
        $identifier = $request['params']['identifier'] ?? null;
        
        if (empty($identifier)) {
            Response::badRequest('traffic.identifier_required');
            return;
        }
        
        $status = [
            'identifier' => $identifier,
            'rate_limit' => $this->trafficService->getRateLimitStatus($identifier),
            'throttle' => $this->trafficService->getThrottleStatus($identifier),
            'quota' => $this->trafficService->getQuotaStatus($identifier),
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z')
        ];
        
        Response::success($status, 'traffic.status_retrieved');
    }
    
    /**
     * Reset all traffic controls for client
     * 
     * POST /api/v1/system/traffic/reset-all/{identifier}
     * 
     * @param Request $request HTTP request
     * @return Response JSON response
     */
    #[Route('/reset-all/{identifier}', method: 'POST')]
    public function resetAllTraffic(array $request): void
    {
        $identifier = $request['params']['identifier'] ?? null;
        
        if (empty($identifier)) {
            Response::badRequest('traffic.identifier_required');
            return;
        }
        
        $results = [
            'rate_limit' => $this->trafficService->resetRateLimit($identifier),
            'throttle' => $this->trafficService->resetThrottle($identifier),
            'quota' => $this->trafficService->resetQuota($identifier)
        ];
        
        $allSuccess = $results['rate_limit'] && $results['throttle'] && $results['quota'];
        
        if ($allSuccess) {
            Response::success([
                'identifier' => $identifier,
                'reset' => $results
            ], 'traffic.reset_all_success');
            return;
        }

        Response::multiStatus([
            'identifier' => $identifier,
            'reset' => $results
        ], 'traffic.reset_all_partial');
    }
    
    /**
     * Get comprehensive traffic statistics
     * 
     * GET /api/v1/system/traffic/stats/summary
     * Query params: date (Y-m-d)
     * 
     * @param Request $request HTTP request
     * @return Response JSON response
     */
    #[Route('/stats/summary', method: 'GET')]
    public function getTrafficSummary(array $request): void
    {
        $date = $request['query']['date'] ?? null;
        
        $summary = [
            'date' => $date ?? date('Y-m-d'),
            'rate_limit' => $this->trafficService->getRateLimitStats($date),
            'throttle' => $this->trafficService->getThrottleStats($date),
            'quota' => $this->trafficService->getQuotaStats($date)
        ];
        
        Response::success($summary, 'traffic.summary_retrieved');
    }
}
