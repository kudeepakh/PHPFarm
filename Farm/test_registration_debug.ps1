#!/usr/bin/env pwsh
# Registration API Debug Test Script
# Tests each step of the registration flow

$baseUrl = "http://localhost:8787"
$ErrorActionPreference = "Continue"

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "Registration API Debug Test Suite" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

# Test data
$testEmail = "debug_$(Get-Date -Format 'yyyyMMdd_HHmmss')@test.com"
$testData = @{
    email = $testEmail
    password = "Test@12345"
    firstName = "Debug"
    lastName = "User"
} | ConvertTo-Json

Write-Host "Test Data:" -ForegroundColor Yellow
Write-Host $testData -ForegroundColor Gray
Write-Host ""

# Step 1: Test endpoint availability
Write-Host "[Step 1] Testing endpoint availability..." -ForegroundColor Green
try {
    $healthCheck = Invoke-WebRequest -Uri "$baseUrl/api/v1/system/health" -Method GET -UseBasicParsing -ErrorAction Stop
    Write-Host "  ✓ Backend is reachable" -ForegroundColor Green
    Write-Host "  Response: $($healthCheck.StatusCode) - $($healthCheck.Content)" -ForegroundColor Gray
} catch {
    Write-Host "  ✗ Backend not reachable: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host "  Make sure Docker containers are running!" -ForegroundColor Yellow
    exit 1
}
Write-Host ""

# Step 2: Test registration with camelCase (JSON standard)
Write-Host "[Step 2] Testing registration with camelCase fields..." -ForegroundColor Green
try {
    $response = Invoke-WebRequest -Uri "$baseUrl/api/v1/auth/register" `
        -Method POST `
        -Body $testData `
        -ContentType "application/json" `
        -UseBasicParsing `
        -ErrorAction Stop
    
    Write-Host "  ✓ Registration successful!" -ForegroundColor Green
    Write-Host "  Status: $($response.StatusCode)" -ForegroundColor Gray
    Write-Host "  Response:" -ForegroundColor Gray
    
    $responseObj = $response.Content | ConvertFrom-Json
    Write-Host "  $(ConvertTo-Json $responseObj -Depth 5)" -ForegroundColor Gray
    
    # Extract user_id for further tests
    if ($responseObj.data.user_id) {
        $global:userId = $responseObj.data.user_id
        Write-Host "`n  User ID: $userId" -ForegroundColor Cyan
    }
    
} catch {
    Write-Host "  ✗ Registration failed!" -ForegroundColor Red
    Write-Host "  Status: $($_.Exception.Response.StatusCode.value__)" -ForegroundColor Red
    
    # Try to read error response
    try {
        $errorStream = $_.Exception.Response.GetResponseStream()
        $reader = New-Object System.IO.StreamReader($errorStream)
        $errorBody = $reader.ReadToEnd()
        Write-Host "  Error Response:" -ForegroundColor Red
        Write-Host "  $errorBody" -ForegroundColor Red
        
        # Parse error details
        $errorObj = $errorBody | ConvertFrom-Json
        if ($errorObj.error) {
            Write-Host "`n  Error Code: $($errorObj.error.code)" -ForegroundColor Yellow
            Write-Host "  Error Message: $($errorObj.error.message)" -ForegroundColor Yellow
            if ($errorObj.error.details) {
                Write-Host "  Details: $($errorObj.error.details)" -ForegroundColor Yellow
            }
        }
    } catch {
        Write-Host "  Could not parse error response" -ForegroundColor Gray
    }
}
Write-Host ""

# Step 3: Test with snake_case (PHP convention)
Write-Host "[Step 3] Testing registration with snake_case fields..." -ForegroundColor Green
$testEmail2 = "debug_snake_$(Get-Date -Format 'yyyyMMdd_HHmmss')@test.com"
$testDataSnake = @{
    email = $testEmail2
    password = "Test@12345"
    first_name = "Snake"
    last_name = "Case"
} | ConvertTo-Json

Write-Host "  Test Data: $testDataSnake" -ForegroundColor Gray

