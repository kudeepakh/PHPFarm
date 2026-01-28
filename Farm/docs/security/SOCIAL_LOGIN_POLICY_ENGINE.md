# 🔐 Social Login & Policy Engine Guide

## Module 2: Social Login (OAuth 2.0)

### Overview
Complete OAuth 2.0 implementation supporting Google, Facebook, and GitHub login with automatic account creation and linking.

---

### Supported Providers

#### 1. Google OAuth 2.0
- **Scopes**: `openid`, `email`, `profile`
- **Features**:
  - Refresh token support
  - ID token validation (JWT)
  - Email verification status
  - Profile picture URLs
- **Token Expiry**: 1 hour (refresh available)

#### 2. Facebook Login
- **Scopes**: `email`, `public_profile`
- **Features**:
  - Long-lived tokens (60 days)
  - Token debugging API
  - Large profile picture fetching
  - Graph API v18.0
- **Token Expiry**: 2 hours (can exchange for 60-day token)

#### 3. GitHub OAuth
- **Scopes**: `read:user`, `user:email`
- **Features**:
  - Tokens never expire
  - Separate email endpoint for private emails
  - Username, bio, company, location fields
  - Profile URL included
- **Token Expiry**: Never (no refresh needed)

---

### Environment Configuration

Add to `.env`:

```env
# Google OAuth
GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-secret
GOOGLE_REDIRECT_URI=https://yourdomain.com/api/auth/social/google/callback
GOOGLE_OAUTH_ENABLED=true

# Facebook OAuth
FACEBOOK_APP_ID=your-app-id
FACEBOOK_APP_SECRET=your-secret
FACEBOOK_REDIRECT_URI=https://yourdomain.com/api/auth/social/facebook/callback
FACEBOOK_OAUTH_ENABLED=true

# GitHub OAuth
GITHUB_CLIENT_ID=your-client-id
GITHUB_CLIENT_SECRET=your-secret
GITHUB_REDIRECT_URI=https://yourdomain.com/api/auth/social/github/callback
GITHUB_OAUTH_ENABLED=true

# OAuth Behavior
OAUTH_LINK_ACCOUNTS=auto  # auto|prompt|deny
OAUTH_AUTO_VERIFY_EMAIL=true
OAUTH_STATE_STORAGE=session  # session|cache
OAUTH_STATE_TTL=600  # 10 minutes
```

---

### API Endpoints

#### 1. Start OAuth Flow
```http
GET /api/auth/social/{provider}?redirect_uri=https://yourdomain.com/callback
```

**Response**:
```json
{
  "status": "success",
  "data": {
    "authorization_url": "https://accounts.google.com/o/oauth2/v2/auth?...",
    "state": "csrf-token",
    "provider": "google"
  }
}
```

**Frontend Flow**:
```javascript
// Step 1: Get authorization URL
const response = await fetch('/api/auth/social/google?redirect_uri=' + encodeURIComponent(callbackUrl));
const { data } = await response.json();

// Step 2: Redirect user to authorization URL
window.location.href = data.authorization_url;
```

#### 2. Handle OAuth Callback
```http
GET /api/auth/social/{provider}/callback?code=xxx&state=xxx&redirect_uri=...
```

**Response** (Success):
```json
{
  "status": "success",
  "data": {
    "user": {
      "user_id": "uuid",
      "email": "user@gmail.com",
      "first_name": "John",
      "last_name": "Doe",
      "status": "active"
    },
    "tokens": {
      "access_token": "jwt...",
      "refresh_token": "jwt...",
      "expires_in": 3600
    },
    "oauth_provider": "google"
  },
  "message": "OAuth login successful"
}
```

**Frontend Flow**:
```javascript
// User redirected back from Google with code
const urlParams = new URLSearchParams(window.location.search);
const code = urlParams.get('code');
const state = urlParams.get('state');

// Exchange code for tokens
const response = await fetch(`/api/auth/social/google/callback?code=${code}&state=${state}&redirect_uri=${callbackUrl}`);
const { data } = await response.json();

// Store tokens and redirect
localStorage.setItem('access_token', data.tokens.access_token);
localStorage.setItem('refresh_token', data.tokens.refresh_token);
```

#### 3. Unlink OAuth Provider
```http
POST /api/auth/social/{provider}/unlink
Authorization: Bearer {token}
```

**Response**:
```json
{
  "status": "success",
  "data": {
    "provider": "google",
    "unlinked": true
  }
}
```

#### 4. List Linked Providers
```http
GET /api/auth/social/providers
Authorization: Bearer {token}
```

**Response**:
```json
{
  "status": "success",
  "data": {
    "providers": ["google", "facebook"]
  }
}
```

---

### Account Linking Behavior

When user logs in via OAuth with an email that already exists:

#### `OAUTH_LINK_ACCOUNTS=auto` (Recommended)
- Automatically link OAuth to existing email account
- User can login with either OAuth or email/password
- No user action required

#### `OAUTH_LINK_ACCOUNTS=prompt`
- Ask user to confirm linking
- Require password verification before linking
- More secure but extra step

