<?php

namespace PHPFrarm\Modules\Auth\DAO;

use PHPFrarm\Core\Database;
use PHPFrarm\Core\TraceContext;

/**
 * User DAO - Data Access Object for User operations
 * 
 * Uses User Identifiers pattern: 
 * - Users have a core record (id, name, password)
 * - Identifiers (email, phone, username) are stored separately
 * - One user can have multiple identifiers
 * - Any verified identifier can be used for login
 */
class UserDAO
{
    // =========================================================================
    // REGISTRATION METHODS (Creates user + identifier atomically)
    // =========================================================================
    
    /**
     * Register user with email
     * Creates user record + email identifier in a single transaction
     */
    public function registerWithEmail(
        string $userId, 
        string $identifierId,
        string $email, 
        string $passwordHash, 
        ?string $firstName, 
        ?string $lastName
    ): array {
        $result = Database::callProcedure('sp_register_with_email', [
            $userId,
            $identifierId,
            $email,
            $passwordHash,
            $firstName ?? '',
            $lastName ?? '',
            TraceContext::getCorrelationId()
        ]);
        
        return !empty($result) ? $result[0] : ['success' => false, 'message' => 'Registration failed'];
    }
    
    /**
     * Register user with phone (requires OTP verification)
     * Creates user (pending status) + phone identifier + OTP record
     */
    public function registerWithPhone(
        string $userId, 
        string $identifierId,
        string $phone, 
        string $passwordHash, 
        ?string $firstName, 
        ?string $lastName,
        string $otpId,
        string $otpHash,
        \DateTime $otpExpiresAt
    ): array {
        $result = Database::callProcedure('sp_register_with_phone', [
            $userId,
            $identifierId,
            $phone,
            $passwordHash,
            $firstName ?? '',
            $lastName ?? '',
            $otpId,
            $otpHash,
            $otpExpiresAt->format('Y-m-d H:i:s'),
            TraceContext::getCorrelationId()
        ]);
        
        return !empty($result) ? $result[0] : ['success' => false, 'message' => 'Registration failed'];
    }
    
    // =========================================================================
    // LOOKUP METHODS (Find user by any identifier)
    // =========================================================================
    
    /**
     * Get user by any identifier (email, phone, or username)
     * Used for login - returns user + identifier info
     */
    public function getUserByIdentifier(string $identifierValue): ?array
    {
        $results = Database::callProcedure('sp_get_user_by_identifier', [$identifierValue]);
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * Check if an identifier exists (for validation before registration)
     */
    public function checkIdentifierExists(string $identifierValue): ?array
    {
        $results = Database::callProcedure('sp_check_identifier_exists', [$identifierValue]);
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * Get user by ID
     */
    public function getUserById(string $userId): ?array
    {
        $users = Database::callProcedure('sp_get_user_by_id', [$userId]);
        return !empty($users) ? $users[0] : null;
    }
    
    /**
     * Get all identifiers for a user
     */
    public function getUserIdentifiers(string $userId): array
    {
        return Database::callProcedure('sp_get_user_identifiers', [$userId]);
    }
    
    // =========================================================================
    // IDENTIFIER MANAGEMENT
    // =========================================================================
    
    /**
     * Add a new identifier to an existing user
     */
    public function addIdentifier(
        string $identifierId,
        string $userId, 
        string $type, 
        string $value,
        bool $isVerified = false,
        bool $isPrimary = false
    ): array {
        $result = Database::callProcedure('sp_add_user_identifier', [
            $identifierId,
            $userId,
            $type,
            $value,
            $isVerified ? 1 : 0,
            $isPrimary ? 1 : 0,
            TraceContext::getCorrelationId()
        ]);
        
        return !empty($result) ? $result[0] : ['success' => false, 'message' => 'Failed to add identifier'];
    }
    
    /**
     * Mark an identifier as verified
     */
    public function verifyIdentifier(string $type, string $value): array
    {
        $result = Database::callProcedure('sp_verify_identifier', [
            $type,
            $value,
            TraceContext::getCorrelationId()
        ]);
        
        return !empty($result) ? $result[0] : ['success' => false, 'message' => 'Verification failed'];
    }
    
    /**
     * Link an identifier to an existing user account
     */
    public function linkIdentifier(
        string $userId, 
        string $identifierId,
        string $type, 
        string $value
    ): array {
        $result = Database::callProcedure('sp_link_identifier', [
            $userId,
            $identifierId,
            $type,
            $value,
            TraceContext::getCorrelationId()
        ]);
        
        return !empty($result) ? $result[0] : ['success' => false, 'message' => 'Failed to link identifier'];
    }
    
    // =========================================================================
    // SESSION & SECURITY METHODS
    // =========================================================================
    
    /**
     * Update last login timestamp
     */
    public function updateLastLogin(string $userId): void
    {
        Database::callProcedure('sp_update_last_login', [$userId]);
    }
    
    /**
     * Update user password
     */
    public function updatePassword(string $userId, string $passwordHash, bool $incrementTokenVersion = true): array
    {
        return Database::callProcedure('sp_update_user_password', [
            $userId,
            $passwordHash,
            $incrementTokenVersion ? 1 : 0
        ]);
    }
    
    // =========================================================================
    // LEGACY COMPATIBILITY METHODS (Deprecated - will be removed)
    // =========================================================================
    
    /**
     * @deprecated Use registerWithEmail() instead
     */
    public function createUser(string $userId, string $email, string $passwordHash, ?string $firstName, ?string $lastName): array
    {
        $identifierId = $this->generateUUID();
        return $this->registerWithEmail($userId, $identifierId, $email, $passwordHash, $firstName, $lastName);
    }
    
    /**
     * @deprecated Use getUserByIdentifier() instead
     */
    public function getUserByEmail(string $email): ?array
    {
        return $this->getUserByIdentifier($email);
    }
    
    /**
     * @deprecated Use getUserByIdentifier() instead
     */
    public function getUserByPhone(string $phone): ?array
    {
        return $this->getUserByIdentifier($phone);
    }
    
    /**
     * @deprecated Use verifyIdentifier('email', $email) instead
     */
    public function verifyEmail(string $userId): void
    {
        // Old behavior - kept for backward compatibility
        Database::callProcedure('sp_verify_user_email', [$userId]);
    }
    
    /**
     * Generate UUID v4
     */
    private function generateUUID(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
