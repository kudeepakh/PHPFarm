<?php

namespace Farm\Backend\App\Middleware;

use Farm\Backend\App\Core\Security\BotDetector;
use Farm\Backend\App\Core\Security\IpReputationManager;
use Farm\Backend\App\Core\Security\GeoBlocker;
use Farm\Backend\App\Core\Security\AnomalyDetector;
use Farm\Backend\App\Core\Security\WafEngine;
use Farm\Backend\App\Core\Security\Attributes\BotProtection;
use Farm\Backend\App\Core\Logging\LogManager;
use Farm\Backend\App\Core\Observability\TraceContext;
use ReflectionClass;
use ReflectionMethod;

/**
 * DDoSProtectionMiddleware - Multi-layer security orchestrator
 * 
 * Coordinates all protection mechanisms:
 * 1. IP Reputation (blacklist check)
 * 2. Bot Detection (User-Agent analysis)
 * 3. Geo-Blocking (country restrictions)
 * 4. WAF (attack signature detection)
 * 5. Anomaly Detection (velocity/pattern analysis)
 * 
 * Processes #[BotProtection] attributes on routes for custom rules.
 * Blocks malicious requests with 403 Forbidden response.
 */
class DDoSProtectionMiddleware
{
    private BotDetector $botDetector;
    private IpReputationManager $ipReputation;
    private GeoBlocker $geoBlocker;
    private AnomalyDetector $anomalyDetector;
    private WafEngine $wafEngine;
    private LogManager $logger;
    private array $config;

    public function __construct(
        BotDetector $botDetector,
        IpReputationManager $ipReputation,
        GeoBlocker $geoBlocker,
        AnomalyDetector $anomalyDetector,
        WafEngine $wafEngine,
        LogManager $logger,
        array $config = []
    ) {
        $this->botDetector = $botDetector;
        $this->ipReputation = $ipReputation;
        $this->geoBlocker = $geoBlocker;
        $this->anomalyDetector = $anomalyDetector;
        $this->wafEngine = $wafEngine;
        $this->logger = $logger;
        $this->config = array_merge([
            'enabled' => true,
            'check_ip_reputation' => true,
            'check_bots' => true,
            'check_geo' => true,
            'check_waf' => true,
            'check_anomalies' => true,
            'whitelist_ips' => [],          // Never block these IPs
            'log_blocked_requests' => true,
        ], $config);
    }

    /**
     * Process request through all protection layers
     * 
     * @param array $request Request data
     * @param callable $next Next middleware/handler
     * @return mixed Response
     */
    public function handle(array $request, callable $next)
    {
        if (!$this->config['enabled']) {
            return $next($request);
        }
        
        $ip = $request['ip'] ?? '0.0.0.0';
        $headers = $request['headers'] ?? [];
        $method = $request['method'] ?? 'GET';
        $path = $request['path'] ?? '/';
        $query = $request['query'] ?? [];
        $body = $request['body'] ?? [];
        $controller = $request['controller'] ?? null;
        $action = $request['action'] ?? null;
        
        // Whitelist check (skip all protections)
        if (in_array($ip, $this->config['whitelist_ips'], true)) {
            return $next($request);
        }
        
        // Get route-level configuration from #[BotProtection] attribute
        $routeConfig = $this->getRouteConfiguration($controller, $action);
        
        // 1. IP Reputation Check
        if ($this->shouldCheckIpReputation($routeConfig)) {
            $blockReason = $this->checkIpReputation($ip);
            if ($blockReason !== null) {
                return $this->blockRequest($ip, $blockReason, $path);
            }
        }
        
        // 2. Bot Detection
        if ($this->shouldCheckBots($routeConfig)) {
            $blockReason = $this->checkBot($ip, $headers, $routeConfig);
            if ($blockReason !== null) {
                return $this->blockRequest($ip, $blockReason, $path);
            }
        }
        
        // 3. Geo-Blocking
        if ($this->shouldCheckGeo($routeConfig)) {
            $blockReason = $this->checkGeoLocation($ip, $routeConfig);
            if ($blockReason !== null) {
                return $this->blockRequest($ip, $blockReason, $path);
            }
        }
        
        // 4. WAF Scanning
        if ($this->shouldCheckWaf($routeConfig)) {
            $blockReason = $this->checkWaf($query, $body, $headers);
            if ($blockReason !== null) {
                return $this->blockRequest($ip, $blockReason, $path);
            }
        }
        
        // 5. Anomaly Detection
        if ($this->shouldCheckAnomalies($routeConfig)) {
            $blockReason = $this->checkAnomalies($ip, $path, $method, $routeConfig);
            if ($blockReason !== null) {
                return $this->blockRequest($ip, $blockReason, $path);
            }
        }
        
        // All checks passed - record clean request
        $this->ipReputation->recordCleanRequest($ip);
        
        return $next($request);
    }

    /**
     * Get route-level protection configuration
     * 
     * @param string|null $controller Controller class name
     * @param string|null $action Action method name
     * @return BotProtection|null
     */
    private function getRouteConfiguration(?string $controller, ?string $action): ?BotProtection
    {
        if ($controller === null || $action === null) {
            return null;
        }
        
        try {
            $reflection = new ReflectionMethod($controller, $action);
            $attributes = $reflection->getAttributes(BotProtection::class);
            
            if (!empty($attributes)) {
                return $attributes[0]->newInstance();
            }
            
            // Check class-level attribute
            $classReflection = new ReflectionClass($controller);
            $classAttributes = $classReflection->getAttributes(BotProtection::class);
            
            if (!empty($classAttributes)) {
                return $classAttributes[0]->newInstance();
            }
        } catch (\ReflectionException $e) {
            // Attribute not found or invalid
        }
        
        return null;
    }

