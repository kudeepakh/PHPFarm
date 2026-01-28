<?php

namespace PHPFrarm\Modules\User\Controllers;

use PHPFrarm\Core\Request;
use PHPFrarm\Core\Response;
use PHPFrarm\Core\Attributes\Route;
use PHPFrarm\Core\Attributes\RouteGroup;
use PHPFrarm\Modules\UserManagement\Services\AccountStatusService;
use PHPFrarm\Modules\UserManagement\Services\IdentifierService;
use PHPFrarm\Core\Logger;
use PHPFrarm\Core\I18n\Translator;

/**
 * Account Status Controller
 * Admin APIs for account status management
 */
#[RouteGroup('/api/v1', middleware: ['cors'])]
class AccountStatusController
{
    /**
     * Lock user account
     */
    #[Route('/api/v1/system/users/{userId}/lock', method: 'POST', middleware: ['auth', 'permission:users:lock'])]
    public function lockAccount(array $request, string $userId): void
    {
        try {
            $data = $request['body'] ?? [];
            $reason = $data['reason'] ?? Translator::translate('account.lock.reason.default');

            $adminId = $request['user']['user_id'] ?? '';
            $ipAddress = $request['headers']['X-Forwarded-For'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');
            
            AccountStatusService::lockAccount($userId, $reason, $adminId, $ipAddress);
            
            Response::success([
                'message' => Translator::translate('account.lock.success'),
                'user_id' => $userId
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Failed to lock account', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::serverError('account.lock.failure');
        }
    }
    
    /**
     * Unlock user account
     */
    #[Route('/api/v1/system/users/{userId}/unlock', method: 'POST', middleware: ['auth', 'permission:users:unlock'])]
    public function unlockAccount(array $request, string $userId): void
    {
        try {
            $adminId = $request['user']['user_id'] ?? '';
            $ipAddress = $request['headers']['X-Forwarded-For'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');
            
            AccountStatusService::unlockAccount($userId, $adminId, $ipAddress);
            
            Response::success([
                'message' => Translator::translate('account.unlock.success'),
                'user_id' => $userId
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Failed to unlock account', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::serverError('account.unlock.failure');
        }
    }
    
    /**
     * Suspend user account
     */
    #[Route('/api/v1/system/users/{userId}/suspend', method: 'POST', middleware: ['auth', 'permission:users:suspend'])]
    public function suspendAccount(array $request, string $userId): void
    {
        try {
            $data = $request['body'] ?? [];
            $reason = $data['reason'] ?? Translator::translate('account.suspend.reason.default');

            $adminId = $request['user']['user_id'] ?? '';
            $ipAddress = $request['headers']['X-Forwarded-For'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');
            
            AccountStatusService::suspendAccount($userId, $reason, $adminId, $ipAddress);
            
            Response::success([
                'message' => Translator::translate('account.suspend.success'),
                'user_id' => $userId
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Failed to suspend account', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::serverError('account.suspend.failure');
        }
    }
    
    /**
     * Activate user account
     */
    #[Route('/api/v1/system/users/{userId}/activate', method: 'POST', middleware: ['auth', 'permission:users:activate'])]
    public function activateAccount(array $request, string $userId): void
    {
        try {
            $adminId = $request['user']['user_id'] ?? '';
            $ipAddress = $request['headers']['X-Forwarded-For'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');
            
            AccountStatusService::activateAccount($userId, $adminId, $ipAddress);
            
            Response::success([
                'message' => Translator::translate('account.activate.success'),
                'user_id' => $userId
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Failed to activate account', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::serverError('account.activate.failure');
        }
    }
    
    /**
     * Deactivate own account (user-initiated)
     */
    #[Route('/account/deactivate', method: 'POST', middleware: ['auth'])]
    public function deactivateOwnAccount(array $request): void
    {
        try {
            $userId = $request['user']['user_id'] ?? '';
            $data = $request['body'] ?? [];
            $reason = $data['reason'] ?? null;
            $ipAddress = $request['headers']['X-Forwarded-For'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');
            
            AccountStatusService::deactivateAccount($userId, $reason, $ipAddress);
            
            Response::success([
                'message' => Translator::translate('account.deactivate.success')
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Failed to deactivate account', [
                'error' => $e->getMessage()
            ]);
            Response::serverError('account.deactivate.failure');
        }
    }
    
    /**
     * Get account status history
     */
    #[Route('/api/v1/system/users/{userId}/status-history', method: 'GET', middleware: ['auth', 'permission:users:read'])]
    public function getStatusHistory(array $request, string $userId): void
    {
        try {
            $limit = (int)($request['query']['limit'] ?? 50);
            
            $history = AccountStatusService::getStatusHistory($userId, $limit);
            
            Response::success([
                'user_id' => $userId,
                'history' => $history
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Failed to get status history', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::serverError('account.history.failure');
        }
    }
    
    /**
     * Check account accessibility
     */
    #[Route('/api/v1/system/users/{userId}/check-access', method: 'GET', middleware: ['auth', 'permission:users:read'])]
    public function checkAccess(array $request, string $userId): void
    {
        try {
            $access = AccountStatusService::checkAccessible($userId);
            
            Response::success($access);
            
        } catch (\Exception $e) {
            Logger::error('Failed to check account access', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::serverError('account.access.failure');
        }
    }
    
    /**
     * Get user identifiers
     */
    #[Route('/api/v1/system/users/{userId}/identifiers', method: 'GET', middleware: ['auth', 'permission:users:read'])]
    public function getUserIdentifiers(array $request, string $userId): void
    {
        try {
            $identifiers = IdentifierService::getUserIdentifiers($userId);
            
            Response::success([
                'user_id' => $userId,
                'identifiers' => $identifiers
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Failed to get user identifiers', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::serverError('account.identifiers.failure');
        }
    }
}
