# Test Token Refresh Bug Fix
# This script verifies the token refresh issue has been resolved

Write-Host "🧪 Testing Token Refresh Fix" -ForegroundColor Cyan
Write-Host "============================`n" -ForegroundColor Cyan

$baseUrl = "http://localhost:8787"
$apiUrl = "$baseUrl/api/v1"

# Step 1: Login to get tokens
Write-Host "Step 1: Logging in to get tokens..." -ForegroundColor Yellow

$loginBody = @{
    identifier = "admin@example.com"
    password = "Admin@123"
} | ConvertTo-Json

try {
    $loginResponse = Invoke-RestMethod -Uri "$apiUrl/auth/login" -Method Post -Body $loginBody -ContentType "application/json"
    
    if ($loginResponse.success) {
        Write-Host "✅ Login successful!" -ForegroundColor Green
        $accessToken = $loginResponse.data.token
        $refreshToken = $loginResponse.data.refresh_token
        
        Write-Host "   Access Token: $($accessToken.Substring(0, 30))..." -ForegroundColor Gray
        Write-Host "   Refresh Token: $($refreshToken.Substring(0, 30))...`n" -ForegroundColor Gray
    } else {
        Write-Host "❌ Login failed: $($loginResponse.error.message)" -ForegroundColor Red
        exit 1
    }
} catch {
    Write-Host "❌ Login request failed: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host "💡 Make sure backend is running and user exists`n" -ForegroundColor Yellow
    exit 1
}

# Step 2: Wait a moment
Write-Host "Step 2: Waiting 2 seconds..." -ForegroundColor Yellow
Start-Sleep -Seconds 2
Write-Host "✅ Ready to test refresh`n" -ForegroundColor Green

# Step 3: Test token refresh
Write-Host "Step 3: Testing token refresh endpoint..." -ForegroundColor Yellow

$refreshBody = @{
    refresh_token = $refreshToken
} | ConvertTo-Json

try {
    $refreshResponse = Invoke-RestMethod -Uri "$apiUrl/auth/refresh" -Method Post -Body $refreshBody -ContentType "application/json"
    
    if ($refreshResponse.success) {
        Write-Host "✅ Token refresh successful!" -ForegroundColor Green
        Write-Host "   New Access Token: $($refreshResponse.data.token.Substring(0, 30))..." -ForegroundColor Gray
        Write-Host "   New Refresh Token: $($refreshResponse.data.refresh_token.Substring(0, 30))...`n" -ForegroundColor Gray
        
        Write-Host "🎉 TOKEN REFRESH BUG IS FIXED!" -ForegroundColor Green
        Write-Host "   No TypeError occurred" -ForegroundColor Green
        Write-Host "   New tokens issued successfully`n" -ForegroundColor Green
    } else {
        Write-Host "⚠️  Token refresh returned error:" -ForegroundColor Yellow
        Write-Host "   Error Code: $($refreshResponse.error.code)" -ForegroundColor Yellow
        Write-Host "   Error Message: $($refreshResponse.error.message)`n" -ForegroundColor Yellow
        
        # Check if it's the old TypeError
        if ($refreshResponse.error.message -match "issueTokens|TypeError") {
            Write-Host "❌ BUG STILL EXISTS - TypeError detected!" -ForegroundColor Red
        } else {
            Write-Host "💡 Different error - may be expected (e.g., expired token)" -ForegroundColor Cyan
        }
    }
} catch {
    Write-Host "❌ Token refresh request failed: $($_.Exception.Message)" -ForegroundColor Red
    
    if ($_.Exception.Message -match "500") {
        Write-Host "⚠️  Server error (500) - Check logs for details" -ForegroundColor Yellow
        
        # Try to fetch recent error logs
        Write-Host "`n🔍 Checking MongoDB for recent errors..." -ForegroundColor Cyan
        
        $mongoCmd = 'db.application_logs.find({level: \"ERROR\", message: /issueTokens|TypeError/}).sort({timestamp: -1}).limit(1).forEach(function(doc) { print(JSON.stringify(doc.message)); })'
        
        $recentError = docker exec phpfrarm_mongodb mongosh -u admin -p mongo_password_change_me --authenticationDatabase admin phpfrarm_logs --quiet --eval $mongoCmd 2>&1
        
        if ($recentError) {
            Write-Host "   Latest Error: $recentError" -ForegroundColor Red
            Write-Host "`n❌ BUG STILL EXISTS!" -ForegroundColor Red
        }
    }
}

# Step 4: Check MongoDB logs
Write-Host "`nStep 4: Checking MongoDB for token refresh errors..." -ForegroundColor Yellow

$errorCountCmd = 'db.application_logs.countDocuments({level: \"ERROR\", message: /issueTokens|TypeError/, timestamp: {$gte: new Date(Date.now() - 300000)}})'

$errorCount = docker exec phpfrarm_mongodb mongosh -u admin -p mongo_password_change_me --authenticationDatabase admin phpfrarm_logs --quiet --eval $errorCountCmd 2>&1

Write-Host "   Errors in last 5 minutes: $errorCount" -ForegroundColor $(if ([int]$errorCount -eq 0) { 'Green' } else { 'Red' })

if ([int]$errorCount -eq 0) {
    Write-Host "   ✅ No TypeError logs found!`n" -ForegroundColor Green
} else {
    Write-Host "   ⚠️  Still seeing errors - bug may not be fully fixed`n" -ForegroundColor Yellow
}

# Summary
Write-Host "📊 Test Summary:" -ForegroundColor Cyan
Write-Host "===============`n" -ForegroundColor Cyan

if ($refreshResponse.success -and [int]$errorCount -eq 0) {
    Write-Host "✅ ALL TESTS PASSED" -ForegroundColor Green
    Write-Host "   - Login: Success" -ForegroundColor Green
    Write-Host "   - Token Refresh: Success" -ForegroundColor Green
    Write-Host "   - Error Logs: Clean" -ForegroundColor Green
    Write-Host "`n🎉 Token refresh bug is FIXED!" -ForegroundColor Green
} else {
    Write-Host "⚠️  SOME TESTS FAILED" -ForegroundColor Yellow
    Write-Host "   Please review the output above for details`n" -ForegroundColor Yellow
    
    Write-Host "💡 Troubleshooting:" -ForegroundColor Cyan
    Write-Host "   1. Check backend logs: docker-compose logs backend | Select-String 'ERROR'" -ForegroundColor Gray
    Write-Host "   2. Verify user has identifiers: Check user_identifiers table" -ForegroundColor Gray
    Write-Host "   3. Review AuthService.php changes: backend/modules/Auth/Services/AuthService.php" -ForegroundColor Gray
}

Write-Host ""
