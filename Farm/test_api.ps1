$baseUrl = "http://localhost:8787"

Write-Host "`n========== COMPLETE API TEST ==========" -ForegroundColor Cyan
Write-Host "User: test@example.com`n"

$loginResp = Invoke-WebRequest -Uri "$baseUrl/api/v1/auth/login" -Method POST -Headers @{"Content-Type"="application/json";"X-Correlation-Id"="login";"X-Transaction-Id"="login";"X-Request-Id"="login"} -Body '{"email":"test@example.com","password":"Test@1234"}' -UseBasicParsing

$loginData = $loginResp.Content | ConvertFrom-Json
$token = $loginData.data.token

Write-Host "✓ Login Success" -ForegroundColor Green
Write-Host "  User: $($loginData.data.user.email)"
Write-Host "  Roles: $($loginData.data.user.roles -join ', ')`n"

$endpoints = @(
    @{Path="/api/v1/auth/me"; Name="Auth - User Context"}
    @{Path="/api/v1/admin/security/overview"; Name="Security - Overview"}
    @{Path="/api/v1/admin/security/ip/whitelist"; Name="Security - IP Whitelist"}
    @{Path="/api/v1/admin/security/ip/blacklist"; Name="Security - IP Blacklist"}
    @{Path="/api/v1/admin/security/health"; Name="Security - Health"}
    @{Path="/api/v1/admin/roles"; Name="Roles - List"}
    @{Path="/api/v1/admin/permissions"; Name="Permissions - List"}
    @{Path="/api/v1/admin/users"; Name="Users - List"}
)

$passed = 0
$failed = 0

foreach ($endpoint in $endpoints) {
    try {
        $resp = Invoke-WebRequest -Uri "$baseUrl$($endpoint.Path)" -Method GET -Headers @{"Authorization"="Bearer $token";"X-Correlation-Id"=[guid]::NewGuid().ToString();"X-Transaction-Id"=[guid]::NewGuid().ToString();"X-Request-Id"=[guid]::NewGuid().ToString()} -UseBasicParsing
        $data = $resp.Content | ConvertFrom-Json
        if ($resp.StatusCode -eq 200 -and $data.success -eq $true) {
            Write-Host "✓ $($endpoint.Name)" -ForegroundColor Green
            $passed++
        } else {
            Write-Host "✗ $($endpoint.Name) - Status: $($resp.StatusCode), Success: $($data.success)" -ForegroundColor Red
            $failed++
        }
    } catch {
        Write-Host "✗ $($endpoint.Name) - HTTP $($_.Exception.Response.StatusCode.value__)" -ForegroundColor Red
        $failed++
    }
}

Write-Host "`n========== RESULTS ==========" -ForegroundColor Cyan
Write-Host "PASSED: $passed" -ForegroundColor Green
Write-Host "FAILED: $failed" -ForegroundColor $(if ($failed -eq 0) { "Green" } else { "Red" })
Write-Host "==============================`n"
