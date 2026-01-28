<?php

namespace PHPFrarm\Core\Notifications\Services;

use PHPFrarm\Core\Logger;

/**
 * MSG91 SMS Service
 * 
 * Sends SMS via MSG91 API (India-focused).
 * One of the most popular SMS providers in India.
 * 
 * Features:
 * - SMS OTP
 * - Transactional SMS
 * - Promotional SMS
 * - WhatsApp messaging
 * - Email service
 * - Voice OTP
 * - International SMS
 * 
 * Pricing: Very competitive for India (~₹0.15-0.25 per SMS)
 * 
 * Setup:
 * 1. Sign up at msg91.com
 * 2. Get Auth Key from dashboard
 * 3. Get Sender ID (your brand name in SMS)
 * 4. Get Template ID for transactional SMS (DLT requirement)
 * 
 * @package PHPFrarm\Modules\Notification\Services
 */
class MSG91Service implements NotificationServiceInterface
{
    private string $authKey;
    private string $senderId;
    private string $templateId;
    private string $route; // 4 = transactional, 1 = promotional

    public function __construct()
    {
        $this->authKey = env('MSG91_AUTH_KEY', '');
        $this->senderId = env('MSG91_SENDER_ID', ''); // e.g., 'TXTLCL'
        $this->templateId = env('MSG91_TEMPLATE_ID', ''); // DLT approved template
        $this->route = env('MSG91_ROUTE', '4'); // 4 = transactional
    }

    /**
     * Send SMS via MSG91
     * 
     * @param string $to Recipient phone number (with country code: +91XXXXXXXXXX)
     * @param string $subject Not used for SMS
     * @param string $body SMS message body
     * @param array $options Additional options
     * @return array Result
     * @throws \Exception
     */
    public function send(string $to, string $subject, string $body, array $options = []): array
    {
        // Clean phone number (remove + and spaces)
        $to = preg_replace('/[^0-9]/', '', $to);

        // Use Flow API (recommended for transactional SMS)
        if (!empty($options['otp'])) {
            return $this->sendOTP($to, $options['otp'], $options);
        }

        // Send regular SMS
        $endpoint = 'https://api.msg91.com/api/v5/flow/';

        $payload = [
            'flow_id' => $options['template_id'] ?? $this->templateId,
            'sender' => $options['sender_id'] ?? $this->senderId,
            'mobiles' => $to,
            'VAR1' => $body, // Variable in template
            'route' => $this->route
        ];

        // Add additional variables for template
        if (!empty($options['variables'])) {
            foreach ($options['variables'] as $key => $value) {
                $payload[$key] = $value;
            }
        }

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'authkey: ' . $this->authKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            Logger::error('MSG91 cURL error', ['error' => $curlError]);
            throw new \Exception("Failed to send SMS via MSG91: $curlError");
        }

        $result = json_decode($response, true);

        // MSG91 returns type: success/error
        if (isset($result['type']) && $result['type'] === 'error') {
            $errorMsg = $result['message'] ?? 'Unknown error';
            Logger::error('MSG91 API error', [
                'status' => $httpCode,
                'response' => $response
            ]);
            throw new \Exception("MSG91 error: $errorMsg");
        }

        Logger::info('SMS sent via MSG91', [
            'to' => $to,
            'message' => substr($body, 0, 50),
            'request_id' => $result['request_id'] ?? null
        ]);

        return [
            'success' => true,
            'message_id' => $result['request_id'] ?? null,
            'type' => $result['type'] ?? 'success',
            'message' => $result['message'] ?? 'SMS sent',
            'provider' => 'msg91'
        ];
    }

    /**
     * Send OTP via MSG91 OTP API
     * 
     * @param string $to Phone number
     * @param string $otp OTP code
     * @param array $options Options
     * @return array Result
     */
    public function sendOTP(string $to, string $otp, array $options = []): array
    {
        // Clean phone number
        $to = preg_replace('/[^0-9]/', '', $to);

        // Use OTP API (simpler for OTP)
        $endpoint = 'https://api.msg91.com/api/v5/otp';

        $payload = [
            'template_id' => $options['template_id'] ?? $this->templateId,
            'mobile' => $to,
            'otp' => $otp,
            'otp_expiry' => $options['expiry_minutes'] ?? 10
        ];

        // Add sender ID if specified
        if (!empty($options['sender_id'])) {
            $payload['sender'] = $options['sender_id'];
        }

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'authkey: ' . $this->authKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if (isset($result['type']) && $result['type'] === 'error') {
            throw new \Exception($result['message'] ?? 'OTP send failed');
        }

        Logger::info('OTP sent via MSG91', [
            'to' => $to,
            'otp_length' => strlen($otp),
            'request_id' => $result['request_id'] ?? null
        ]);

        return [
            'success' => true,
            'message_id' => $result['request_id'] ?? null,
            'type' => $result['type'] ?? 'success',
            'provider' => 'msg91'
        ];
    }

    /**
     * Verify OTP (if using MSG91 OTP generation)
     * 
     * @param string $to Phone number
     * @param string $otp OTP to verify
     * @return array Verification result
     */
    public function verifyOTP(string $to, string $otp): array
    {
        $to = preg_replace('/[^0-9]/', '', $to);
        $endpoint = 'https://api.msg91.com/api/v5/otp/verify';

        $payload = [
            'mobile' => $to,
            'otp' => $otp
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'authkey: ' . $this->authKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $result = json_decode($response, true);

        return [
            'valid' => isset($result['type']) && $result['type'] === 'success',
            'message' => $result['message'] ?? '',
            'type' => $result['type'] ?? 'error'
        ];
    }

    /**
     * Resend OTP
     * 
     * @param string $to Phone number
     * @param string $retryType 'voice' or 'text'
     * @return array Result
     */
    public function resendOTP(string $to, string $retryType = 'text'): array
    {
        $to = preg_replace('/[^0-9]/', '', $to);
        $endpoint = 'https://api.msg91.com/api/v5/otp/retry';

        $payload = [
            'mobile' => $to,
            'retrytype' => $retryType // voice or text
        ];

        $ch = curl_init($endpoint . '?' . http_build_query($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'authkey: ' . $this->authKey
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        return [
            'success' => isset($result['type']) && $result['type'] === 'success',
            'type' => $result['type'] ?? 'error',
            'message' => $result['message'] ?? ''
        ];
    }

    /**
     * Check if service is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->authKey) && 
               !empty($this->senderId) && 
               !empty($this->templateId);
    }

    /**
     * Get provider name
     */
    public function getProviderName(): string
    {
        return 'msg91';
    }
}
