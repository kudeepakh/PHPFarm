<?php

namespace PHPFrarm\Core\Notifications\Services;

use PHPFrarm\Core\Logger;

/**
 * Mailgun Email Service
 * 
 * Sends emails via Mailgun API.
 * 
 * Features:
 * - Powerful API
 * - Email validation
 * - Template support
 * - Webhooks for tracking
 * - Detailed analytics
 * - Mailing lists
 * 
 * Pricing: $0.80 per 1,000 emails (first 5,000 free/month)
 * 
 * Setup:
 * 1. Sign up at mailgun.com
 * 2. Add and verify your domain
 * 3. Get API key from dashboard
 * 4. Choose region (US or EU)
 * 
 * @package PHPFrarm\Modules\Notification\Services
 */
class MailgunService implements NotificationServiceInterface
{
    private string $apiKey;
    private string $domain;
    private string $region; // 'us' or 'eu'
    private string $fromEmail;
    private string $fromName;

    public function __construct()
    {
        $this->apiKey = env('MAILGUN_API_KEY', '');
        $this->domain = env('MAILGUN_DOMAIN', '');
        $this->region = env('MAILGUN_REGION', 'us'); // us or eu
        $this->fromEmail = env('MAILGUN_FROM_EMAIL', '');
        $this->fromName = env('MAILGUN_FROM_NAME', 'Application');
    }

    /**
     * Send email via Mailgun
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $body Email body
     * @param array $options Additional options
     * @return array Result with message_id
     * @throws \Exception
     */
    public function send(string $to, string $subject, string $body, array $options = []): array
    {
        $baseUrl = $this->region === 'eu' 
            ? 'https://api.eu.mailgun.net/v3'
            : 'https://api.mailgun.net/v3';
        
        $endpoint = "$baseUrl/$this->domain/messages";

        // Build form data
        $data = [
            'from' => $options['from'] ?? "{$this->fromName} <{$this->fromEmail}>",
            'to' => $to,
            'subject' => $subject
        ];

        // Add HTML or text body
        if (!empty($options['html']) || strpos($body, '<') !== false) {
            $data['html'] = $body;
        } else {
            $data['text'] = $body;
        }

        // Add CC
        if (!empty($options['cc'])) {
            $data['cc'] = is_array($options['cc']) ? implode(',', $options['cc']) : $options['cc'];
        }

        // Add BCC
        if (!empty($options['bcc'])) {
            $data['bcc'] = is_array($options['bcc']) ? implode(',', $options['bcc']) : $options['bcc'];
        }

        // Add reply-to
        if (!empty($options['reply_to'])) {
            $data['h:Reply-To'] = is_array($options['reply_to']) ? implode(',', $options['reply_to']) : $options['reply_to'];
        }

        // Add custom variables for tracking
        if (!empty($options['variables'])) {
            foreach ($options['variables'] as $key => $value) {
                $data["v:$key"] = $value;
            }
        }

        // Add tags for analytics (max 3 tags)
        if (!empty($options['tags'])) {
            $tags = is_array($options['tags']) ? array_slice($options['tags'], 0, 3) : [$options['tags']];
            foreach ($tags as $tag) {
                $data['o:tag'][] = $tag;
            }
        }

        // Tracking options
        if (isset($options['track_clicks'])) {
            $data['o:tracking-clicks'] = $options['track_clicks'] ? 'yes' : 'no';
        }

        if (isset($options['track_opens'])) {
            $data['o:tracking-opens'] = $options['track_opens'] ? 'yes' : 'no';
        }

        // Send request
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_USERPWD, "api:$this->apiKey");
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            Logger::error('Mailgun cURL error', ['error' => $curlError]);
            throw new \Exception("Failed to send email via Mailgun: $curlError");
        }

        if ($httpCode >= 400) {
            $error = json_decode($response, true);
            $errorMsg = $error['message'] ?? 'Unknown error';
            Logger::error('Mailgun API error', [
                'status' => $httpCode,
                'response' => $response
            ]);
            throw new \Exception("Mailgun error: $errorMsg (HTTP $httpCode)");
        }

        $result = json_decode($response, true);

        Logger::info('Email sent via Mailgun', [
            'to' => $to,
            'subject' => $subject,
            'message_id' => $result['id'] ?? null
        ]);

        return [
            'success' => true,
            'message_id' => $result['id'] ?? null,
            'message' => $result['message'] ?? 'Queued',
            'provider' => 'mailgun'
        ];
    }

    /**
     * Validate email address via Mailgun
     * 
     * @param string $email Email to validate
     * @return array Validation result
     */
    public function validateEmail(string $email): array
    {
        $baseUrl = $this->region === 'eu' 
            ? 'https://api.eu.mailgun.net/v4'
            : 'https://api.mailgun.net/v4';
        
        $endpoint = "$baseUrl/address/validate";

        $ch = curl_init($endpoint . '?' . http_build_query(['address' => $email]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "api:$this->apiKey");
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return ['valid' => false, 'reason' => 'Validation failed'];
        }

        $result = json_decode($response, true);

        return [
            'valid' => $result['result'] === 'deliverable',
            'risk' => $result['risk'] ?? 'unknown',
            'reason' => $result['reason'] ?? '',
            'details' => $result
        ];
    }

    /**
     * Check if service is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && 
               !empty($this->domain) && 
               !empty($this->fromEmail);
    }

    /**
     * Get provider name
     */
    public function getProviderName(): string
    {
        return 'mailgun';
    }

    /**
     * Send OTP email
     */
    public function sendOTP(string $to, string $otp, array $options = []): array
    {
        $subject = $options['subject'] ?? 'Your Verification Code';
        $body = $options['body'] ?? "Your OTP code is: <strong>$otp</strong><br><br>Valid for 10 minutes.";

        return $this->send($to, $subject, $body, array_merge($options, [
            'tags' => ['otp', 'verification'],
            'track_clicks' => false,
            'track_opens' => true
        ]));
    }
}
