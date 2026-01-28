# 🚦 Traffic Management Guide

## 📋 Table of Contents

1. [Overview](#overview)
2. [Rate Limiting](#rate-limiting)
3. [Throttling](#throttling)
4. [Quota Management](#quota-management)
5. [Route Configuration](#route-configuration)
6. [Admin APIs](#admin-apis)
7. [Monitoring & Statistics](#monitoring--statistics)
8. [Best Practices](#best-practices)
9. [Troubleshooting](#troubleshooting)

---

## 🎯 Overview

PHPFrarm's traffic management system provides three layers of protection:

1. **Rate Limiting** - Hard limits on request frequency (429 error when exceeded)
2. **Throttling** - Progressive delays for excessive requests (gradual slowdown)
3. **Quota Management** - Client-level usage limits (daily/monthly quotas)

### Key Features

✅ Multiple rate limiting algorithms (token bucket, sliding window, fixed window)
✅ Progressive throttling with exponential backoff
✅ Flexible quota tiers (free, basic, premium, enterprise)
✅ Route-level configuration via PHP attributes
✅ Redis-backed for distributed systems
✅ Comprehensive admin APIs
✅ Real-time statistics and monitoring
✅ Automatic recovery (fail open on errors)

---

## 🚨 Rate Limiting

### Algorithms

**1. Token Bucket** (Default)
- Allows burst traffic
- Tokens refill at constant rate
- Best for: APIs with bursty traffic patterns

```php
// 60 requests per minute with 90 burst capacity
#[RateLimit(limit: 60, window: 60, burst: 90)]
```

**2. Sliding Window**
- Most accurate
- Tracks individual timestamps
- Best for: Strict rate enforcement

```php
#[RateLimit(limit: 100, window: 60, algorithm: 'sliding_window')]
```

**3. Fixed Window**
- Simple counter
- Resets at fixed intervals
- Best for: High-throughput APIs

```php
#[RateLimit(limit: 1000, window: 3600, algorithm: 'fixed_window')]
```

### Configuration

**Global Configuration** (`config/traffic.php`):
```php
'rate_limit' => [
    'algorithm' => 'token_bucket',
    'default_limit' => 60,    // requests per minute
    'default_window' => 60,    // seconds
    'burst_multiplier' => 1.5, // 50% burst capacity
    'fail_open' => true,       // Allow on Redis failure
],
```

**Route-Level Configuration**:
```php
use Farm\Backend\App\Core\Traffic\Attributes\RateLimit;

class UserController
{
    // 100 requests per minute
    #[RateLimit(limit: 100, window: 60)]
    public function index() { }
    
    // Strict limit on writes
    #[RateLimit(limit: 10, window: 60, algorithm: 'sliding_window')]
    public function create() { }
    
    // High burst capacity for reads
    #[RateLimit(limit: 200, window: 60, burst: 500)]
    public function show() { }
}
```

### Response Headers

When rate limited, clients receive:
```
HTTP/1.1 429 Too Many Requests
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1704067200
Retry-After: 45
```

### Client Experience

```bash
# First 60 requests succeed
curl http://api.example.com/users  # 200 OK

# 61st request fails
curl http://api.example.com/users
# 429 Too Many Requests
# { "error": "Rate limit exceeded. Please try again later." }
```

---

## ⏱️ Throttling

Throttling applies **progressive delays** instead of blocking requests.

### How It Works

1. Count requests in window
2. If over threshold, calculate delay
3. Apply delay using exponential backoff
4. Allow request after delay

### Configuration

**Global Settings**:
```php
'throttle' => [
    'threshold' => 100,     // Start throttling after 100 req/min
    'window' => 60,         // 60-second window
    'base_delay' => 0.1,    // 100ms initial delay
    'max_delay' => 5.0,     // 5-second maximum delay
],
```

**Enable Throttling on Route**:
```php
#[RateLimit(
    limit: 200,
    window: 60,
    throttle: true,              // Enable throttling
    throttleThreshold: 150        // Start at 150 requests
)]
public function search() { }
```

### Delay Calculation

```
delay = min(base_delay * (2 ^ excess_requests), max_delay)

Examples:
- 151st request (1 over):   delay = 0.1s
- 152nd request (2 over):   delay = 0.2s
- 154th request (4 over):   delay = 0.8s
- 160th request (10 over):  delay = 5.0s (capped)
```

### Response Headers

```
X-Throttle-Status: throttled
X-Throttle-Delay: 0.250
X-Throttle-Requests: 152
X-Throttle-Threshold: 150
```

### When to Use

✅ **Use throttling** when:
- You want graceful degradation
- Some delay is acceptable
- Preventing complete service denial

❌ **Don't use throttling** when:
- Real-time requirements (<100ms)
- Binary allow/deny needed
- Write operations (avoid partial writes)

---

## 📊 Quota Management

Client-level quotas limit total API usage over longer periods (day/month).

### Quota Tiers

| Tier | Daily Limit | Period | Use Case |
|------|-------------|--------|----------|
| **free** | 1,000 | daily | Trial users |
| **basic** | 10,000 | daily | Small apps |
| **premium** | 100,000 | daily | Production apps |
| **enterprise** | 1,000,000 | daily | High-volume APIs |
| **unlimited** | ∞ | - | Internal services |

### Configuration

**Define Tiers** (`config/traffic.php`):
```php
'tiers' => [
    'startup' => [
        'limit' => 25000,
        'period' => 'daily',
        'description' => 'Startup plan'
    ],
    'monthly_plan' => [
        'limit' => 500000,
        'period' => 'monthly',
        'description' => 'Monthly subscription'
    ],
],
```

### Usage Examples

**Set Client Tier**:
```php
$quotaManager = new QuotaManager($redis, $logger, $config);
$quotaManager->setTier('user:12345', 'premium');
```

**Set Custom Quota**:
```php
// 50,000 requests per day
$quotaManager->setCustomQuota('user:12345', 50000, 'daily');
```

**Check Quota**:
```php
$result = $quotaManager->check('user:12345');
// [
//     'allowed' => true,
//     'remaining' => 45230,
//     'limit' => 50000,
//     'used' => 4770,
//     'reset' => 1704067200,
//     'tier' => 'custom'
// ]
```

### Route-Level Quota Cost

Some operations are more expensive than others:

```php
// Regular operation (cost = 1)
#[RateLimit(quota: true, quotaCost: 1)]
public function getUser() { }

// Expensive export (cost = 10)
#[RateLimit(quota: true, quotaCost: 10)]
public function exportAllData() { }

// Search (cost = 5)
#[RateLimit(quota: true, quotaCost: 5)]
public function complexSearch() { }
```

### Response Headers

```
X-Quota-Limit: 10000
X-Quota-Remaining: 7543
X-Quota-Used: 2457
X-Quota-Reset: 1704153600
X-Quota-Tier: premium
```

---

## ⚙️ Route Configuration

### Using #[RateLimit] Attribute

**Method-Level**:
```php
use Farm\Backend\App\Core\Traffic\Attributes\RateLimit;

class ApiController
{
    // Public endpoint - strict limits
    #[RateLimit(limit: 10, window: 60)]
    public function publicData() { }
    
    // Authenticated - higher limits
    #[RateLimit(limit: 100, window: 60)]
    public function authenticatedData() { }
    
    // Disable traffic management
    #[RateLimit(enabled: false)]
    public function healthCheck() { }
}
```

**Class-Level** (applies to all methods):
```php
#[RateLimit(limit: 50, window: 60, throttle: true)]
class SearchController
{
    // Inherits class-level limits
    public function search() { }
    
    // Override with method-level attribute
    #[RateLimit(limit: 10, window: 60)]
    public function complexSearch() { }
}
```

### Complete Example

```php
#[RateLimit(
    limit: 100,                    // 100 requests per minute
    window: 60,                    // 60-second window
    burst: 150,                    // Allow 150 burst
    algorithm: 'token_bucket',     // Use token bucket
    throttle: true,                // Enable throttling
    throttleThreshold: 80,         // Throttle after 80
    quota: true,                   // Check quota
    quotaCost: 2,                  // Cost 2 quota points
    message: 'Custom error message' // Custom 429 message
)]
public function expensiveOperation() {
    // Your code here
}
```

### Config File Overrides

**Route-Specific** (`config/traffic.php`):
```php
'routes' => [
    'POST /api/v1/auth/login' => [
        'limit' => 5,
        'window' => 300, // 5 minutes
        'throttle' => false,
    ],
    
    'GET /api/v1/*/export' => [
        'limit' => 10,
        'window' => 3600, // 1 hour
        'quota_cost' => 10,
    ],
    
    'GET /health' => [
        'enabled' => false, // No limits
    ],
],
```

### Priority Order

1. `#[RateLimit]` attribute (highest priority)
2. `config/traffic.php` route-specific
3. Global defaults

---

## 🔧 Admin APIs

### Rate Limiter APIs

**Get Status**:
```bash
GET /admin/traffic/rate-limit/status/{identifier}

Response:
{
  "success": true,
  "data": {
    "allowed": true,
    "remaining": 45,
    "limit": 60,
    "reset": 1704067200
  }
}
```

**Get Statistics**:
```bash
GET /admin/traffic/rate-limit/stats?date=2026-01-18

Response:
{
  "date": "2026-01-18",
  "total_allowed": 125430,
  "total_blocked": 3210,
  "block_rate": 2.49,
  "clients": {
    "user:12345": {"allowed": 850, "blocked": 12},
    "ip:203.0.113.42": {"allowed": 420, "blocked": 35}
  }
}
```

**Reset Rate Limit**:
```bash
POST /admin/traffic/rate-limit/reset/{identifier}

Response:
{
  "success": true,
  "message": "Rate limit reset successfully"
}
```

### Throttle APIs

**Get Status**:
```bash
GET /admin/traffic/throttle/status/{identifier}

Response:
{
  "identifier": "user:12345",
  "requests_in_window": 85,
  "threshold": 100,
  "is_throttled": false,
  "current_delay_seconds": 0.0
}
```

**Get Statistics**:
```bash
GET /admin/traffic/throttle/stats?date=2026-01-18

Response:
{
  "date": "2026-01-18",
  "total_throttled": 1240,
  "total_delay_seconds": 523.45,
  "average_delay_seconds": 0.422,
  "top_throttled_clients": {
    "user:98765": 85,
    "ip:198.51.100.1": 62
  }
}
```

### Quota APIs

**Get Status**:
```bash
GET /admin/traffic/quota/status/{clientId}

Response:
{
  "client_id": "user:12345",
  "tier": "premium",
  "limit": 100000,
  "used": 45230,
  "remaining": 54770,
  "period": "daily",
  "reset": 1704067200,
  "usage_percent": 45.23
}
```

**Set Tier**:
```bash
POST /admin/traffic/quota/tier
Content-Type: application/json

{
  "clientId": "user:12345",
  "tier": "enterprise"
}
```

**Set Custom Quota**:
```bash
POST /admin/traffic/quota/custom
Content-Type: application/json

{
  "clientId": "user:12345",
  "limit": 50000,
  "period": "daily"
}
```

**Get Tiers**:
```bash
GET /admin/traffic/quota/tiers

Response:
{
  "tiers": {
    "free": {"limit": 1000, "period": "daily"},
    "basic": {"limit": 10000, "period": "daily"},
    ...
  }
}
```

### Combined APIs

**Get All Traffic Status**:
```bash
GET /admin/traffic/status/{identifier}

Response:
{
  "identifier": "user:12345",
  "rate_limit": {...},
  "throttle": {...},
  "quota": {...}
}
```

**Reset All**:
```bash
POST /admin/traffic/reset-all/{identifier}
```

**Get Summary**:
```bash
GET /admin/traffic/stats/summary?date=2026-01-18

Response:
{
  "date": "2026-01-18",
  "rate_limit": {...},
  "throttle": {...},
  "quota": {...}
}
```

---

## 📈 Monitoring & Statistics

### Real-Time Monitoring

**Check Client Status**:
```php
// Get current status for user
$status = $rateLimiter->getStatus('user:12345');
echo "Remaining: {$status['remaining']}/{$status['limit']}";
```

**Track Top Users**:
```php
$stats = $quotaManager->getStats();
$topUsers = $stats['top_users']; // Top 10 by usage
```

### Daily Statistics

```php
// Get yesterday's stats
$stats = $rateLimiter->getStats('2026-01-17');

echo "Total allowed: {$stats['total_allowed']}\n";
echo "Total blocked: {$stats['total_blocked']}\n";
echo "Block rate: {$stats['block_rate']}%\n";

foreach ($stats['clients'] as $client => $data) {
    echo "$client: {$data['blocked']} blocked\n";
}
```

### Alerting Integration

```php
// Check for high block rates
if ($stats['block_rate'] > 50) {
    // Send alert to ops team
    $logger->critical('High rate limit block rate', [
        'block_rate' => $stats['block_rate'],
        'date' => $stats['date']
    ]);
}

// Check quota usage
$quotaStatus = $quotaManager->getStatus('user:12345');
if ($quotaStatus['usage_percent'] > 90) {
    // Alert user they're near quota
    sendQuotaWarning($quotaStatus['client_id']);
}
```

---

## ✅ Best Practices

### 1. Rate Limit Strategy

**Public Endpoints** (unauthenticated):
```php
#[RateLimit(limit: 10, window: 60)] // Strict
```

**Authenticated Endpoints**:
```php
#[RateLimit(limit: 100, window: 60)] // Generous
```

**Write Operations**:
```php
#[RateLimit(limit: 20, window: 60, algorithm: 'sliding_window')]
// Use sliding window for accuracy
```

**Read Operations**:
```php
#[RateLimit(limit: 200, window: 60, burst: 300)]
// Allow bursts with token bucket
```

### 2. Throttling Strategy

✅ **Enable throttling for**:
- Search endpoints
- Analytics queries
- Report generation
- Non-critical reads

❌ **Don't throttle**:
- Authentication
- Payment processing
- Data writes
- Real-time operations

### 3. Quota Strategy

**Free Users**: 1,000-10,000 requests/day
**Paid Users**: 100,000-1,000,000 requests/day
**Enterprise**: Custom or unlimited

**Set Different Costs**:
- Simple GET: cost = 1
- Complex query: cost = 5
- Data export: cost = 10
- Bulk operations: cost = 50

### 4. Client Identification

**Priority Order**:
1. User ID (authenticated users)
2. API Key (service accounts)
3. IP Address (anonymous users)

```php
'identification' => [
    'use_user_id' => true,     // Best
    'use_api_key' => true,      // Good
    'use_ip_address' => true,   // Fallback
],
```

### 5. Error Messages

Be clear and actionable:
```php
#[RateLimit(
    limit: 10,
    window: 60,
    message: 'Login attempts exceeded. Try again in 5 minutes or reset your password.'
)]
```

### 6. Monitoring

✅ Track daily:
- Block rates (>20% needs attention)
- Top blocked clients (potential attackers)
- Quota usage trends

✅ Alert on:
- Block rate > 50%
- Individual client > 1000 blocks/day
- Quota usage > 90%

---

## 🐛 Troubleshooting

### Issue: Client Always Rate Limited

**Check**:
1. Identifier format (user:ID vs ip:ADDRESS)
2. Redis connectivity
3. Time sync (clock skew issues)

```bash
# Verify Redis
redis-cli ping

# Check rate limit status
curl /admin/traffic/rate-limit/status/user:12345

# Reset if stuck
curl -X POST /admin/traffic/rate-limit/reset/user:12345
```

### Issue: Rate Limits Not Working

**Check**:
1. Middleware enabled in config
2. #[RateLimit] attribute applied
3. Redis connection configured

```php
// In config/traffic.php
'enabled' => true, // Must be true

// In middleware registration
$app->middleware(TrafficMiddleware::class);
```

### Issue: High Block Rates

**Possible Causes**:
1. Limits too strict
2. Legitimate burst traffic
3. DDoS attack

**Solutions**:
```php
// Increase limits
#[RateLimit(limit: 100, window: 60, burst: 200)]

// Enable throttling instead of hard blocking
#[RateLimit(throttle: true, throttleThreshold: 80)]

// Check if attack
$stats = $rateLimiter->getStats();
// Look for single client causing most blocks
```

### Issue: Redis Performance

**Optimize**:
1. Use connection pooling
2. Enable persistence
3. Monitor memory usage

```bash
# Check Redis memory
redis-cli INFO memory

# Monitor commands
redis-cli MONITOR | grep "traffic:"

# Check key count
redis-cli DBSIZE
```

### Issue: Quota Not Resetting

**Check**:
1. Timezone configuration (UTC)
2. Reset schedule
3. Redis TTL

```php
// Manual reset
$quotaManager->reset('user:12345');

// Check status
$status = $quotaManager->getStatus('user:12345');
echo "Reset at: " . date('Y-m-d H:i:s', $status['reset']);
```

---

## 🎓 Examples

### Example 1: Authentication Endpoint

```php
#[RateLimit(
    limit: 5,                  // 5 attempts
    window: 300,               // per 5 minutes
    algorithm: 'sliding_window', // Strict enforcement
    throttle: false,           // No throttling
    quota: false,              // Don't count against quota
    message: 'Too many login attempts. Please wait 5 minutes.'
)]
public function login(Request $request): Response
{
    // Login logic
}
```

### Example 2: Data Export

```php
#[RateLimit(
    limit: 10,        // 10 exports
    window: 3600,     // per hour
    quota: true,      // Check quota
    quotaCost: 10,    // Expensive operation
    throttle: true,   // Throttle if approaching limit
    message: 'Export limit reached. Please try again in an hour.'
)]
public function exportData(Request $request): Response
{
    // Export logic
}
```

### Example 3: Public API (Generous)

```php
#[RateLimit(
    limit: 1000,              // 1000 requests
    window: 60,                // per minute
    burst: 1500,               // 1500 burst capacity
    algorithm: 'token_bucket', // Allow bursts
    throttle: true,            // Throttle when busy
    throttleThreshold: 800     // Start throttling at 800
)]
public function getData(Request $request): Response
{
    // Public data access
}
```

---

## 📊 Summary

**Traffic Management provides**:
✅ Rate limiting (3 algorithms)
✅ Progressive throttling
✅ Client quotas
✅ Route-level configuration
✅ Admin management APIs
✅ Real-time statistics
✅ Automatic recovery

**Use rate limiting for**: Hard enforcement
**Use throttling for**: Graceful degradation
**Use quotas for**: Long-term usage limits

**Best practice**: Combine all three for comprehensive traffic management.

