<?php

namespace PHPFrarm\Modules\UserManagement\Services;

use PHPFrarm\Modules\UserManagement\DAO\IdentifierDAO;
use PHPFrarm\Core\Utils\UuidGenerator;
use PHPFrarm\Core\Logger;

/**
 * Identifier Service
 * Business logic for multi-identifier management
 */
class IdentifierService
{
    /**
     * Find user by any identifier
     */
    public static function findUserByIdentifier(string $identifier): ?array
    {
        // Detect identifier type
        $type = self::detectIdentifierType($identifier);
        
        Logger::debug('Finding user by identifier', [
            'identifier_type' => $type
        ]);
        
        return IdentifierDAO::findUserByIdentifier($identifier, $type);
    }
    
    /**
     * Add identifier to user
     */
    public static function addIdentifier(
        string $userId,
        string $identifierType,
        string $identifierValue,
        bool $isPrimary = false,
        bool $isVerified = false
    ): bool {
        // Validate identifier format
        self::validateIdentifier($identifierType, $identifierValue);
        
        // Check if identifier already exists
        $existingUser = IdentifierDAO::findUserByIdentifier($identifierValue, $identifierType);
        if ($existingUser) {
            throw new \Exception("Identifier already exists for another user");
        }
        
        $identifierId = UuidGenerator::v4();
        
        return IdentifierDAO::addIdentifier(
            $identifierId,
            $userId,
            $identifierType,
            $identifierValue,
            $isPrimary,
            $isVerified
        );
    }
    
    /**
     * Verify identifier
     */
    public static function verifyIdentifier(
        string $userId,
        string $identifierType,
        string $identifierValue
    ): bool {
        return IdentifierDAO::verifyIdentifier($userId, $identifierType, $identifierValue);
    }
    
    /**
     * Get all identifiers for user
     */
    public static function getUserIdentifiers(string $userId): array
    {
        return IdentifierDAO::getUserIdentifiers($userId);
    }
    
    /**
     * Remove identifier
     */
    public static function removeIdentifier(string $userId, string $identifierId): bool
    {
        // Ensure user has at least one identifier remaining
        $identifiers = IdentifierDAO::getUserIdentifiers($userId);
        if (count($identifiers) <= 1) {
            throw new \Exception("Cannot remove last identifier");
        }
        
        return IdentifierDAO::removeIdentifier($userId, $identifierId);
    }
    
    /**
     * Set primary identifier
     */
    public static function setPrimaryIdentifier(string $userId, string $identifierId): bool
    {
        return IdentifierDAO::setPrimaryIdentifier($userId, $identifierId);
    }
    
    /**
     * Detect identifier type from value
     */
    private static function detectIdentifierType(string $identifier): string
    {
        // Email pattern
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }
        
        // Phone pattern (simple check for digits, can be enhanced)
        if (preg_match('/^\+?[0-9]{10,15}$/', $identifier)) {
            return 'phone';
        }
        
        // OAuth pattern (provider:id)
        if (str_contains($identifier, ':')) {
            return 'oauth';
        }
        
        // Default to username
        return 'username';
    }
    
    /**
     * Validate identifier format
     */
    private static function validateIdentifier(string $type, string $value): void
    {
        switch ($type) {
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new \InvalidArgumentException("Invalid email format");
                }
                break;
                
            case 'phone':
                if (!preg_match('/^\+?[0-9]{10,15}$/', $value)) {
                    throw new \InvalidArgumentException("Invalid phone format");
                }
                break;
                
            case 'username':
                if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $value)) {
                    throw new \InvalidArgumentException("Invalid username format (3-30 alphanumeric and underscore only)");
                }
                break;
                
            case 'oauth':
                if (!preg_match('/^[a-z]+:[a-zA-Z0-9_-]+$/', $value)) {
                    throw new \InvalidArgumentException("Invalid OAuth identifier format");
                }
                break;
        }
    }
}
