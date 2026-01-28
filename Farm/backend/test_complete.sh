#!/bin/bash
# Complete API test - Update password, login, test security endpoints

echo "=== Setting up test user ==="
# Update password for test user (password: Admin@123)
docker exec -i phpfrarm_mysql mysql -uroot -proot_password_change_me phpfrarm_db <<EOF
UPDATE users 
SET password_hash = '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
WHERE email = 'kudeepakh@gmail.com';
EOF

echo "✓ Password updated for kudeepakh@gmail.com (password: Admin@123)"
echo ""

# Login and get token
echo "=== 1. POST /auth/login ==="
LOGIN_RESPONSE=$(curl -s -X POST \
  -H "Content-Type: application/json" \
  -H "X-Correlation-Id: test-login" \
  -H "X-Transaction-Id: txn-login" \
  -H "X-Request-Id: req-login" \
  -d '{"email":"kudeepakh@gmail.com","password":"Admin@123"}' \
  http://localhost:8787/api/v1/auth/login)

echo "$LOGIN_RESPONSE"
TOKEN=$(echo "$LOGIN_RESPONSE" | grep -o '"access_token":"[^"]*"' | sed 's/"access_token":"//;s/"//')

if [ -z "$TOKEN" ]; then
  echo "❌ Login failed - no token received"
  exit 1
fi

echo ""
echo "✓ Token received: ${TOKEN:0:60}..."
echo ""
echo "==============================================="
echo ""

# Test Security Endpoints
echo "=== 2. GET /admin/security/overview ==="
curl -s -H "X-Correlation-Id: test-overview" \
  -H "X-Transaction-Id: txn-overview" \
  -H "X-Request-Id: req-overview" \
  -H "Authorization: Bearer $TOKEN" \
  http://localhost:8787/api/v1/admin/security/overview
echo ""
echo ""

echo "=== 3. GET /admin/security/ip/whitelist ==="
curl -s -H "X-Correlation-Id: test-whitelist" \
  -H "X-Transaction-Id: txn-whitelist" \
  -H "X-Request-Id: req-whitelist" \
  -H "Authorization: Bearer $TOKEN" \
  http://localhost:8787/api/v1/admin/security/ip/whitelist
echo ""
echo ""

echo "=== 4. GET /admin/security/ip/blacklist ==="
curl -s -H "X-Correlation-Id: test-blacklist" \
  -H "X-Transaction-Id: txn-blacklist" \
  -H "X-Request-Id: req-blacklist" \
  -H "Authorization: Bearer $TOKEN" \
  http://localhost:8787/api/v1/admin/security/ip/blacklist
echo ""
echo ""

echo "=== Test Complete ==="
