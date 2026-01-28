<?php

/**
 * Notification Services Configuration
 * 
 * Configuration for email (SendGrid) and SMS (Twilio) notification providers.
 * 
 * @package PHPFrarm\Config
 */

return [
    
    /*
    |--------------------------------------------------------------------------
    | Email Configuration (SendGrid)
    |--------------------------------------------------------------------------
    |
    | SendGrid API v3 configuration for sending transactional emails.
    | Get your API key from: https://app.sendgrid.com/settings/api_keys
    |
    */
    'email' => [
        'enabled' => env('MAIL_ENABLED', true),
        'provider' => env('MAIL_PROVIDER', 'smtp'), // smtp, sendgrid, amazon_ses, mailgun, postmark
        
        // Fallback providers (tried in order if primary fails)
        'fallback_providers' => env('MAIL_FALLBACK_PROVIDERS', ''),
        
        // SMTP Configuration (MailHog, Gmail, etc.)
        'smtp' => [
            'host' => env('MAIL_HOST', 'mailhog'),
            'port' => env('MAIL_PORT', 1025),
            'username' => env('MAIL_USERNAME', ''),
            'password' => env('MAIL_PASSWORD', ''),
            'encryption' => env('MAIL_ENCRYPTION', ''), // tls, ssl, or empty for none
            'from_email' => env('MAIL_FROM_ADDRESS', 'noreply@phpfrarm.com'),
            'from_name' => env('MAIL_FROM_NAME', 'PHPFrarm'),
            'timeout' => env('MAIL_TIMEOUT', 5), // SMTP connection timeout in seconds
        ],
        
        'sendgrid' => [
            'api_key' => env('SENDGRID_API_KEY', ''),
            'from_email' => env('MAIL_FROM_ADDRESS', 'noreply@phpfrarm.com'),
            'from_name' => env('MAIL_FROM_NAME', 'PHPFrarm'),
            
            // SendGrid Dynamic Templates (optional)
            'templates' => [
                'otp' => env('SENDGRID_OTP_TEMPLATE_ID', ''),
                'welcome' => env('SENDGRID_WELCOME_TEMPLATE_ID', ''),
                'password_reset' => env('SENDGRID_PASSWORD_RESET_TEMPLATE_ID', ''),
                'email_verification' => env('SENDGRID_EMAIL_VERIFICATION_TEMPLATE_ID', ''),
            ],
            
            // Timeout for SendGrid API requests (seconds)
            'timeout' => env('MAIL_TIMEOUT', 3), // Reduced for development
            
            // Sandbox mode (for testing - emails won't be sent)
            'sandbox_mode' => env('SENDGRID_SANDBOX_MODE', false),
        ],
        
        'amazon_ses' => [
            'access_key_id' => env('AWS_SES_ACCESS_KEY_ID', ''),
            'secret_access_key' => env('AWS_SES_SECRET_ACCESS_KEY', ''),
            'region' => env('AWS_SES_REGION', 'us-east-1'),
            'from_email' => env('AWS_SES_FROM_EMAIL', env('MAIL_FROM_ADDRESS', 'noreply@phpfrarm.com')),
            'from_name' => env('AWS_SES_FROM_NAME', env('MAIL_FROM_NAME', 'PHPFrarm')),
            
            // Use SMTP instead of API (optional)
            'use_smtp' => env('AWS_SES_USE_SMTP', false),
            'smtp_host' => env('AWS_SES_SMTP_HOST', 'email-smtp.us-east-1.amazonaws.com'),
            'smtp_port' => env('AWS_SES_SMTP_PORT', 587),
            'smtp_username' => env('AWS_SES_SMTP_USERNAME', ''),
            'smtp_password' => env('AWS_SES_SMTP_PASSWORD', ''),
            
            // Configuration set (for tracking)
            'configuration_set' => env('AWS_SES_CONFIGURATION_SET', ''),
            
            'timeout' => 10,
        ],
        
        'mailgun' => [
            'api_key' => env('MAILGUN_API_KEY', ''),
            'domain' => env('MAILGUN_DOMAIN', ''),
            'region' => env('MAILGUN_REGION', 'us'), // us or eu
            'from_email' => env('MAILGUN_FROM_EMAIL', env('MAIL_FROM_ADDRESS', 'noreply@phpfrarm.com')),
            'from_name' => env('MAILGUN_FROM_NAME', env('MAIL_FROM_NAME', 'PHPFrarm')),
            
            // Enable email validation API
            'enable_validation' => env('MAILGUN_ENABLE_VALIDATION', false),
            
            // Tracking
            'tracking' => [
                'opens' => env('MAILGUN_TRACK_OPENS', true),
                'clicks' => env('MAILGUN_TRACK_CLICKS', true),
            ],
            
            'timeout' => 10,
        ],
        
        'postmark' => [
            'api_token' => env('POSTMARK_API_TOKEN', ''),
            'from_email' => env('POSTMARK_FROM_EMAIL', env('MAIL_FROM_ADDRESS', 'noreply@phpfrarm.com')),
            'from_name' => env('POSTMARK_FROM_NAME', env('MAIL_FROM_NAME', 'PHPFrarm')),
            
            // Postmark templates
            'templates' => [
                'otp' => env('POSTMARK_OTP_TEMPLATE_ID', ''),
                'welcome' => env('POSTMARK_WELCOME_TEMPLATE_ID', ''),
                'password_reset' => env('POSTMARK_PASSWORD_RESET_TEMPLATE_ID', ''),
            ],
            
            // Message stream (transactional, broadcasts, etc.)
            'message_stream' => env('POSTMARK_MESSAGE_STREAM', 'outbound'),
            
            // Tracking
            'track_opens' => env('POSTMARK_TRACK_OPENS', true),
            'track_links' => env('POSTMARK_TRACK_LINKS', 'HtmlAndText'),
            
            'timeout' => 10,
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | SMS Configuration (Twilio)
    |--------------------------------------------------------------------------
    |
    | Twilio API configuration for sending SMS messages.
    | Get your credentials from: https://console.twilio.com/
    |
    | Phone numbers must be in E.164 format: +[country code][number]
    | Examples: +14155552671 (US), +447911123456 (UK), +919876543210 (India)
    |
    */
    'sms' => [
        'enabled' => env('SMS_ENABLED', true),
        'provider' => env('SMS_PROVIDER', 'twilio'), // twilio, msg91, vonage
        
        // Fallback providers (tried in order if primary fails)
        'fallback_providers' => env('SMS_FALLBACK_PROVIDERS', 'vonage,msg91'),
        
        'twilio' => [
            'account_sid' => env('TWILIO_ACCOUNT_SID', ''),
            'auth_token' => env('TWILIO_AUTH_TOKEN', ''),
            'from_number' => env('TWILIO_FROM_NUMBER', ''),
            
            // Messaging Service SID (alternative to from_number)
            'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID', ''),
            
            // Status callback URL (for delivery reports)
            'status_callback_url' => env('TWILIO_STATUS_CALLBACK_URL', ''),
            
            // Timeout for Twilio API requests (seconds)
            'timeout' => 10,
            
            // Enable Twilio Lookup API (phone number validation)
            'enable_lookup' => env('TWILIO_ENABLE_LOOKUP', false),
        ],
        
        'msg91' => [
            'auth_key' => env('MSG91_AUTH_KEY', ''),
            'sender_id' => env('MSG91_SENDER_ID', ''), // 6-character brand name
            'template_id' => env('MSG91_TEMPLATE_ID', ''), // DLT approved template
            'route' => env('MSG91_ROUTE', 4), // 4 = Transactional, 1 = Promotional
            
            // Enable voice OTP (fallback if SMS fails)
            'enable_voice_otp' => env('MSG91_ENABLE_VOICE_OTP', false),
            
            // Timeout
            'timeout' => 10,
            
            // India DLT compliance (required for sending SMS in India)
            'dlt_entity_id' => env('MSG91_DLT_ENTITY_ID', ''),
            'dlt_template_id' => env('MSG91_DLT_TEMPLATE_ID', ''),
        ],
        
        'vonage' => [
            'api_key' => env('VONAGE_API_KEY', ''),
            'api_secret' => env('VONAGE_API_SECRET', ''),
            'from_number' => env('VONAGE_FROM_NUMBER', ''), // Or brand name
            
            // Use Verify API (Vonage generates and manages OTP)
            'use_verify_api' => env('VONAGE_USE_VERIFY_API', true),
            
            // Verify API settings
            'verify' => [
                'code_length' => env('VONAGE_VERIFY_CODE_LENGTH', 6),
                'pin_expiry' => env('VONAGE_VERIFY_PIN_EXPIRY', 300), // 5 minutes
                'next_event_wait' => env('VONAGE_VERIFY_NEXT_EVENT_WAIT', 60), // Wait before next attempt
            ],
            
            // Status callback URL
            'status_callback_url' => env('VONAGE_STATUS_CALLBACK_URL', ''),
            
            'timeout' => 10,
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | WhatsApp Configuration
    |--------------------------------------------------------------------------
    |
    | WhatsApp Business API configuration for sending messages.
    | Supports multiple providers: Twilio, MSG91, Meta Official, Vonage
    |
    | WhatsApp is ideal for:
    | - OTP delivery (higher read rates than SMS)
    | - Transactional messages
    | - Order updates, appointment reminders
    |
    | Requirements:
    | - WhatsApp Business Account approved
    | - Message templates approved by WhatsApp
    |
    */
    'whatsapp' => [
        'enabled' => env('WHATSAPP_ENABLED', false),
        'provider' => env('WHATSAPP_PROVIDER', 'twilio'), // twilio, msg91, meta, vonage
        
        'twilio' => [
            'account_sid' => env('TWILIO_ACCOUNT_SID', ''),
            'auth_token' => env('TWILIO_AUTH_TOKEN', ''),
            'from_number' => env('TWILIO_WHATSAPP_NUMBER', ''), // Your WhatsApp number
            
            // Content Templates (for template messages)
            'templates' => [
                'otp' => env('TWILIO_WHATSAPP_OTP_TEMPLATE', ''),
            ],
            
            'timeout' => 10,
        ],
        
        'msg91' => [
            'auth_key' => env('MSG91_AUTH_KEY', ''),
            'sender_id' => env('MSG91_WHATSAPP_SENDER_ID', ''),
            
            // Approved templates
            'templates' => [
                'otp' => env('MSG91_WHATSAPP_OTP_TEMPLATE', ''),
            ],
            
            'timeout' => 10,
        ],
        
        'meta' => [
            'access_token' => env('WHATSAPP_META_ACCESS_TOKEN', ''),
            'phone_number_id' => env('WHATSAPP_META_PHONE_NUMBER_ID', ''),
            'business_account_id' => env('WHATSAPP_META_BUSINESS_ACCOUNT_ID', ''),
            'verify_token' => env('WHATSAPP_META_VERIFY_TOKEN', ''), // For webhook verification
            'language' => env('WHATSAPP_META_LANGUAGE', 'en'),
            
            // Approved templates
            'templates' => [
                'otp' => env('WHATSAPP_META_OTP_TEMPLATE', ''),
            ],
            
            'timeout' => 10,
        ],
        
        'vonage' => [
            'api_key' => env('VONAGE_API_KEY', ''),
            'api_secret' => env('VONAGE_API_SECRET', ''),
            'from_number' => env('VONAGE_WHATSAPP_NUMBER', ''),
            
            // Approved templates
            'templates' => [
                'otp' => env('VONAGE_WHATSAPP_OTP_TEMPLATE', ''),
            ],
            
            'timeout' => 10,
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | OTP Notifications Configuration
    |--------------------------------------------------------------------------
    |
    | Specific configuration for OTP (One-Time Password) messages.
    |
    */
    'otp' => [
        // OTP expiry time (seconds)
        'expiry' => env('OTP_EXPIRY', 300), // 5 minutes
        
        // Maximum OTP retry attempts
        'max_attempts' => env('OTP_MAX_ATTEMPTS', 3),
        
        // OTP length (digits)
        'length' => 6,
        
        // Rate limiting for OTP requests
        'rate_limit' => [
            'enabled' => true,
            'max_requests_per_hour' => 5,
            'max_requests_per_day' => 10,
        ],
        
        // Auto-select appropriate channel based on identifier
        'auto_detect_channel' => true,
        
        // Fallback to email if SMS fails
        'fallback_to_email' => env('OTP_FALLBACK_TO_EMAIL', false),
        
        // Include OTP in development response (NEVER in production)
        'include_in_response' => env('APP_ENV', 'production') !== 'production',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Notification Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Queue notifications for asynchronous sending to improve performance.
    |
    */
    'queue' => [
        'enabled' => env('NOTIFICATION_QUEUE_ENABLED', false),
        'connection' => env('NOTIFICATION_QUEUE_CONNECTION', 'redis'),
        'queue_name' => 'notifications',
        
        // Retry failed notifications
        'retry' => [
            'enabled' => true,
            'max_attempts' => 3,
            'backoff_seconds' => [60, 300, 900], // 1min, 5min, 15min
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Monitoring & Logging
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        // Log all notification attempts
        'log_attempts' => true,
        
        // Log notification failures
        'log_failures' => true,
        
        // Alert on delivery failures
        'alert_on_failure' => env('NOTIFICATION_ALERT_ON_FAILURE', false),
        'alert_webhook_url' => env('NOTIFICATION_ALERT_WEBHOOK', ''),
        
        // Track delivery statistics
        'track_statistics' => true,
        'statistics_retention_days' => 30,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Testing Configuration
    |--------------------------------------------------------------------------
    */
    'testing' => [
        // Use mock services in testing
        'use_mocks' => env('APP_ENV') === 'testing',
        
        // Test phone numbers (bypass rate limits)
        'test_phone_numbers' => [
            '+15005550006', // Twilio magic number (valid)
            '+15005550007', // Twilio magic number (invalid)
        ],
        
        // Test email addresses
        'test_emails' => [
            'test@phpfrarm.com',
            'staging@phpfrarm.com',
        ],
    ],
    
];
