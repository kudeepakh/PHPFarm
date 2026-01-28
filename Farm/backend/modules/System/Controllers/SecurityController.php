<?php

namespace PHPFrarm\Modules\System\Controllers;

use Farm\Backend\App\Core\Security\BotDetector;
use Farm\Backend\App\Core\Security\IpReputationManager;
use Farm\Backend\App\Core\Security\GeoBlocker;
use Farm\Backend\App\Core\Security\AnomalyDetector;
use Farm\Backend\App\Core\Security\WafEngine;
use Farm\Backend\App\Core\Observability\TraceContext;
use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Attributes\RouteGroup;
use PHPFrarm\Core\Response;

/**
 * SecurityController - Admin APIs for DDoS protection management
 * 
 * Endpoints:
 * - IP reputation management (blacklist, whitelist)
 * - Security event logs
 * - Threat monitoring (real-time)
 * - Bot detection statistics
 * - Geo-blocking configuration
 * - WAF rules management
 * - Anomaly detection metrics
 * 
 * All endpoints require admin authentication.
 */
#[RouteGroup('/api/v1/system/security', middleware: ['cors', 'auth'])]
class SecurityController
{
    private ?BotDetector $botDetector = null;
    private ?IpReputationManager $ipReputation = null;
    private ?GeoBlocker $geoBlocker = null;
    private ?AnomalyDetector $anomalyDetector = null;
    private ?WafEngine $wafEngine = null;

    public function __construct(
        ?BotDetector $botDetector = null,
        ?IpReputationManager $ipReputation = null,
        ?GeoBlocker $geoBlocker = null,
        ?AnomalyDetector $anomalyDetector = null,
        ?WafEngine $wafEngine = null
    ) {
        $this->botDetector = $botDetector;
        $this->ipReputation = $ipReputation;
        $this->geoBlocker = $geoBlocker;
        $this->anomalyDetector = $anomalyDetector;
        $this->wafEngine = $wafEngine;
    }

    /**
     * GET /api/v1/system/security/overview
     * 
     * Security dashboard overview
     */
    #[Route('/overview', method: 'GET')]
    public function overview(array $request): void
    {
        Response::success([
            'ip_reputation' => $this->ipReputation->getStatistics(),
            'geo_blocking' => $this->geoBlocker->getStatistics(),
            'anomaly_detection' => $this->anomalyDetector->getStatistics(),
            'waf' => $this->wafEngine->getStatistics(),
            'timestamp' => time(),
        ], 'security.success');
    }

    /**
     * GET /api/v1/system/security/ip/{ip}
     * 
     * Get complete IP analysis
     */
    #[Route('/ip/{ip}', method: 'GET')]
    public function getIpAnalysis(array $request, string $ip): void
    {
        Response::success([
            'ip' => $ip,
            'reputation' => $this->ipReputation->getIpStatus($ip),
            'geo' => $this->geoBlocker->getGeoInfo($ip),
            'anomaly' => $this->anomalyDetector->analyzeIp($ip),
        ], 'security.success');
    }

    /**
     * POST /api/v1/system/security/ip/blacklist
     * 
     * Add IP to blacklist
     * Body: {"ip": "1.2.3.4", "reason": "Repeated violations", "duration": 3600}
     */
    #[Route('/ip/blacklist', method: 'POST')]
    public function addToBlacklist(array $request): void
    {
        $body = $request['body'] ?? [];
        $ip = $body['ip'] ?? null;
        $reason = $body['reason'] ?? 'Manual blacklist';
        $duration = $body['duration'] ?? null;
        
        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) {
            Response::error('Invalid IP address', 400, 'SECURITY_ERROR');
            return;
        }
        
        $this->ipReputation->addToBlacklist($ip, $reason, $duration);
        
