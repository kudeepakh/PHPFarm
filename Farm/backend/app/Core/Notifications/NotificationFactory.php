<?php

namespace PHPFrarm\Core\Notifications;

/**
 * Notification Factory
 * 
 * Factory class to instantiate appropriate notification service based on type.
 * Supports email (SendGrid) and SMS (Twilio) with easy extensibility.
 * 
 * @package PHPFrarm\Core\Notifications
 */
class NotificationFactory
{
    /** @var array<string, EmailService> */
    private static array $emailServices = [];
    
    /** @var array<string, SMSService> */
    private static array $smsServices = [];
    
    /** @var array<string, Services\WhatsAppService> */
    private static array $whatsappServices = [];

    /**
     * Get notification service by type
     * 
     * @param string $type 'email' or 'sms'
     * @return NotificationServiceInterface
     * @throws \InvalidArgumentException
     */
    public static function getService(string $type): NotificationServiceInterface
    {
        return match (strtolower($type)) {
            'email' => self::getEmailService(),
            'sms' => self::getSMSService(),
            'whatsapp' => self::getWhatsAppService(),
            default => throw new \InvalidArgumentException("Unknown notification type: $type")
        };
    }

    /**
     * Get email service (singleton with provider selection and fallback)
     * 
     * @param string|null $provider Specific provider (sendgrid, amazon_ses, mailgun, postmark)
     * @return EmailService
     * @throws \RuntimeException if no configured provider found
     */
    public static function getEmailService(?string $provider = null): EmailService
    {
        // If provider specified and already instantiated, return cached instance
        if ($provider && isset(self::$emailServices[$provider])) {
            return self::$emailServices[$provider];
        }
        
        // Get default provider from config
        $config = require __DIR__ . '/../../../config/notifications.php';
        $defaultProvider = $provider ?? $config['email']['provider'] ?? 'sendgrid';
        
        // Try to get the requested/default provider
        try {
            $service = self::getEmailProvider($defaultProvider);
            if ($service->isConfigured()) {
                self::$emailServices[$defaultProvider] = $service;
                return $service;
            }
        } catch (\Exception $e) {
            Logger::warning("Email provider $defaultProvider failed", [
                'error' => $e->getMessage()
            ]);
        }
        
        // Try fallback providers if primary fails
        if (!$provider) { // Only use fallbacks if no specific provider requested
            $fallbackProviders = explode(',', $config['email']['fallback_providers'] ?? '');
            foreach ($fallbackProviders as $fallbackProvider) {
                $fallbackProvider = trim($fallbackProvider);
                if (empty($fallbackProvider)) continue;
                
                try {
                    $service = self::getEmailProvider($fallbackProvider);
                    if ($service->isConfigured()) {
                        Logger::info("Using fallback email provider: $fallbackProvider");
                        self::$emailServices[$fallbackProvider] = $service;
                        return $service;
                    }
                } catch (\Exception $e) {
                    Logger::warning("Fallback email provider $fallbackProvider failed", [
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
        }
        
        throw new \RuntimeException('No configured email provider available');
    }
    
    /**
     * Get specific email provider instance
     * 
     * @param string $provider Provider name (smtp, sendgrid, amazon_ses, mailgun, postmark)
     * @return EmailService
     * @throws \InvalidArgumentException if provider not supported
     */
    private static function getEmailProvider(string $provider): EmailService
    {
        $service = match(strtolower($provider)) {
            'smtp' => new EmailService(), // Default EmailService handles SMTP
            'sendgrid' => new EmailService(),
            'amazon_ses', 'ses' => new Services\AmazonSESService(),
            'mailgun' => new Services\MailgunService(),
            'postmark' => new Services\PostmarkService(),
            default => throw new \InvalidArgumentException("Unknown email provider: $provider")
        };
        
        return $service;
    }

    /**
     * Get SMS service (singleton with provider selection and fallback)
     * 
     * @param string|null $provider Specific provider (twilio, msg91, vonage)
     * @return SMSService
     * @throws \RuntimeException if no configured provider found
     */
    public static function getSMSService(?string $provider = null): SMSService
    {
        // If provider specified and already instantiated, return cached instance
        if ($provider && isset(self::$smsServices[$provider])) {
            return self::$smsServices[$provider];
        }
        
        // Get default provider from config
        $config = require __DIR__ . '/../../../config/notifications.php';
        $defaultProvider = $provider ?? $config['sms']['provider'] ?? 'twilio';
        
        // Try to get the requested/default provider
        try {
            $service = self::getSMSProvider($defaultProvider);
            if ($service->isConfigured()) {
                self::$smsServices[$defaultProvider] = $service;
                return $service;
            }
        } catch (\Exception $e) {
            Logger::warning("SMS provider $defaultProvider failed", [
                'error' => $e->getMessage()
            ]);
        }
        
        // Try fallback providers if primary fails
        if (!$provider) { // Only use fallbacks if no specific provider requested
            $fallbackProviders = explode(',', $config['sms']['fallback_providers'] ?? '');
            foreach ($fallbackProviders as $fallbackProvider) {
                $fallbackProvider = trim($fallbackProvider);
                if (empty($fallbackProvider)) continue;
                
                try {
                    $service = self::getSMSProvider($fallbackProvider);
                    if ($service->isConfigured()) {
                        Logger::info("Using fallback SMS provider: $fallbackProvider");
                        self::$smsServices[$fallbackProvider] = $service;
                        return $service;
                    }
                } catch (\Exception $e) {
                    Logger::warning("Fallback SMS provider $fallbackProvider failed", [
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
        }
        
        throw new \RuntimeException('No configured SMS provider available');
    }
    
    /**
     * Get specific SMS provider instance
     * 
     * @param string $provider Provider name (twilio, msg91, vonage)
     * @return SMSService
     * @throws \InvalidArgumentException if provider not supported
     */
    private static function getSMSProvider(string $provider): SMSService
    {
        $service = match(strtolower($provider)) {
            'twilio' => new SMSService(),
            'msg91' => new Services\MSG91Service(),
            'vonage', 'nexmo' => new Services\VonageService(),
            default => throw new \InvalidArgumentException("Unknown SMS provider: $provider")
        };
        
        return $service;
    }

    /**
     * Get WhatsApp service (singleton with provider selection)
     * 
     * @param string|null $provider Specific provider (twilio, msg91, meta, vonage)
     * @return Services\WhatsAppService
     * @throws \RuntimeException if no configured provider found
     */
    public static function getWhatsAppService(?string $provider = null): Services\WhatsAppService
    {
        // If provider specified and already instantiated, return cached instance
        if ($provider && isset(self::$whatsappServices[$provider])) {
            return self::$whatsappServices[$provider];
        }
        
        // Get default provider from config
        $config = require __DIR__ . '/../../../config/notifications.php';
        $defaultProvider = $provider ?? $config['whatsapp']['provider'] ?? 'twilio';
        
        // Get the requested/default provider
        $service = new Services\WhatsAppService();
        
        if ($service->isConfigured()) {
            self::$whatsappServices[$defaultProvider] = $service;
            return $service;
        }
        
        throw new \RuntimeException('WhatsApp service not configured');
    }

    /**
     * Send notification (auto-detect type based on recipient)
     * 
     * @param string $recipient Email or phone number
     * @param string $subject Subject (ignored for SMS)
     * @param string $message Message body
     * @param array $options Additional options
     * @return array ['success' => bool, 'message_id' => string, 'error' => string|null]
     */
    public static function send(string $recipient, string $subject, string $message, array $options = []): array
    {
        $type = self::detectType($recipient);
        $service = self::getService($type);
        
        return $service->send($recipient, $subject, $message, $options);
    }

    /**
     * Send OTP notification (auto-detect type)
     * 
     * @param string $recipient Email or phone number
     * @param string $otp 6-digit code
     * @param string $purpose Purpose of OTP
     * @return array ['success' => bool, 'message_id' => string, 'error' => string|null]
     */
    public static function sendOTP(string $recipient, string $otp, string $purpose = 'verification'): array
    {
        $type = self::detectType($recipient);
        $service = self::getService($type);
        
        return $service->sendOTP($recipient, $otp, $purpose);
    }

    /**
     * Detect recipient type (email or phone)
     * 
     * @param string $recipient
     * @return string 'email' or 'sms'
     */
    public static function detectType(string $recipient): string
    {
        // E.164 phone format: +1234567890
        if (preg_match('/^\+[1-9]\d{1,14}$/', $recipient)) {
            return 'sms';
        }
        
        // Email format
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }
        
        // Default to email if ambiguous
        return 'email';
    }

    /**
     * Check if all notification services are configured
     */
    public static function areServicesConfigured(): array
    {
        return [
            'email' => self::getEmailService()->isConfigured(),
            'sms' => self::getSMSService()->isConfigured(),
            'whatsapp' => self::getWhatsAppService()->isConfigured(),
        ];
    }

    /**
     * Get status of all notification services
     */
    public static function getServicesStatus(): array
    {
        $emailService = self::getEmailService();
        $smsService = self::getSMSService();
        $whatsappService = self::getWhatsAppService();

        return [
            'email' => [
                'type' => $emailService->getType(),
                'provider' => $emailService->getProvider(),
                'configured' => $emailService->isConfigured(),
            ],
            'sms' => [
                'type' => $smsService->getType(),
                'provider' => $smsService->getProvider(),
                'configured' => $smsService->isConfigured(),
            ],
            'whatsapp' => [
                'type' => $whatsappService->getType(),
                'provider' => $whatsappService->getProvider(),
                'configured' => $whatsappService->isConfigured(),
            ],
        ];
    }

    /**
     * Reset singleton instances (useful for testing)
     */
    public static function reset(): void
    {
        self::$emailServices = [];
        self::$smsServices = [];
        self::$whatsappServices = [];
    }
}
