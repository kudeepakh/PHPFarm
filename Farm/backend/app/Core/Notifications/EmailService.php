<?php

namespace PHPFrarm\Core\Notifications;

use PHPFrarm\Core\Logger;

/**
 * Email Service - Multi-driver Support (SMTP / SendGrid)
 * 
 * Sends emails using SMTP (for local dev with Mailhog) or SendGrid API (production).
 * Driver is selected based on MAIL_DRIVER environment variable.
 * 
 * @package PHPFrarm\Core\Notifications
 */
class EmailService implements NotificationServiceInterface
{
    private string $driver;
    private string $apiKey;
    private string $fromEmail;
    private string $fromName;
    private bool $enabled;
    private string $otpTemplate;
    
    // SMTP settings
    private string $smtpHost;
    private int $smtpPort;
    private string $smtpUsername;
    private string $smtpPassword;
    private string $smtpEncryption;

    public function __construct()
    {
        $this->driver = $_ENV['MAIL_DRIVER'] ?? 'smtp';
        $this->apiKey = $_ENV['SENDGRID_API_KEY'] ?? '';
        $this->fromEmail = $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@phpfrarm.local';
        $this->fromName = $_ENV['MAIL_FROM_NAME'] ?? 'PHPFrarm';
        $this->enabled = ($_ENV['MAIL_ENABLED'] ?? 'true') === 'true';
        $this->otpTemplate = $_ENV['SENDGRID_OTP_TEMPLATE_ID'] ?? '';
        
        // SMTP configuration
        $this->smtpHost = $_ENV['MAIL_HOST'] ?? 'mailhog';
        $this->smtpPort = (int)($_ENV['MAIL_PORT'] ?? 1025);
        $this->smtpUsername = $_ENV['MAIL_USERNAME'] ?? '';
        $this->smtpPassword = $_ENV['MAIL_PASSWORD'] ?? '';
        $this->smtpEncryption = $_ENV['MAIL_ENCRYPTION'] ?? '';
    }

    /**
     * Send email via configured driver (SMTP or SendGrid)
     */
    public function send(string $recipient, string $subject, string $message, array $options = []): array
    {
        if (!$this->enabled) {
            Logger::info('Email service disabled, skipping send', [
                'recipient' => $recipient,
            ]);
            return [
                'success' => true,
                'message_id' => 'disabled_' . bin2hex(random_bytes(8)),
                'error' => null
            ];
        }

        // Route to appropriate driver
        if ($this->driver === 'smtp') {
            return $this->sendViaSMTP($recipient, $subject, $message, $options);
        } else {
            return $this->sendViaSendGrid($recipient, $subject, $message, $options);
        }
    }

