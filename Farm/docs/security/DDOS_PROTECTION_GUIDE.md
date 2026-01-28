# 🛡️ **DDoS & Abuse Protection Guide**

> **Module 9: DDoS & Abuse Protection**  
> Comprehensive multi-layer security for REST APIs

---

## 📋 **Table of Contents**

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Quick Start](#quick-start)
4. [Protection Layers](#protection-layers)
5. [Configuration](#configuration)
6. [Usage Examples](#usage-examples)
7. [Admin APIs](#admin-apis)
8. [Monitoring & Troubleshooting](#monitoring)
9. [Best Practices](#best-practices)
10. [Performance Impact](#performance)

---

## 🎯 **Overview** {#overview}

The DDoS Protection module provides **5 layers of defense** against malicious traffic:

| Layer | Protection Type | Detection Method |
|-------|----------------|------------------|
| 1 | IP Reputation | Blacklist/whitelist + reputation scoring |
| 2 | Bot Detection | User-Agent analysis + browser fingerprinting |
| 3 | Geo-Blocking | Country-based access control |
| 4 | WAF | Attack signature detection (SQL injection, XSS, etc.) |
| 5 | Anomaly Detection | Velocity + pattern analysis |

**Key Features:**
- ✅ Route-level control via `#[BotProtection]` attribute
- ✅ Auto-blocking based on violation thresholds
- ✅ Redis-backed for high performance
- ✅ MongoDB logging for security events
- ✅ Zero-config defaults (works out of the box)
- ✅ Admin APIs for real-time management

---

## 🏗️ **Architecture** {#architecture}

```
┌─────────────────────────────────────────────┐
│         Incoming HTTP Request               │
└──────────────┬──────────────────────────────┘
               │
               ▼
┌──────────────────────────────────────────────┐
│     DDoSProtectionMiddleware                 │
│     (Orchestrates all layers)                │
└──────────────┬───────────────────────────────┘
               │
     ┌─────────┴──────────┐
     │                    │
     ▼                    ▼
┌─────────────┐     ┌────────────────┐
│ Layer 1:    │     │ Layer 2:       │
│ IP Reputa-  │     │ Bot Detection  │
│ tion Check  │     │                │
└─────┬───────┘     └────────┬───────┘
      │                      │
      └──────────┬───────────┘
                 │
     ┌───────────┴────────────┐
     │                        │
     ▼                        ▼
┌─────────────┐     ┌────────────────┐
│ Layer 3:    │     │ Layer 4:       │
│ Geo-Blocking│     │ WAF Scanning   │
│             │     │                │
└─────┬───────┘     └────────┬───────┘
      │                      │
      └──────────┬───────────┘
                 │
                 ▼
       ┌─────────────────┐
       │ Layer 5:        │
       │ Anomaly         │
       │ Detection       │
       └────────┬────────┘
                │
     ┌──────────┴────────────┐
     │                       │
   BLOCK                   ALLOW
     │                       │
     ▼                       ▼
┌──────────┐         ┌──────────────┐
│ 403      │         │ Process      │
│ Forbidden│         │ Request      │
└──────────┘         └──────────────┘
```

---

## 🚀 **Quick Start** {#quick-start}

### Step 1: Enable Protection Globally

Add middleware to your middleware stack:

```php
// bootstrap/middleware.php
use Farm\Backend\App\Middleware\DDoSProtectionMiddleware;

return [
    // ... other middleware
    DDoSProtectionMiddleware::class,
];
```

### Step 2: Configure Environment

```bash
# .env
DDOS_PROTECTION_ENABLED=true

# Bot Detection
BOT_DETECTION_ENABLED=true
BOT_ALLOW_SEARCH_ENGINES=true

# IP Reputation
IP_AUTO_BLOCK_ENABLED=true
IP_AUTO_BLOCK_THRESHOLD=10

# WAF
WAF_ENABLED=true
WAF_BLOCK_ON_DETECTION=true

# Anomaly Detection
ANOMALY_DETECTION_ENABLED=true
ANOMALY_VELOCITY_PER_SECOND=10
ANOMALY_VELOCITY_PER_MINUTE=100
```

### Step 3: Protect Your Routes

```php
use Farm\Backend\App\Core\Security\Attributes\BotProtection;

class UserController
{
    // Standard protection (blocks bots, enables WAF)
    #[BotProtection::standard()]
    public function getUsers()
    {
        // Your code here
    }
    
    // Maximum protection for sensitive endpoints
    #[BotProtection::maximum()]
    public function deleteAccount()
    {
        // High-security operation
    }
}
```

**That's it!** 🎉 Your APIs are now protected.

---

## 🔐 **Protection Layers** {#protection-layers}

### Layer 1: IP Reputation Management

**Purpose:** Block known malicious IPs, whitelist trusted IPs

**Features:**
- Blacklist/whitelist management
- Reputation scoring (0-100)
- Auto-blocking after threshold violations
- Temporary blocks with TTL

**Example:**

```php
use Farm\Backend\App\Core\Security\IpReputationManager;

$ipReputation = app(IpReputationManager::class);

// Check if IP is blocked
if ($ipReputation->isBlocked('1.2.3.4')) {
    // Handle blocked IP
}

// Add to blacklist
$ipReputation->addToBlacklist('1.2.3.4', 'Repeated violations', 3600);

// Add to whitelist (trusted)
$ipReputation->addToWhitelist('10.0.0.1', 'Internal server');

// Record violation (auto-blocks after threshold)
$ipReputation->recordViolation('1.2.3.4', 'rate_limit_exceeded');

// Get IP status
$status = $ipReputation->getIpStatus('1.2.3.4');
// [
//   'ip' => '1.2.3.4',
//   'blocked' => false,
//   'reputation' => 50,
//   'reputation_level' => 'Neutral',
//   'violations' => 3
// ]
```

---

### Layer 2: Bot Detection

**Purpose:** Identify and block automated bot traffic

**Detection Methods:**
1. **User-Agent Analysis** – Known bot patterns
2. **Browser Fingerprinting** – Missing required headers
3. **Behavioral Analysis** – Request patterns

**Example:**

```php
use Farm\Backend\App\Core\Security\BotDetector;

$botDetector = app(BotDetector::class);

// Check if request is from a bot
$headers = [
    'User-Agent' => 'python-requests/2.31',
    'Accept' => '*/*',
];

if ($botDetector->isBot($headers, $ip)) {
    // Block bot
}

// Get detailed analysis
$analysis = $botDetector->analyzeBotTraffic($headers, $ip);
// [
//   'is_bot' => true,
//   'bot_type' => 'python',
//   'confidence' => 90,
//   'suspicious_pattern' => true,
//   'valid_fingerprint' => false
// ]
```

**Known Bot Patterns:**
- `python-requests`, `curl`, `wget` (CLI tools)
- `scrapy`, `bot`, `crawler`, `spider` (scrapers)
- `nikto`, `nmap`, `masscan` (security scanners)

**Whitelisted Bots:**
- `googlebot`, `bingbot` (search engines)
- `uptimerobot`, `pingdom` (monitoring)

---

### Layer 3: Geo-Blocking

**Purpose:** Country-based access control

**Modes:**
- **Blacklist Mode** – Block specific countries
- **Whitelist Mode** – Only allow specific countries

**Example:**

```php
use Farm\Backend\App\Core\Security\GeoBlocker;

$geoBlocker = app(GeoBlocker::class);

// Check if IP is geo-blocked
if ($geoBlocker->isBlocked('1.2.3.4')) {
    // Block request
}

// Get country
$country = $geoBlocker->getCountry('1.2.3.4'); // 'US'

// Block a country
$geoBlocker->blockCountry('CN');

// Allow a country (whitelist mode)
$geoBlocker->allowCountry('US');
```

**Route-Level Geo-Blocking:**

```php
// Only allow US and Canada
#[BotProtection(
    allowedCountries: ['US', 'CA'],
    geoMode: 'whitelist'
)]
public function restrictedContent()
{
    // Only accessible from US/CA
}

// Block specific countries
#[BotProtection(
    blockedCountries: ['CN', 'RU'],
    geoMode: 'blacklist'
)]
public function sensitiveData()
{
    // Blocked from CN, RU
}
```

**Geolocation Providers:**
- **ip-api.com** (free, 45 requests/min)
- **MaxMind GeoIP2** (local database, unlimited)
- Custom provider (implement your own)

---

### Layer 4: WAF (Web Application Firewall)

**Purpose:** Detect common attack vectors

**Detects:**
- ✅ SQL Injection
- ✅ Cross-Site Scripting (XSS)
- ✅ Path Traversal
- ✅ Command Injection
- ✅ LDAP Injection
- ✅ XML External Entity (XXE)
- ✅ Server-Side Request Forgery (SSRF)

**Example:**

```php
use Farm\Backend\App\Core\Security\WafEngine;

$waf = app(WafEngine::class);

// Scan input
$input = "' OR 1=1 --";
$result = $waf->scan($input, 'query');

// [
//   'detected' => true,
//   'attacks' => ['sql_injection'],
//   'severity' => 10,
//   'should_block' => true
// ]

// Test specific attack types
$waf->detectSqlInjection("' OR '1'='1"); // true
$waf->detectXss("<script>alert('XSS')</script>"); // true
$waf->detectPathTraversal("../../etc/passwd"); // true

// Add custom rule
$waf->addCustomRule('credit_card', '/\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}/');
```

**Auto-Blocking:**

WAF automatically blocks detected attacks and records violations:

```php
// This input triggers SQL injection detection
$query = ['username' => "admin' OR 1=1 --"];

// WAF scans and blocks
// Response: 403 Forbidden
// Reason: waf:sql_injection
```

---

### Layer 5: Anomaly Detection

**Purpose:** Detect unusual patterns and velocity attacks

**Detects:**
1. **Velocity Anomalies** – Too many requests per second/minute/hour
2. **Endpoint Abuse** – Repeated access to same endpoint
3. **Pattern Anomalies** – Unusual request sequences
4. **Scanning Behavior** – Rapid endpoint enumeration

**Example:**

```php
use Farm\Backend\App\Core\Security\AnomalyDetector;

$anomalyDetector = app(AnomalyDetector::class);

// Detect anomaly
$result = $anomalyDetector->detectAnomaly($ip, '/api/users', 'GET');

// [
//   'has_anomaly' => true,
//   'anomaly_types' => ['velocity_exceeded', 'endpoint_abuse'],
//   'severity' => 7
// ]

// Get velocity metrics
$metrics = $anomalyDetector->getVelocityMetrics($ip);
// [
//   'per_second' => 15,   // Current rate
//   'per_minute' => 120,
//   'per_hour' => 950,
//   'limits' => [
//     'per_second' => 10,
//     'per_minute' => 100,
//     'per_hour' => 1000
//   ]
// ]

// Clear tracking
$anomalyDetector->clearTracking($ip);
```

**Route-Level Rate Limiting:**

```php
// Custom rate limits per route
#[BotProtection(
    maxRequestsPerSecond: 5,
    maxRequestsPerMinute: 50,
    maxRequestsPerHour: 500
)]
public function criticalEndpoint()
{
    // Strict rate limits
}
```

---

## ⚙️ **Configuration** {#configuration}

### Environment Variables

```bash
# Master Switch
DDOS_PROTECTION_ENABLED=true

# Bot Detection
BOT_DETECTION_ENABLED=true
BOT_DETECTION_STRICT=false
BOT_ALLOW_SEARCH_ENGINES=true

# IP Reputation
IP_REPUTATION_ENABLED=true
IP_AUTO_BLOCK_ENABLED=true
IP_AUTO_BLOCK_THRESHOLD=10
IP_AUTO_BLOCK_DURATION=3600
IP_WHITELIST=10.0.0.1,192.168.1.1
IP_BLACKLIST=

# Geo-Blocking
GEO_BLOCKING_ENABLED=false
GEO_BLOCKING_MODE=blacklist
GEO_BLOCKED_COUNTRIES=
GEO_ALLOWED_COUNTRIES=US,CA,GB
GEO_PROVIDER=ip-api

# WAF
WAF_ENABLED=true
WAF_SENSITIVITY=medium
WAF_BLOCK_ON_DETECTION=true

# Anomaly Detection
ANOMALY_DETECTION_ENABLED=true
ANOMALY_VELOCITY_PER_SECOND=10
ANOMALY_VELOCITY_PER_MINUTE=100
ANOMALY_VELOCITY_PER_HOUR=1000
ANOMALY_ENDPOINT_THRESHOLD=50

# Response
DDOS_BLOCK_STATUS_CODE=403
DDOS_BLOCK_MESSAGE="Access denied due to security policy"
```

### Config File (`config/ddos.php`)

See [config/ddos.php](config/ddos.php) for complete configuration options.

---

## 💡 **Usage Examples** {#usage-examples}

### Example 1: Public API (Minimal Protection)

```php
// Allow all traffic, log only
#[BotProtection::minimal()]
public function getPublicData()
{
    return ['data' => 'public'];
}
```

### Example 2: Standard API (Default Protection)

```php
// Block bots, enable WAF and anomaly detection
#[BotProtection::standard()]
public function getUsers()
{
    return User::all();
}
```

### Example 3: High-Security API (Maximum Protection)

```php
// All protections enabled, strict limits
#[BotProtection::maximum()]
public function deleteAccount()
{
    // Critical operation
}
```

### Example 4: Geo-Restricted Content

```php
// Only allow US traffic
#[BotProtection(
    allowedCountries: ['US'],
    geoMode: 'whitelist',
    blockBots: true
)]
public function usOnlyContent()
{
    return ['message' => 'US-only content'];
}
```

### Example 5: Custom Protection Rules

```php
#[BotProtection(
    blockBots: true,
    allowGoodBots: false,          // Block ALL bots
    strictMode: true,               // Strict fingerprint check
    maxRequestsPerSecond: 2,
    maxRequestsPerMinute: 20,
    blockedCountries: ['CN', 'RU'],
    enableWaf: true,
    anomalySensitivity: 9,
    onViolation: 'block'
)]
public function superSensitiveApi()
{
    // Maximum protection
}
```

### Example 6: Log-Only Mode (Testing)

```php
// Don't block, just log violations
#[BotProtection(
    blockBots: false,
    onViolation: 'log'
)]
public function testEndpoint()
{
    // Log violations but don't block
}
```

---

## 🔧 **Admin APIs** {#admin-apis}

### Security Overview

```http
GET /admin/security/overview
```

**Response:**
```json
{
  "success": true,
  "data": {
    "ip_reputation": {
      "blacklisted_count": 45,
      "whitelisted_count": 12,
      "auto_block_enabled": true
    },
    "geo_blocking": {
      "enabled": true,
      "mode": "blacklist",
      "blocked_countries_count": 3
    },
    "anomaly_detection": {
      "anomalies_detected": 127,
      "velocity_per_second_limit": 10
    },
    "waf": {
      "enabled": true,
      "detection_types": ["sql_injection", "xss", ...]
    }
  }
}
```

### IP Management

#### Blacklist IP

```http
POST /admin/security/ip/blacklist
Content-Type: application/json

{
  "ip": "1.2.3.4",
  "reason": "Repeated attacks",
  "duration": 3600
}
```

#### Remove from Blacklist

```http
DELETE /admin/security/ip/blacklist/1.2.3.4
```

#### Whitelist IP

```http
POST /admin/security/ip/whitelist
Content-Type: application/json

{
  "ip": "10.0.0.1",
  "reason": "Internal server"
}
```

#### List Blacklist

```http
GET /admin/security/ip/blacklist
```

### Geo-Blocking Management

#### Block Country

```http
POST /admin/security/geo/block
Content-Type: application/json

{
  "country": "CN"
}
```

#### List Blocked Countries

```http
GET /admin/security/geo/blocked
```

### WAF Management

#### Add Custom WAF Rule

```http
POST /admin/security/waf/rule
Content-Type: application/json

{
  "name": "detect_credit_card",
  "pattern": "/\\d{4}[\\s-]?\\d{4}[\\s-]?\\d{4}[\\s-]?\\d{4}/"
}
```

#### Test WAF Scan

```http
POST /admin/security/waf/scan
Content-Type: application/json

{
  "input": "' OR 1=1 --",
  "context": "test"
}
```

### Analysis

#### Get IP Analysis

```http
GET /admin/security/ip/1.2.3.4
```

**Response:**
```json
{
  "success": true,
  "data": {
    "ip": "1.2.3.4",
    "reputation": {
      "blocked": false,
      "reputation": 65,
      "reputation_level": "Good",
      "violations": 2
    },
    "geo": {
      "country_code": "US",
      "is_blocked": false
    },
    "anomaly": {
      "velocity": {
        "per_second": 3,
        "per_minute": 45,
        "per_hour": 320
      }
    }
  }
}
```

---

## 📊 **Monitoring & Troubleshooting** {#monitoring}

### MongoDB Security Logs

All security events are logged to MongoDB:

```javascript
// Query security events
db.security_events.find({
  "timestamp": { $gte: ISODate("2026-01-18") }
}).sort({ timestamp: -1 }).limit(100)

// Find blocked requests
db.security_events.find({
  "event": "request_blocked"
})

// Find SQL injection attempts
db.security_events.find({
  "event": "waf_attack_detected",
  "attacks": "sql_injection"
})
```

### Health Check

```http
GET /admin/security/health
```

**Response:**
```json
{
  "success": true,
  "data": {
    "status": "healthy",
    "modules": {
      "ip_reputation": "active",
      "bot_detector": "active",
      "geo_blocker": "active",
      "anomaly_detector": "active",
      "waf_engine": "active"
    }
  }
}
```

### Common Issues

#### Issue: Legitimate users getting blocked

**Solution:**
1. Whitelist their IP: `POST /admin/security/ip/whitelist`
2. Lower anomaly sensitivity in route:
   ```php
   #[BotProtection(anomalySensitivity: 3)]
   ```
3. Use log-only mode: `onViolation: 'log'`

#### Issue: Search engines blocked

**Solution:**
Enable good bot allowlist:
```bash
BOT_ALLOW_SEARCH_ENGINES=true
```

#### Issue: High false-positive rate

**Solution:**
Adjust WAF sensitivity:
```bash
WAF_SENSITIVITY=low
```

---

## ✅ **Best Practices** {#best-practices}

### 1. Start with Log-Only Mode

```php
#[BotProtection(onViolation: 'log')]
```

Monitor logs, then enable blocking once confident.

### 2. Whitelist Internal IPs

```bash
IP_WHITELIST=10.0.0.0/8,192.168.0.0/16
```

### 3. Use Progressive Protection

- **Public APIs** → `BotProtection::minimal()`
- **Standard APIs** → `BotProtection::standard()`
- **Critical APIs** → `BotProtection::maximum()`

### 4. Monitor Security Logs

Set up alerts for:
- High anomaly detection rate
- WAF attack detection spikes
- Auto-block threshold breaches

### 5. Combine with Rate Limiting

Use rate limiting middleware alongside DDoS protection for best results.

---

## ⚡ **Performance Impact** {#performance}

| Layer | Overhead | Mitigations |
|-------|----------|-------------|
| IP Reputation | ~0.5ms | Redis caching |
| Bot Detection | ~1ms | Header-only analysis |
| Geo-Blocking | ~2ms (API) / ~0.1ms (local) | Cache results (24h TTL) |
| WAF | ~3ms | Skip for trusted IPs |
| Anomaly Detection | ~1ms | Redis atomic ops |
| **Total** | **~7.5ms** | Minimal for security gained |

**Recommendations:**
- Use Redis for caching (required)
- Use MaxMind local database for geo-blocking (no API calls)
- Whitelist internal/trusted IPs to skip checks

---

## 🎓 **Summary**

**What You Get:**
- ✅ 5-layer DDoS protection
- ✅ Route-level configuration via attributes
- ✅ Auto-blocking with reputation scoring
- ✅ Real-time admin APIs
- ✅ MongoDB security event logging
- ✅ ~7.5ms overhead per request

**Next Steps:**
1. Configure environment variables
2. Add middleware to your app
3. Apply `#[BotProtection]` to routes
4. Monitor logs and adjust thresholds

**Module Status:** ✅ **COMPLETE** (Module 9 – 100%)

---

**Questions?** Check [config/ddos.php](config/ddos.php) or admin APIs documentation.
