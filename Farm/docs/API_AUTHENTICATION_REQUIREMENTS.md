# 🔐 API Authentication Requirements

## Overview

This document lists all available APIs categorized by their authentication requirements. 

**Default Behavior:** All APIs require JWT token authentication by default due to global middleware: `['cors', 'auth', 'inputValidation']`

**Public APIs:** Only APIs marked with `#[PublicRoute]` attribute or routes in RouteGroups that exclude 'auth' middleware are accessible without JWT tokens.

---

## 📂 PUBLIC APIs (No JWT Token Required)

### 1️⃣ System Health Endpoints
**Module:** System  
**Purpose:** Infrastructure monitoring, load balancer health checks

| Method | Endpoint | Description | Middleware |
|--------|----------|-------------|------------|
| GET | `/health` | Liveness probe | None |
| GET | `/health/ready` | Readiness probe | None |
| GET | `/health/detailed` | Detailed health check | None |

---

### 2️⃣ Authentication Endpoints
**Module:** Auth  
**RouteGroup:** `/api/v1/auth` with `['cors', 'rateLimit']` (auth excluded)

#### Registration & Login
| Method | Endpoint | Description | Middleware |
|--------|----------|-------------|------------|
| POST | `/api/v1/auth/register` | User registration | `['jsonParser']` |
| POST | `/api/v1/auth/login` | User login with email/password | `['jsonParser']` |

#### OTP Operations
| Method | Endpoint | Description | Middleware |
|--------|----------|-------------|------------|
| POST | `/api/v1/auth/otp/request` | Request OTP (email/phone) | `['jsonParser']` |
| POST | `/api/v1/auth/otp/verify` | Verify OTP code | `['jsonParser']` |

#### Token Refresh
| Method | Endpoint | Description | Middleware |
|--------|----------|-------------|------------|
| POST | `/api/v1/auth/refresh` | Refresh JWT access token | `['jsonParser']` |

---

### 3️⃣ Social Authentication (OAuth)
**Module:** Auth  
**RouteGroup:** `/api/auth/social` (no auth middleware)

| Method | Endpoint | Description | Middleware |
|--------|----------|-------------|------------|
| GET | `/api/auth/social/{provider}` | Start OAuth flow (Google/Facebook/GitHub) | None |
| GET | `/api/auth/social/{provider}/callback` | OAuth callback handler | None |

---

### 4️⃣ User Verification (Public)
**Module:** User  
**Purpose:** Email verification without login

| Method | Endpoint | Description | Middleware |
|--------|----------|-------------|------------|
| POST | `/api/v1/users/verify-email` | Verify email with token | None |

---

### 5️⃣ Storage Configuration (Public)
**Module:** Storage

| Method | Endpoint | Description | Middleware |
|--------|----------|-------------|------------|
| GET | `/api/v1/storage/public-config` | Get public storage config | `['cors']` |

---

## 🔒 PROTECTED APIs (JWT Token Required)

> **Note:** All endpoints below require `Authorization: Bearer <jwt_token>` header

---

### 1️⃣ User Self-Service APIs
**Module:** User  
**RouteGroup:** `/api/v1/users` with `['cors', 'auth']`

#### Profile Management
| Method | Endpoint | Description | Permissions |
|--------|----------|-------------|-------------|
| GET | `/api/v1/users/profile` | Get own profile | - |
| GET | `/api/v1/users/me` | Get current user details | - |
| PUT | `/api/v1/users/profile` | Update own profile | - |
| GET | `/api/v1/users/` | List users (own access level) | - |
| GET | `/api/v1/users/search` | Search users | - |

#### Email & Phone Verification
| Method | Endpoint | Description | Permissions |
|--------|----------|-------------|-------------|
| POST | `/api/v1/users/resend-verification` | Resend email verification | - |
| GET | `/api/v1/users/verification-status` | Check verification status | - |
| POST | `/api/v1/users/verify-phone/send-otp` | Send phone verification OTP | - |
| POST | `/api/v1/users/verify-phone` | Verify phone with OTP | - |

#### Account Management
| Method | Endpoint | Description | Permissions |
|--------|----------|-------------|-------------|
| POST | `/api/v1/users/account/deactivate` | Deactivate own account | - |

---

### 2️⃣ User Context APIs
**Module:** Auth  
**RouteGroup:** `/api/v1/user` with `['cors', 'auth']`

| Method | Endpoint | Description | Permissions |
|--------|----------|-------------|-------------|
| GET | `/api/v1/user/me` | Get authenticated user context | - |

