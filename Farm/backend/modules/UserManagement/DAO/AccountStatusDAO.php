<?php

namespace PHPFrarm\Modules\UserManagement\DAO;

use PHPFrarm\Core\Database;
use PHPFrarm\Core\Logger;

/**
 * Account Status Data Access Object
 * Manages account status and history
 */
class AccountStatusDAO
{
    /**
     * Update account status with history tracking
     */
    public static function updateAccountStatus(
        string $historyId,
        string $userId,
        string $newStatus,
        ?string $reason = null,
        ?string $changedBy = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): bool {
        try {
            $stmt = Database::prepare("CALL sp_update_account_status(?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $historyId,
                $userId,
                $newStatus,
                $reason,
                $changedBy,
                $ipAddress,
                $userAgent
            ]);
            
            Logger::info('Account status updated', [
                'user_id' => $userId,
                'new_status' => $newStatus,
                'changed_by' => $changedBy
            ]);
            
            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to update account status', [
                'user_id' => $userId,
                'new_status' => $newStatus,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Lock account
     */
    public static function lockAccount(
        string $historyId,
        string $userId,
        string $reason,
        ?string $lockedBy = null,
        ?string $ipAddress = null
    ): bool {
        try {
            $stmt = Database::prepare("CALL sp_lock_account(?, ?, ?, ?, ?)");
            $stmt->execute([$historyId, $userId, $reason, $lockedBy, $ipAddress]);
            
            Logger::security('Account locked', [
                'user_id' => $userId,
                'reason' => $reason,
                'locked_by' => $lockedBy
            ]);
            
            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to lock account', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Unlock account
     */
    public static function unlockAccount(
        string $historyId,
        string $userId,
        string $unlockedBy,
        ?string $ipAddress = null
    ): bool {
        try {
            $stmt = Database::prepare("CALL sp_unlock_account(?, ?, ?, ?)");
            $stmt->execute([$historyId, $userId, $unlockedBy, $ipAddress]);
            
            Logger::info('Account unlocked', [
                'user_id' => $userId,
                'unlocked_by' => $unlockedBy
            ]);
            
            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to unlock account', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Suspend account
     */
    public static function suspendAccount(
        string $historyId,
        string $userId,
        string $reason,
        string $suspendedBy,
        ?string $ipAddress = null
    ): bool {
        try {
            $stmt = Database::prepare("CALL sp_suspend_account(?, ?, ?, ?, ?)");
            $stmt->execute([$historyId, $userId, $reason, $suspendedBy, $ipAddress]);
            
            Logger::security('Account suspended', [
                'user_id' => $userId,
                'reason' => $reason,
                'suspended_by' => $suspendedBy
            ]);
            
            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to suspend account', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Deactivate account (user-initiated)
     */
    public static function deactivateAccount(
        string $historyId,
        string $userId,
        ?string $reason = null,
        ?string $ipAddress = null
    ): bool {
        try {
            $stmt = Database::prepare("CALL sp_deactivate_account(?, ?, ?, ?)");
            $stmt->execute([$historyId, $userId, $reason, $ipAddress]);
            
            Logger::info('Account deactivated', [
                'user_id' => $userId,
                'reason' => $reason
            ]);
            
            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to deactivate account', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Activate account
     */
    public static function activateAccount(
        string $historyId,
        string $userId,
        string $activatedBy,
        ?string $ipAddress = null
    ): bool {
        try {
            $stmt = Database::prepare("CALL sp_activate_account(?, ?, ?, ?)");
            $stmt->execute([$historyId, $userId, $activatedBy, $ipAddress]);
            
            Logger::info('Account activated', [
                'user_id' => $userId,
                'activated_by' => $activatedBy
            ]);
            
            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to activate account', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Increment failed login attempts
     */
    public static function incrementFailedLogin(string $userId, ?string $ipAddress = null): bool
    {
        try {
            $stmt = Database::prepare("CALL sp_increment_failed_login(?, ?)");
            $stmt->execute([$userId, $ipAddress]);
            
            Logger::security('Failed login attempt recorded', [
                'user_id' => $userId,
                'ip_address' => $ipAddress
            ]);
            
            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to increment failed login', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Reset failed login attempts
     */
    public static function resetFailedLogin(string $userId): bool
    {
        try {
            $stmt = Database::prepare("CALL sp_reset_failed_login(?)");
            $stmt->execute([$userId]);
            
            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to reset failed login', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get account status history
     */
    public static function getAccountStatusHistory(string $userId, int $limit = 50): array
    {
        try {
            $stmt = Database::prepare("CALL sp_get_account_status_history(?, ?)");
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            Logger::error('Failed to get account status history', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Check if account is accessible
     */
    public static function checkAccountAccessible(string $userId): array
    {
        try {
            $stmt = Database::prepare("CALL sp_check_account_accessible(?, @is_accessible, @status, @reason)");
            $stmt->execute([$userId]);
            
            // Get output parameters using the safe method
            $result = Database::fetchOutputParameters(['is_accessible', 'status', 'reason']);
            
            return [
                'is_accessible' => (bool)$result['is_accessible'],
                'status' => $result['status'] ?? 'unknown',
                'reason' => $result['reason'] ?? 'Unable to verify account status'
            ];
        } catch (\Exception $e) {
            Logger::error('Failed to check account accessibility', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
