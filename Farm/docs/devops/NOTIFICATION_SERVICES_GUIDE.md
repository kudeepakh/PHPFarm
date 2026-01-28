# 📧📱 Notification Services Guide - SendGrid & Twilio Integration

**Complete guide for email (SendGrid) and SMS (Twilio) notifications in PHPFrarm**

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [SendGrid Email Service](#sendgrid-email-service)
4. [Twilio SMS Service](#twilio-sms-service)
5. [Notification Factory](#notification-factory)
6. [OTP Integration](#otp-integration)
7. [Configuration](#configuration)
8. [Usage Examples](#usage-examples)
9. [Testing](#testing)
10. [Troubleshooting](#troubleshooting)

---

## 1. Overview

PHPFrarm includes production-ready notification services for:
- **Email**: SendGrid API v3 integration
- **SMS**: Twilio API integration
- **OTP**: Automatic delivery via email or SMS

### Key Features
✅ **Provider abstraction** - Easy to swap providers (SendGrid → AWS SES, Twilio → AWS SNS)
✅ **Auto-detection** - Automatically detect email vs phone number
✅ **Template support** - SendGrid dynamic templates for branded emails
✅ **Error handling** - Graceful fallbacks and retry logic
✅ **Security** - PII masking in logs, secure credential management
✅ **Testing** - Mock services for unit tests
✅ **Monitoring** - Complete audit trail of all notifications

---

## 2. Architecture

### Components

```
NotificationServiceInterface (abstract)
    ├── EmailService (SendGrid)
    ├── SMSService (Twilio)
    └── NotificationFactory (factory)

Integration:
    └── OTPService → NotificationFactory → EmailService/SMSService
```

### File Structure

```
Farm/backend/app/Core/Notifications/
├── NotificationServiceInterface.php   # Abstract interface
├── EmailService.php                   # SendGrid implementation
├── SMSService.php                     # Twilio implementation
└── NotificationFactory.php            # Factory pattern

Farm/backend/config/
└── notifications.php                  # Configuration

Farm/backend/modules/Auth/Services/
└── OTPService.php                     # Uses NotificationFactory
```

---

## 3. SendGrid Email Service

### Setup

1. **Get SendGrid API Key**
   - Sign up: https://signup.sendgrid.com/
   - Navigate to: Settings → API Keys
   - Create API key with "Mail Send" permission

2. **Configure .env**
   ```env
   SENDGRID_API_KEY=SG.xxxxxxxxxxxxxxxxxxxxxx
   MAIL_FROM_ADDRESS=noreply@yourdomain.com
   MAIL_FROM_NAME=Your App Name
   MAIL_ENABLED=true
   ```

3. **Verify Domain (Production)**
   - Settings → Sender Authentication → Domain Authentication
   - Add DNS records to your domain
   - Improves deliverability and removes "via sendgrid.net"

### Features

#### Plain Text Email
```php
use PHPFrarm\Core\Notifications\EmailService;

$emailService = new EmailService();

$result = $emailService->send(
    recipient: 'user@example.com',
    subject: 'Welcome to PHPFrarm',
    message: 'Hello! Welcome to our platform.',
    options: [
        'content_type' => 'text/plain'
    ]
);

if ($result['success']) {
    echo "Email sent! ID: {$result['message_id']}";
} else {
    echo "Failed: {$result['error']}";
}
```

#### HTML Email
```php
$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        .button { background: #007bff; color: white; padding: 10px 20px; }
    </style>
</head>
<body>
    <h1>Welcome!</h1>
    <p>Thank you for signing up.</p>
    <a href="https://example.com" class="button">Get Started</a>
</body>
</html>
HTML;

$result = $emailService->send(
    recipient: 'user@example.com',
    subject: 'Welcome to PHPFrarm',
    message: $html,
    options: [
        'content_type' => 'text/html'
    ]
);
```

#### Dynamic Templates
Create branded templates in SendGrid dashboard, then:

```php
$result = $emailService->send(
    recipient: 'user@example.com',
    subject: 'Welcome', // Can be set in template
    message: '', // Ignored when using templates
    options: [
        'template_id' => 'd-abc123def456',
        'template_data' => [
            'user_name' => 'John Doe',
            'verification_link' => 'https://example.com/verify/token123',
            'expires_at' => '2026-01-25 14:30:00'
        ]
    ]
);
```

#### OTP Email
```php
$result = $emailService->sendOTP(
    recipient: 'user@example.com',
    otp: '123456',
    purpose: 'login' // login, registration, password_reset, etc.
);
```

**OTP Email HTML Template** (built-in):
- Responsive design
- Large, readable OTP code
- Security warning
- 5-minute expiry notice
- Professional branding

#### Response Format
```php
[
    'success' => true,
    'message_id' => 'xxxxxx.xxxxx',
    'error' => null
]

// On failure:
[
    'success' => false,
    'message_id' => null,
    'error' => 'Invalid API key'
]
```

#### Error Handling
```php
try {
    $result = $emailService->send(...);
    
    if (!$result['success']) {
        // Log error, retry, or alert admin
        Logger::error('Email failed', ['error' => $result['error']]);
    }
} catch (\Exception $e) {
    // Handle exceptions
}
```

### SendGrid Dashboard

**Monitor deliverability**:
- Activity Feed: See all sent emails
- Statistics: Open rates, click rates, bounces
- Suppressions: Bounced/unsubscribed emails

---

## 4. Twilio SMS Service

### Setup

1. **Get Twilio Credentials**
   - Sign up: https://www.twilio.com/try-twilio
   - Navigate to: Console Dashboard
   - Copy Account SID and Auth Token

2. **Get Phone Number**
   - Phone Numbers → Buy a Number
   - Select country and capabilities (SMS)
   - Purchase number (costs ~$1/month)

3. **Configure .env**
   ```env
   TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
   TWILIO_AUTH_TOKEN=your_auth_token_here
   TWILIO_FROM_NUMBER=+14155551234
   SMS_ENABLED=true
   ```

### Features

#### Send SMS
```php
use PHPFrarm\Core\Notifications\SMSService;

$smsService = new SMSService();

$result = $smsService->send(
    recipient: '+14155552671', // E.164 format required
    subject: '', // Ignored for SMS
    message: 'Your verification code is 123456. Valid for 5 minutes.',
    options: []
);

if ($result['success']) {
    echo "SMS sent! Twilio SID: {$result['message_id']}";
} else {
    echo "Failed: {$result['error']}";
}
```

#### Phone Number Format (E.164)
**REQUIRED FORMAT**: `+[country code][number]`

| Country | Format | Example |
|---------|--------|---------|
| USA | +1XXXXXXXXXX | +14155552671 |
| UK | +44XXXXXXXXXX | +447911123456 |
| India | +91XXXXXXXXXX | +919876543210 |
| Australia | +61XXXXXXXXX | +61412345678 |
| Germany | +49XXXXXXXXX | +491701234567 |

**Invalid formats** (will be rejected):
- `4155552671` (missing +)
- `001-415-555-2671` (dashes not allowed)
- `(415) 555-2671` (parentheses not allowed)

#### OTP SMS
```php
$result = $smsService->sendOTP(
    recipient: '+14155552671',
    otp: '123456',
    purpose: 'login'
);

// SMS message (auto-generated):
// "Your PHPFrarm login code is: 123456. Valid for 5 minutes. Never share this code."
```

**Built-in OTP Messages**:
- **login**: "Your {app} login code is: {otp}..."
- **registration**: "Your {app} verification code is: {otp}..."
- **password_reset**: "Your {app} password reset code is: {otp}..."
- **phone_verification**: "Your {app} phone verification code is: {otp}..."
- **two_factor**: "Your {app} 2FA code is: {otp}..."

#### Character Limits
SMS has strict character limits affecting cost:

| Length | Segments | Cost |
|--------|----------|------|
| 1-160 chars | 1 segment | 1x |
| 161-306 chars | 2 segments | 2x |
| 307-459 chars | 3 segments | 3x |

**Best practice**: Keep SMS under 160 characters

```php
// Check segment count before sending
$segmentCount = $smsService->getSegmentCount($message);

if ($segmentCount > 1) {
    Logger::warning("SMS will use $segmentCount segments");
}
```

#### Phone Number Lookup
Validate phone numbers before sending (optional, requires Twilio Lookup API):

```php
$info = $smsService->lookupPhoneNumber('+14155552671');

if ($info) {
    echo "Valid number!";
    echo "Country: {$info['country_code']}";
    echo "National format: {$info['national_format']}";
} else {
    echo "Invalid phone number";
}
```

**Enable Lookup API**:
```env
TWILIO_ENABLE_LOOKUP=true
```

#### Response Format
```php
[
    'success' => true,
    'message_id' => 'SMxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', // Twilio SID
    'error' => null
]

// On failure:
[
    'success' => false,
    'message_id' => null,
    'error' => '[21614] To number is not a valid mobile number'
]
```

#### Common Twilio Error Codes

| Code | Error | Solution |
|------|-------|----------|
| 21211 | Invalid phone number | Check E.164 format |
| 21408 | Permission denied | Verify trial account restrictions |
| 21610 | Attempt to send to unverified number | Add to verified numbers (trial only) |
| 21614 | Not a valid mobile number | Use mobile, not landline |
| 30003 | Unreachable destination | Number may be inactive |
| 30006 | Landline or unreachable carrier | Use mobile number |

### Twilio Dashboard

**Monitor SMS**:
- Messaging → Logs: See all sent messages
- Programmable SMS → Settings: Configure webhooks
- Usage → SMS: Track costs

---

## 5. Notification Factory

**Automatic service selection based on recipient**

### Auto-Detect Type

```php
use PHPFrarm\Core\Notifications\NotificationFactory;

// Automatically detects email
$result = NotificationFactory::send(
    recipient: 'user@example.com',
    subject: 'Hello',
    message: 'Welcome!'
);
// Uses EmailService internally

// Automatically detects phone (E.164 format)
$result = NotificationFactory::send(
    recipient: '+14155552671',
    subject: '', // Ignored for SMS
    message: 'Your code is 123456'
);
// Uses SMSService internally
```

### Send OTP (Auto-Detect)

```php
// Email OTP
NotificationFactory::sendOTP(
    recipient: 'user@example.com',
    otp: '123456',
    purpose: 'login'
);

// SMS OTP
NotificationFactory::sendOTP(
    recipient: '+14155552671',
    otp: '123456',
    purpose: 'login'
);
```

### Get Service Directly

```php
// Get email service (singleton)
$emailService = NotificationFactory::getEmailService();

// Get SMS service (singleton)
$smsService = NotificationFactory::getSMSService();

// Get by type
$service = NotificationFactory::getService('email'); // or 'sms'
```

### Check Service Configuration

```php
$status = NotificationFactory::areServicesConfigured();

// Returns:
[
    'email' => true,  // SendGrid API key configured
    'sms' => false    // Twilio credentials missing
]
```

### Get Service Status

```php
$status = NotificationFactory::getServicesStatus();

// Returns:
[
    'email' => [
        'type' => 'email',
        'provider' => 'sendgrid',
        'configured' => true
    ],
    'sms' => [
        'type' => 'sms',
        'provider' => 'twilio',
        'configured' => true
    ]
]
```

---

## 6. OTP Integration

OTPService automatically uses NotificationFactory to send OTPs.

### Request OTP

```php
use PHPFrarm\Modules\Auth\Services\OTPService;

$otpService = new OTPService();

// Email OTP
$result = $otpService->requestOTP(
    identifier: 'user@example.com',
    type: 'email',
    purpose: 'login',
    userId: 'user_123' // optional
);

// SMS OTP
$result = $otpService->requestOTP(
    identifier: '+14155552671',
    type: 'sms',
    purpose: 'login',
    userId: 'user_123'
);

// Response (development only):
[
    'otp_sent' => true,
    'otp' => '123456' // Only in development!
]
```

### Verify OTP

```php
$isValid = $otpService->verifyOTP(
    identifier: 'user@example.com',
    otp: '123456',
    purpose: 'login',
    userId: 'user_123'
);

if ($isValid) {
    // OTP valid, proceed with login
} else {
    // Invalid OTP or expired
}
```

### OTP Flow

```
1. Client requests OTP
   ↓
2. OTPService generates 6-digit code
   ↓
3. Stores in database (hashed)
   ↓
4. Determines type (email/sms) from identifier
   ↓
5. NotificationFactory::sendOTP()
   ↓
6. EmailService or SMSService sends OTP
   ↓
7. Client receives OTP
   ↓
8. Client submits OTP for verification
   ↓
9. OTPService validates (max 3 attempts)
   ↓
10. Success or failure
```

### Error Handling

OTPService continues even if notification sending fails:

```php
// OTP is created in database
// Notification sending is attempted
// If sending fails:
//   - OTP still valid
//   - User can retry
//   - Error logged
```

**Rationale**: Allows retry without generating new OTP (prevents OTP spam).

---

## 7. Configuration

### Environment Variables

**Required for SendGrid**:
```env
SENDGRID_API_KEY=SG.xxxxxxxxxxxxxxxxxxxxxx
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME=Your App Name
```

**Required for Twilio**:
```env
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=your_auth_token_here
TWILIO_FROM_NUMBER=+14155551234
```

**Optional**:
```env
# Disable services temporarily
MAIL_ENABLED=false
SMS_ENABLED=false

# SendGrid templates
SENDGRID_OTP_TEMPLATE_ID=d-abc123
SENDGRID_WELCOME_TEMPLATE_ID=d-def456

# Twilio advanced
TWILIO_MESSAGING_SERVICE_SID=MGxxxx
TWILIO_STATUS_CALLBACK_URL=https://yourapp.com/webhooks/twilio
TWILIO_ENABLE_LOOKUP=true

# OTP settings
OTP_EXPIRY=300
OTP_MAX_ATTEMPTS=3
OTP_FALLBACK_TO_EMAIL=false

# Notification queue (future)
NOTIFICATION_QUEUE_ENABLED=false
```

### Configuration File

**File**: `Farm/backend/config/notifications.php`

```php
return [
    'email' => [
        'enabled' => env('MAIL_ENABLED', true),
        'sendgrid' => [
            'api_key' => env('SENDGRID_API_KEY'),
            'from_email' => env('MAIL_FROM_ADDRESS'),
            // ...
        ]
    ],
    
    'sms' => [
        'enabled' => env('SMS_ENABLED', true),
        'twilio' => [
            'account_sid' => env('TWILIO_ACCOUNT_SID'),
            // ...
        ]
    ],
    
    'otp' => [
        'expiry' => 300,
        'max_attempts' => 3,
        // ...
    ]
];
```

---

## 8. Usage Examples

### Example 1: Send Welcome Email

```php
use PHPFrarm\Core\Notifications\NotificationFactory;

function sendWelcomeEmail(string $email, string $name): void
{
    $html = <<<HTML
    <h1>Welcome, $name!</h1>
    <p>Thanks for joining PHPFrarm.</p>
    <p><a href="https://example.com/getting-started">Get Started</a></p>
    HTML;
    
    NotificationFactory::send(
        recipient: $email,
        subject: 'Welcome to PHPFrarm!',
        message: $html,
        options: ['content_type' => 'text/html']
    );
}
```

### Example 2: Send Password Reset OTP

```php
function sendPasswordResetOTP(string $identifier, string $type): void
{
    $otpService = new OTPService();
    
    $result = $otpService->requestOTP(
        identifier: $identifier, // email or phone
        type: $type,             // 'email' or 'sms'
        purpose: 'password_reset'
    );
    
    // OTP sent automatically
}
```

### Example 3: Phone Verification Flow

```php
// Step 1: Send OTP
function sendPhoneVerificationOTP(string $phone): array
{
    $otpService = new OTPService();
    
    return $otpService->requestOTP(
        identifier: $phone,      // +14155552671
        type: 'sms',
        purpose: 'phone_verification',
        userId: getCurrentUserId()
    );
}

// Step 2: Verify OTP
function verifyPhoneOTP(string $phone, string $otp): bool
{
    $otpService = new OTPService();
    
    $isValid = $otpService->verifyOTP(
        identifier: $phone,
        otp: $otp,
        purpose: 'phone_verification',
        userId: getCurrentUserId()
    );
    
    if ($isValid) {
        // Mark phone as verified
        $userDAO = new UserDAO();
        $userDAO->verifyPhone(getCurrentUserId());
    }
    
    return $isValid;
}
```

### Example 4: Bulk Email Notifications

```php
function sendBulkNotifications(array $users, string $message): void
{
    $emailService = NotificationFactory::getEmailService();
    
    foreach ($users as $user) {
        $result = $emailService->send(
            recipient: $user['email'],
            subject: 'Important Update',
            message: $message
        );
        
        if (!$result['success']) {
            Logger::error('Bulk email failed', [
                'user_id' => $user['id'],
                'error' => $result['error']
            ]);
        }
        
        // Rate limit to avoid API throttling
        usleep(100000); // 0.1 second delay
    }
}
```

### Example 5: Notification with Retry

```php
function sendWithRetry(string $recipient, string $message, int $maxRetries = 3): bool
{
    $attempts = 0;
    
    while ($attempts < $maxRetries) {
        $result = NotificationFactory::send(
            recipient: $recipient,
            subject: 'Notification',
            message: $message
        );
        
        if ($result['success']) {
            return true;
        }
        
        $attempts++;
        
        if ($attempts < $maxRetries) {
            sleep(pow(2, $attempts)); // Exponential backoff
        }
    }
    
    Logger::error('Notification failed after retries', [
        'recipient' => $recipient,
        'attempts' => $attempts
    ]);
    
    return false;
}
```

---

## 9. Testing

### Unit Tests

```php
use PHPFrarm\Core\Notifications\NotificationFactory;
use PHPFrarm\Core\Testing\ExternalServiceMock;

class NotificationTest extends TestCase
{
    public function testSendEmail()
    {
        $emailMock = ExternalServiceMock::sendgrid();
        $emailMock->mockSuccess('msg_123');
        
        $result = NotificationFactory::send(
            recipient: 'test@example.com',
            subject: 'Test',
            message: 'Hello'
        );
        
        $this->assertTrue($result['success']);
        $emailMock->assertSent();
        $emailMock->assertSentTo('test@example.com');
    }
    
    public function testSendSMS()
    {
        $smsMock = ExternalServiceMock::twilio();
        $smsMock->mockSmsSuccess('SM123');
        
        $result = NotificationFactory::send(
            recipient: '+14155552671',
            subject: '',
            message: 'Your code: 123456'
        );
        
        $this->assertTrue($result['success']);
        $smsMock->assertSentTo('+14155552671');
    }
}
```

### Testing with Disabled Services

```env
# .env.testing
MAIL_ENABLED=false
SMS_ENABLED=false
```

When disabled, services return success without actually sending.

### Twilio Test Numbers

Twilio provides magic numbers for testing:

| Number | Behavior |
|--------|----------|
| +15005550006 | Valid, delivers successfully |
| +15005550007 | Invalid number error |
| +15005550001 | Cannot route to this number |

```php
$result = NotificationFactory::send(
    recipient: '+15005550006', // Twilio test number
    subject: '',
    message: 'Test SMS'
);
// Returns success without sending actual SMS or charging
```

---

## 10. Troubleshooting

### SendGrid Issues

**Problem**: `401 Unauthorized`
- **Cause**: Invalid API key
- **Solution**: Verify SENDGRID_API_KEY in .env

**Problem**: `403 Forbidden`
- **Cause**: API key doesn't have "Mail Send" permission
- **Solution**: Regenerate API key with correct permissions

**Problem**: `550 Sender address rejected`
- **Cause**: From address not verified
- **Solution**: Verify sender in SendGrid dashboard or use domain authentication

**Problem**: High bounce rate
- **Cause**: Invalid email addresses or poor sender reputation
- **Solution**:
  - Validate emails before sending
  - Use double opt-in for signups
  - Monitor SendGrid bounce reports

**Problem**: Emails going to spam
- **Solution**:
  - Set up SPF, DKIM, DMARC records
  - Use domain authentication
  - Avoid spam trigger words
  - Include unsubscribe link

### Twilio Issues

**Problem**: `[21211] The 'To' number is not a valid phone number`
- **Cause**: Phone not in E.164 format
- **Solution**: Format as `+[country code][number]`

**Problem**: `[21408] Permission to send an SMS has not been enabled`
- **Cause**: Trial account restrictions
- **Solution**: Add recipient to verified numbers or upgrade account

**Problem**: `[21610] Attempt to send to unverified number`
- **Cause**: Trial account can only send to verified numbers
- **Solution**: Verify recipient in Twilio console or upgrade

**Problem**: `[21614] 'To' number is not a valid mobile number`
- **Cause**: Number is landline or invalid
- **Solution**: Use mobile number only

**Problem**: `[30006] Landline or unreachable carrier`
- **Cause**: Number doesn't support SMS
- **Solution**: Confirm mobile number with user

**Problem**: SMS not received
- **Solutions**:
  - Check Twilio logs for delivery status
  - Verify phone number is active
  - Try different phone number
  - Check if carrier blocks short codes

### General Issues

**Problem**: OTP not received
- **Check**:
  1. Is service configured? `NotificationFactory::areServicesConfigured()`
  2. Is service enabled? `MAIL_ENABLED=true` / `SMS_ENABLED=true`
  3. Check logs: MongoDB `notification_logs` collection
  4. Verify credentials in .env
  5. Check spam folder (email) or carrier blocks (SMS)

**Problem**: Slow notification sending
- **Solutions**:
  - Enable queue: `NOTIFICATION_QUEUE_ENABLED=true`
  - Use async job processing
  - Increase API timeout

**Problem**: High costs (SMS)
- **Solutions**:
  - Keep messages under 160 characters
  - Use email for non-critical OTPs
  - Implement rate limiting
  - Monitor Twilio usage dashboard

---

## 📊 Summary

### What's Implemented

✅ **NotificationServiceInterface** - Abstract interface for providers
✅ **EmailService** - SendGrid API v3 integration
✅ **SMSService** - Twilio API integration
✅ **NotificationFactory** - Auto-detect email/SMS
✅ **OTP Integration** - Automatic OTP delivery
✅ **Configuration** - Complete .env setup
✅ **Error Handling** - Graceful failures and logging
✅ **Testing Support** - Mock services and test numbers

### SendGrid Capabilities

✅ Plain text and HTML emails
✅ Dynamic templates with variables
✅ From address customization
✅ Reply-to address
✅ OTP emails with professional design
✅ Error handling with Twilio error codes
✅ Message ID tracking
✅ Timeout configuration

### Twilio Capabilities

✅ E.164 phone number format validation
✅ International SMS support
✅ Character count and segment calculation
✅ Phone number lookup API
✅ OTP SMS with professional messages
✅ Error handling with Twilio error codes
✅ Message SID tracking
✅ PII masking in logs
✅ Status callbacks (webhooks)

### Next Steps

1. **Get API Keys**: Sign up for SendGrid and Twilio
2. **Configure .env**: Add credentials
3. **Test in Development**: Send test email/SMS
4. **Verify Domain**: Set up SPF/DKIM for production emails
5. **Monitor**: Check SendGrid/Twilio dashboards
6. **Scale**: Enable queue for high-volume sending

---

**Module 5 OTP & Verification is now 100% complete with SMS and Email support!** 📧📱✅

