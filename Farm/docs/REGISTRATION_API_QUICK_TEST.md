# 🚀 Quick Test Guide - Registration API

## ✅ Fixed Issues
1. **Field name mismatch** - Now accepts both `firstName` and `first_name`
2. **Missing token_version** - Database INSERT fixed
3. **API contract** - Flexible to handle both camelCase and snake_case

---

## 🏃 Quick Start

### 1. Start Docker Containers
```powershell
cd Farm
docker-compose up -d
```

### 2. Wait for Services (30 seconds)
```powershell
Start-Sleep -Seconds 30
```

### 3. Run Test Suite
```powershell
./test_registration_debug.ps1
```

---

## 📋 Manual Test Examples

### Test 1: Register with camelCase (Recommended)
```powershell
$body = @{
    email = "john@example.com"
    password = "SecurePass123!"
    firstName = "John"
    lastName = "Doe"
} | ConvertTo-Json

Invoke-WebRequest -Uri "http://localhost:8787/api/v1/auth/register" `
    -Method POST `
    -Body $body `
    -ContentType "application/json" `
    -UseBasicParsing | Select-Object -ExpandProperty Content | ConvertFrom-Json
```

### Test 2: Register with snake_case
```powershell
$body = @{
    email = "jane@example.com"
    password = "SecurePass123!"
    first_name = "Jane"
    last_name = "Smith"
} | ConvertTo-Json

Invoke-WebRequest -Uri "http://localhost:8787/api/v1/auth/register" `
    -Method POST `
    -Body $body `
    -ContentType "application/json" `
    -UseBasicParsing | Select-Object -ExpandProperty Content | ConvertFrom-Json
```

### Test 3: Validate Error Handling
```powershell
# Invalid email
$body = @{
    email = "not-an-email"
    password = "SecurePass123!"
    firstName = "Test"
    lastName = "User"
} | ConvertTo-Json

try {
    Invoke-WebRequest -Uri "http://localhost:8787/api/v1/auth/register" `
        -Method POST -Body $body -ContentType "application/json" -UseBasicParsing
} catch {
    Write-Host "✓ Validation working - Invalid email rejected"
}
```

---

## 🔍 Expected Responses

### Success Response (HTTP 201)
```json
{
  "success": true,
  "data": {
    "user_id": "a1b2c3d4-e5f6-7890-1234-567890abcdef",
    "email": "john@example.com"
  },
  "message": "Registration successful",
  "timestamp": "2026-01-27T10:30:00Z",
  "correlationId": "corr-abc123",
  "transactionId": "txn-def456",
  "requestId": "req-ghi789"
}
```

### Validation Error (HTTP 400)
```json
{
  "success": false,
  "error": {
    "code": "validation.failed",
    "message": "Validation failed",
    "details": [
      "Valid email is required",
      "Password must be at least 8 characters"
    ]
  },
  "timestamp": "2026-01-27T10:30:00Z",
  "correlationId": "corr-abc123",
  "requestId": "req-ghi789"
}
```

### Duplicate Email (HTTP 400)
```json
{
  "success": false,
  "error": {
    "code": "auth.register.email_exists",
    "message": "Email already registered"
  },
  "timestamp": "2026-01-27T10:30:00Z",
  "correlationId": "corr-abc123",
  "requestId": "req-ghi789"
}
```

---

## 🐛 Troubleshooting

### Issue: "Cannot connect to server"
**Solution:** Start Docker containers
```powershell
docker-compose up -d
```

### Issue: "Database connection failed"
**Solution:** Check MySQL container
```powershell
docker-compose logs mysql
docker-compose restart mysql
```

### Issue: "404 Not Found"
**Solution:** Verify route registration
```powershell
docker-compose exec backend php check_routes.php
```

### Issue: "500 Internal Server Error"
**Solution:** Check backend logs
```powershell
docker-compose logs backend -f
```

---

## 📊 Validation Rules

| Field | Rule | Example |
|-------|------|---------|
| `email` | Valid email format | `user@example.com` |
| `password` | Min 8 characters | `Password123!` |
| `firstName` | Optional, any string | `John` |
| `lastName` | Optional, any string | `Doe` |

---

## 🎯 What Was Fixed

### Before Fix ❌
```php
// Only worked with snake_case
$this->firstName = $data['first_name'] ?? null;
```
**Problem:** JSON sends `firstName`, PHP looks for `first_name` → NULL

### After Fix ✅
```php
// Works with BOTH formats
$this->firstName = $data['firstName'] ?? $data['first_name'] ?? null;
```
**Solution:** Accepts both camelCase (JSON) and snake_case (PHP)

---

## 📖 Related Documentation

- Full Debug Report: `docs/REGISTRATION_API_DEBUG_REPORT.md`
- API Documentation: `docs/api/API_COMPLETE_REFERENCE.md`
- Test Script: `test_registration_debug.ps1`

---

## ✅ Quick Verification

```powershell
# 1. Check if backend is up
Invoke-WebRequest -Uri "http://localhost:8787/api/v1/system/health" -UseBasicParsing

# 2. Test registration
$body = @{email="test@test.com";password="Test@12345";firstName="T";lastName="U"} | ConvertTo-Json
Invoke-WebRequest -Uri "http://localhost:8787/api/v1/auth/register" -Method POST -Body $body -ContentType "application/json" -UseBasicParsing

# 3. Check logs
docker-compose logs backend --tail=50
```

---

**Status:** ✅ Ready for Testing  
**Next Step:** Run `./test_registration_debug.ps1`