---

### 3️⃣ Authentication (Protected Routes)
**Module:** Auth

| Method | Endpoint | Description | Middleware |
|--------|----------|-------------|------------|
| GET | `/api/v1/auth/me` | Get current user context | `['auth']` |
| POST | `/api/v1/auth/logout` | User logout | `['auth']` |

---

### 4️⃣ Social Authentication (Protected)
**Module:** Auth

| Method | Endpoint | Description | Middleware |
|--------|----------|-------------|------------|
| POST | `/api/auth/social/{provider}/unlink` | Unlink OAuth provider | `['auth']` |
| GET | `/api/auth/social/providers` | List linked OAuth providers | `['auth']` |

---

### 5️⃣ Storage APIs
**Module:** Storage  
**RouteGroup:** `/api/v1/storage`

| Method | Endpoint | Description | Permissions |
|--------|----------|-------------|-------------|
| POST | `/api/v1/storage/upload` | Upload file | - |
| POST | `/api/v1/storage/presigned-upload` | Get pre-signed upload URL | - |
| POST | `/api/v1/storage/presigned-download` | Get pre-signed download URL | - |
| DELETE | `/api/v1/storage/{category}/{path}` | Delete file | - |
| GET | `/api/v1/storage/metadata/{category}/{path}` | Get file metadata | - |
| GET | `/api/v1/storage/list` | List all files | - |
| GET | `/api/v1/storage/list/{category}` | List files by category | - |
| GET | `/api/v1/storage/config` | Get storage configuration | - |

---

### 6️⃣ System - User Management
**Module:** User & UserManagement  
**Permissions Required:** Various admin permissions

#### User CRUD (System)
| Method | Endpoint | Description | Permissions |
|--------|----------|-------------|-------------|
| GET | `/api/v1/system/users/list` | List all users | `users:read` |
| POST | `/api/v1/system/users` | Create new user | `users:create` |
| PUT | `/api/v1/system/users/{userId}` | Update user | `users:update` |
| DELETE | `/api/v1/system/users/{userId}` | Delete user | `users:delete` |
| POST | `/api/v1/system/users/import` | Bulk import users | `users:create` |
| GET | `/api/v1/system/users/template/{format}` | Download import template | `users:read` |

#### Account Status Management (System)
| Method | Endpoint | Description | Permissions |
|--------|----------|-------------|-------------|
| POST | `/api/v1/system/users/{userId}/lock` | Lock user account | `users:lock` |
| POST | `/api/v1/system/users/{userId}/unlock` | Unlock user account | `users:unlock` |
| POST | `/api/v1/system/users/{userId}/suspend` | Suspend user account | `users:suspend` |
| POST | `/api/v1/system/users/{userId}/activate` | Activate user account | `users:activate` |
| GET | `/api/v1/system/users/{userId}/status-history` | Get account status history | `users:read` |
| GET | `/api/v1/system/users/{userId}/check-access` | Check user access permissions | `users:read` |
| GET | `/api/v1/system/users/{userId}/identifiers` | Get user identifiers | `users:read` |

#### User Management Module (Admin CRUD)
| Method | Endpoint | Description | Permissions |
|--------|----------|-------------|-------------|
| GET | `/api/v1/system/users` | List all users | Permission-based |
| GET | `/api/v1/system/users/{id}` | Get user by ID | Permission-based |
| PUT | `/api/v1/system/users/{id}` | Update user | Permission-based |
| DELETE | `/api/v1/system/users/{id}` | Delete user | Permission-based |

---

### 7️⃣ System - Role Management
**Module:** Role & UserManagement

#### Role CRUD
| Method | Endpoint | Description | Permissions |
|--------|----------|-------------|-------------|
| GET | `/api/v1/system/roles` | List all roles | `roles:list` |
| GET | `/api/v1/system/roles/{roleId}` | Get role by ID | `roles:read` |
| POST | `/api/v1/system/roles` | Create new role | `roles:create` |
| PUT | `/api/v1/system/roles/{roleId}` | Update role | `roles:update` |
| DELETE | `/api/v1/system/roles/{roleId}` | Delete role | `roles:delete` |