#### `OAUTH_LINK_ACCOUNTS=deny`
- Create separate account (not recommended)
- User ends up with duplicate accounts

---

### Database Schema

Add to `user_identifiers` table:

```sql
-- OAuth identifiers format: "google:123456789"
INSERT INTO user_identifiers (user_id, identifier_type, identifier_value, verified)
VALUES ('user-uuid', 'oauth', 'google:1234567890', 1);
```

---

## Module 3: Policy Engine

### Overview
Advanced authorization system for complex rules beyond RBAC. Supports time-based access, quota enforcement, and custom policies.

---

### Policy Types

#### 1. Time-Based Policy
Restrict access by time, day of week, or date range.

**Example: Office Hours Only**
```php
use PHPFrarm\Core\Authorization\PolicyEngine;
use PHPFrarm\Core\Authorization\TimeBasedPolicy;

$engine = new PolicyEngine();

// Allow access only during office hours
$officeHours = new TimeBasedPolicy('OfficeHours');
$officeHours->setTimeRange('09:00', '17:00')
            ->setAllowedDays([1, 2, 3, 4, 5])  // Monday-Friday
            ->setTimezone('America/New_York');

$engine->addRule($officeHours);

// Check access
$allowed = $engine->can($user, 'read', $resource);
```

**Example: Special Event Access**
```php
$eventPolicy = new TimeBasedPolicy('BetaAccess');
$eventPolicy->setDateRange(
    new \DateTime('2024-01-01'),
    new \DateTime('2024-12-31')
);

$engine->addRule($eventPolicy);
```

#### 2. Resource Quota Policy
Enforce usage limits on resources.

**Example: API Rate Limiting**
```php
use PHPFrarm\Core\Authorization\ResourceQuotaPolicy;

$apiQuota = new ResourceQuotaPolicy('api_calls', 100, 'hour');
$apiQuota->setUsageCallback(function($userId, $resourceType, $period) {
    // Fetch from database or cache
    return getAPICallCount($userId, $period);
});

$engine->addRule($apiQuota);

if ($engine->can($user, 'api_call', null)) {
    // Allow API call
} else {
    // Quota exceeded
}
```

**Example: Storage Quota**
```php
$storageQuota = new ResourceQuotaPolicy('storage', 10 * 1024 * 1024 * 1024, 'total'); // 10 GB
$storageQuota->setUsageCallback(function($userId) {
    return getUserStorageUsage($userId);
});

$engine->addRule($storageQuota);
```

#### 3. Advanced User Policy
Custom logic for complex scenarios.

**Example: VIP Users Only**
```php
use PHPFrarm\Core\Authorization\AdvancedUserPolicy;

$vipPolicy = new AdvancedUserPolicy('VIPOnly', function($user, $action, $resource, $context) {
    return in_array('vip', $user['roles'] ?? []);
});

$engine->addRule($vipPolicy);
```

**Example: Beta Testers**
```php
$betaPolicy = new AdvancedUserPolicy('BetaAccess', function($user, $action, $resource, $context) {
    return $user['is_beta_tester'] ?? false;
});
```

---

### Evaluation Modes

#### ALL Mode (AND)
All policies must allow access (default).

```php
$engine->setMode('all');
$engine->addRule($officeHours);
$engine->addRule($vipPolicy);

// User must satisfy BOTH office hours AND VIP status
```

#### ANY Mode (OR)
At least one policy must allow access.

```php
$engine->setMode('any');
$engine->addRule($vipPolicy);
$engine->addRule($adminPolicy);

// User can be EITHER VIP OR admin
```

---

### Integration with AuthorizationManager

**Basic Usage**:
```php
use PHPFrarm\Core\Authorization\AuthorizationManager;
use PHPFrarm\Core\Authorization\PolicyEngine;

// Create policy engine
$policyEngine = new PolicyEngine();
$policyEngine->addRule(new TimeBasedPolicy('OfficeHours'));

// Pass to AuthorizationManager
$authz = new AuthorizationManager($user, $policyEngine);

// Check access (runs RBAC + policies)
if ($authz->canAccess($resource, 'update')) {
    // Allow
}
```

**Advanced Example**:
```php
// Office hours + API quota + VIP access
$engine = new PolicyEngine();

// Weekday office hours
$officeHours = (new TimeBasedPolicy('OfficeHours'))
    ->setTimeRange('09:00', '17:00')
    ->setAllowedDays([1, 2, 3, 4, 5]);

// API quota
$apiQuota = new ResourceQuotaPolicy('api_calls', 1000, 'day');
$apiQuota->setUsageCallback(fn($uid, $type, $period) => getUsage($uid, $period));

// VIP bypass
$vipBypass = new AdvancedUserPolicy('VIP', fn($u) => $u['tier'] === 'premium');

$engine->setMode('any');  // Office hours OR VIP
$engine->addRule($officeHours);
$engine->addRule($vipBypass);
$engine->addRule($apiQuota);  // Quota applies to all

$authz = new AuthorizationManager($user, $engine);
```

