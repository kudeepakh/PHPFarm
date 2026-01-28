<?php

namespace PHPFrarm\Modules\UserManagement\DAO;

use PHPFrarm\Core\Database;
use PHPFrarm\Core\Logger;

/**
 * User Identifier Data Access Object
 * Manages multi-identifier support for users
 */
class IdentifierDAO
{
    /**
     * Find user by any identifier (email, phone, username)
     */
    public static function findUserByIdentifier(string $identifierValue, string $identifierType): ?array
    {
        try {
            $stmt = Database::prepare("CALL sp_find_user_by_identifier(?, ?)");
            $stmt->execute([$identifierValue, $identifierType]);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (\Exception $e) {
            Logger::error('Failed to find user by identifier', [
                'identifier_value' => $identifierValue,
                'identifier_type' => $identifierType,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Add identifier to user
     */
    public static function addIdentifier(
        string $identifierId,
        string $userId,
        string $identifierType,
        string $identifierValue,
        bool $isPrimary = false,
        bool $isVerified = false
    ): bool {
        try {
            $stmt = Database::prepare("CALL sp_add_identifier_to_user(?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $identifierId,
                $userId,
                $identifierType,
                $identifierValue,
                $isPrimary ? 1 : 0,
                $isVerified ? 1 : 0
            ]);
            
            Logger::info('Identifier added to user', [
                'user_id' => $userId,
                'identifier_type' => $identifierType
            ]);
            
            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to add identifier', [
                'user_id' => $userId,
                'identifier_type' => $identifierType,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Verify identifier
     */
    public static function verifyIdentifier(
        string $userId,
        string $identifierType,
        string $identifierValue
    ): bool {
        try {
            $stmt = Database::prepare("CALL sp_verify_identifier(?, ?, ?)");
            $stmt->execute([$userId, $identifierType, $identifierValue]);
            
            Logger::info('Identifier verified', [
                'user_id' => $userId,
                'identifier_type' => $identifierType
            ]);
            
            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to verify identifier', [
                'user_id' => $userId,
                'identifier_type' => $identifierType,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get all identifiers for a user
     */
    public static function getUserIdentifiers(string $userId): array
    {
        try {
            $stmt = Database::prepare("CALL sp_get_user_identifiers(?)");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            Logger::error('Failed to get user identifiers', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Remove identifier from user
     */
    public static function removeIdentifier(string $userId, string $identifierId): bool
    {
        try {
            $stmt = Database::prepare("CALL sp_remove_identifier(?, ?)");
            $stmt->execute([$userId, $identifierId]);
            
            Logger::info('Identifier removed from user', [
                'user_id' => $userId,
                'identifier_id' => $identifierId
            ]);
            
            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to remove identifier', [
                'user_id' => $userId,
                'identifier_id' => $identifierId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Set identifier as primary
     */
    public static function setPrimaryIdentifier(string $userId, string $identifierId): bool
    {
        try {
            $stmt = Database::prepare("CALL sp_set_primary_identifier(?, ?)");
            $stmt->execute([$userId, $identifierId]);
            
            Logger::info('Primary identifier updated', [
                'user_id' => $userId,
                'identifier_id' => $identifierId
            ]);
            
            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to set primary identifier', [
                'user_id' => $userId,
                'identifier_id' => $identifierId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
