<?php

namespace PHPFrarm\Core\Utils;

/**
 * OTP Generator Utility
 * 
 * Centralized OTP generation for consistency across the application.
 * Generates cryptographically secure random OTP codes.
 * 
 * @package PHPFrarm\Core\Utils
 */
class OTPGenerator
{
    /**
     * Generate a secure 6-digit OTP
     * 
     * @return string 6-digit OTP code
     */
    public static function generate(): string
    {
        return str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Generate OTP with custom length
     * 
     * @param int $length Length of OTP (default: 6)
     * @return string OTP code with specified length
     */
    public static function generateWithLength(int $length = 6): string
    {
        if ($length < 4 || $length > 10) {
            throw new \InvalidArgumentException('OTP length must be between 4 and 10 digits');
        }
        
        $min = (int)str_pad('1', $length, '0');
        $max = (int)str_repeat('9', $length);
        
        return str_pad((string)random_int($min, $max), $length, '0', STR_PAD_LEFT);
    }
    
    /**
     * Generate alphanumeric OTP
     * 
     * @param int $length Length of OTP (default: 6)
     * @return string Alphanumeric OTP code
     */
    public static function generateAlphanumeric(int $length = 6): string
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $otp = '';
        
        for ($i = 0; $i < $length; $i++) {
            $otp .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        return $otp;
    }
}
