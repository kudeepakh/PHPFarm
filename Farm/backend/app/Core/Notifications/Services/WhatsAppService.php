<?php

namespace PHPFrarm\Core\Notifications\Services;

use PHPFrarm\Core\Logger;

/**
 * WhatsApp Business Service
 * 
 * Sends messages via WhatsApp Business API.
 * Supports multiple providers: Twilio, MSG91, Meta Official API.
 * 
 * WhatsApp is ideal for:
 * - Transactional messages
 * - OTP delivery (higher read rates than SMS)
 * - Order updates
 * - Appointment reminders
 * 
 * Requirements:
 * - WhatsApp Business Account approved
 * - Message templates approved by WhatsApp
 * - Phone number verified
 * 
 * Pricing (approximate):
 * - Twilio: $0.005-0.08 per message (varies by country)
 * - MSG91: ₹0.25-0.40 per message (India)
 * - Meta Official: $0.005-0.10 per conversation
 * 
 * @package PHPFrarm\Core\Notifications\Services
 */
class WhatsAppService
{
    private string $provider;
    private array $config;
    
    // Provider types
    const PROVIDER_TWILIO = 'twilio';
    const PROVIDER_MSG91 = 'msg91';
    const PROVIDER_META = 'meta';
    const PROVIDER_VONAGE = 'vonage';
    
    // Message types
    const TYPE_TEXT = 'text';
    const TYPE_TEMPLATE = 'template';
    const TYPE_MEDIA = 'media';
    
    public function __construct()
    {
        $this->config = require __DIR__ . '/../../../../config/notifications.php';
        $this->provider = $this->config['whatsapp']['provider'] ?? self::PROVIDER_TWILIO;
    }

