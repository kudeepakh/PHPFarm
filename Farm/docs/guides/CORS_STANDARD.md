# 🌐 **CORS Standard Configuration Guide**

## **Overview**

This document defines the **single source of truth** for CORS (Cross-Origin Resource Sharing) configuration in the PHPFrarm framework. Following these standards ensures consistent, secure, and browser-compatible API access without header duplication conflicts.

---

## 🎯 **Core Principles**

### **1. Single Source of Truth**
- **ONLY** `CommonMiddleware::cors()` sets CORS headers
- `SecureHeadersMiddleware` handles security headers only (CSP, HSTS, etc.)
- No other middleware or controller should set `Access-Control-*` headers

### **2. Header Echo Strategy**
- During preflight (OPTIONS), echo back **exactly** what browser requests in `Access-Control-Request-Headers`
- This prevents header duplication and browser request cancellation
- Fallback to comprehensive default list if no headers requested

### **3. Environment-Aware Caching**
- **Development**: `Access-Control-Max-Age: 0` (no preflight cache, changes apply immediately)
- **Production**: `Access-Control-Max-Age: 86400` (24-hour cache, reduces overhead)

---

## 📋 **Configuration**

### **Backend: `.env` Settings**

```env
# CORS Configuration
CORS_ALLOWED_ORIGINS=http://localhost:3900,http://localhost:3000
CORS_ALLOW_CREDENTIALS=true
APP_ENV=development  # or production
```

### **Frontend: Axios Client Configuration**

Location: `frontend/src/utils/apiClient.js`

```javascript
const apiClient = axios.create({
  baseURL: process.env.REACT_APP_API_URL || 'http://localhost:8787',
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json', // ✅ MANDATORY - prevents preflight issues
  },
  withCredentials: false, // Set true if using cookies
});
```

**Critical Points:**
- Always include `Accept: application/json`
- `withCredentials: false` unless using cookie-based auth
- Trace IDs auto-added by request interceptor

---

## 🔄 **CORS Flow**

### **Preflight Request (OPTIONS)**

```
Browser → OPTIONS /api/v1/auth/otp/request
Headers:
  Origin: http://localhost:3900
  Access-Control-Request-Method: POST
  Access-Control-Request-Headers: content-type, accept, x-correlation-id

Backend Response:
  Status: 204 No Content
  Access-Control-Allow-Origin: http://localhost:3900
  Access-Control-Allow-Credentials: true
  Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS, HEAD
  Access-Control-Allow-Headers: content-type, accept, x-correlation-id
  Access-Control-Max-Age: 0 (dev) | 86400 (prod)
  Access-Control-Expose-Headers: X-Correlation-Id, X-Transaction-Id, X-Request-Id
```

### **Actual Request (POST)**

```
Browser → POST /api/v1/auth/otp/request
Headers:
  Content-Type: application/json
  Accept: application/json
  X-Correlation-Id: <ULID>
Body: {"identifier": "user@example.com", "type": "email", "purpose": "login"}

Backend Response:
  Status: 200 OK
  Access-Control-Allow-Origin: http://localhost:3900
  Access-Control-Allow-Credentials: true
  Access-Control-Expose-Headers: X-Correlation-Id, X-Transaction-Id, X-Request-Id
  X-Correlation-Id: <ULID>
  X-Transaction-Id: <ULID>
  X-Request-Id: <ULID>
Body: {"success": true, "message": "OTP sent", "data": {...}}
```

---

## 🛡️ **Allowed Headers List**

### **Default Headers (When Browser Doesn't Specify)**

```
Content-Type, Authorization, Accept, Accept-Language, Accept-Encoding,
X-Correlation-Id, X-Transaction-Id, X-Request-Id, Origin, DNT,
User-Agent, X-Requested-With, If-Modified-Since, Cache-Control,
Sec-CH-UA, Sec-CH-UA-Mobile, Sec-CH-UA-Platform,
Sec-Fetch-Dest, Sec-Fetch-Mode, Sec-Fetch-Site, Sec-Fetch-User, Referer
```

### **Browser-Specific Headers (Always Allowed)**
- `Sec-CH-*`: Client hints for user-agent, mobile, platform
- `Sec-Fetch-*`: Fetch metadata for security policies
- `DNT`: Do Not Track privacy header
- `Referer`: Referrer policy compliance

---

## 🚨 **Common Issues & Solutions**

### **Issue 1: Request Cancelled in Browser**
**Symptom**: Frontend shows "canceled" status, no error message

**Root Cause**: Header duplication (e.g., `content-type, content-type`)

**Solution**: 
- Ensure ONLY `CommonMiddleware::cors()` sets CORS headers
- Never manually call `header('Access-Control-*')` elsewhere
- Always echo exact `Access-Control-Request-Headers` during preflight

