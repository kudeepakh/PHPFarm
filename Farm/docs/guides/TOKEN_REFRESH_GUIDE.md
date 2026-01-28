# 🔄 Automatic Token Refresh Guide

## Overview

The PHPFrarm framework now supports **automatic token refresh** when the access token expires. The frontend will automatically attempt to refresh the token using the refresh token before redirecting to login.

---

## 🎯 How It Works

### Token Lifecycle

```
1. User logs in
   ↓
2. Receives access_token (1 hour) + refresh_token (7 days)
   ↓
3. Makes API requests with access_token
   ↓
4. Access token expires → API returns 401
   ↓
5. Frontend intercepts 401 → calls /auth/refresh with refresh_token
   ↓
6. Receives new access_token + new refresh_token
   ↓
7. Original API request is automatically retried
   ↓
8. User continues without interruption
```

---

## 📡 Backend Endpoint

### POST `/api/v1/auth/refresh`

**Request:**
```json
{
  "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

**Response (Success):**
```json
{
  "success": true,
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "expires_at": "2026-01-24T12:34:36Z",
    "refresh_expires_at": "2026-01-31T11:34:36Z"
  },
  "message": "Token refreshed successfully"
}
```

**Response (Failed):**
```json
{
  "success": false,
  "message": "Invalid or expired token",
  "error_code": "ERR_UNAUTHORIZED"
}
```

---

## 💻 Frontend Implementation

### Automatic Refresh in `apiClient.js`

The axios interceptor automatically handles 401 errors:

```javascript
// On 401 response:
1. Check if we're already refreshing (prevent duplicate requests)
2. Queue failed requests while refreshing
3. Call /api/v1/auth/refresh with refresh_token
4. Store new tokens
5. Retry all queued requests with new token
6. If refresh fails → clear tokens and redirect to login
```

### Key Features

✅ **Request Queuing** - Multiple requests wait for single refresh
✅ **Prevent Infinite Loop** - Won't retry refresh endpoint itself
✅ **Automatic Retry** - Original request retries transparently
✅ **Token Rotation** - Both tokens are renewed on refresh

---

## 🧪 Testing the Flow

### Test 1: Login and Store Tokens

```bash
# Login
curl -X POST http://localhost:8787/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "identifier": "test@example.com",
    "password": "password123"
  }'
```

**Save the tokens from response:**
- `token` → access_token (1 hour expiry)
- `refresh_token` → refresh_token (7 days expiry)

---

### Test 2: Wait for Access Token to Expire

```bash
# Option 1: Wait 1 hour (real expiry)
# Option 2: Modify JWT_EXPIRY in .env to 60 seconds for testing
```

---

### Test 3: Make API Request with Expired Token

```bash
# This will return 401
curl -X GET http://localhost:8787/api/v1/users \
  -H "Authorization: Bearer <expired_access_token>"
```

**Expected:** 401 Unauthorized

---

### Test 4: Refresh Token

```bash
# Manually test refresh endpoint
curl -X POST http://localhost:8787/api/v1/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{
    "refresh_token": "<your_refresh_token>"
  }'
```

**Expected:** New access_token + new refresh_token

---

### Test 5: Frontend Auto-Refresh (Browser)

```javascript
// 1. Login via frontend
// 2. Wait for access token to expire
// 3. Try to access /api/v1/users
// 4. Check browser console logs:

[Auth] Token refreshed successfully
[API Request] GET /api/v1/users (retry)
[API Success] status: 200
```

---

## ⚙️ Configuration

### Backend (.env)

```env
# Access token expiry (seconds) - default 1 hour
JWT_EXPIRY=3600

# Refresh token expiry (seconds) - default 7 days
JWT_REFRESH_EXPIRY=604800

# JWT secret
JWT_SECRET=your_jwt_secret_key_change_me_in_production
```

### For Testing (Quick Expiry)

```env
# Access token expires in 60 seconds
JWT_EXPIRY=60

# Refresh token expires in 10 minutes
JWT_REFRESH_EXPIRY=600
```

---

## 🔒 Security Features

### Token Validation

✅ **Session Tracking** - Tokens linked to database session
✅ **Token Version** - Invalidate all tokens on password change
✅ **Revocation Check** - `revoked_at` column in `user_sessions`
✅ **Expiry Check** - Both access and refresh tokens expire
✅ **One-Time Use** - Refresh token generates new refresh token

### Database Schema

```sql
-- user_sessions table includes:
- token_hash (SHA-256 of access token)
- refresh_token_hash (SHA-256 of refresh token)
- expires_at (access token expiry)
- refresh_expires_at (refresh token expiry)
- revoked_at (session revocation)
```

---

## 🚨 Error Scenarios

### Scenario 1: Refresh Token Expired

```
User: Makes API request
API: Returns 401
Frontend: Calls /auth/refresh
Backend: Returns 401 (refresh token expired)
Frontend: Redirects to /login
```

### Scenario 2: Token Revoked

```
User: Logs out or admin revokes session
Frontend: Calls /auth/refresh
Backend: Returns 401 (session revoked)
Frontend: Redirects to /login
```

### Scenario 3: Multiple Concurrent 401s

```
User: Makes 5 API requests simultaneously
API: All return 401 (token expired)
Frontend: Only calls /auth/refresh ONCE
         Queues the 5 failed requests
         Retries all 5 with new token
```

---

## 📊 Monitoring

### Logs to Check

```bash
# Backend logs
docker compose logs backend | grep "Token refresh"
docker compose logs backend | grep "Authentication failed"

# MongoDB audit logs
docker compose exec mongodb mongosh -u admin -p mongo_password_change_me
use phpfrarm_logs
db.audit_logs.find({ action: "token_refresh" }).sort({ timestamp: -1 })
```

---

## 🎉 Benefits

✅ **Better UX** - Users don't get logged out every hour
✅ **Seamless** - Happens automatically in background
✅ **Secure** - Short-lived access tokens + token rotation
✅ **Scalable** - Request queuing prevents thundering herd
✅ **Auditable** - All refresh events logged

---

## 📝 Implementation Checklist

For developers implementing this in other projects:

- [ ] Backend refresh endpoint created
- [ ] Frontend axios interceptor configured
- [ ] Refresh token stored in localStorage
- [ ] Request queuing implemented
- [ ] Infinite loop prevention added
- [ ] Token rotation working
- [ ] Database session tracking enabled
- [ ] Audit logging configured
- [ ] Error scenarios tested
- [ ] Documentation updated

---

## 🔗 Related Documentation

- [API Features Checklist](../api/API-Features.md)
- [Authentication Guide](./AUTHENTICATION_GUIDE.md)
- [Security Guide](../security/SECURITY_GUIDE.md)
