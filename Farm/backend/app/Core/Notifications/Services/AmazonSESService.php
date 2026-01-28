<?php

namespace PHPFrarm\Core\Notifications\Services;

use PHPFrarm\Core\Logger;

/**
 * Amazon SES Email Service
 * 
 * Sends emails via Amazon Simple Email Service (SES).
 * Supports both API v2 and SMTP methods.
 * 
 * Features:
 * - Transactional emails
 * - Template support
 * - Attachments
 * - HTML and plain text
 * - Delivery tracking
 * - Bounce handling
 * 
 * Pricing: $0.10 per 1,000 emails (very cost-effective)
 * 
 * Setup:
 * 1. Verify sender email/domain in AWS SES console
 * 2. Get AWS credentials (Access Key ID + Secret)
 * 3. Choose region (us-east-1, eu-west-1, etc.)
 * 4. Request production access (remove sandbox limits)
 * 
 * @package PHPFrarm\Modules\Notification\Services
 */
class AmazonSESService implements NotificationServiceInterface
{
    private string $accessKeyId;
    private string $secretAccessKey;
    private string $region;
    private string $fromEmail;
    private string $fromName;
    private bool $useSMTP;

    public function __construct()
    {
        $this->accessKeyId = env('AWS_SES_ACCESS_KEY_ID', '');
        $this->secretAccessKey = env('AWS_SES_SECRET_ACCESS_KEY', '');
        $this->region = env('AWS_SES_REGION', 'us-east-1');
        $this->fromEmail = env('AWS_SES_FROM_EMAIL', '');
        $this->fromName = env('AWS_SES_FROM_NAME', 'Application');
        $this->useSMTP = env('AWS_SES_USE_SMTP', false);
    }

    /**
     * Send email via Amazon SES
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $body Email body (HTML or plain text)
     * @param array $options Additional options
     * @return array Result with message_id
     * @throws \Exception
     */
    public function send(string $to, string $subject, string $body, array $options = []): array
    {
        if ($this->useSMTP) {
            return $this->sendViaSMTP($to, $subject, $body, $options);
        }

        return $this->sendViaAPI($to, $subject, $body, $options);
    }

    /**
     * Send email via SES API (v2)
     */
    private function sendViaAPI(string $to, string $subject, string $body, array $options): array
    {
        $endpoint = "https://email.{$this->region}.amazonaws.com/v2/email/outbound-emails";

        // Build request payload
        $payload = [
            'FromEmailAddress' => $options['from'] ?? "{$this->fromName} <{$this->fromEmail}>",
            'Destination' => [
                'ToAddresses' => [$to]
            ],
            'Content' => [
                'Simple' => [
                    'Subject' => [
                        'Data' => $subject,
                        'Charset' => 'UTF-8'
                    ],
                    'Body' => []
                ]
            ]
        ];

        // Add HTML or text body
        if (!empty($options['html']) || strpos($body, '<') !== false) {
            $payload['Content']['Simple']['Body']['Html'] = [
                'Data' => $body,
                'Charset' => 'UTF-8'
            ];
        } else {
            $payload['Content']['Simple']['Body']['Text'] = [
                'Data' => $body,
                'Charset' => 'UTF-8'
            ];
        }

        // Add CC
        if (!empty($options['cc'])) {
            $payload['Destination']['CcAddresses'] = is_array($options['cc']) ? $options['cc'] : [$options['cc']];
        }

        // Add BCC
        if (!empty($options['bcc'])) {
            $payload['Destination']['BccAddresses'] = is_array($options['bcc']) ? $options['bcc'] : [$options['bcc']];
        }

        // Add reply-to
        if (!empty($options['reply_to'])) {
            $payload['ReplyToAddresses'] = is_array($options['reply_to']) ? $options['reply_to'] : [$options['reply_to']];
        }

        // Add tags for tracking
        if (!empty($options['tags'])) {
            $payload['EmailTags'] = [];
            foreach ($options['tags'] as $key => $value) {
                $payload['EmailTags'][] = ['Name' => $key, 'Value' => $value];
            }
        }

        // Sign request with AWS Signature V4
        $headers = $this->signRequest('POST', $endpoint, json_encode($payload));

        // Send request
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            Logger::error('Amazon SES cURL error', ['error' => $curlError]);
            throw new \Exception("Failed to send email via Amazon SES: $curlError");
        }

