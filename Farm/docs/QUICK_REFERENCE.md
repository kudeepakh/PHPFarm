# 🚀 **QUICK REFERENCE - What Changed**

## 🔒 **Authentication (BREAKING CHANGE)**

### ⚠️ **ALL ROUTES NOW REQUIRE AUTHENTICATION BY DEFAULT**

**Before:**
- Routes were public unless you added `'auth'` middleware
- Easy to forget authentication

**After:**
- All routes require authentication automatically
- Must explicitly mark public routes with `#[PublicRoute]` attribute

**How to Mark a Route as Public:**
```php
use PHPFrarm\Core\Attributes\PublicRoute;

#[PublicRoute(reason: 'Public documentation endpoint')]
#[Route('/docs', method: 'GET')]
public function docs(): void {
    // This route is accessible without auth
}
```

**Important:** Public routes are logged for security audit. Always provide a reason.

---

## 🗄️ **Database Access (BREAKING CHANGE)**

### ⚠️ **ALL QUERIES MUST USE STORED PROCEDURES**

**Before:**
```php
// ❌ THIS NO LONGER WORKS
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
```

**After:**
```php
// ✅ USE STORED PROCEDURES ONLY
$result = Database::callProcedure('sp_get_user_by_email', [$email]);
$user = !empty($result) ? $result[0] : null;
```

**Creating Stored Procedures:**
1. Create SQL file in `database/mysql/stored_procedures/{module}/`
2. Use DELIMITER $$ for procedure definitions
3. Follow naming convention: `sp_{action}_{resource}`

**Example:**
```sql
DELIMITER $$

DROP PROCEDURE IF EXISTS sp_get_user_by_email$$
CREATE PROCEDURE sp_get_user_by_email(IN p_email VARCHAR(255))
BEGIN
    SELECT id, email, first_name, last_name, status
    FROM users
    WHERE email = p_email
    LIMIT 1;
END$$

DELIMITER ;
```

---

## 📊 **Audit Logging (NEW)**

### Use `AuditLogger` for Compliance Tracking

**When to Use:**
- User creates/updates/deletes data
- Permission changes
- Security events
- Data access to sensitive resources

**Basic Usage:**
```php
use PHPFrarm\Core\AuditLogger;

// Log data change
AuditLogger::log(
    'user.updated',           // Action
    'user',                   // Resource type
    $userId,                  // Resource ID
    $beforeData,              // Old data
    $afterData,               // New data
    ['reason' => 'Profile update']  // Metadata
);

// Log authentication event
AuditLogger::logAuth('login', $userId, ['method' => 'password']);

// Log security event
AuditLogger::logSecurity('failed_login', ['attempts' => 3, 'ip' => $ip]);
```

**PII is Automatically Masked** - No need to manually redact passwords, tokens, etc.

---

## ⏱️ **Rate Limiting (UPDATED)**

### Redis-Backed Production Rate Limiting

**How It Works:**
- Automatically applied to all routes via global middleware
- Uses Redis for distributed rate limiting
- Supports per-client quotas
- Adds standard rate limit headers

**Response Headers:**
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1706270400
```

**Setting Client-Specific Quotas:**
```php
use PHPFrarm\Services\RateLimiter;

// Set quota for a client
RateLimiter::setQuota(
    $clientId,    // User ID or API key
    'premium',    // Tier: free, basic, premium
    10000,        // Requests allowed
    'daily'       // Period: hourly, daily, monthly
);

// Check quota status
$status = RateLimiter::getStatus($clientId, $limit, $window);