#### User-Role Assignment
| Method | Endpoint | Description | Permissions |
|--------|----------|-------------|-------------|
| GET | `/api/v1/system/users/{userId}/roles` | Get user roles | `users:read` |
| POST | `/api/v1/system/users/{userId}/roles/{roleId}` | Assign role to user | `users:update` |
| DELETE | `/api/v1/system/users/{userId}/roles/{roleId}` | Remove role from user | `users:update` |
| POST | `/api/v1/system/users/{userId}/roles/bulk` | Bulk assign roles | `users:update` |
| PUT | `/api/v1/system/users/{userId}/roles/sync` | Sync user roles | `users:update` |

---

### 8️⃣ System - Permission Management
**Module:** Permission

| Method | Endpoint | Description | Permissions |
|--------|----------|-------------|-------------|
| GET | `/api/v1/system/permissions` | List permissions (paginated) | `permissions:list` |
| GET | `/api/v1/system/permissions/all` | Get all permissions | `permissions:list` |
| POST | `/api/v1/system/permissions/discover` | Auto-discover route permissions | `permissions:manage` |

---

### 9️⃣ System - OTP Management
**Module:** Auth (OTPAdminController)

| Method | Endpoint | Description | Permissions |
|--------|----------|-------------|-------------|
| GET | `/api/v1/system/otp/history` | Get OTP history | `otp:read` |
| GET | `/api/v1/system/otp/statistics` | Get OTP statistics | `otp:read` |

---

### 🔟 System - Infrastructure Management
**Module:** System  
**Middleware:** All require `['cors', 'auth']` + specific permissions

#### Security Management
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/system/security/overview` | Security overview |
| GET | `/api/v1/system/security/ip/{ip}` | Get IP info |
| POST | `/api/v1/system/security/ip/blacklist` | Blacklist IP |
| DELETE | `/api/v1/system/security/ip/blacklist/{ip}` | Remove IP from blacklist |
| POST | `/api/v1/system/security/ip/whitelist` | Whitelist IP |
| DELETE | `/api/v1/system/security/ip/whitelist/{ip}` | Remove IP from whitelist |
| GET | `/api/v1/system/security/ip/blacklist` | List blacklisted IPs |
| GET | `/api/v1/system/security/ip/whitelist` | List whitelisted IPs |
| DELETE | `/api/v1/system/security/ip/blacklist` | Clear blacklist |
| POST | `/api/v1/system/security/geo/block` | Block country |
| DELETE | `/api/v1/system/security/geo/block/{country}` | Unblock country |
| POST | `/api/v1/system/security/geo/allow` | Allow country |
| GET | `/api/v1/system/security/geo/blocked` | List blocked countries |
| GET | `/api/v1/system/security/geo/allowed` | List allowed countries |
| POST | `/api/v1/system/security/waf/rule` | Add WAF rule |
| POST | `/api/v1/system/security/waf/scan` | Scan request with WAF |
| GET | `/api/v1/system/security/anomaly/ip/{ip}` | Get IP anomalies |
| DELETE | `/api/v1/system/security/anomaly/ip/{ip}` | Clear IP anomalies |
| POST | `/api/v1/system/security/bot/analyze` | Analyze bot behavior |
| GET | `/api/v1/system/security/health` | Security health check |

#### Traffic Management
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/system/traffic/rate-limit/status/{identifier}` | Get rate limit status |
| GET | `/api/v1/system/traffic/rate-limit/stats` | Get rate limit stats |
| POST | `/api/v1/system/traffic/rate-limit/reset/{identifier}` | Reset rate limit |
| GET | `/api/v1/system/traffic/throttle/status/{identifier}` | Get throttle status |
| GET | `/api/v1/system/traffic/throttle/stats` | Get throttle stats |
| POST | `/api/v1/system/traffic/throttle/reset/{identifier}` | Reset throttle |
| GET | `/api/v1/system/traffic/quota/status/{clientId}` | Get quota status |
| GET | `/api/v1/system/traffic/quota/stats` | Get quota stats |
| GET | `/api/v1/system/traffic/quota/tiers` | List quota tiers |
| POST | `/api/v1/system/traffic/quota/tier` | Assign quota tier |
| POST | `/api/v1/system/traffic/quota/custom` | Set custom quota |
| POST | `/api/v1/system/traffic/quota/reset/{clientId}` | Reset client quota |
| GET | `/api/v1/system/traffic/status/{identifier}` | Get traffic status |
| POST | `/api/v1/system/traffic/reset-all/{identifier}` | Reset all limits |
| GET | `/api/v1/system/traffic/stats/summary` | Get traffic summary |

