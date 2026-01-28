<?php

namespace PHPFrarm\Core\Notifications\Services;

use PHPFrarm\Core\Logger;

/**
 * Postmark Email Service
 * 
 * Sends emails via Postmark API.
 * Known for excellent deliverability and speed.
 * 
 * Features:
 * - Fast delivery (< 2 seconds average)
 * - Excellent deliverability rates
 * - Template support
 * - Real-time webhooks
 * - Detailed analytics
 * - Bounce/spam tracking
 * 
 * Pricing: $1.25 per 1,000 emails (first 100 free/month)
 * 
 * Setup:
 * 1. Sign up at postmarkapp.com
 * 2. Create a server
 * 3. Verify sender signature (email/domain)
 * 4. Get Server API Token
 * 
 * @package PHPFrarm\Modules\Notification\Services
 */
class PostmarkService implements NotificationServiceInterface
{
    private string $apiToken;
    private string $fromEmail;
    private string $fromName;

    public function __construct()
    {
        $this->apiToken = env('POSTMARK_API_TOKEN', '');
        $this->fromEmail = env('POSTMARK_FROM_EMAIL', '');
        $this->fromName = env('POSTMARK_FROM_NAME', 'Application');
    }

    /**
     * Send email via Postmark
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
        $endpoint = 'https://api.postmarkapp.com/email';

        // Build request payload
        $payload = [
            'From' => $options['from'] ?? "{$this->fromName} <{$this->fromEmail}>",
            'To' => $to,
            'Subject' => $subject
        ];

        // Add HTML or text body
        if (!empty($options['html']) || strpos($body, '<') !== false) {
            $payload['HtmlBody'] = $body;
        } else {
            $payload['TextBody'] = $body;
        }

        // Add CC
        if (!empty($options['cc'])) {
            $payload['Cc'] = is_array($options['cc']) ? implode(',', $options['cc']) : $options['cc'];
        }

        // Add BCC
        if (!empty($options['bcc'])) {
            $payload['Bcc'] = is_array($options['bcc']) ? implode(',', $options['bcc']) : $options['bcc'];
        }

        // Add reply-to
        if (!empty($options['reply_to'])) {
            $payload['ReplyTo'] = is_array($options['reply_to']) ? implode(',', $options['reply_to']) : $options['reply_to'];
        }

        // Add tag for analytics (single tag only)
        if (!empty($options['tag'])) {
            $payload['Tag'] = is_array($options['tag']) ? $options['tag'][0] : $options['tag'];
        }

        // Add metadata for tracking
        if (!empty($options['metadata'])) {
            $payload['Metadata'] = $options['metadata'];
        }

        // Tracking options
        if (isset($options['track_opens'])) {
            $payload['TrackOpens'] = (bool)$options['track_opens'];
        }

        if (isset($options['track_links'])) {
            $payload['TrackLinks'] = $options['track_links'] ? 'HtmlAndText' : 'None';
        }

        // Message stream (transactional, broadcast, etc.)
        if (!empty($options['message_stream'])) {
            $payload['MessageStream'] = $options['message_stream'];
        }

        // Send request
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-Postmark-Server-Token: ' . $this->apiToken
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            Logger::error('Postmark cURL error', ['error' => $curlError]);
            throw new \Exception("Failed to send email via Postmark: $curlError");
        }

        if ($httpCode >= 400) {
            $error = json_decode($response, true);
            $errorMsg = $error['Message'] ?? 'Unknown error';
            $errorCode = $error['ErrorCode'] ?? 0;
            Logger::error('Postmark API error', [
                'status' => $httpCode,
                'error_code' => $errorCode,
                'response' => $response
            ]);
            throw new \Exception("Postmark error: $errorMsg (Code: $errorCode)");
        }

        $result = json_decode($response, true);

        Logger::info('Email sent via Postmark', [
            'to' => $to,
            'subject' => $subject,
            'message_id' => $result['MessageID'] ?? null,
            'submitted_at' => $result['SubmittedAt'] ?? null
        ]);

        return [
            'success' => true,
            'message_id' => $result['MessageID'] ?? null,
            'submitted_at' => $result['SubmittedAt'] ?? null,
            'to' => $result['To'] ?? $to,
            'provider' => 'postmark'
        ];
    }

    /**
     * Send email using Postmark template
     * 
     * @param string $to Recipient email
     * @param int|string $templateId Template ID or alias
     * @param array $templateData Data for template variables
     * @param array $options Additional options
     * @return array Result
     */
    public function sendWithTemplate(string $to, $templateId, array $templateData, array $options = []): array
    {
        $endpoint = 'https://api.postmarkapp.com/email/withTemplate';

        $payload = [
            'From' => $options['from'] ?? "{$this->fromName} <{$this->fromEmail}>",
            'To' => $to,
            'TemplateId' => is_numeric($templateId) ? (int)$templateId : null,
            'TemplateAlias' => is_string($templateId) && !is_numeric($templateId) ? $templateId : null,
            'TemplateModel' => $templateData
        ];

        // Remove null values
        $payload = array_filter($payload, fn($v) => $v !== null);

        // Add options
        if (!empty($options['tag'])) {
            $payload['Tag'] = is_array($options['tag']) ? $options['tag'][0] : $options['tag'];
        }

        if (!empty($options['metadata'])) {
            $payload['Metadata'] = $options['metadata'];
        }

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-Postmark-Server-Token: ' . $this->apiToken
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            $error = json_decode($response, true);
            throw new \Exception($error['Message'] ?? 'Template send failed');
        }

        $result = json_decode($response, true);

        return [
            'success' => true,
            'message_id' => $result['MessageID'] ?? null,
            'provider' => 'postmark'
        ];
    }

    /**
     * Check if service is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiToken) && !empty($this->fromEmail);
    }

    /**
     * Get provider name
     */
    public function getProviderName(): string
    {
        return 'postmark';
    }

    /**
     * Send OTP email
     */
    public function sendOTP(string $to, string $otp, array $options = []): array
    {
        $subject = $options['subject'] ?? 'Your Verification Code';
        $body = $options['body'] ?? "Your OTP code is: <strong>$otp</strong><br><br>Valid for 10 minutes.";

        return $this->send($to, $subject, $body, array_merge($options, [
            'tag' => 'otp-verification',
            'track_opens' => true,
            'track_links' => false,
            'message_stream' => 'outbound'
        ]));
    }
}