// Reset rate limit (admin only)
RateLimiter::reset($clientId);
```

**Configuration (config/traffic.php):**
```php
'rate_limit' => [
    'algorithm' => 'token_bucket',  // token_bucket, sliding_window, fixed_window
    'default_limit' => 60,          // Requests per window
    'default_window' => 60,         // Seconds
    'burst_multiplier' => 1.5,      // Allow burst up to 90 requests
    'fail_open' => true,            // Allow if Redis down
]
```

---

## ❌ **Error Handling (UPDATED)**

### No More Exception Message Exposure

**Before:**
```php
// ❌ Exposed internals in debug mode
Response::serverError($exception->getMessage());
```

**After:**
```php
// ✅ Always sanitized, use correlation ID to find logs
Response::serverError('error.unexpected');
// Full details logged to MongoDB with correlation ID
```

**For Debugging:**
1. Get correlation ID from error response
2. Search MongoDB logs: `{ correlation_id: "01JQXXX..." }`
3. See full exception details, stack trace, etc.

---

## 🏥 **Health Checks (NEW)**

### Three Health Endpoints Available

**Liveness Probe:**
```bash
GET /health
# Returns 200 if service is running
```

**Readiness Probe:**
```bash
GET /health/ready
# Returns 200 if all dependencies healthy
# Returns 503 if MySQL/MongoDB down
```

**Detailed Health:**
```bash
GET /health/detailed
# Returns detailed status of all components
# Includes latency, versions, disk, memory
```

**All health endpoints are public** (no auth required).

---

## 🔍 **Trace IDs (AUTOMATIC)**

### Every Request Gets Three IDs

**Automatically Generated:**
- `X-Correlation-Id` - Across multiple services/requests
- `X-Transaction-Id` - Business transaction
- `X-Request-Id` - Single HTTP request

**Returned in All Responses:**
```json
{
    "success": true,
    "data": { ... },
    "trace": {
        "correlation_id": "01JQXXX...",
        "transaction_id": "01JQYYY...",
        "request_id": "01JQZZZ..."
    }
}
```

**Logged Automatically:**
- All Logger calls include trace IDs
- All AuditLogger calls include trace IDs
- All database operation logs include trace IDs

**No action required** - framework handles it.

---

## 📝 **Development Checklist**

When creating a new API endpoint:

### 1. Authentication
- [ ] Is this truly a public endpoint?
- [ ] If yes, add `#[PublicRoute(reason: '...')]`
- [ ] If no, authentication is automatic ✅

### 2. Database Access
- [ ] Create stored procedure in `database/mysql/stored_procedures/`
- [ ] Use `Database::callProcedure()` in DAO
- [ ] Never use `SELECT`, `INSERT`, `UPDATE`, `DELETE` directly

### 3. Audit Logging
- [ ] Log user actions with `AuditLogger::log()`
- [ ] Include before/after data for updates
- [ ] Classify security events appropriately

### 4. Error Handling
- [ ] Never expose exception messages to clients
- [ ] Use domain error codes (e.g., 'USER_NOT_FOUND')
- [ ] Log full details with `Logger::error()`

### 5. Testing
- [ ] Test with and without authentication
- [ ] Test rate limiting behavior
- [ ] Verify audit logs are created
- [ ] Check health endpoint includes your dependencies

---

## 🚨 **Common Mistakes to Avoid**

### ❌ **DON'T:**
```php
// Don't use direct SQL
$pdo->prepare("SELECT...");

// Don't expose exceptions
Response::error($e->getMessage());

// Don't skip audit logging
// (Missing AuditLogger call for data change)

// Don't create public routes without reason
#[PublicRoute]  // ❌ No reason provided
```

### ✅ **DO:**
```php
// Use stored procedures
Database::callProcedure('sp_get_user', [$id]);

// Sanitize errors
Logger::error('User fetch failed', ['user_id' => $id, 'error' => $e->getMessage()]);
Response::error('error.user_not_found');

// Log all changes
AuditLogger::log('user.updated', 'user', $id, $before, $after);

// Document public routes
#[PublicRoute(reason: 'Public API documentation')]
```

---

## 🔧 **Troubleshooting**

### "Only CALL statements for stored procedures are allowed"
**Cause:** Trying to use direct SQL  
**Fix:** Create a stored procedure and use `Database::callProcedure()`

### "Authentication required" on a public endpoint
**Cause:** Missing `#[PublicRoute]` attribute  
**Fix:** Add `#[PublicRoute(reason: 'explain why')]` to the controller method

### "Rate limit exceeded"
**Cause:** Too many requests  
**Fix:** Check rate limit headers, implement exponential backoff, or request quota increase

### Redis connection failed
**Cause:** Redis not running or misconfigured  
**Fix:** Check REDIS_HOST, REDIS_PORT in .env. Rate limiting will fail-open (allow requests) if Redis is down and `RATE_LIMIT_FAIL_OPEN=true`

### Audit logs not appearing in MongoDB
**Cause:** MongoDB connection issue or LOG_TO_MONGO=false  
**Fix:** Check MONGO_* environment variables, ensure LOG_TO_MONGO=true

---

## 📚 **Additional Resources**

- [Full Audit Report](./BACKEND_AUDIT_REPORT.md) - Complete analysis
- [Critical Fixes Summary](./CRITICAL_FIXES_COMPLETE.md) - Detailed fix documentation
- [API Features Checklist](./api/API-Features.md) - Enterprise standards
- [Architecture Prompt](./api/Prompt.md) - Framework design principles

---

**Last Updated:** January 26, 2026  
**Framework Version:** PHPFrarm 1.0  
**Compliance Status:** ✅ Critical issues resolved