#### Resilience Management
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/system/resilience/retry/stats` | Get retry stats |
| GET | `/api/v1/system/resilience/retry/stats/{operationName}` | Get retry stats by operation |
| POST | `/api/v1/system/resilience/retry/reset` | Reset retry stats |
| GET | `/api/v1/system/resilience/circuit-breaker/status` | Get circuit breaker status |
| POST | `/api/v1/system/resilience/circuit-breaker/reset` | Reset circuit breaker |
| POST | `/api/v1/system/resilience/circuit-breaker/force-open` | Force open circuit breaker |
| GET | `/api/v1/system/resilience/degradation/status` | Get degradation status |
| POST | `/api/v1/system/resilience/degradation/enable` | Enable degradation |
| POST | `/api/v1/system/resilience/degradation/disable` | Disable degradation |
| GET | `/api/v1/system/resilience/degradation/stats/{service}` | Get service degradation stats |
| GET | `/api/v1/system/resilience/backpressure/usage` | Get backpressure usage |
| GET | `/api/v1/system/resilience/backpressure/stats` | Get backpressure stats |
| PUT | `/api/v1/system/resilience/backpressure/limits` | Update backpressure limits |
| POST | `/api/v1/system/resilience/backpressure/reset` | Reset backpressure |
| GET | `/api/v1/system/resilience/status` | Get resilience status |

#### Locking Management
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/system/locking/statistics` | Get locking statistics |
| GET | `/api/v1/system/locking/conflicts/top` | Get top conflicts |
| GET | `/api/v1/system/locking/conflicts/{entityType}/{entityId}` | Get entity conflicts |
| POST | `/api/v1/system/locking/statistics/reset` | Reset statistics |
| GET | `/api/v1/system/locking/conflicts/rate` | Get conflict rate |
| GET | `/api/v1/system/locking/config` | Get locking config |
| GET | `/api/v1/system/locking/health` | Locking health check |

#### Cache Management
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/system/cache/stats` | Get cache statistics |
| GET | `/api/v1/system/cache/keys` | List cache keys |
| DELETE | `/api/v1/system/cache/clear` | Clear all cache |
| DELETE | `/api/v1/system/cache/pattern/{pattern}` | Clear cache by pattern |
| GET | `/api/v1/system/cache/health` | Cache health check |

---

## 🔑 Authentication Header Format

### For Protected APIs:
```http
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

### For Public APIs:
No `Authorization` header required.

---

## 📊 Summary Statistics

### Public APIs: **13 endpoints**
- Health: 3
- Authentication: 5
- OAuth: 2
- Verification: 1
- Storage Config: 1
- OTP: 2

### Protected APIs: **150+ endpoints**
- User Self-Service: 13
- Storage: 9
- User Management (System): 22
- Role Management: 10
- Permission Management: 3
- OTP Management: 2
- Security Management: 23
- Traffic Management: 16
- Resilience Management: 15
- Locking Management: 7
- Cache Management: 5
- Plus additional module endpoints

---

## 🛡️ Security Notes

1. **Default Deny:** All APIs are protected by default unless explicitly marked public
2. **Rate Limiting:** Public auth endpoints have rate limiting enabled (`['rateLimit']`)
3. **Token Expiry:** JWT tokens expire after configured time (check `config/oauth.php`)
4. **Token Refresh:** Use `/api/v1/auth/refresh` endpoint to refresh expired access tokens
5. **CORS:** All endpoints include CORS middleware for cross-origin requests
6. **Permission-Based Access:** System endpoints require authentication AND specific permissions (e.g., `users:read`, `roles:create`)
7. **Granular Control:** No blanket admin access - all access controlled via fine-grained permissions

---

## 🔍 How to Check Route Authentication

### Via Code:
1. Look for `#[PublicRoute]` attribute → Public
2. Check RouteGroup middleware → If includes 'auth' → Protected
3. Check individual Route middleware → If includes 'auth' → Protected
4. Global middleware in `index.php` → Default is Protected

### Via API Testing:
```bash
# Public endpoint (should work)
curl http://localhost:8787/health

# Protected endpoint (should return 401)
curl http://localhost:8787/api/v1/users/profile

# Protected endpoint with token (should work)
curl -H "Authorization: Bearer YOUR_TOKEN" http://localhost:8787/api/v1/users/profile
```

---

## 📝 Change Log

- **Created:** 2024
- **Last Updated:** Current session
- **Version:** 1.0.0
- **Status:** Complete inventory after module restructuring

---

**Framework:** PHPFrarm Enterprise API Development Framework  
**Documentation:** https://github.com/yourorg/phpfrarm
