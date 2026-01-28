<?php

namespace PHPFrarm\Modules\Auth\DAO;

use PHPFrarm\Core\Database;
use PHPFrarm\Core\Logger;

/**
 * OTP History DAO
 * Manages OTP audit trail and history tracking
 */
class OTPHistoryDAO
{
    /**
     * Log OTP request
     */
    public static function logRequest(
        string $historyId,
        ?string $userId,
        string $identifierType,
        string $identifierValue,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?array $metadata = null
    ): bool {
        try {
            $metadataJson = $metadata ? json_encode($metadata) : null;
            
            $stmt = Database::prepare("CALL sp_log_otp_request(?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $historyId,
                $userId,
                $identifierType,
                $identifierValue,
                $ipAddress,
                $userAgent,
                $metadataJson
            ]);
            
            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to log OTP request', [
                'identifier' => $identifierValue,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Log OTP verification (success/fail)
     */
    public static function logVerification(
        string $historyId,
        ?string $userId,
        string $action,
        string $identifierType,
        string $identifierValue,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $reason = null,
        ?array $metadata = null
    ): bool {
        try {
            $metadataJson = $metadata ? json_encode($metadata) : null;
            
            $stmt = Database::prepare("CALL sp_log_otp_verification(?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $historyId,
                $userId,
                $action,
                $identifierType,
                $identifierValue,
                $ipAddress,
                $userAgent,
                $reason,
                $metadataJson
            ]);
            
            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to log OTP verification', [
                'action' => $action,
                'identifier' => $identifierValue,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get OTP history for user
     */
    public static function getHistoryByUser(string $userId, int $limit = 50): array
    {
        try {
            $stmt = Database::prepare("CALL sp_get_otp_history_by_user(?, ?)");
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            Logger::error('Failed to get OTP history by user', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get OTP history for identifier
     */
    public static function getHistoryByIdentifier(
        string $identifierType,
        string $identifierValue,
        int $limit = 50
    ): array {
        try {
            $stmt = Database::prepare("CALL sp_get_otp_history_by_identifier(?, ?, ?)");
            $stmt->execute([$identifierType, $identifierValue, $limit]);
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            Logger::error('Failed to get OTP history by identifier', [
                'identifier_type' => $identifierType,
                'identifier_value' => $identifierValue,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get OTP statistics
     */
    public static function getStatistics(int $timeWindowHours = 24): array
    {
        try {
            $stmt = Database::prepare("CALL sp_get_otp_statistics(?)");
            $stmt->execute([$timeWindowHours]);
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            Logger::error('Failed to get OTP statistics', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get failed attempts count
     */
    public static function getFailedAttemptsCount(
        string $identifierType,
        string $identifierValue,
        int $timeWindowMinutes = 60
    ): int {
        try {
            $stmt = Database::prepare("CALL sp_get_failed_attempts_count(?, ?, ?, @count)");
            $stmt->execute([$identifierType, $identifierValue, $timeWindowMinutes]);
            
            $result = Database::fetchOutputParameters(['count']);
            return (int)$result['count'];
        } catch (\Exception $e) {
            Logger::error('Failed to get failed attempts count', [
                'identifier_type' => $identifierType,
                'identifier_value' => $identifierValue,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Cleanup old history
     */
    public static function cleanupOldHistory(int $retentionDays = 90): int
    {
        try {
            $stmt = Database::prepare("CALL sp_cleanup_old_history(?)");
            $stmt->execute([$retentionDays]);
            
            $result = $stmt->fetch();
            return (int)($result['deleted_count'] ?? 0);
        } catch (\Exception $e) {
            Logger::error('Failed to cleanup old history', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get recent activity
     */
    public static function getRecentActivity(int $limit = 100): array
    {
        try {
            $stmt = Database::prepare("CALL sp_get_recent_activity(?)");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            Logger::error('Failed to get recent activity', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
