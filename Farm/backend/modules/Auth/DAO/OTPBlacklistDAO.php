<?php

namespace PHPFrarm\Modules\Auth\DAO;

use PHPFrarm\Core\Database;
use PHPFrarm\Core\Logger;

/**
 * OTP Blacklist DAO
 * Manages OTP blacklisting for abuse prevention
 */
class OTPBlacklistDAO
{
    /**
     * Check if identifier is blacklisted
     */
    public static function checkBlacklist(
        string $identifierType,
        string $identifierValue
    ): array {
        try {
            $stmt = Database::prepare("CALL sp_check_blacklist(?, ?, @is_blacklisted, @reason, @expires_at)");
            $stmt->execute([$identifierType, $identifierValue]);

            $pdo = Database::getConnection();
            $result = $pdo->query("SELECT @is_blacklisted as is_blacklisted, @reason as reason, @expires_at as expires_at")->fetch();
            
            return [
                'is_blacklisted' => (bool)$result['is_blacklisted'],
                'reason' => $result['reason'],
                'expires_at' => $result['expires_at']
            ];
        } catch (\Exception $e) {
            Logger::error('Failed to check blacklist', [
                'identifier_type' => $identifierType,
                'identifier_value' => $identifierValue,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Add to blacklist
     */
    public static function addToBlacklist(
        string $blacklistId,
        string $identifierType,
        string $identifierValue,
        string $reason,
        ?string $blacklistedBy = null,
        int $durationHours = 24,
        bool $isPermanent = false,
        bool $autoBlacklisted = false
    ): bool {
        try {
            $stmt = Database::prepare("CALL sp_add_to_blacklist(?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $blacklistId,
                $identifierType,
                $identifierValue,
                $reason,
                $blacklistedBy,
                $durationHours,
                $isPermanent ? 1 : 0,
                $autoBlacklisted ? 1 : 0
            ]);
            
            Logger::security('Added to OTP blacklist', [
                'identifier_type' => $identifierType,
                'identifier_value' => $identifierValue,
                'reason' => $reason,
                'permanent' => $isPermanent
            ]);
            
            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to add to blacklist', [
                'identifier_type' => $identifierType,
                'identifier_value' => $identifierValue,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Remove from blacklist
     */
    public static function removeFromBlacklist(string $blacklistId): bool
    {
        try {
            $stmt = Database::prepare("CALL sp_remove_from_blacklist(?)");
            $stmt->execute([$blacklistId]);
            
            Logger::info('Removed from OTP blacklist', [
                'blacklist_id' => $blacklistId
            ]);
            
            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to remove from blacklist', [
                'blacklist_id' => $blacklistId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Auto-blacklist if threshold exceeded
     */
    public static function autoBlacklistIfThreshold(
        string $identifierType,
        string $identifierValue,
        int $threshold = 5,
        int $timeWindowMinutes = 60
    ): void {
        try {
            $stmt = Database::prepare("CALL sp_auto_blacklist_if_threshold(?, ?, ?, ?)");
            $stmt->execute([
                $identifierType,
                $identifierValue,
                $threshold,
                $timeWindowMinutes
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to auto-blacklist', [
                'identifier_type' => $identifierType,
                'identifier_value' => $identifierValue,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get blacklist entries
     */
    public static function getBlacklist(int $limit = 50, int $offset = 0): array
    {
        try {
            $stmt = Database::prepare("CALL sp_get_blacklist(?, ?)");
            $stmt->execute([$limit, $offset]);
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            Logger::error('Failed to get blacklist', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Cleanup expired blacklist entries
     */
    public static function cleanupExpired(): int
    {
        try {
            $stmt = Database::prepare("CALL sp_cleanup_expired_blacklist()");
            $stmt->execute();
            
            $result = $stmt->fetch();
            return (int)($result['deleted_count'] ?? 0);
        } catch (\Exception $e) {
            Logger::error('Failed to cleanup expired blacklist', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