    /**
     * Send email via SMTP (for local development with Mailhog)
     */
    private function sendViaSMTP(string $recipient, string $subject, string $message, array $options = []): array
    {
        try {
            // Build email headers
            $headers = [];
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-type: text/html; charset=UTF-8';
            $headers[] = 'From: ' . ($options['from_name'] ?? $this->fromName) . ' <' . ($options['from_email'] ?? $this->fromEmail) . '>';
            $headers[] = 'Reply-To: ' . ($options['reply_to'] ?? $this->fromEmail);
            $headers[] = 'X-Mailer: PHPFrarm/1.0';
            
            // Use fsockopen for direct SMTP connection
            $messageId = $this->sendSMTPDirect($recipient, $subject, $message, $headers);

            Logger::info('Email sent via SMTP', [
                'recipient' => $recipient,
                'subject' => $subject,
                'message_id' => $messageId,
                'smtp_host' => $this->smtpHost,
            ]);

            return [
                'success' => true,
                'message_id' => $messageId,
                'error' => null
            ];

        } catch (\Exception $e) {
            Logger::error('SMTP email send failed', [
                'recipient' => $recipient,
                'error' => $e->getMessage(),
                'smtp_host' => $this->smtpHost,
            ]);

            return [
                'success' => false,
                'message_id' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Direct SMTP connection (works with Mailhog without authentication)
     */
    private function sendSMTPDirect(string $recipient, string $subject, string $body, array $headers): string
    {
        $messageId = 'smtp_' . bin2hex(random_bytes(16)) . '@phpfrarm.local';
        
        Logger::info('Attempting SMTP connection', [
            'host' => $this->smtpHost,
            'port' => $this->smtpPort,
            'recipient' => $recipient,
            'message_id' => $messageId,
        ]);
        
        $socket = @fsockopen($this->smtpHost, $this->smtpPort, $errno, $errstr, 10);
        if (!$socket) {
            Logger::error('SMTP connection failed', [
                'host' => $this->smtpHost,
                'port' => $this->smtpPort,
                'error' => "$errstr ($errno)",
            ]);
            throw new \Exception("Could not connect to SMTP server: $errstr ($errno)");
        }

        Logger::info('SMTP connection established', ['host' => $this->smtpHost]);

        try {
            // Read greeting
            $this->smtpReadResponse($socket, 220);
            Logger::debug('SMTP greeting received');

            // EHLO
            fwrite($socket, "EHLO phpfrarm.local\r\n");
            $this->smtpReadResponse($socket, 250);
            Logger::debug('SMTP EHLO successful');

            // MAIL FROM
            fwrite($socket, "MAIL FROM:<{$this->fromEmail}>\r\n");
            $this->smtpReadResponse($socket, 250);
            Logger::debug('SMTP MAIL FROM successful', ['from' => $this->fromEmail]);

            // RCPT TO
            fwrite($socket, "RCPT TO:<{$recipient}>\r\n");
            $this->smtpReadResponse($socket, 250);
            Logger::debug('SMTP RCPT TO successful', ['to' => $recipient]);

            // DATA
            fwrite($socket, "DATA\r\n");
            $this->smtpReadResponse($socket, 354);
            Logger::debug('SMTP DATA command successful');

            // Build message
            $email = "To: {$recipient}\r\n";
            $email .= "Subject: {$subject}\r\n";
            $email .= "Message-ID: <{$messageId}>\r\n";
            $email .= implode("\r\n", $headers) . "\r\n";
            $email .= "\r\n";
            $email .= $body;
            $email .= "\r\n.\r\n";

            fwrite($socket, $email);
            $this->smtpReadResponse($socket, 250);
            Logger::debug('SMTP message sent successfully');

            // QUIT
            fwrite($socket, "QUIT\r\n");
            fclose($socket);
            
            Logger::info('SMTP transaction completed successfully', ['message_id' => $messageId]);

        } catch (\Exception $e) {
            fclose($socket);
            Logger::error('SMTP transaction failed', [
                'error' => $e->getMessage(),
                'message_id' => $messageId,
            ]);
            throw $e;
        }

        return $messageId;
    }

    /**
     * Read SMTP response and verify expected code
     */
    private function smtpReadResponse($socket, int $expectedCode): string
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }

        $code = (int)substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new \Exception("SMTP error: expected $expectedCode, got $code - $response");
        }

        return $response;
    }

