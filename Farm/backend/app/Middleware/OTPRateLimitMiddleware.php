<?php

namespace PHPFrarm\Middleware;

use PHPFrarm\Core\Request;
use PHPFrarm\Core\Response;
use PHPFrarm\Modules\Auth\DAO\OTPBlacklistDAO;
use PHPFrarm\Modules\Auth\DAO\OTPHistoryDAO;
use PHPFrarm\Core\Logger;

/**
 * OTP Rate Limit Middleware
 * Enforces per-user and per-IP rate limiting for OTP operations
 */
class OTPRateLimitMiddleware
{
    // Rate limit configurations
    private const RATE_LIMIT_PER_USER = 5;      // 5 requests per hour per user
    private const RATE_LIMIT_PER_IP = 10;        // 10 requests per hour per IP
    private const TIME_WINDOW_MINUTES = 60;      // 1 hour window
    private const AUTO_BLACKLIST_THRESHOLD = 15; // Auto-blacklist after 15 requests in window
    
    public function handle(Request $request, callable $next): Response
    {
        $ipAddress = $request->getClientIp();
        $user = $request->getAttribute('user');
        $userId = $user['user_id'] ?? null;
        
        try {
            // Check IP-based rate limit
            $ipRateLimitExceeded = $this->checkIPRateLimit($ipAddress);
            if ($ipRateLimitExceeded) {
                Logger::security('OTP rate limit exceeded for IP', [
                    'ip_address' => $ipAddress
                ]);
                header('Retry-After: 3600');
                return Response::tooManyRequests('rate_limit.exceeded');
            }
            
            // Check user-based rate limit (if authenticated)
            if ($userId) {
                $userRateLimitExceeded = $this->checkUserRateLimit($userId);
                if ($userRateLimitExceeded) {
                    Logger::security('OTP rate limit exceeded for user', [
                        'user_id' => $userId,
                        'ip_address' => $ipAddress
                    ]);
                    header('Retry-After: 3600');
                    return Response::tooManyRequests('rate_limit.exceeded');
                }
            }
            
            // Check for potential abuse and auto-blacklist
            $this->checkAndAutoBlacklist($ipAddress, $userId);
            
            // Proceed with request
            return $next($request);
            
        } catch (\Exception $e) {
            Logger::error('OTP rate limit middleware error', [
                'error' => $e->getMessage()
            ]);
            
            // On error, allow request but log
            return $next($request);
        }
    }
    
    /**
     * Check IP-based rate limit
     */
    private function checkIPRateLimit(string $ipAddress): bool
    {
        $count = $this->getRequestCount('ip_address', $ipAddress);
        return $count >= self::RATE_LIMIT_PER_IP;
    }
    
    /**
     * Check user-based rate limit
     */
    private function checkUserRateLimit(string $userId): bool
    {
        $count = $this->getRequestCount('user_id', $userId);
        return $count >= self::RATE_LIMIT_PER_USER;
    }
    
    /**
     * Get request count in time window
     */
    private function getRequestCount(string $type, string $value): int
    {
        // Count OTP requests in time window from history
        $history = OTPHistoryDAO::getHistoryByIdentifier($type, $value, 100);
        
        $cutoffTime = strtotime('-' . self::TIME_WINDOW_MINUTES . ' minutes');
        $count = 0;
        
        foreach ($history as $entry) {
            $entryTime = strtotime($entry['created_at']);
            if ($entryTime >= $cutoffTime && $entry['action'] === 'request') {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Check for abuse and auto-blacklist
     */
    private function checkAndAutoBlacklist(string $ipAddress, ?string $userId): void
    {
        // Check IP for excessive requests
        $ipCount = $this->getRequestCount('ip_address', $ipAddress);
        if ($ipCount >= self::AUTO_BLACKLIST_THRESHOLD) {
            OTPBlacklistDAO::autoBlacklistIfThreshold(
                'ip_address',
                $ipAddress,
                self::AUTO_BLACKLIST_THRESHOLD,
                self::TIME_WINDOW_MINUTES
            );
            
            Logger::security('IP auto-blacklisted for OTP abuse', [
                'ip_address' => $ipAddress,
                'request_count' => $ipCount
            ]);
        }
        
        // Check user for excessive requests
        if ($userId) {
            $userCount = $this->getRequestCount('user_id', $userId);
            if ($userCount >= self::AUTO_BLACKLIST_THRESHOLD) {
                OTPBlacklistDAO::autoBlacklistIfThreshold(
                    'user_id',
                    $userId,
                    self::AUTO_BLACKLIST_THRESHOLD,
                    self::TIME_WINDOW_MINUTES
                );
                
                Logger::security('User auto-blacklisted for OTP abuse', [
                    'user_id' => $userId,
                    'request_count' => $userCount
                ]);
            }
        }
    }
}
