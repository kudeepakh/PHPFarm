<?php

namespace PHPFrarm\Modules\UserManagement\Services;

use PHPFrarm\Modules\UserManagement\DAO\AccountStatusDAO;
use PHPFrarm\Core\Utils\UuidGenerator;
use PHPFrarm\Core\Logger;

/**
 * Account Status Service
 * Business logic for account status management
 */
class AccountStatusService
{
    // Valid account statuses
    const STATUS_ACTIVE = 'active';
    const STATUS_LOCKED = 'locked';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_PENDING_VERIFICATION = 'pending_verification';
    const STATUS_DEACTIVATED = 'deactivated';
    
    // Failed login threshold
    const MAX_FAILED_ATTEMPTS = 5;
    
    /**
     * Update account status
     */
    public static function updateStatus(
        string $userId,
        string $newStatus,
        ?string $reason = null,
        ?string $changedBy = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): bool {
        self::validateStatus($newStatus);
        
        $historyId = UuidGenerator::v4();
        
        return AccountStatusDAO::updateAccountStatus(
            $historyId,
            $userId,
            $newStatus,
            $reason,
            $changedBy,
            $ipAddress,
            $userAgent
        );
    }
    
    /**
     * Lock account
     */
    public static function lockAccount(
        string $userId,
        string $reason,
        ?string $lockedBy = null,
        ?string $ipAddress = null
    ): bool {
        $historyId = UuidGenerator::v4();
        
        return AccountStatusDAO::lockAccount($historyId, $userId, $reason, $lockedBy, $ipAddress);
    }
    
    /**
     * Unlock account
     */
    public static function unlockAccount(
        string $userId,
        string $unlockedBy,
        ?string $ipAddress = null
    ): bool {
        $historyId = UuidGenerator::v4();
        
        return AccountStatusDAO::unlockAccount($historyId, $userId, $unlockedBy, $ipAddress);
    }
    
    /**
     * Suspend account (admin action)
     */
    public static function suspendAccount(
        string $userId,
        string $reason,
        string $suspendedBy,
        ?string $ipAddress = null
    ): bool {
        $historyId = UuidGenerator::v4();
        
        return AccountStatusDAO::suspendAccount($historyId, $userId, $reason, $suspendedBy, $ipAddress);
    }
    
    /**
     * Deactivate account (user-initiated)
     */
    public static function deactivateAccount(
        string $userId,
        ?string $reason = null,
        ?string $ipAddress = null
    ): bool {
        $historyId = UuidGenerator::v4();
        
        return AccountStatusDAO::deactivateAccount($historyId, $userId, $reason, $ipAddress);
    }
    
    /**
     * Activate account
     */
    public static function activateAccount(
        string $userId,
        string $activatedBy,
        ?string $ipAddress = null
    ): bool {
        $historyId = UuidGenerator::v4();
        
        return AccountStatusDAO::activateAccount($historyId, $userId, $activatedBy, $ipAddress);
    }
    
    /**
     * Handle failed login attempt
     * Returns true if account was locked
     */
    public static function handleFailedLogin(string $userId, ?string $ipAddress = null): bool
    {
        AccountStatusDAO::incrementFailedLogin($userId, $ipAddress);
        
        // Check if account is now locked (stored procedure handles this)
        $access = AccountStatusDAO::checkAccountAccessible($userId);
        
        if ($access['status'] === self::STATUS_LOCKED) {
            Logger::security('Account auto-locked due to failed login attempts', [
                'user_id' => $userId,
                'ip_address' => $ipAddress
            ]);
            return true;
        }
        
        return false;
    }
    
    /**
     * Handle successful login
     */
    public static function handleSuccessfulLogin(string $userId): void
    {
        AccountStatusDAO::resetFailedLogin($userId);
    }
    
    /**
     * Get account status history
     */
    public static function getStatusHistory(string $userId, int $limit = 50): array
    {
        return AccountStatusDAO::getAccountStatusHistory($userId, $limit);
    }
    
    /**
     * Check if account is accessible
     */
    public static function checkAccessible(string $userId): array
    {
        return AccountStatusDAO::checkAccountAccessible($userId);
    }
    
    /**
     * Validate if account can perform action
     * Throws exception if account is not accessible
     */
    public static function validateAccessOrThrow(string $userId): void
    {
        $access = self::checkAccessible($userId);
        
        if (!$access['is_accessible']) {
            Logger::warning('Access denied for non-accessible account', [
                'user_id' => $userId,
                'status' => $access['status'],
                'reason' => $access['reason']
            ]);
            
            throw new \Exception("Account is {$access['status']}: {$access['reason']}", 403);
        }
    }
    
    /**
     * Validate status value
     */
    private static function validateStatus(string $status): void
    {
        $validStatuses = [
            self::STATUS_ACTIVE,
            self::STATUS_LOCKED,
            self::STATUS_SUSPENDED,
            self::STATUS_PENDING_VERIFICATION,
            self::STATUS_DEACTIVATED
        ];
        
        if (!in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException("Invalid account status: {$status}");
        }
    }
}