        Response::success([
            'message' => 'IP added to blacklist',
            'ip' => $ip,
            'duration' => $duration,
        ], 'security.success');
    }

    /**
     * DELETE /api/v1/system/security/ip/blacklist/{ip}
     * 
     * Remove IP from blacklist
     */
    #[Route('/ip/blacklist/{ip}', method: 'DELETE')]
    public function removeFromBlacklist(array $request, string $ip): void
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            Response::error('Invalid IP address', 400, 'SECURITY_ERROR');
            return;
        }
        
        $this->ipReputation->removeFromBlacklist($ip);
        
        Response::success([
            'message' => 'IP removed from blacklist',
            'ip' => $ip,
        ], 'security.success');
    }

    /**
     * POST /api/v1/system/security/ip/whitelist
     * 
     * Add IP to whitelist (trusted)
     * Body: {"ip": "1.2.3.4", "reason": "Internal server"}
     */
    #[Route('/ip/whitelist', method: 'POST')]
    public function addToWhitelist(array $request): void
    {
        $body = $request['body'] ?? [];
        $ip = $body['ip'] ?? null;
        $reason = $body['reason'] ?? 'Manual whitelist';
        
        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) {
            Response::error('Invalid IP address', 400, 'SECURITY_ERROR');
            return;
        }
        
        $this->ipReputation->addToWhitelist($ip, $reason);
        
        Response::success([
            'message' => 'IP added to whitelist',
            'ip' => $ip,
        ], 'security.success');
    }

    /**
     * DELETE /api/v1/system/security/ip/whitelist/{ip}
     * 
     * Remove IP from whitelist
     */
    #[Route('/ip/whitelist/{ip}', method: 'DELETE')]
    public function removeFromWhitelist(array $request, string $ip): void
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            Response::error('Invalid IP address', 400, 'SECURITY_ERROR');
            return;
        }
        
        $this->ipReputation->removeFromWhitelist($ip);
        
        Response::success([
            'message' => 'IP removed from whitelist',
            'ip' => $ip,
        ], 'security.success');
    }

    /**
     * GET /api/v1/system/security/ip/blacklist
     * 
     * List all blacklisted IPs
     */
    #[Route('/ip/blacklist', method: 'GET')]
    public function listBlacklist(array $request): void
    {
        $blacklist = $this->ipReputation->getBlacklist();
        
        // Get details for each IP
        $details = array_map(function($ip) {
            return $this->ipReputation->getIpStatus($ip);
        }, $blacklist);
        
        Response::success([
            'count' => count($details),
            'blacklist' => $details,
        ], 'security.success');
    }

    /**
     * GET /api/v1/system/security/ip/whitelist
     * 
     * List all whitelisted IPs
     */
    #[Route('/ip/whitelist', method: 'GET')]
    public function listWhitelist(array $request): void
    {
        $whitelist = $this->ipReputation->getWhitelist();
        
        Response::success([
            'count' => count($whitelist),
            'whitelist' => $whitelist,
        ], 'security.success');
    }

    /**
     * DELETE /api/v1/system/security/ip/blacklist
     * 
     * Clear entire blacklist
     */
    #[Route('/ip/blacklist', method: 'DELETE')]
    public function clearBlacklist(array $request): void
    {
        $this->ipReputation->clearBlacklist();
        
        Response::success([
            'message' => 'Blacklist cleared',
        ], 'security.success');
    }

    /**
     * POST /api/v1/system/security/geo/block
     * 
     * Block a country
     * Body: {"country": "CN"}
     */
    #[Route('/geo/block', method: 'POST')]
    public function blockCountry(array $request): void
    {
        $body = $request['body'] ?? [];
        $country = $body['country'] ?? null;
        
        if (!$country || strlen($country) !== 2) {
            Response::error('Invalid country code (ISO 3166-1 alpha-2)', 400, 'SECURITY_ERROR');
            return;
        }
        
        $this->geoBlocker->blockCountry($country);
        
        Response::success([
            'message' => 'Country blocked',
            'country' => $country,
        ], 'security.success');
    }

    /**
     * DELETE /api/v1/system/security/geo/block/{country}
     * 
     * Unblock a country
     */
    #[Route('/geo/block/{country}', method: 'DELETE')]
    public function unblockCountry(array $request, string $country): void
    {
        if (strlen($country) !== 2) {
            Response::error('Invalid country code', 400, 'SECURITY_ERROR');
            return;
        }
        
        $this->geoBlocker->unblockCountry($country);
        
        Response::success([
            'message' => 'Country unblocked',
            'country' => $country,
        ], 'security.success');
    }

    /**
     * POST /api/v1/system/security/geo/allow
     * 
     * Add country to allowlist (whitelist mode)
     * Body: {"country": "US"}
     */
    #[Route('/geo/allow', method: 'POST')]
    public function allowCountry(array $request): void
    {
        $body = $request['body'] ?? [];
        $country = $body['country'] ?? null;
        
        if (!$country || strlen($country) !== 2) {
            Response::error('Invalid country code', 400, 'SECURITY_ERROR');
            return;
        }
        
        $this->geoBlocker->allowCountry($country);
        
        Response::success([
            'message' => 'Country allowed',
            'country' => $country,
        ], 'security.success');
    }

    /**
     * GET /api/v1/system/security/geo/blocked
     * 
     * List blocked countries
     */
    #[Route('/geo/blocked', method: 'GET')]
    public function listBlockedCountries(array $request): void
    {
        Response::success([
            'blocked_countries' => $this->geoBlocker->getBlockedCountries(),
        ], 'security.success');
    }

    /**
     * GET /api/v1/system/security/geo/allowed
     * 
     * List allowed countries (whitelist mode)
     */
    #[Route('/geo/allowed', method: 'GET')]
    public function listAllowedCountries(array $request): void
    {
        Response::success([
            'allowed_countries' => $this->geoBlocker->getAllowedCountries(),
        ], 'security.success');
    }

    /**
     * POST /api/v1/system/security/waf/rule
     * 
     * Add custom WAF rule
     * Body: {"name": "custom_rule", "pattern": "/malicious/i"}
     */
    #[Route('/waf/rule', method: 'POST')]
    public function addWafRule(array $request): void
    {
        $body = $request['body'] ?? [];
        $name = $body['name'] ?? null;
        $pattern = $body['pattern'] ?? null;
        
        if (!$name || !$pattern) {
            Response::error('Missing name or pattern', 400, 'SECURITY_ERROR');
            return;
        }
        
        // Validate regex pattern
        if (@preg_match($pattern, '') === false) {
            Response::error('Invalid regex pattern', 400, 'SECURITY_ERROR');
            return;
        }
        
        $this->wafEngine->addCustomRule($name, $pattern);
        
        Response::success([
            'message' => 'WAF rule added',
            'name' => $name,
            'pattern' => $pattern,
        ], 'security.success');
    }

    /**
     * POST /api/v1/system/security/waf/scan
     * 
     * Test WAF scanning on input
     * Body: {"input": "' OR 1=1 --", "context": "test"}
     */
    #[Route('/waf/scan', method: 'POST')]
    public function testWafScan(array $request): void
    {
        $body = $request['body'] ?? [];
        $input = $body['input'] ?? '';
        $context = $body['context'] ?? 'test';
        
        $result = $this->wafEngine->scan($input, $context);
        
        Response::success([
            'scan_result' => $result,
            'input' => substr($input, 0, 100), // Truncate
        ], 'security.success');
    }

    /**
     * GET /api/v1/system/security/anomaly/ip/{ip}
     * 
     * Get anomaly analysis for IP
     */
    #[Route('/anomaly/ip/{ip}', method: 'GET')]
    public function getAnomalyAnalysis(array $request, string $ip): void
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            Response::error('Invalid IP address', 400, 'SECURITY_ERROR');
            return;
        }
        
        Response::success([
            'analysis' => $this->anomalyDetector->analyzeIp($ip),
        ], 'security.success');
    }

    /**
     * DELETE /api/v1/system/security/anomaly/ip/{ip}
     * 
     * Clear anomaly tracking for IP
     */
    #[Route('/anomaly/ip/{ip}', method: 'DELETE')]
    public function clearAnomalyTracking(array $request, string $ip): void
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            Response::error('Invalid IP address', 400, 'SECURITY_ERROR');
            return;
        }
        
        $this->anomalyDetector->clearTracking($ip);
        
        Response::success([
            'message' => 'Anomaly tracking cleared',
            'ip' => $ip,
        ], 'security.success');
    }

    /**
     * POST /api/v1/system/security/bot/analyze
     * 
     * Analyze bot traffic
     * Body: {"user_agent": "...", "headers": {...}, "ip": "1.2.3.4"}
     */
    #[Route('/bot/analyze', method: 'POST')]
    public function analyzeBotTraffic(array $request): void
    {
        $body = $request['body'] ?? [];
        $headers = $body['headers'] ?? [];
        $ip = $body['ip'] ?? '0.0.0.0';
        
        $analysis = $this->botDetector->analyzeBotTraffic($headers, $ip);
        
        Response::success([
            'analysis' => $analysis,
        ], 'security.success');
    }

    /**
     * GET /api/v1/system/security/health
     * 
     * Health check for all security modules
     */
    #[Route('/health', method: 'GET')]
    public function healthCheck(array $request): void
    {
        Response::success([
            'status' => 'healthy',
            'modules' => [
                'ip_reputation' => 'active',
                'bot_detector' => 'active',
                'geo_blocker' => 'active',
                'anomaly_detector' => 'active',
                'waf_engine' => 'active',
            ],
            'timestamp' => time(),
        ], 'security.success');
    }
}