    /**
     * Send WhatsApp message
     * 
     * @param string $to Phone number (E.164 format: +919876543210)
     * @param string $subject Not used for WhatsApp
     * @param string $message Message text (for text messages)
     * @param array $options Additional options
     *        - template: Template name (required for template messages)
     *        - template_params: Template parameter values
     *        - media_url: URL for media messages
     *        - media_type: image, document, video, audio
     * @return array ['success' => bool, 'message_id' => string, 'error' => string|null]
     */
    public function send(string $to, string $subject, string $message, array $options = []): array
    {
        try {
            // Validate phone number format
            if (!preg_match('/^\+[1-9]\d{1,14}$/', $to)) {
                throw new \InvalidArgumentException('Invalid phone number format. Use E.164 format (e.g., +919876543210)');
            }

            // Determine message type
            $messageType = $options['type'] ?? self::TYPE_TEXT;
            
            // Route to appropriate provider
            return match($this->provider) {
                self::PROVIDER_TWILIO => $this->sendViaTwilio($to, $message, $messageType, $options),
                self::PROVIDER_MSG91 => $this->sendViaMSG91($to, $message, $messageType, $options),
                self::PROVIDER_META => $this->sendViaMeta($to, $message, $messageType, $options),
                self::PROVIDER_VONAGE => $this->sendViaVonage($to, $message, $messageType, $options),
                default => throw new \InvalidArgumentException("Unsupported WhatsApp provider: {$this->provider}")
            };
            
        } catch (\Exception $e) {
            Logger::error('WhatsApp send failed', [
                'provider' => $this->provider,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message_id' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send OTP via WhatsApp
     * 
     * @param string $to Phone number
     * @param string $otp OTP code
     * @param string $purpose Purpose of OTP (login, registration, etc.)
     * @return array
     */
    public function sendOTP(string $to, string $otp, string $purpose = 'verification'): array
    {
        // Use template if configured, otherwise plain text
        $templateName = $this->config['whatsapp'][$this->provider]['templates']['otp'] ?? null;
        
        if ($templateName) {
            // Send using approved template
            return $this->send($to, '', '', [
                'type' => self::TYPE_TEMPLATE,
                'template' => $templateName,
                'template_params' => [$otp, $purpose],
            ]);
        } else {
            // Send as plain text (may require pre-approved template)
            $message = "Your OTP for {$purpose} is: {$otp}. Valid for 5 minutes. Do not share this code.";
            return $this->send($to, '', $message);
        }
    }

    /**
     * Send via Twilio WhatsApp
     * 
     * Twilio WhatsApp API endpoint: https://api.twilio.com/2010-04-01/Accounts/{AccountSid}/Messages.json
     */
    private function sendViaTwilio(string $to, string $message, string $type, array $options): array
    {
        $config = $this->config['whatsapp']['twilio'];
        
        $data = [
            'From' => 'whatsapp:' . $config['from_number'],
            'To' => 'whatsapp:' . $to,
        ];
        
        // Handle different message types
        if ($type === self::TYPE_TEMPLATE) {
            // Twilio Content Templates
            $data['ContentSid'] = $options['template'];
            $data['ContentVariables'] = json_encode($options['template_params'] ?? []);
        } elseif ($type === self::TYPE_MEDIA) {
            $data['Body'] = $message;
            $data['MediaUrl'] = $options['media_url'];
        } else {
            // Plain text
            $data['Body'] = $message;
        }
        
        // Make API request
        $ch = curl_init('https://api.twilio.com/2010-04-01/Accounts/' . $config['account_sid'] . '/Messages.json');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_USERPWD, $config['account_sid'] . ':' . $config['auth_token']);
        curl_setopt($ch, CURLOPT_TIMEOUT, $config['timeout']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 201) {
            $result = json_decode($response, true);
            Logger::info('WhatsApp sent via Twilio', [
                'to' => $to,
                'message_id' => $result['sid'],
            ]);
            
            return [
                'success' => true,
                'message_id' => $result['sid'],
                'error' => null,
            ];
        } else {
            $error = json_decode($response, true);
            throw new \Exception($error['message'] ?? 'Twilio WhatsApp API error');
        }
    }

    /**
     * Send via MSG91 WhatsApp
     * 
     * MSG91 WhatsApp API (India-focused)
     */
    private function sendViaMSG91(string $to, string $message, string $type, array $options): array
    {
        $config = $this->config['whatsapp']['msg91'];
        
        $data = [
            'sender' => $config['sender_id'],
            'to' => $to,
        ];
        
        // MSG91 uses different endpoint for template vs text
        if ($type === self::TYPE_TEMPLATE) {
            // Template-based message
            $url = 'https://api.msg91.com/api/v5/whatsapp/whatsapp-outbound-message/';
            $data['template_id'] = $options['template'];
            $data['params'] = $options['template_params'] ?? [];
        } else {
            // Text message (requires approved template)
            $url = 'https://api.msg91.com/api/v5/whatsapp/whatsapp-outbound-message/';
            $data['message'] = $message;
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'authkey: ' . $config['auth_key'],
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $config['timeout']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 || $httpCode === 202) {
            $result = json_decode($response, true);
            Logger::info('WhatsApp sent via MSG91', [
                'to' => $to,
                'message_id' => $result['message_id'] ?? 'unknown',
            ]);
            
            return [
                'success' => true,
                'message_id' => $result['message_id'] ?? uniqid(),
                'error' => null,
            ];
        } else {
            $error = json_decode($response, true);
            throw new \Exception($error['message'] ?? 'MSG91 WhatsApp API error');
        }
    }

    /**
     * Send via Meta Official WhatsApp Business Platform
     * 
     * Meta's official Cloud API
     */
    private function sendViaMeta(string $to, string $message, string $type, array $options): array
    {
        $config = $this->config['whatsapp']['meta'];
        
        $url = "https://graph.facebook.com/v18.0/{$config['phone_number_id']}/messages";
        
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => ltrim($to, '+'),
        ];
        
        // Handle different message types
        if ($type === self::TYPE_TEMPLATE) {
            // Template message
            $data['type'] = 'template';
            $data['template'] = [
                'name' => $options['template'],
                'language' => ['code' => $config['language'] ?? 'en'],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => array_map(function($param) {
                            return ['type' => 'text', 'text' => $param];
                        }, $options['template_params'] ?? [])
                    ]
                ]
            ];
        } elseif ($type === self::TYPE_MEDIA) {
            // Media message
            $data['type'] = $options['media_type'] ?? 'image';
            $data[$data['type']] = [
                'link' => $options['media_url'],
                'caption' => $message,
            ];
        } else {
            // Text message
            $data['type'] = 'text';
            $data['text'] = ['body' => $message];
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $config['access_token'],
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $config['timeout']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            Logger::info('WhatsApp sent via Meta', [
                'to' => $to,
                'message_id' => $result['messages'][0]['id'] ?? 'unknown',
            ]);
            
            return [
                'success' => true,
                'message_id' => $result['messages'][0]['id'] ?? uniqid(),
                'error' => null,
            ];
        } else {
            $error = json_decode($response, true);
            throw new \Exception($error['error']['message'] ?? 'Meta WhatsApp API error');
        }
    }

