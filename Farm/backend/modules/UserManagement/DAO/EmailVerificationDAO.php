<?php

namespace PHPFrarm\Modules\UserManagement\DAO;

use PHPFrarm\Core\Database;
use PHPFrarm\Core\Logger;

/**
 * Email Verification Data Access Object
 * Manages email verification tokens and workflow
 */
class EmailVerificationDAO
{
    /**
     * Create email verification token
     */
    public static function createVerificationToken(
        string $tokenId,
        string $userId,
        string $token,
        string $email,
        string $expiresAt,
        ?string $ipAddress = null
    ): bool {
        try {
            $stmt = Database::prepare("CALL sp_create_email_verification_token(?, ?, ?, ?, ?, ?)");
            $stmt->execute([$tokenId, $userId, $token, $email, $expiresAt, $ipAddress]);
            
            Logger::info('Email verification token created', [
                'user_id' => $userId,
                'email' => $email
            ]);
            
            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to create verification token', [
                'user_id' => $userId,
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Verify email token
     * Returns ['is_valid' => bool, 'user_id' => string, 'email' => string, 'message' => string]
     */
    public static function verifyToken(string $token): array
    {
        try {
            $stmt = Database::prepare("CALL sp_verify_email_token(?, @is_valid, @user_id, @email, @message)");
            $stmt->execute([$token]);
            
            // Get output parameters using the safe method
            $result = Database::fetchOutputParameters(['is_valid', 'user_id', 'email', 'message']);
            
            $isValid = (bool)$result['is_valid'];
            
            if ($isValid) {
                Logger::info('Email token verified', [
                    'user_id' => $result['user_id'],
                    'email' => $result['email']
                ]);
            } else {
                Logger::warning('Email token verification failed', [
                    'message' => $result['message']
                ]);
            }
            
            return [
                'is_valid' => $isValid,
                'user_id' => $result['user_id'],
                'email' => $result['email'],
                'message' => $result['message']
            ];
        } catch (\Exception $e) {
            Logger::error('Failed to verify email token', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Mark email as verified
     */
    public static function markEmailVerified(string $userId, string $email): bool
    {
        try {
            $stmt = Database::prepare("CALL sp_mark_email_verified(?, ?)");
            $stmt->execute([$userId, $email]);
            
            Logger::info('Email marked as verified', [
                'user_id' => $userId,
                'email' => $email
            ]);
            
            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to mark email as verified', [
                'user_id' => $userId,
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get pending verification status
     */
    public static function getPendingVerification(string $userId): ?array
    {
        try {
            $stmt = Database::prepare("CALL sp_get_pending_verification(?)");
            $stmt->execute([$userId]);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (\Exception $e) {
            Logger::error('Failed to get pending verification', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Check if email is verified
     */
    public static function isEmailVerified(string $userId): bool
    {
        try {
            $stmt = Database::prepare("CALL sp_is_email_verified(?, @is_verified)");
            $stmt->execute([$userId]);
            
            $result = Database::fetchOutputParameters(['is_verified']);
            return (bool)$result['is_verified'];
        } catch (\Exception $e) {
            Logger::error('Failed to check email verification', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Clean up expired tokens (maintenance task)
     */
    public static function cleanupExpiredTokens(): int
    {
        try {
            $stmt = Database::prepare("CALL sp_cleanup_expired_verification_tokens()");
            $stmt->execute();
            
            $deleted = $stmt->rowCount();
            
            Logger::info('Expired verification tokens cleaned up', [
                'deleted_count' => $deleted
            ]);
            
            return $deleted;
        } catch (\Exception $e) {
            Logger::error('Failed to cleanup expired tokens', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
