<?php

namespace PHPFrarm\Core\Utils;

/**
 * ULID Generator (Universally Unique Lexicographically Sortable Identifier)
 * 
 * ULIDs are:
 * - 128-bit compatible with UUID
 * - Lexicographically sortable
 * - Canonically encoded as 26 character string
 * - URL safe (base32)
 * - Case insensitive
 * - No special characters
 * - Monotonic sort order (within same millisecond)
 * 
 * Format: TTTTTTTTTTRRRRRRRRRRRRRRRR
 * - 10 characters timestamp (48 bits)
 * - 16 characters randomness (80 bits)
 * 
 * Advantages over UUID:
 * - Sortable by creation time
 * - Shorter string representation
 * - Better database indexing
 * - More entropy than UUIDv4
 */
class UlidGenerator
{
    private const ENCODING = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'; // Crockford's Base32
    private const ENCODING_LENGTH = 32;
    private const TIME_LENGTH = 10;
    private const RANDOM_LENGTH = 16;
    
    private static ?string $lastTimestamp = null;
    private static int $lastRandom = 0;
    
    /**
     * Generate a new ULID
     */
    public static function generate(?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? (int)(microtime(true) * 1000);
        
        $timePart = self::encodeTime($timestamp);
        $randomPart = self::encodeRandom($timestamp);
        
        return $timePart . $randomPart;
    }
    
    /**
     * Encode timestamp component
     */
    private static function encodeTime(int $timestamp): string
    {
        $encoded = '';
        
        for ($i = self::TIME_LENGTH - 1; $i >= 0; $i--) {
            $mod = $timestamp % self::ENCODING_LENGTH;
            $encoded = self::ENCODING[$mod] . $encoded;
            $timestamp = (int)($timestamp / self::ENCODING_LENGTH);
        }
        
        return $encoded;
    }
    
    /**
     * Encode random component (with monotonicity)
     */
    private static function encodeRandom(int $timestamp): string
    {
        $timestampStr = (string)$timestamp;
        
        // Ensure monotonicity: increment if same timestamp
        if ($timestampStr === self::$lastTimestamp) {
            self::$lastRandom++;
            $randomValue = self::$lastRandom;
        } else {
            self::$lastTimestamp = $timestampStr;
            $randomValue = self::generateRandomValue();
            self::$lastRandom = $randomValue;
        }
        
        $encoded = '';
        
        for ($i = self::RANDOM_LENGTH - 1; $i >= 0; $i--) {
            $mod = $randomValue % self::ENCODING_LENGTH;
            $encoded = self::ENCODING[$mod] . $encoded;
            $randomValue = (int)($randomValue / self::ENCODING_LENGTH);
        }
        
        return $encoded;
    }
    
    /**
     * Generate random 80-bit value
     */
    private static function generateRandomValue(): int
    {
        // Generate 10 random bytes (80 bits)
        $bytes = random_bytes(10);
        
        // Convert to integer
        $value = 0;
        for ($i = 0; $i < 10; $i++) {
            $value = $value * 256 + ord($bytes[$i]);
        }
        
        return $value;
    }
    
    /**
     * Extract timestamp from ULID
     */
    public static function getTimestamp(string $ulid): int
    {
        if (strlen($ulid) !== (self::TIME_LENGTH + self::RANDOM_LENGTH)) {
            throw new \InvalidArgumentException('Invalid ULID length');
        }
        
        $timePart = substr($ulid, 0, self::TIME_LENGTH);
        $timestamp = 0;
        
        for ($i = 0; $i < self::TIME_LENGTH; $i++) {
            $char = $timePart[$i];
            $value = strpos(self::ENCODING, $char);
            
            if ($value === false) {
                throw new \InvalidArgumentException('Invalid ULID character');
            }
            
            $timestamp = $timestamp * self::ENCODING_LENGTH + $value;
        }
        
        return $timestamp;
    }
    
    /**
     * Get DateTime from ULID
     */
    public static function getDateTime(string $ulid): \DateTime
    {
        $timestamp = self::getTimestamp($ulid);
        $seconds = (int)($timestamp / 1000);
        $microseconds = ($timestamp % 1000) * 1000;
        
        $dateTime = new \DateTime();
        $dateTime->setTimestamp($seconds);
        $dateTime->modify("+{$microseconds} microseconds");
        
        return $dateTime;
    }
    
    /**
     * Validate ULID format
     */
    public static function isValid(string $ulid): bool
    {
        if (strlen($ulid) !== (self::TIME_LENGTH + self::RANDOM_LENGTH)) {
            return false;
        }
        
        for ($i = 0; $i < strlen($ulid); $i++) {
            if (strpos(self::ENCODING, $ulid[$i]) === false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Compare two ULIDs
     */
    public static function compare(string $ulid1, string $ulid2): int
    {
        return strcmp($ulid1, $ulid2);
    }
    
    /**
     * Generate ULID from specific datetime
     */
    public static function fromDateTime(\DateTime $dateTime): string
    {
        $timestamp = (int)($dateTime->getTimestamp() * 1000) + 
                    (int)($dateTime->format('u') / 1000);
        
        return self::generate($timestamp);
    }
}
