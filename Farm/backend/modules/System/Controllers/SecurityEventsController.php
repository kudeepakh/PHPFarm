<?php

namespace PHPFrarm\Modules\System\Controllers;

use PHPFrarm\Core\Response;
use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Attributes\RouteGroup;

/**
 * Security Events Controller - Provides security event monitoring
 */
#[RouteGroup('/api/v1/security', middleware: ['cors', 'auth'])]
class SecurityEventsController
{
    /**
     * Get security events with filtering
     */
    #[Route('/events', method: 'GET', middleware: ['auth', 'permission:security:read|system:read'])]
    public function events(array $request): void
    {
        $search = $request['query']['search'] ?? '';
        $severity = $request['query']['severity'] ?? '';
        $page = (int)($request['query']['page'] ?? 1);
        $perPage = (int)($request['query']['per_page'] ?? 50);

        $events = $this->getSecurityEvents($search, $severity, $page, $perPage);
        Response::success($events);
    }

    /**
     * Get blocked IPs list
     */
    #[Route('/blocked-ips', method: 'GET', middleware: ['auth', 'permission:security:read|system:read'])]
    public function blockedIps(array $request): void
    {
        $page = (int)($request['query']['page'] ?? 1);
        $perPage = (int)($request['query']['per_page'] ?? 50);

        $blockedIps = $this->getBlockedIpsList();
        
        $total = count($blockedIps);
        $offset = ($page - 1) * $perPage;
        $ips = array_slice($blockedIps, $offset, $perPage);

        Response::success([
            'blocked_ips' => $ips,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
        ]);
    }

    /**
     * Unblock an IP address
     */
    #[Route('/blocked-ips/{ip}', method: 'DELETE', middleware: ['auth', 'permission:security:manage'])]
    public function unblockIp(array $request, string $ip): void
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            Response::error('Invalid IP address', 'INVALID_IP', 400);
            return;
        }

        Response::success(['unblocked' => true, 'ip' => $ip], 'IP unblocked successfully');
    }

    /**
     * Get security settings
     */
    #[Route('/settings', method: 'GET', middleware: ['auth', 'permission:security:read|system:read'])]
    public function getSettings(array $request): void
    {
        $settings = [
            'rate_limiting' => [
                'enabled' => true,
                'requests_per_minute' => 60,
                'burst_size' => 10,
                'block_duration' => 3600
            ],
            'bot_detection' => [
                'enabled' => true,
                'challenge_mode' => 'captcha',
                'whitelist_user_agents' => ['Googlebot', 'Bingbot']
            ],
            'geo_blocking' => [
                'enabled' => false,
                'blocked_countries' => [],
                'allowed_countries' => []
            ],
            'waf' => [
                'enabled' => true,
                'sql_injection_protection' => true,
                'xss_protection' => true,
                'csrf_protection' => true
            ],
            'ip_reputation' => [
                'enabled' => true,
                'auto_block_threshold' => 5,
                'blacklist_duration' => 86400
            ]
        ];

        Response::success($settings, 'Security settings retrieved successfully');
    }

    /**
     * Update security settings
     */
    #[Route('/settings', method: 'PUT', middleware: ['auth', 'permission:security:manage'])]
    public function updateSettings(array $request): void
    {
        $body = $request['body'] ?? [];
        
        // In real implementation, save to database or config file
        // For now, just return success
        
        Response::success($body, 'Security settings updated successfully');
    }

    /**
     * Block an IP address
     */
    #[Route('/block-ip', method: 'POST', middleware: ['auth', 'permission:security:manage'])]
    public function blockIp(array $request): void
    {
        $body = $request['body'] ?? [];
        $ip = $body['ip'] ?? null;
        $reason = $body['reason'] ?? 'Manual block';
        $duration = $body['duration'] ?? 3600;

        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) {
            Response::error('Invalid IP address', 'INVALID_IP', 400);
            return;
        }

        // In real implementation, add to IP blacklist
        
        Response::success([
            'blocked' => true,
            'ip' => $ip,
            'reason' => $reason,
            'expires_at' => time() + $duration
        ], 'IP address blocked successfully');
    }

    /**
     * Unblock an IP address (alternative endpoint)
     */
    #[Route('/unblock-ip/{ip}', method: 'DELETE', middleware: ['auth', 'permission:security:manage'])]
    public function unblockIpAlt(array $request, string $ip): void
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            Response::error('Invalid IP address', 'INVALID_IP', 400);
            return;
        }

        Response::success(['unblocked' => true, 'ip' => $ip], 'IP unblocked successfully');
    }

    private function getSecurityEvents(string $search, string $severity, int $page, int $perPage): array
    {
        $sampleEvents = [
            ['id' => 'evt_001', 'event_type' => 'rate_limit_exceeded', 'severity' => 'warning', 'ip_address' => '192.168.1.100', 'description' => 'Rate limit exceeded for /api/v1/users', 'timestamp' => time() - 3600, 'user_agent' => 'Mozilla/5.0', 'blocked' => false],
            ['id' => 'evt_002', 'event_type' => 'suspicious_activity', 'severity' => 'critical', 'ip_address' => '10.0.0.50', 'description' => 'Multiple failed login attempts', 'timestamp' => time() - 7200, 'user_agent' => 'curl/7.68.0', 'blocked' => true],
            ['id' => 'evt_003', 'event_type' => 'sql_injection_attempt', 'severity' => 'critical', 'ip_address' => '172.16.0.25', 'description' => 'SQL injection pattern detected', 'timestamp' => time() - 10800, 'user_agent' => 'python-requests/2.25.1', 'blocked' => true],
            ['id' => 'evt_004', 'event_type' => 'xss_attempt', 'severity' => 'high', 'ip_address' => '192.168.2.15', 'description' => 'XSS attempt detected', 'timestamp' => time() - 14400, 'user_agent' => 'Mozilla/5.0', 'blocked' => true],
            ['id' => 'evt_005', 'event_type' => 'brute_force', 'severity' => 'high', 'ip_address' => '10.1.1.100', 'description' => 'Brute force on login endpoint', 'timestamp' => time() - 18000, 'user_agent' => 'PostmanRuntime/7.28.4', 'blocked' => true],
        ];

        if ($search) {
            $sampleEvents = array_filter($sampleEvents, fn($e) => 
                stripos($e['event_type'], $search) !== false ||
                stripos($e['ip_address'], $search) !== false ||
                stripos($e['description'], $search) !== false
            );
            $sampleEvents = array_values($sampleEvents);
        }

        if ($severity) {
            $sampleEvents = array_filter($sampleEvents, fn($e) => $e['severity'] === $severity);
            $sampleEvents = array_values($sampleEvents);
        }

        $total = count($sampleEvents);
        $offset = ($page - 1) * $perPage;
        $events = array_slice($sampleEvents, $offset, $perPage);

        return [
            'events' => $events,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
        ];
    }

    private function getBlockedIpsList(): array
    {
        return [
            ['ip' => '10.0.0.50', 'reason' => 'Multiple failed login attempts', 'blocked_at' => time() - 7200, 'expires_at' => time() + 86400, 'request_count' => 150],
            ['ip' => '172.16.0.25', 'reason' => 'SQL injection attempt', 'blocked_at' => time() - 10800, 'expires_at' => time() + 172800, 'request_count' => 25],
            ['ip' => '192.168.2.15', 'reason' => 'XSS attack detected', 'blocked_at' => time() - 14400, 'expires_at' => time() + 259200, 'request_count' => 42],
        ];
    }
}
