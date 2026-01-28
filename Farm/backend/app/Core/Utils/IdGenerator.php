<?php

namespace PHPFrarm\Core\Utils;

/**
 * ID Generator Facade
 * 
 * Provides unified interface for generating unique identifiers
 * Default: ULID (as per framework standards)
 * 
 * Usage:
 * - IdGenerator::generate() - ULID (default)
 * - IdGenerator::ulid() - Explicit ULID
 * - IdGenerator::uuid() - UUIDv4
 * - IdGenerator::uuid5($namespace, $name) - UUIDv5
 */
class IdGenerator
{
    /**
     * Generate default ID (ULID)
     */
    public static function generate(): string
    {
        return UlidGenerator::generate();
    }
    
    /**
     * Generate ULID
     */
    public static function ulid(): string
    {
        return UlidGenerator::generate();
    }
    
    /**
     * Generate UUIDv4
     */
    public static function uuid(): string
    {
        return UuidGenerator::v4();
    }
    
    /**
     * Generate UUIDv5
     */
    public static function uuid5(string $namespace, string $name): string
    {
        return UuidGenerator::v5($namespace, $name);
    }
    
    /**
     * Validate ID format (auto-detect ULID or UUID)
     */
    public static function isValid(string $id): bool
    {
        // Check ULID format
        if (UlidGenerator::isValid($id)) {
            return true;
        }
        
        // Check UUID format
        if (UuidGenerator::isValid($id)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get type of ID
     */
    public static function getType(string $id): string
    {
        if (UlidGenerator::isValid($id)) {
            return 'ulid';
        }
        
        if (UuidGenerator::isValid($id)) {
            return 'uuid';
        }
        
        return 'unknown';
    }
    
    /**
     * Get timestamp from ID (ULID only)
     */
    public static function getTimestamp(string $id): ?int
    {
        if (UlidGenerator::isValid($id)) {
            return UlidGenerator::getTimestamp($id);
        }
        
        return null;
    }
    
    /**
     * Get DateTime from ID (ULID only)
     */
    public static function getDateTime(string $id): ?\DateTime
    {
        if (UlidGenerator::isValid($id)) {
            return UlidGenerator::getDateTime($id);
        }
        
        return null;
    }
}
