<?php

namespace PHPFrarm\Core\Notifications;

/**
 * Notification Service Interface
 * 
 * Abstract interface for email and SMS notification providers.
 * Allows swapping providers (SendGrid, Twilio, AWS SES, etc.) without changing application code.
 * 
 * @package PHPFrarm\Core\Notifications
 */
interface NotificationServiceInterface
{
    /**
     * Send a notification
     * 
     * @param string $recipient Email address or phone number
     * @param string $subject Subject line (email) or null (SMS)
     * @param string $message Message body (plain text or HTML)
     * @param array $options Additional options (template_id, from_name, etc.)
     * @return array ['success' => bool, 'message_id' => string, 'error' => string|null]
     */
    public function send(string $recipient, string $subject, string $message, array $options = []): array;

    /**
     * Send OTP notification (convenience method)
     * 
     * @param string $recipient Email or phone
     * @param string $otp 6-digit code
     * @param string $purpose Purpose (login, registration, password_reset, etc.)
     * @return array ['success' => bool, 'message_id' => string, 'error' => string|null]
     */
    public function sendOTP(string $recipient, string $otp, string $purpose = 'verification'): array;

    /**
     * Check if the service is configured and ready
     * 
     * @return bool
     */
    public function isConfigured(): bool;

    /**
     * Get service name
     * 
     * @return string 'email' or 'sms'
     */
    public function getType(): string;

    /**
     * Get provider name
     * 
     * @return string 'sendgrid', 'twilio', etc.
     */
    public function getProvider(): string;
}
