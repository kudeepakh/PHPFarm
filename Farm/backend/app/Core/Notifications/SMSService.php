<?php

namespace PHPFrarm\Core\Notifications;

use PHPFrarm\Core\Logger;

/**
 * SMS Service - Twilio Integration
 * 
 * Sends SMS messages using Twilio API.
 * Supports international phone numbers in E.164 format (+1234567890).
 * 
 * @package PHPFrarm\Core\Notifications
 */
class SMSService implements NotificationServiceInterface
{
    private string $accountSid;
    private string $authToken;
    private string $fromNumber;
    private bool $enabled;

    public function __construct()
    {
        $this->accountSid = $_ENV['TWILIO_ACCOUNT_SID'] ?? '';
        $this->authToken = $_ENV['TWILIO_AUTH_TOKEN'] ?? '';
        $this->fromNumber = $_ENV['TWILIO_FROM_NUMBER'] ?? '';
        $this->enabled = ($_ENV['SMS_ENABLED'] ?? 'true') === 'true';
    }

    /**
     * Send SMS via Twilio API
     */
    public function send(string $recipient, string $subject, string $message, array $options = []): array
    {
        // Subject is ignored for SMS
        
        if (!$this->isConfigured()) {
            Logger::warning('SMS service not configured, skipping send', [
                'recipient' => $this->maskPhone($recipient),
            ]);
            return [
                'success' => false,
                'message_id' => null,
                'error' => 'SMS service not configured'
            ];
        }

        if (!$this->enabled) {
            Logger::info('SMS service disabled, skipping send', [
                'recipient' => $this->maskPhone($recipient),
            ]);
            return [
                'success' => true, // Return success in disabled mode
                'message_id' => 'disabled_' . bin2hex(random_bytes(8)),
                'error' => null
            ];
        }

        // Validate phone number format
        if (!$this->isValidPhoneNumber($recipient)) {
            return [
                'success' => false,
                'message_id' => null,
                'error' => 'Invalid phone number format. Use E.164 format (+1234567890)'
            ];
        }

        try {
            $payload = [
                'To' => $recipient,
                'From' => $options['from_number'] ?? $this->fromNumber,
                'Body' => $message
            ];

            // Add status callback if specified
            if (!empty($options['status_callback'])) {
                $payload['StatusCallback'] = $options['status_callback'];
            }

            $response = $this->sendRequest($payload);

            Logger::info('SMS sent successfully', [
                'recipient' => $this->maskPhone($recipient),
                'message_id' => $response['sid'],
                'status' => $response['status']
            ]);

            return [
                'success' => true,
                'message_id' => $response['sid'],
                'error' => null
            ];

        } catch (\Exception $e) {
            Logger::error('SMS send failed', [
                'recipient' => $this->maskPhone($recipient),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message_id' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send OTP SMS
     */
    public function sendOTP(string $recipient, string $otp, string $purpose = 'verification'): array
    {
        $message = $this->getOTPMessage($otp, $purpose);
        return $this->send($recipient, '', $message);
    }

    /**
     * Check if Twilio is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->accountSid) && 
               !empty($this->authToken) && 
               !empty($this->fromNumber);
    }

    /**
     * Get service type
     */
    public function getType(): string
    {
        return 'sms';
    }

    /**
     * Get provider name
     */
    public function getProvider(): string
    {
        return 'twilio';
    }

    /**
     * Send HTTP request to Twilio API
     */
    private function sendRequest(array $payload): array
    {
        $url = sprintf(
            'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json',
            $this->accountSid
        );

        $ch = curl_init($url);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_USERPWD => $this->accountSid . ':' . $this->authToken,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ],
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode < 200 || $httpCode >= 300) {
            $errorMessage = $data['message'] ?? 'Twilio API error: ' . $httpCode;
            $errorCode = $data['code'] ?? null;
            
            throw new \Exception(
                $errorCode ? "[$errorCode] $errorMessage" : $errorMessage
            );
        }

        return $data;
    }

    /**
     * Validate phone number format (E.164)
     */
    private function isValidPhoneNumber(string $phone): bool
    {
        // E.164 format: +[country code][number]
        // Example: +14155552671 (US), +447911123456 (UK)
        return preg_match('/^\+[1-9]\d{1,14}$/', $phone) === 1;
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

    /**
     * Get OTP SMS message
     */
    private function getOTPMessage(string $otp, string $purpose): string
    {
        $appName = $_ENV['APP_NAME'] ?? 'PHPFrarm';
        
        $messages = [
            'login' => "Your {$appName} login code is: {$otp}. Valid for 5 minutes. Never share this code.",
            'registration' => "Your {$appName} verification code is: {$otp}. Valid for 5 minutes.",
            'password_reset' => "Your {$appName} password reset code is: {$otp}. Valid for 5 minutes. If you didn't request this, ignore this message.",
            'phone_verification' => "Your {$appName} phone verification code is: {$otp}. Valid for 5 minutes.",
            'two_factor' => "Your {$appName} 2FA code is: {$otp}. Valid for 5 minutes.",
        ];

        $message = $messages[$purpose] ?? "Your {$appName} verification code is: {$otp}. Valid for 5 minutes.";

        // SMS character limit warning
        if (strlen($message) > 160) {
            Logger::warning('SMS message exceeds 160 characters', [
                'length' => strlen($message),
                'purpose' => $purpose
            ]);
        }

        return $message;
    }

    /**
     * Get message segments count (for billing estimation)
     */
    public function getSegmentCount(string $message): int
    {
        $length = strlen($message);
        
        if ($length <= 160) {
            return 1;
        } elseif ($length <= 306) {
            return 2;
        } else {
            return (int) ceil($length / 153);
        }
    }

    /**
     * Lookup phone number information (requires additional Twilio service)
     */
    public function lookupPhoneNumber(string $phone): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $url = sprintf(
                'https://lookups.twilio.com/v1/PhoneNumbers/%s',
                urlencode($phone)
            );

            $ch = curl_init($url);
            
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERPWD => $this->accountSid . ':' . $this->authToken,
                CURLOPT_TIMEOUT => 5
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                return json_decode($response, true);
            }

            return null;

        } catch (\Exception $e) {
            Logger::error('Phone lookup failed', [
                'phone' => $this->maskPhone($phone),
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
