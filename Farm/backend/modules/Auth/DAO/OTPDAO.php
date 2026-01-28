<?php

namespace PHPFrarm\Modules\Auth\DAO;

use PHPFrarm\Core\Database;
use PHPFrarm\Core\Logger;
use PHPFrarm\Core\TraceContext;

/**
 * OTP DAO - Data Access Object for OTP operations
 * Enhanced with retry limits, replay prevention, and blacklist support
 * Now includes correlation ID tracking for observability
 */
class OTPDAO
{
    public function createOTP(string $otpId, string $identifier, string $type, string $otpHash, string $purpose, string $expiresAt): array
    {
        try {
            $correlationId = TraceContext::getCorrelationId();
            
            $result = Database::callProcedure('sp_create_otp', [
                $otpId,
                $identifier,
                $type,
                $otpHash,
                $purpose,
                $expiresAt,
                $correlationId
            ]);
            
            if (!empty($result) && $result[0]['status'] === 'success') {
                Logger::info('OTP created successfully', [
                    'otp_id' => $otpId,
                    'identifier' => $identifier,
                    'purpose' => $purpose,
                    'correlation_id' => $correlationId
                ]);
                
                return [
                    'success' => true,
                    'message' => $result[0]['message'],
                    'otp_id' => $result[0]['otp_id']
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to create OTP record'
            ];
            
        } catch (\Exception $e) {
            Logger::error('Failed to create OTP', [
                'error' => $e->getMessage(),
                'identifier' => $identifier,
                'purpose' => $purpose,
                'correlation_id' => TraceContext::getCorrelationId()
            ]);
            
            return [
                'success' => false,
                'message' => 'Database operation failed'
            ];
        }
    }

    public function verifyOTP(string $identifier, string $otpHash, string $purpose): array
    {
        $correlationId = TraceContext::getCorrelationId();
        
        $result = Database::callProcedure('sp_verify_otp', [
            $identifier,
            $otpHash,
            $purpose,
            $correlationId
        ]);

        return $result[0] ?? ['is_valid' => false, 'message' => 'Verification failed'];
    }
    
    /**
     * Verify OTP with retry limit enforcement
     */
    public function verifyOTPWithRetryLimit(
        string $identifier,
        string $otp,
        string $purpose,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        try {
            $maxRetries = 3;
            $otpHash = hash('sha256', $otp);
            $correlationId = TraceContext::getCorrelationId();
            
            $result = Database::callProcedure('sp_verify_otp_with_retry', [
                $identifier,
                $otpHash,
                $purpose,
                $maxRetries,
                $ipAddress ?? 'unknown',
                $userAgent ?? 'unknown',
                $correlationId
            ]);
            
            if (!empty($result)) {
                $verification = $result[0];
                return [
                    'is_valid' => $verification['is_valid'] ?? false,
                    'user_id' => null,
                    'message' => $verification['message'] ?? 'Unknown error'
                ];
            }
            
            return [
                'is_valid' => false,
                'user_id' => null,
                'message' => 'Verification failed'
            ];
            
        } catch (\Exception $e) {
            Logger::error('Failed to verify OTP with retry limit', [
                'identifier' => $identifier,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Mark OTP as used
     */
    public function markAsUsed(string $requestId): bool
    {
        try {
            $stmt = Database::prepare("CALL sp_mark_otp_used(?)");
            $stmt->execute([$requestId]);
            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to mark OTP as used', [
                'request_id' => $requestId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get OTP retry count
     */
    public function getRetryCount(string $identifier): array
    {
        try {
            $stmt = Database::prepare("CALL sp_get_otp_retry_count(?, @retry_count, @max_retries)");
            $stmt->execute([$identifier]);
            
            $result = Database::fetchOutputParameters(['retry_count', 'max_retries']);
            
            return [
                'retry_count' => (int)$result['retry_count'],
                'max_retries' => (int)$result['max_retries'],
                'remaining' => (int)$result['max_retries'] - (int)$result['retry_count']
            ];
        } catch (\Exception $e) {
            Logger::error('Failed to get retry count', [
                'identifier' => $identifier,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Check OTP validity without verification
     */
    public function checkValidity(string $identifier): array
    {
        try {
            $stmt = Database::prepare("CALL sp_check_otp_validity(?, @is_valid, @retry_count, @expires_in)");
            $stmt->execute([$identifier]);
            
            $result = Database::fetchOutputParameters(['is_valid', 'retry_count', 'expires_in']);
            
            return [
                'is_valid' => (bool)$result['is_valid'],
                'retry_count' => (int)$result['retry_count'],
                'expires_in_seconds' => (int)$result['expires_in']
            ];
        } catch (\Exception $e) {
            Logger::error('Failed to check OTP validity', [
                'identifier' => $identifier,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Cleanup expired OTPs
     */
    public function cleanupExpired(): int
    {
        try {
            $stmt = Database::prepare("CALL sp_cleanup_expired_otps()");
            $stmt->execute();
            
            $result = $stmt->fetch();
            return (int)($result['deleted_count'] ?? 0);
        } catch (\Exception $e) {
            Logger::error('Failed to cleanup expired OTPs', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