    /**
     * Check IP reputation
     * 
     * @param string $ip
     * @return string|null Block reason or null if allowed
     */
    private function checkIpReputation(string $ip): ?string
    {
        if ($this->ipReputation->isBlocked($ip)) {
            return 'ip_blacklisted';
        }
        
        return null;
    }

    /**
     * Check bot detection
     * 
     * @param string $ip
     * @param array $headers
     * @param BotProtection|null $config
     * @return string|null Block reason or null if allowed
     */
    private function checkBot(string $ip, array $headers, ?BotProtection $config): ?string
    {
        $shouldBlock = $config?->shouldBlockBots() ?? $this->config['check_bots'];
        
        if (!$shouldBlock) {
            return null;
        }
        
        $isBot = $this->botDetector->isBot($headers, $ip);
        
        if ($isBot) {
            // Record violation
            $this->ipReputation->recordViolation($ip, 'bot_detected');
            return 'bot_detected';
        }
        
        return null;
    }

    /**
     * Check geo-location restrictions
     * 
     * @param string $ip
     * @param BotProtection|null $config
     * @return string|null Block reason or null if allowed
     */
    private function checkGeoLocation(string $ip, ?BotProtection $config): ?string
    {
        // Route-level geo config takes priority
        if ($config?->hasGeoRestrictions()) {
            $geoConfig = $config->getGeoConfig();
            
            // Temporarily override GeoBlocker config
            $this->geoBlocker = new GeoBlocker(
                $this->geoBlocker->cache ?? app('cache'),
                $this->logger,
                [
                    'enabled' => true,
                    'mode' => $geoConfig['mode'],
                    'allowed_countries' => $geoConfig['allowed'],
                    'blocked_countries' => $geoConfig['blocked'],
                ]
            );
        }
        
        if ($this->geoBlocker->isBlocked($ip)) {
            $country = $this->geoBlocker->getCountry($ip);
            $this->ipReputation->recordViolation($ip, 'geo_blocked');
            return "geo_blocked:{$country}";
        }
        
        return null;
    }

    /**
     * Check WAF (Web Application Firewall)
     * 
     * @param array $query Query parameters
     * @param array $body Request body
     * @param array $headers Headers
     * @return string|null Block reason or null if allowed
     */
    private function checkWaf(array $query, array $body, array $headers): ?string
    {
        // Scan query parameters
        $queryResult = $this->wafEngine->scan($query, 'query');
        if ($queryResult['detected']) {
            return 'waf:' . implode(',', $queryResult['attacks']);
        }
        
        // Scan request body
        $bodyResult = $this->wafEngine->scan($body, 'body');
        if ($bodyResult['detected']) {
            return 'waf:' . implode(',', $bodyResult['attacks']);
        }
        
        // Scan suspicious headers
        $suspiciousHeaders = ['X-Forwarded-For', 'Referer', 'User-Agent'];
        foreach ($suspiciousHeaders as $header) {
            if (isset($headers[$header])) {
                $headerResult = $this->wafEngine->scan($headers[$header], "header:{$header}");
                if ($headerResult['detected']) {
                    return 'waf:' . implode(',', $headerResult['attacks']);
                }
            }
        }
        
        return null;
    }

    /**
     * Check anomaly detection
     * 
     * @param string $ip
     * @param string $path
     * @param string $method
     * @param BotProtection|null $config
     * @return string|null Block reason or null if allowed
     */
    private function checkAnomalies(string $ip, string $path, string $method, ?BotProtection $config): ?string
    {
        $result = $this->anomalyDetector->detectAnomaly($ip, $path, $method);
        
        if ($result['has_anomaly']) {
            $this->ipReputation->recordViolation($ip, 'anomaly_detected');
            
            // Check if should block or just log
            if ($config?->onViolation === 'log') {
                return null; // Log only, don't block
            }
            
            return 'anomaly:' . implode(',', $result['anomaly_types']);
        }
        
        return null;
    }

    /**
     * Block request with 403 response
     * 
     * @param string $ip
     * @param string $reason
     * @param string $path
     * @return array Response
     */
    private function blockRequest(string $ip, string $reason, string $path): array
    {
        if ($this->config['log_blocked_requests']) {
            $this->logger->security('request_blocked', [
                'ip' => $ip,
                'reason' => $reason,
                'path' => $path,
                'correlation_id' => TraceContext::getCorrelationId(),
                'timestamp' => time(),
            ]);
        }
        
        return [
            'status' => 403,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Correlation-Id' => TraceContext::getCorrelationId(),
            ],
            'body' => json_encode([
                'error' => 'Forbidden',
                'message' => 'Access denied due to security policy',
                'code' => 'SECURITY_VIOLATION',
                'correlation_id' => TraceContext::getCorrelationId(),
            ]),
        ];
    }

    // Configuration check methods

    private function shouldCheckIpReputation(?BotProtection $config): bool
    {
        return $this->config['check_ip_reputation'] && ($config?->enabled ?? true);
    }

    private function shouldCheckBots(?BotProtection $config): bool
    {
        return $this->config['check_bots'] && ($config?->enabled ?? true);
    }

    private function shouldCheckGeo(?BotProtection $config): bool
    {
        return $this->config['check_geo'] && ($config?->enabled ?? true);
    }

    private function shouldCheckWaf(?BotProtection $config): bool
    {
        return $this->config['check_waf'] && ($config?->shouldScanWithWaf() ?? true);
    }

    private function shouldCheckAnomalies(?BotProtection $config): bool
    {
        return $this->config['check_anomalies'] && ($config?->shouldDetectAnomalies() ?? true);
    }
}