    /**
     * Send via Vonage (Nexmo) WhatsApp
     */
    private function sendViaVonage(string $to, string $message, string $type, array $options): array
    {
        $config = $this->config['whatsapp']['vonage'];
        
        $url = 'https://messages-sandbox.nexmo.com/v1/messages';
        
        $data = [
            'from' => $config['from_number'],
            'to' => $to,
            'channel' => 'whatsapp',
            'message_type' => $type === self::TYPE_TEMPLATE ? 'template' : 'text',
        ];
        
        if ($type === self::TYPE_TEMPLATE) {
            $data['whatsapp'] = [
                'policy' => 'deterministic',
                'locale' => 'en',
            ];
            $data['template'] = [
                'name' => $options['template'],
                'parameters' => $options['template_params'] ?? [],
            ];
        } else {
            $data['text'] = $message;
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . base64_encode($config['api_key'] . ':' . $config['api_secret']),
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $config['timeout']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 202) {
            $result = json_decode($response, true);
            Logger::info('WhatsApp sent via Vonage', [
                'to' => $to,
                'message_id' => $result['message_uuid'] ?? 'unknown',
            ]);
            
            return [
                'success' => true,
                'message_id' => $result['message_uuid'] ?? uniqid(),
                'error' => null,
            ];
        } else {
            $error = json_decode($response, true);
            throw new \Exception($error['title'] ?? 'Vonage WhatsApp API error');
        }
    }

    /**
     * Get notification type
     */
    public function getType(): string
    {
        return 'whatsapp';
    }

    /**
     * Get provider name
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * Check if service is configured
     */
    public function isConfigured(): bool
    {
        $providerConfig = $this->config['whatsapp'][$this->provider] ?? [];
        
        return match($this->provider) {
            self::PROVIDER_TWILIO => !empty($providerConfig['account_sid']) && !empty($providerConfig['auth_token']) && !empty($providerConfig['from_number']),
            self::PROVIDER_MSG91 => !empty($providerConfig['auth_key']) && !empty($providerConfig['sender_id']),
            self::PROVIDER_META => !empty($providerConfig['access_token']) && !empty($providerConfig['phone_number_id']),
            self::PROVIDER_VONAGE => !empty($providerConfig['api_key']) && !empty($providerConfig['api_secret']) && !empty($providerConfig['from_number']),
            default => false,
        };
    }

    /**
     * Send media message (image, document, video, audio)
     * 
     * @param string $to Phone number
     * @param string $mediaUrl Public URL of media file
     * @param string $mediaType image, document, video, audio
     * @param string $caption Optional caption
     * @return array
     */
    public function sendMedia(string $to, string $mediaUrl, string $mediaType, string $caption = ''): array
    {
        return $this->send($to, '', $caption, [
            'type' => self::TYPE_MEDIA,
            'media_url' => $mediaUrl,
            'media_type' => $mediaType,
        ]);
    }

    /**
     * Send template message
     * 
     * @param string $to Phone number
     * @param string $templateName Pre-approved template name
     * @param array $templateParams Dynamic parameter values
     * @return array
     */
    public function sendTemplate(string $to, string $templateName, array $templateParams = []): array
    {
        return $this->send($to, '', '', [
            'type' => self::TYPE_TEMPLATE,
            'template' => $templateName,
            'template_params' => $templateParams,
        ]);
    }
}
