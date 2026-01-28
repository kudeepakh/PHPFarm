#!/bin/bash
# Test auth login and security endpoints

echo "=== Testing Authentication & Security APIs ==="
echo ""

# 1. Login
echo "1. POST /auth/login"
LOGIN_RESPONSE=$(curl -s -X POST \
  -H "Content-Type: application/json" \
  -H "X-Correlation-Id: test-login" \
  -H "X-Transaction-Id: txn-login" \
  -H "X-Request-Id: req-login" \
  -d '{"email":"test@test.com","password":"Test@123"}' \
  http://localhost:8787/api/v1/auth/login)

echo "$LOGIN_RESPONSE"
echo ""

# Extract token using grep and sed
TOKEN=$(echo "$LOGIN_RESPONSE" | grep -o '"access_token":"[^"]*"' | sed 's/"access_token":"//;s/"//')

if [ -z "$TOKEN" ]; then
  echo "❌ Login failed - no token received"
  exit 1
fi

echo "✓ Token received: ${TOKEN:0:60}..."
echo ""
echo "==============================================="
echo ""

# 2. Test Security Endpoints with Token
echo "2. GET /admin/security/overview"
curl -s -H "X-Correlation-Id: test-overview" \
  -H "X-Transaction-Id: txn-overview" \
  -H "X-Request-Id: req-overview" \
  -H "Authorization: Bearer $TOKEN" \
  http://localhost:8787/api/v1/admin/security/overview
echo ""
echo ""

echo "3. GET /admin/security/ip/whitelist"
curl -s -H "X-Correlation-Id: test-whitelist" \
  -H "X-Transaction-Id: txn-whitelist" \
  -H "X-Request-Id: req-whitelist" \
  -H "Authorization: Bearer $TOKEN" \
  http://localhost:8787/api/v1/admin/security/ip/whitelist
echo ""
echo ""

echo "4. GET /admin/security/ip/blacklist"
curl -s -H "X-Correlation-Id: test-blacklist" \
  -H "X-Transaction-Id: txn-blacklist" \
  -H "X-Request-Id: req-blacklist" \
  -H "Authorization: Bearer $TOKEN" \
  http://localhost:8787/api/v1/admin/security/ip/blacklist
echo ""
echo ""

echo "5. GET /admin/security/ip/8.8.8.8 (IP Analysis)"
curl -s -H "X-Correlation-Id: test-ip" \
  -H "X-Transaction-Id: txn-ip" \
  -H "X-Request-Id: req-ip" \
  -H "Authorization: Bearer $TOKEN" \
  http://localhost:8787/api/v1/admin/security/ip/8.8.8.8
echo ""
echo ""

echo "=== Test Complete ==="
