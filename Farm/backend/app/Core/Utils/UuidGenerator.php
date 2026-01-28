<?php

namespace PHPFrarm\Core\Utils;

/**
 * UUID Generator (RFC 4122 compliant)
 * 
 * Supports:
 * - UUIDv4 (random)
 * - UUIDv5 (namespace + name based, SHA-1)
 * 
 * Format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
 * - 8-4-4-4-12 hexadecimal digits
 * - Version 4: Random
 * - Variant: RFC 4122
 */
class UuidGenerator
{
    // Standard UUID namespaces
    const NAMESPACE_DNS = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
    const NAMESPACE_URL = '6ba7b811-9dad-11d1-80b4-00c04fd430c8';
    const NAMESPACE_OID = '6ba7b812-9dad-11d1-80b4-00c04fd430c8';
    const NAMESPACE_X500 = '6ba7b814-9dad-11d1-80b4-00c04fd430c8';
    
    /**
     * Generate UUIDv4 (random)
     */
    public static function v4(): string
    {
        // Generate 16 random bytes
        $data = random_bytes(16);
        
        // Set version (4) - 4 bits at position 48-51
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        
        // Set variant (RFC 4122) - 2 bits at position 64-65
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        // Format as UUID string
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
    /**
     * Generate UUIDv5 (namespace + name based, SHA-1)
     */
    public static function v5(string $namespace, string $name): string
    {
        if (!self::isValid($namespace)) {
            throw new \InvalidArgumentException('Invalid namespace UUID');
        }
        
        // Convert namespace to binary
        $namespaceHex = str_replace('-', '', $namespace);
        $namespaceBinary = '';
        
        for ($i = 0; $i < strlen($namespaceHex); $i += 2) {
            $namespaceBinary .= chr(hexdec(substr($namespaceHex, $i, 2)));
        }
        
        // Create hash
        $hash = sha1($namespaceBinary . $name, true);
        
        // Set version (5) - 4 bits at position 48-51
        $hash[6] = chr(ord($hash[6]) & 0x0f | 0x50);
        
        // Set variant (RFC 4122) - 2 bits at position 64-65
        $hash[8] = chr(ord($hash[8]) & 0x3f | 0x80);
        
        // Format as UUID string
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(substr($hash, 0, 16)), 4));
    }
    
    /**
     * Validate UUID format
     */
    public static function isValid(string $uuid): bool
    {
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        return (bool)preg_match($pattern, $uuid);
    }
    
    /**
     * Convert UUID to binary
     */
    public static function toBinary(string $uuid): string
    {
        if (!self::isValid($uuid)) {
            throw new \InvalidArgumentException('Invalid UUID format');
        }
        
        $hex = str_replace('-', '', $uuid);
        $binary = '';
        
        for ($i = 0; $i < strlen($hex); $i += 2) {
            $binary .= chr(hexdec(substr($hex, $i, 2)));
        }
        
        return $binary;
    }
    
    /**
     * Convert binary to UUID
     */
    public static function fromBinary(string $binary): string
    {
        if (strlen($binary) !== 16) {
            throw new \InvalidArgumentException('Invalid binary UUID length');
        }
        
        $hex = bin2hex($binary);
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($hex, 4));
    }
    
    /**
     * Generate nil UUID (all zeros)
     */
    public static function nil(): string
    {
        return '00000000-0000-0000-0000-000000000000';
    }
    
    /**
     * Check if UUID is nil
     */
    public static function isNil(string $uuid): bool
    {
        return $uuid === self::nil();
    }
}