---

### **Issue 2: Stale Preflight Cache**
**Symptom**: Changes to CORS config don't take effect

**Root Cause**: Browser cached preflight response with `Max-Age: 86400`

**Solution**:
- Set `APP_ENV=development` in `.env` (sets `Max-Age: 0`)
- Hard refresh frontend: `Ctrl + Shift + R` (Chrome/Firefox)
- Clear site data: DevTools → Application → Clear Storage

---

### **Issue 3: Missing Accept Header**
**Symptom**: 406 Not Acceptable or preflight fails

**Root Cause**: Frontend not sending `Accept: application/json`

**Solution**:
```javascript
// ✅ CORRECT
axios.create({
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json', // MANDATORY
  }
});

// ❌ WRONG - Missing Accept header
axios.create({
  headers: {
    'Content-Type': 'application/json',
  }
});
```

---

### **Issue 4: Multiple CORS Middleware**
**Symptom**: Duplicate `Access-Control-*` headers in response

**Root Cause**: Multiple middleware setting CORS

**Solution**:
- Use `cors` middleware in route groups:
  ```php
  #[RouteGroup('/api/v1/auth', middleware: ['cors', 'rateLimit'])]
  ```
- Remove any manual `header()` calls in controllers
- Disable `SecureHeadersMiddleware::applyCORS()` (already removed)

---

## ✅ **Verification Checklist**

### **Backend Verification**

```bash
# Test preflight
curl -X OPTIONS http://localhost:8787/api/v1/auth/otp/request \
  -H "Origin: http://localhost:3900" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: content-type, accept" \
  -v 2>&1 | grep "Access-Control"

# Expected output:
# Access-Control-Allow-Origin: http://localhost:3900
# Access-Control-Allow-Credentials: true
# Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS, HEAD
# Access-Control-Allow-Headers: content-type, accept
# Access-Control-Max-Age: 0
```

### **Frontend Verification**

1. Open Browser DevTools → Network tab
2. Trigger API request (e.g., OTP request)
3. Check preflight request:
   - Status: `204 No Content`
   - No duplicate headers
   - `Access-Control-Allow-Origin` matches frontend origin
4. Check actual request:
   - Status: `200 OK`
   - Response includes trace IDs
   - No CORS errors in Console

---

## 📝 **Development Guidelines**

### **DO ✅**
- Use `middleware: ['cors']` in route groups
- Let `CommonMiddleware::cors()` handle all CORS
- Include `Accept: application/json` in frontend
- Test with `APP_ENV=development` for instant cache refresh
- Check Network tab for duplicate headers

### **DON'T ❌**
- Manually set `Access-Control-*` headers in controllers
- Add CORS logic to `SecureHeadersMiddleware`
- Omit `Accept` header from frontend requests
- Merge/append headers to `Access-Control-Request-Headers`
- Use `Access-Control-Max-Age > 0` in development

---

## 🔧 **Debugging Commands**

### **Check Active Middleware**
```bash
grep -r "Access-Control" backend/app/Middleware/
# Should ONLY find matches in CommonMiddleware.php
```

### **Monitor CORS Headers**
```bash
# Terminal 1: Watch backend logs
docker compose logs -f backend | grep -i "cors\|origin"

# Terminal 2: Test endpoint
curl -X POST http://localhost:8787/api/v1/auth/otp/request \
  -H "Origin: http://localhost:3900" \
  -H "Content-Type: application/json" \
  -d '{"identifier":"test@example.com","type":"email","purpose":"login"}' \
  -v 2>&1 | grep "Access-Control"
```

### **Clear Browser Cache**
```javascript
// Run in Browser Console
localStorage.clear();
sessionStorage.clear();
caches.keys().then(keys => keys.forEach(key => caches.delete(key)));
location.reload(true);
```

---

## 🎓 **Best Practices**

1. **Consistency**: All APIs use same CORS config via `CommonMiddleware::cors()`
2. **Transparency**: Log CORS decisions at debug level for troubleshooting
3. **Testing**: Include CORS tests in integration test suite
4. **Documentation**: Update this guide when CORS requirements change
5. **Security**: Never use `Access-Control-Allow-Origin: *` with credentials in production

---

## 📚 **References**

- [MDN: CORS](https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS)
- [CORS Spec](https://fetch.spec.whatwg.org/#http-cors-protocol)
- [Axios CORS](https://axios-http.com/docs/handling_errors)
- PHPFrarm: `backend/app/Middleware/CommonMiddleware.php`
- PHPFrarm: `frontend/src/utils/apiClient.js`

---

**Last Updated**: January 24, 2026  
**Maintained By**: PHPFrarm Core Team  
**Version**: 1.0.0