---

### Policy Priority

Policies are evaluated by priority (higher first):

```php
$policy1 = new TimeBasedPolicy('P1');
$policy1->setPriority(200);  // Evaluated first

$policy2 = new ResourceQuotaPolicy('P2', 100, 'day');
$policy2->setPriority(100);  // Evaluated second

$engine->addRule($policy1);
$engine->addRule($policy2);
```

Default priorities:
- TimeBasedPolicy: 200
- ResourceQuotaPolicy: 150
- AdvancedUserPolicy: 100

---

### Complete Example: SaaS Platform

```php
// Multi-tier access control
$engine = new PolicyEngine();

// 1. Time-based: Business hours for free users
$businessHours = (new TimeBasedPolicy('FreeUserHours'))
    ->setTimeRange('08:00', '20:00');

// 2. Quota: Free tier API limits
$freeQuota = new ResourceQuotaPolicy('api_calls', 100, 'day');
$freeQuota->setUsageCallback(fn($uid) => getAPICallCount($uid));

// 3. Premium bypass
$premiumBypass = new AdvancedUserPolicy('Premium', function($user) {
    return in_array($user['subscription_tier'] ?? 'free', ['premium', 'enterprise']);
});

// Free users: business hours + quota
// Premium users: bypass both
$engine->setMode('any');
$engine->addRule($premiumBypass);
$engine->addRule($businessHours);
$engine->addRule($freeQuota);

// Check access
$authz = new AuthorizationManager($user, $engine);
if ($authz->canAccess($apiEndpoint, 'call')) {
    // Process API request
} else {
    // Deny (outside hours, quota exceeded, or not premium)
}
```

---

### Logging & Debugging

All policy evaluations are logged:

```json
{
  "level": "info",
  "message": "Policy evaluation complete",
  "context": {
    "user_id": "uuid",
    "action": "read",
    "mode": "all",
    "allowed": true,
    "rules_evaluated": 3,
    "rules": [
      {"rule": "TimeBasedPolicy", "result": true, "priority": 200},
      {"rule": "ResourceQuotaPolicy", "result": true, "priority": 150},
      {"rule": "AdvancedUserPolicy", "result": true, "priority": 100}
    ]
  }
}
```

---

### Testing Policies

```php
// Test time-based policy
$policy = new TimeBasedPolicy('Test');
$policy->setTimeRange('09:00', '17:00');

// Mock current time to 10:00
$result = $policy->evaluate($user, 'read', $resource);
assert($result === true);

// Mock current time to 20:00
$result = $policy->evaluate($user, 'read', $resource);
assert($result === false);
```

---

### Best Practices

1. **Keep policies focused**: One concern per policy
2. **Use priority wisely**: Critical policies first
3. **Log all decisions**: Enable audit trail
4. **Test edge cases**: Time boundaries, quota limits
5. **Cache usage data**: Don't query DB on every request
6. **Use ANY mode sparingly**: Prefer explicit rules
7. **Document custom policies**: Explain complex logic

---

### Security Considerations

- **CSRF Protection**: OAuth state tokens prevent forgery
- **Token Storage**: Store refresh tokens securely (encrypted)
- **Email Verification**: Auto-verify only if provider confirms
- **Account Linking**: Validate before linking to existing accounts
- **Policy Evaluation**: Default to deny on error
- **Quota Enforcement**: Use distributed counters (Redis)

---

## Complete Flow Diagram

```
User → Click "Login with Google"
     → Frontend: GET /api/auth/social/google?redirect_uri=...
     → Backend: Generate auth URL + state token
     → Frontend: Redirect to Google
     → User: Authorize on Google
     → Google: Redirect back with code
     → Frontend: GET /api/auth/social/google/callback?code=...
     → Backend: Exchange code for token
     → Backend: Fetch user info
     → Backend: Find or create user
     → Backend: Generate JWT tokens
     → Frontend: Store tokens, redirect to dashboard
```

---

## Troubleshooting

### OAuth Issues

**"Invalid state parameter"**
- Session expired (> 10 minutes)
- User opened multiple OAuth tabs
- Solution: Restart OAuth flow

**"Provider not configured"**
- Missing environment variables
- Check `.env` has client ID and secret

**"Email already exists"**
- User has email/password account
- Check `OAUTH_LINK_ACCOUNTS` setting

### Policy Issues

**Policy always denies**
- Check evaluation mode (all vs any)
- Verify usage callback returns correct data
- Check policy priority order

**Policy not evaluated**
- Ensure PolicyEngine passed to AuthorizationManager
- Verify policy added to engine
- Check logs for evaluation results

---

## Migration Path

### From email/password to OAuth:
1. User registers with email/password
2. User connects Google account (link)
3. User can login with either method
4. Optional: Require 2FA for email/password only

### From OAuth to email/password:
1. User logged in via OAuth
2. User sets password in account settings
3. User can now login with email/password
4. OAuth remains linked (can unlink later)