    /**
     * Send email via SendGrid API (production)
     */
    private function sendViaSendGrid(string $recipient, string $subject, string $message, array $options = []): array
    {
        if (!$this->isConfigured()) {
            Logger::warning('SendGrid not configured, skipping send', [
                'recipient' => $recipient,
            ]);
            return [
                'success' => false,
                'message_id' => null,
                'error' => 'SendGrid API key not configured'
            ];
        }

        try {
            $payload = [
                'personalizations' => [
                    [
                        'to' => [
                            ['email' => $recipient]
                        ],
                        'subject' => $subject
                    ]
                ],
                'from' => [
                    'email' => $options['from_email'] ?? $this->fromEmail,
                    'name' => $options['from_name'] ?? $this->fromName
                ],
                'content' => [
                    [
                        'type' => $options['content_type'] ?? 'text/html',
                        'value' => $message
                    ]
                ]
            ];

            // Add template if specified
            if (!empty($options['template_id'])) {
                $payload['template_id'] = $options['template_id'];
                
                if (!empty($options['template_data'])) {
                    $payload['personalizations'][0]['dynamic_template_data'] = $options['template_data'];
                }
                
                unset($payload['content']);
            }

            if (!empty($options['reply_to'])) {
                $payload['reply_to'] = ['email' => $options['reply_to']];
            }

            $response = $this->sendRequest($payload);

            Logger::info('Email sent via SendGrid', [
                'recipient' => $recipient,
                'subject' => $subject,
                'message_id' => $response['message_id'],
            ]);

            return [
                'success' => true,
                'message_id' => $response['message_id'],
                'error' => null
            ];

        } catch (\Exception $e) {
            Logger::error('SendGrid email send failed', [
                'recipient' => $recipient,
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
     * Send OTP email
     */
    public function sendOTP(string $recipient, string $otp, string $purpose = 'verification'): array
    {
        $subject = $this->getOTPSubject($purpose);
        $message = $this->getOTPMessage($otp, $purpose);

        $options = [];
        
        // Use template if configured
        if (!empty($this->otpTemplate)) {
            $options = [
                'template_id' => $this->otpTemplate,
                'template_data' => [
                    'otp' => $otp,
                    'purpose' => $purpose,
                    'expires_in' => '5 minutes',
                    'app_name' => $this->fromName
                ]
            ];
        }

        return $this->send($recipient, $subject, $message, $options);
    }

    /**
     * Check if email service is configured
     */
    public function isConfigured(): bool
    {
        // Check based on configured driver
        if ($this->driver === 'smtp') {
            // For SMTP, check if host is configured
            return !empty($this->smtpHost);
        } else {
            // For SendGrid, check if API key is configured
            return !empty($this->apiKey);
        }
    }

    /**
     * Get service type
     */
    public function getType(): string
    {
        return 'email';
    }

    /**
     * Get provider name
     */
    public function getProvider(): string
    {
        return $this->driver;
    }

    /**
     * Send HTTP request to SendGrid API
     */
    private function sendRequest(array $payload): array
    {
        $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HEADER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            $error = json_decode($body, true);
            throw new \Exception($error['errors'][0]['message'] ?? 'SendGrid API error: ' . $httpCode);
        }

        // Extract X-Message-Id from headers
        $messageId = null;
        if (preg_match('/X-Message-Id: (.+)/i', $headers, $matches)) {
            $messageId = trim($matches[1]);
        }

        return [
            'message_id' => $messageId ?? 'sg_' . bin2hex(random_bytes(16)),
            'status_code' => $httpCode
        ];
    }

    /**
     * Get OTP email subject based on purpose
     */
    private function getOTPSubject(string $purpose): string
    {
        $subjects = [
            'login' => 'Your Login Code',
            'registration' => 'Verify Your Email',
            'password_reset' => 'Reset Your Password',
            'phone_verification' => 'Verify Your Phone Number',
            'email_verification' => 'Verify Your Email Address',
            'two_factor' => 'Two-Factor Authentication Code',
        ];

        return $subjects[$purpose] ?? 'Your Verification Code';
    }

    /**
     * Get OTP email message body
     */
    private function getOTPMessage(string $otp, string $purpose): string
    {
        $appName = $this->fromName;
        
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .otp-box { background: #f4f4f4; border-left: 4px solid #007bff; padding: 20px; margin: 20px 0; }
                .otp-code { font-size: 32px; font-weight: bold; color: #007bff; letter-spacing: 5px; }
                .warning { color: #d9534f; font-size: 14px; margin-top: 20px; }
                .footer { color: #777; font-size: 12px; margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px; }
            </style>
        </head>
        <body>
            <div class="container">
                <h2>Verification Code</h2>
                <p>You requested a verification code for {$appName}.</p>
                
                <div class="otp-box">
                    <p style="margin: 0; font-size: 14px; color: #666;">Your verification code is:</p>
                    <p class="otp-code">{$otp}</p>
                    <p style="margin: 0; font-size: 14px; color: #666;">This code will expire in 5 minutes.</p>
                </div>
                
                <p>If you didn't request this code, please ignore this email or contact support if you have concerns.</p>
                
                <div class="warning">
                    <strong>Security Warning:</strong><br>
                    Never share this code with anyone. {$appName} will never ask for your verification code.
                </div>
                
                <div class="footer">
                    <p>This is an automated message from {$appName}. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }
}