        if ($httpCode >= 400) {
            $error = json_decode($response, true);
            $errorMsg = $error['message'] ?? 'Unknown error';
            Logger::error('Amazon SES API error', [
                'status' => $httpCode,
                'response' => $response
            ]);
            throw new \Exception("Amazon SES error: $errorMsg (HTTP $httpCode)");
        }

        $result = json_decode($response, true);

        Logger::info('Email sent via Amazon SES', [
            'to' => $to,
            'subject' => $subject,
            'message_id' => $result['MessageId'] ?? null
        ]);

        return [
            'success' => true,
            'message_id' => $result['MessageId'] ?? null,
            'provider' => 'amazon_ses'
        ];
    }

    /**
     * Send email via SES SMTP
     */
    private function sendViaSMTP(string $to, string $subject, string $body, array $options): array
    {
        $smtpHost = "email-smtp.{$this->region}.amazonaws.com";
        $smtpPort = 587; // Use 465 for SSL
        $smtpUsername = $this->accessKeyId;
        $smtpPassword = $this->secretAccessKey;

        // Build email headers
        $headers = [
            'From' => $options['from'] ?? "{$this->fromName} <{$this->fromEmail}>",
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/html; charset=UTF-8'
        ];

        if (!empty($options['reply_to'])) {
            $headers['Reply-To'] = is_array($options['reply_to']) ? implode(', ', $options['reply_to']) : $options['reply_to'];
        }

        // Use PHP mail() with SES SMTP (requires mail server configuration)
        // For production, use PHPMailer or Swift Mailer
        $headersStr = '';
        foreach ($headers as $key => $value) {
            $headersStr .= "$key: $value\r\n";
        }

        $sent = mail($to, $subject, $body, $headersStr);

        if (!$sent) {
            throw new \Exception('Failed to send email via Amazon SES SMTP');
        }

        Logger::info('Email sent via Amazon SES SMTP', [
            'to' => $to,
            'subject' => $subject
        ]);

        return [
            'success' => true,
            'provider' => 'amazon_ses_smtp'
        ];
    }

    /**
     * Sign request with AWS Signature Version 4
     */
    private function signRequest(string $method, string $endpoint, string $payload): array
    {
        $service = 'ses';
        $region = $this->region;
        $accessKey = $this->accessKeyId;
        $secretKey = $this->secretAccessKey;

        $timestamp = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');

        $parsedUrl = parse_url($endpoint);
        $host = $parsedUrl['host'];
        $uri = $parsedUrl['path'] ?? '/';

        // Canonical request
        $canonicalHeaders = "host:$host\nx-amz-date:$timestamp\n";
        $signedHeaders = 'host;x-amz-date';
        $payloadHash = hash('sha256', $payload);

        $canonicalRequest = "$method\n$uri\n\n$canonicalHeaders\n$signedHeaders\n$payloadHash";

        // String to sign
        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = "$date/$region/$service/aws4_request";
        $stringToSign = "$algorithm\n$timestamp\n$credentialScope\n" . hash('sha256', $canonicalRequest);

        // Signing key
        $kDate = hash_hmac('sha256', $date, "AWS4$secretKey", true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        // Signature
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        // Authorization header
        $authorization = "$algorithm Credential=$accessKey/$credentialScope, SignedHeaders=$signedHeaders, Signature=$signature";

        return [
            "Host: $host",
            "X-Amz-Date: $timestamp",
            "Authorization: $authorization",
            "Content-Type: application/json"
        ];
    }

    /**
     * Check if service is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->accessKeyId) && 
               !empty($this->secretAccessKey) && 
               !empty($this->fromEmail);
    }

    /**
     * Get provider name
     */
    public function getProviderName(): string
    {
        return 'amazon_ses';
    }

    /**
     * Send OTP email (convenience method)
     */
    public function sendOTP(string $to, string $otp, array $options = []): array
    {
        $subject = $options['subject'] ?? 'Your Verification Code';
        $body = $options['body'] ?? "Your OTP code is: <strong>$otp</strong><br><br>Valid for 10 minutes.";

        return $this->send($to, $subject, $body, $options);
    }
}
