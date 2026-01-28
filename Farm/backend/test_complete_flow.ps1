# Complete API Test - Login and Security Endpoints

Write-Host "`n========== PHPFRARM API TEST ==========`n" -ForegroundColor Cyan

# Step 1: Login
Write-Host "1. Logging in..." -ForegroundColor Yellow
$loginHeaders = @{
    "Content-Type" = "application/json"
    "X-Correlation-Id" = "test-login"
    "X-Transaction-Id" = "test-txn"
    "X-Request-Id" = "test-req"
}
$loginBody = '{"email":"kudeepakh@gmail.com","password":"password"}'

try {
    $loginResp = Invoke-WebRequest -Uri "http://localhost:8787/api/v1/auth/login" -Method POST -Headers $loginHeaders -Body $loginBody -UseBasicParsing

    $loginData = $loginResp.Content | ConvertFrom-Json
    $token = $loginData.data.token
    
    Write-Host "   ✓ Login successful" -ForegroundColor Green
    Write-Host "   Email: $($loginData.data.user.email)"
    Write-Host "   Roles: $($loginData.data.user.roles -join ', ')"
    Write-Host "   Token (first 50): $($token.Substring(0,50))..."
    
} catch {
    Write-Host "   ✗ Login failed: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

# Step 2: Test Security Endpoints
Write-Host "`n2. Testing Security Endpoints..." -ForegroundColor Yellow

$endpoints = @(
    "/api/v1/admin/security/overview",
    "/api/v1/admin/security/ip/whitelist",
    "/api/v1/admin/security/ip/blacklist"
)

foreach ($path in $endpoints) {
    $secHeaders = @{
        "Authorization" = "Bearer $token"
        "X-Correlation-Id" = [System.Guid]::NewGuid().ToString()
        "X-Transaction-Id" = [System.Guid]::NewGuid().ToString()
        "X-Request-Id" = [System.Guid]::NewGuid().ToString()
    }
    
    try {
        $resp = Invoke-WebRequest -Uri "http://localhost:8787$path" -Method GET -Headers $secHeaders -UseBasicParsing
        Write-Host "   ✓ GET $path" -ForegroundColor Green
    } catch {
        Write-Host "   ✗ GET $path - HTTP $($_.Exception.Response.StatusCode.value__)" -ForegroundColor Red
    }
}

Write-Host "`n======================================`n" -ForegroundColor Cyan
