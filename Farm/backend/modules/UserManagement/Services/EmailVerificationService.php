<?php

namespace PHPFrarm\Modules\UserManagement\Services;

use PHPFrarm\Modules\UserManagement\DAO\EmailVerificationDAO;
use PHPFrarm\Core\Utils\UuidGenerator;
use PHPFrarm\Core\Logger;
use PHPFrarm\Core\Notifications\NotificationFactory;

/**
 * Email Verification Service
 * Business logic for email verification workflow
 */
class EmailVerificationService
{
    // Token expiry duration (24 hours)
    const TOKEN_EXPIRY_HOURS = 24;
    
    /**
     * Create and send email verification token
     */
    public static function createVerificationToken(
        string $userId,
        string $email,
        ?string $ipAddress = null
    ): string {
        // Generate secure token
        $token = self::generateSecureToken();
        $tokenId = UuidGenerator::v4();
        
        // Calculate expiry time
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . self::TOKEN_EXPIRY_HOURS . ' hours'));
        
        // Store token
        EmailVerificationDAO::createVerificationToken(
            $tokenId,
            $userId,
            $token,
            $email,
            $expiresAt,
            $ipAddress
        );
        
        self::sendVerificationEmail($email, $token);
        
        Logger::info('Email verification token created', [
            'user_id' => $userId,
            'email' => $email,
            'expires_at' => $expiresAt
        ]);
        
        return $token;
    }
    
    /**
     * Verify email token
     */
    public static function verifyToken(string $token): array
    {
        $result = EmailVerificationDAO::verifyToken($token);
        
        if (!$result['is_valid']) {
            Logger::warning('Invalid email verification attempt', [
                'message' => $result['message']
            ]);
            
            throw new \Exception($result['message'], 400);
        }
        
        // Mark email as verified
        EmailVerificationDAO::markEmailVerified($result['user_id'], $result['email']);
        
        Logger::info('Email successfully verified', [
            'user_id' => $result['user_id'],
            'email' => $result['email']
        ]);
        
        return $result;
    }
    
    /**
     * Check if email is verified
     */
    public static function isEmailVerified(string $userId): bool
    {
        return EmailVerificationDAO::isEmailVerified($userId);
    }
    
    /**
     * Get pending verification status
     */
    public static function getPendingVerification(string $userId): ?array
    {
        return EmailVerificationDAO::getPendingVerification($userId);
    }
    
    /**
     * Resend verification email
     */
    public static function resendVerification(
        string $userId,
        string $email,
        ?string $ipAddress = null
    ): string {
        // Check if already verified
        if (self::isEmailVerified($userId)) {
            throw new \Exception("Email is already verified", 400);
        }
        
        // Check for recent token
        $pending = self::getPendingVerification($userId);
        if ($pending) {
            $createdAt = strtotime($pending['created_at']);
            $timeSince = time() - $createdAt;
            
            // Rate limit: must wait 1 minute between resends
            if ($timeSince < 60) {
                throw new \Exception("Please wait before requesting another verification email", 429);
            }
        }
        
        // Create new token
        return self::createVerificationToken($userId, $email, $ipAddress);
    }
    
    /**
     * Clean up expired tokens (maintenance)
     */
    public static function cleanupExpiredTokens(): int
    {
        return EmailVerificationDAO::cleanupExpiredTokens();
    }
    
    /**
     * Generate secure token
     */
    private static function generateSecureToken(): string
    {
        // Generate 32-byte random token
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Send verification email
     * 
     * NOTE: Currently uses logging for development. In production, integrate with:
     * - AWS SES, SendGrid, Mailgun, or SMTP provider
     * - Configure email templates in config/mail.php
     * - Use queue system for async delivery (SendVerificationEmailJob)
     */
    private static function sendVerificationEmail(string $email, string $token): void
    {
        $appUrl = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8787', '/');
        $frontendUrl = rtrim($_ENV['FRONTEND_URL'] ?? 'http://localhost:3900', '/');
        
        // Link goes to frontend verification page for better UX
        $verifyUrl = $frontendUrl . '/verify-email?token=' . urlencode($token);
        $subject = 'Verify your email';
        $appName = $_ENV['MAIL_FROM_NAME'] ?? 'PHPFrarm';
        $expiresIn = self::TOKEN_EXPIRY_HOURS . ' hours';

        $message = <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .button { display: inline-block; padding: 12px 20px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 6px; }
                .muted { color: #6b7280; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <h2>Verify your email</h2>
                <p>Thanks for signing up for {$appName}. Please confirm your email address by clicking the button below.</p>
                <p><a class="button" href="{$verifyUrl}">Verify Email</a></p>
                <p class="muted">This link will expire in {$expiresIn}. If you did not request this, you can safely ignore this email.</p>
            </div>
        </body>
        </html>
        HTML;

        $options = ['content_type' => 'text/html'];

        $configPath = __DIR__ . '/../../config/notifications.php';
        if (file_exists($configPath)) {
            $config = require $configPath;
            $templateId = $config['email']['sendgrid']['templates']['email_verification'] ?? '';
            if (!empty($templateId)) {
                $options = [
                    'template_id' => $templateId,
                    'template_data' => [
                        'verification_link' => $verifyUrl,
                        'token' => $token,
                        'app_name' => $appName,
                        'expires_in' => $expiresIn,
                        'email' => $email
                    ]
                ];
            }
        }

        $result = NotificationFactory::send(
            recipient: $email,
            subject: $subject,
            message: $message,
            options: $options
        );

        if ($result['success']) {
            Logger::info('Verification email sent', [
                'email' => $email,
                'message_id' => $result['message_id']
            ]);
            return;
        }

        Logger::error('Verification email send failed', [
            'email' => $email,
            'error' => $result['error'] ?? 'unknown'
        ]);

        throw new \Exception('Failed to send verification email', 500);
    }
}
