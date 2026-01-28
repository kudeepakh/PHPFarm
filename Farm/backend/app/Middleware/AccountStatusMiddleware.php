<?php

namespace PHPFrarm\Middleware;

use PHPFrarm\Core\Request;
use PHPFrarm\Core\Response;
use PHPFrarm\Modules\UserManagement\Services\AccountStatusService;
use PHPFrarm\Core\Logger;

/**
 * Account Status Middleware
 * Checks if user account is accessible before processing requests
 * 
 * Blocks locked, suspended, or deactivated accounts
 */
class AccountStatusMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        // Only check for authenticated requests
        $user = $request->getAttribute('user');
        
        if (!$user) {
            return $next($request);
        }
        
        $userId = $user['user_id'];
        
        try {
            // Check if account is accessible
            AccountStatusService::validateAccessOrThrow($userId);
            
            // Account is accessible, proceed
            return $next($request);
            
        } catch (\Exception $e) {
            Logger::warning('Account access denied', [
                'user_id' => $userId,
                'reason' => $e->getMessage(),
                'path' => $request->getPath(),
                'ip_address' => $request->getClientIp()
            ]);
            
            return Response::error(
                $e->getMessage(),
                403,
                'ACCOUNT_NOT_ACCESSIBLE',
                ['user_id' => $userId]
            );
        }
    }
}