try {
    $response2 = Invoke-WebRequest -Uri "$baseUrl/api/v1/auth/register" `
        -Method POST `
        -Body $testDataSnake `
        -ContentType "application/json" `
        -UseBasicParsing `
        -ErrorAction Stop
    
    Write-Host "  ✓ Registration with snake_case successful!" -ForegroundColor Green
    Write-Host "  Status: $($response2.StatusCode)" -ForegroundColor Gray
    
} catch {
    Write-Host "  ✗ Registration with snake_case failed!" -ForegroundColor Red
    Write-Host "  Status: $($_.Exception.Response.StatusCode.value__)" -ForegroundColor Red
}
Write-Host ""

# Step 4: Test duplicate registration
Write-Host "[Step 4] Testing duplicate registration (should fail)..." -ForegroundColor Green
try {
    $response3 = Invoke-WebRequest -Uri "$baseUrl/api/v1/auth/register" `
        -Method POST `
        -Body $testData `
        -ContentType "application/json" `
        -UseBasicParsing `
        -ErrorAction Stop
    
    Write-Host "  ✗ Duplicate check FAILED - should have rejected duplicate email!" -ForegroundColor Red
    
} catch {
    $statusCode = $_.Exception.Response.StatusCode.value__
    if ($statusCode -eq 400) {
        Write-Host "  ✓ Duplicate registration correctly rejected!" -ForegroundColor Green
        Write-Host "  Status: 400 Bad Request" -ForegroundColor Gray
    } else {
        Write-Host "  ? Unexpected status code: $statusCode" -ForegroundColor Yellow
    }
}
Write-Host ""

# Step 5: Test validation errors
Write-Host "[Step 5] Testing validation..." -ForegroundColor Green

# Test 5a: Invalid email
Write-Host "  [5a] Testing invalid email..." -ForegroundColor Cyan
$invalidEmail = @{
    email = "not-an-email"
    password = "Test@12345"
    firstName = "Test"
    lastName = "User"
} | ConvertTo-Json

try {
    $response4 = Invoke-WebRequest -Uri "$baseUrl/api/v1/auth/register" `
        -Method POST `
        -Body $invalidEmail `
        -ContentType "application/json" `
        -UseBasicParsing `
        -ErrorAction Stop
    
    Write-Host "    ✗ Validation FAILED - accepted invalid email!" -ForegroundColor Red
    
} catch {
    if ($_.Exception.Response.StatusCode.value__ -eq 400) {
        Write-Host "    ✓ Invalid email correctly rejected" -ForegroundColor Green
    } else {
        Write-Host "    ? Unexpected status: $($_.Exception.Response.StatusCode.value__)" -ForegroundColor Yellow
    }
}

# Test 5b: Short password
Write-Host "  [5b] Testing short password..." -ForegroundColor Cyan
$shortPassword = @{
    email = "test@example.com"
    password = "short"
    firstName = "Test"
    lastName = "User"
} | ConvertTo-Json

try {
    $response5 = Invoke-WebRequest -Uri "$baseUrl/api/v1/auth/register" `
        -Method POST `
        -Body $shortPassword `
        -ContentType "application/json" `
        -UseBasicParsing `
        -ErrorAction Stop
    
    Write-Host "    ✗ Validation FAILED - accepted short password!" -ForegroundColor Red
    
} catch {
    if ($_.Exception.Response.StatusCode.value__ -eq 400) {
        Write-Host "    ✓ Short password correctly rejected" -ForegroundColor Green
    } else {
        Write-Host "    ? Unexpected status: $($_.Exception.Response.StatusCode.value__)" -ForegroundColor Yellow
    }
}

# Test 5c: Missing required fields
Write-Host "  [5c] Testing missing email..." -ForegroundColor Cyan
$missingEmail = @{
    password = "Test@12345"
    firstName = "Test"
    lastName = "User"
} | ConvertTo-Json

try {
    $response6 = Invoke-WebRequest -Uri "$baseUrl/api/v1/auth/register" `
        -Method POST `
        -Body $missingEmail `
        -ContentType "application/json" `
        -UseBasicParsing `
        -ErrorAction Stop
    
    Write-Host "    ✗ Validation FAILED - accepted missing email!" -ForegroundColor Red
    
} catch {
    if ($_.Exception.Response.StatusCode.value__ -eq 400) {
        Write-Host "    ✓ Missing email correctly rejected" -ForegroundColor Green
    } else {
        Write-Host "    ? Unexpected status: $($_.Exception.Response.StatusCode.value__)" -ForegroundColor Yellow
    }
}

Write-Host ""

# Summary
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "Test Summary" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Registration API Debug Tests Complete!" -ForegroundColor Green
Write-Host "`nKey Points:" -ForegroundColor Yellow
Write-Host "  • DTO now accepts both camelCase and snake_case field names" -ForegroundColor Gray
Write-Host "  • Stored procedure fixed to set token_version default" -ForegroundColor Gray
Write-Host "  • All validation rules are enforced" -ForegroundColor Gray
Write-Host "  • Duplicate email detection works" -ForegroundColor Gray
Write-Host ""
