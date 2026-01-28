<?php

namespace PHPFrarm\Core\Notifications\Services;

use PHPFrarm\Core\Logger;

/**
 * Vonage SMS Service (formerly Nexmo)
 * 
 * Sends SMS via Vonage API.
 * Global SMS provider with excellent coverage.
 * 
 * Features:
 * - SMS worldwide
 * - Voice API
 * - Video API
 * - Verify API (OTP)
 * - Number Insight
 * - Two-way SMS
 * 
 * Pricing: Varies by country (~$0.0072-0.10 per SMS)
 * 
 * Setup:
 * 1. Sign up at vonage.com
 * 2. Get API Key and API Secret
 * 3. (Optional) Get virtual number for two-way SMS
 * 
 * @package PHPFrarm\Modules\Notification\Services
 */
class VonageService implements NotificationServiceInterface
{
    private string $apiKey;
    private string $apiSecret;
    private string $fromNumber;

    public function __construct()
    {
        $this->apiKey = env('VONAGE_API_KEY', '');
        $this->apiSecret = env('VONAGE_API_SECRET', '');
        $this->fromNumber = env('VONAGE_FROM_NUMBER', 'Vonage'); // Brand name or number
    }

    /**
     * Send SMS via Vonage
     * 
     * @param string $to Recipient phone number (E.164 format: +91XXXXXXXXXX)
     * @param string $subject Not used for SMS
     * @param string $body SMS message body
     * @param array $options Additional options
     * @return array Result
     * @throws \Exception
     */
    public function send(string $to, string $subject, string $body, array $options = []): array
    {
        // Use Verify API for OTP
        if (!empty($options['otp'])) {
            return $this->sendOTP($to, $options['otp'], $options);
        }

        // Send regular SMS via SMS API
        $endpoint = 'https://rest.nexmo.com/sms/json';

        $data = [
            'api_key' => $this->apiKey,
            'api_secret' => $this->apiSecret,
            'from' => $options['from'] ?? $this->fromNumber,
            'to' => preg_replace('/[^0-9]/', '', $to), // Remove non-numeric
            'text' => $body,
            'type' => 'unicode' // Support international characters
        ];

        // Add callback URL for delivery receipt
        if (!empty($options['callback_url'])) {
            $data['callback'] = $options['callback_url'];
        }

        // Add client reference
        if (!empty($options['client_ref'])) {
            $data['client-ref'] = $options['client_ref'];
        }

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            Logger::error('Vonage cURL error', ['error' => $curlError]);
            throw new \Exception("Failed to send SMS via Vonage: $curlError");
        }

        $result = json_decode($response, true);

        // Check if any message failed
        if (isset($result['messages'][0])) {
            $message = $result['messages'][0];
            
            if ($message['status'] !== '0') {
                $errorMsg = $message['error-text'] ?? 'Unknown error';
                Logger::error('Vonage API error', [
                    'status' => $message['status'],
                    'error' => $errorMsg
                ]);
                throw new \Exception("Vonage error: $errorMsg (Status: {$message['status']})");
            }

            Logger::info('SMS sent via Vonage', [
                'to' => $to,
                'message_id' => $message['message-id'] ?? null,
                'remaining_balance' => $result['message-count'] ?? null
            ]);

            return [
                'success' => true,
                'message_id' => $message['message-id'] ?? null,
                'remaining_balance' => $message['remaining-balance'] ?? null,
                'message_price' => $message['message-price'] ?? null,
                'network' => $message['network'] ?? null,
                'provider' => 'vonage'
            ];
        }

        throw new \Exception('Vonage: No message sent');
    }

    /**
     * Send OTP via Vonage Verify API
     * 
     * @param string $to Phone number
     * @param string $otp OTP code (or let Vonage generate it)
     * @param array $options Options
     * @return array Result with request_id
     */
    public function sendOTP(string $to, string $otp = '', array $options = []): array
    {
        $to = preg_replace('/[^0-9]/', '', $to);

        // If OTP provided, send via SMS API
        if (!empty($otp)) {
            $body = $options['body'] ?? "Your verification code is: $otp";
            return $this->send($to, '', $body, array_merge($options, ['from' => $this->fromNumber]));
        }

        // Otherwise use Verify API (Vonage generates OTP)
        $endpoint = 'https://api.nexmo.com/verify/json';

        $data = [
            'api_key' => $this->apiKey,
            'api_secret' => $this->apiSecret,
            'number' => $to,
            'brand' => $options['brand'] ?? env('APP_NAME', 'App'),
            'code_length' => $options['code_length'] ?? 6,
            'lg' => $options['language'] ?? 'en-us',
            'pin_expiry' => $options['expiry_seconds'] ?? 300 // 5 minutes
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($result['status'] !== '0') {
            $errorMsg = $result['error_text'] ?? 'Unknown error';
            throw new \Exception("Vonage Verify error: $errorMsg (Status: {$result['status']})");
        }

        Logger::info('OTP sent via Vonage Verify', [
            'to' => $to,
            'request_id' => $result['request_id'] ?? null
        ]);

        return [
            'success' => true,
            'request_id' => $result['request_id'],
            'provider' => 'vonage_verify'
        ];
    }

    /**
     * Verify OTP (when using Vonage Verify API)
     * 
     * @param string $requestId Request ID from sendOTP
     * @param string $code OTP code entered by user
     * @return array Verification result
     */
    public function verifyOTP(string $requestId, string $code): array
    {
        $endpoint = 'https://api.nexmo.com/verify/check/json';

        $data = [
            'api_key' => $this->apiKey,
            'api_secret' => $this->apiSecret,
            'request_id' => $requestId,
            'code' => $code
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        return [
            'valid' => $result['status'] === '0',
            'status' => $result['status'],
            'error_text' => $result['error_text'] ?? '',
            'price' => $result['price'] ?? null,
            'currency' => $result['currency'] ?? null
        ];
    }

    /**
     * Cancel OTP verification request
     * 
     * @param string $requestId Request ID from sendOTP
     * @return array Result
     */
    public function cancelOTP(string $requestId): array
    {
        $endpoint = 'https://api.nexmo.com/verify/control/json';

        $data = [
            'api_key' => $this->apiKey,
            'api_secret' => $this->apiSecret,
            'request_id' => $requestId,
            'cmd' => 'cancel'
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        return [
            'success' => $result['status'] === '0',
            'status' => $result['status'],
            'command' => $result['command'] ?? 'cancel'
        ];
    }

    /**
     * Trigger next verification attempt (useful if SMS not received)
     * 
     * @param string $requestId Request ID
     * @return array Result
     */
    public function triggerNextOTP(string $requestId): array
    {
        $endpoint = 'https://api.nexmo.com/verify/control/json';

        $data = [
            'api_key' => $this->apiKey,
            'api_secret' => $this->apiSecret,
            'request_id' => $requestId,
            'cmd' => 'trigger_next_event'
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        return [
            'success' => $result['status'] === '0',
            'status' => $result['status']
        ];
    }

    /**
     * Check if service is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->apiSecret);
    }

    /**
     * Get provider name
     */
    public function getProviderName(): string
    {
        return 'vonage';
    }
}
