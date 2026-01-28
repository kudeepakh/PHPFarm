<?php

namespace PHPFrarm\Modules\Auth\Services;

use PHPFrarm\Modules\Auth\DAO\OTPDAO;
use PHPFrarm\Modules\Auth\DAO\OTPHistoryDAO;
use PHPFrarm\Modules\Auth\DAO\OTPBlacklistDAO;
use PHPFrarm\Core\Logger;
use PHPFrarm\Core\Utils\UuidGenerator;
use PHPFrarm\Core\Notifications\NotificationFactory;

/**
 * OTP Service - Business logic for OTP operations
 * Enhanced with retry limits, replay prevention, and blacklisting
 */
class OTPService
{
    private OTPDAO $otpDAO;

    public function __construct()
    {
        $this->otpDAO = new OTPDAO();
    }

    public function requestOTP(string $identifier, string $type, string $purpose, ?string $userId = null): array
    {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $blacklistCheck = OTPBlacklistDAO::checkBlacklist($type, $identifier);
        if ($blacklistCheck['is_blacklisted']) {
            Logger::security('OTP request blocked - blacklisted', [
                'identifier' => $identifier,
                'reason' => $blacklistCheck['reason']
            ]);
            
            throw new \Exception('This identifier is temporarily blocked. Please try again later.', 429);
        }
        
        // Generate 6-digit OTP
        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpHash = password_hash($otp, PASSWORD_BCRYPT);
        $otpId = UuidGenerator::v4();
        
        $expiresAt = date('Y-m-d H:i:s', time() + (int)($_ENV['OTP_EXPIRY'] ?? 300));

        // Store OTP in database
        $this->otpDAO->createOTP($otpId, $identifier, $type, $otpHash, $purpose, $expiresAt);

        // Log OTP request
        OTPHistoryDAO::logRequest(
            UuidGenerator::v4(),
            $userId,
            $type,
            $identifier,
            $ipAddress,
            $userAgent,
            ['purpose' => $purpose]
        );
        
        // Send OTP via email/SMS
        $this->sendOTP($identifier, $type, $otp);

        Logger::audit('OTP requested', [
            'identifier' => $identifier,
            'type' => $type,
            'purpose' => $purpose
        ]);

        // Only return OTP in development mode
        $response = ['otp_sent' => true];
        if (($_ENV['APP_ENV'] ?? 'production') !== 'production') {
            $response['otp'] = $otp; // Only for testing!
        }

        return $response;
    }

    public function verifyOTP(string $identifier, string $otp, string $purpose, ?string $userId = null): bool
    {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Check blacklist
        $identifierType = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $blacklistCheck = OTPBlacklistDAO::checkBlacklist($identifierType, $identifier);
        if ($blacklistCheck['is_blacklisted']) {
            Logger::security('OTP verification blocked - blacklisted', [
                'identifier' => $identifier,
                'reason' => $blacklistCheck['reason']
            ]);
            
            throw new \Exception('This identifier is temporarily blocked.', 403);
        }
        
        // Verify OTP with retry limit
        $result = $this->otpDAO->verifyOTPWithRetryLimit($identifier, $otp, $purpose, $ipAddress, $userAgent);

        if ($result['is_valid']) {
            Logger::audit('OTP verified successfully', [
                'identifier' => $identifier,
                'purpose' => $purpose,
                'user_id' => $result['user_id']
            ]);
            return true;
        }

        Logger::security('Invalid OTP attempt', [
            'identifier' => $identifier,
            'message' => $result['message'],
            'ip' => $ipAddress
        ]);
        
        // Auto-blacklist if threshold exceeded (do not fail OTP response on blacklist issues)
        try {
            OTPBlacklistDAO::autoBlacklistIfThreshold($identifierType, $identifier, 5, 60);
        } catch (\Exception $e) {
            Logger::error('Auto-blacklist failed', [
                'identifier' => $identifier,
                'identifier_type' => $identifierType,
                'error' => $e->getMessage()
            ]);
        }

        return false;
    }
    
    /**
     * Check OTP status without verifying
     */
    public function checkOTPStatus(string $identifier): array
    {
        return $this->otpDAO->checkValidity($identifier);
    }
    
    /**
     * Get retry attempts remaining
     */
    public function getRetryInfo(string $identifier): array
    {
        return $this->otpDAO->getRetryCount($identifier);
    }

    /**
     * Send OTP via email or SMS based on type
     */
    private function sendOTP(string $identifier, string $type, string $otp): void
    {
        try {
            $result = NotificationFactory::sendOTP($identifier, $otp, 'verification');
            
            if ($result['success']) {
                Logger::info('OTP sent successfully', [
                    'identifier' => $type === 'email' ? $identifier : $this->maskPhone($identifier),
                    'type' => $type,
                    'message_id' => $result['message_id'],
                ]);
            } else {
                Logger::error('OTP send failed', [
                    'identifier' => $type === 'email' ? $identifier : $this->maskPhone($identifier),
                    'type' => $type,
                    'error' => $result['error'],
                ]);
                
                // Don't throw exception - let OTP creation succeed even if sending fails
                // Client can retry with the same OTP
            }
            
        } catch (\Exception $e) {
            Logger::error('OTP notification exception', [
                'identifier' => $type === 'email' ? $identifier : $this->maskPhone($identifier),
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            
            // Continue - OTP is already created in database
        }
    }
    
    /**
     * Mask phone number for logging (show last 4 digits only)
     */
    private function maskPhone(string $phone): string
    {
        if (strlen($phone) <= 4) {
            return '****';
        }
        
        return substr($phone, 0, 3) . str_repeat('*', strlen($phone) - 7) . substr($phone, -4);
    }
}
